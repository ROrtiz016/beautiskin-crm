<?php

namespace App\Http\Controllers;

use App\Models\AdminAuditLog;
use App\Models\Appointment;
use App\Models\ClinicSetting;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Services\AppointmentPolicyEnforcer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class OperationsDashboardController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.operations-dashboard', $this->operationsIndexPayload($request));
    }

    /**
     * @return array<string, mixed>
     */
    protected function operationsIndexPayload(Request $request): array
    {
        $clinic = ClinicSetting::current();
        $tz = AppointmentPolicyEnforcer::clinicTimezone();
        $todayKey = now($tz)->toDateString();
        [$dayStart, $dayEnd] = AppointmentPolicyEnforcer::clinicDayBounds($todayKey);

        $waitlistDepth = WaitlistEntry::query()
            ->whereIn('status', ['waiting', 'contacted'])
            ->count();

        $defaultLen = max(1, (int) $clinic->default_appointment_length_minutes);
        $workdayMinutes = 8 * 60;

        $todaysAppointments = Appointment::query()
            ->with('staffUser:id,name')
            ->whereBetween('scheduled_at', [$dayStart, $dayEnd])
            ->whereNotIn('status', ['cancelled'])
            ->get(['id', 'staff_user_id', 'scheduled_at', 'ends_at', 'status', 'total_amount']);

        $todaysRevenue = (float) $todaysAppointments
            ->where('status', 'completed')
            ->sum(fn (Appointment $a) => (float) $a->total_amount);

        $noShowsToday = $todaysAppointments->where('status', 'no_show')->count();

        $staffUtilization = $todaysAppointments
            ->groupBy(fn (Appointment $a) => $a->staff_user_id ?? 0)
            ->map(function ($rows) use ($defaultLen, $workdayMinutes) {
                $minutes = 0;
                foreach ($rows as $appt) {
                    if ($appt->ends_at && $appt->scheduled_at) {
                        $minutes += max(0, $appt->scheduled_at->diffInMinutes($appt->ends_at));
                    } else {
                        $minutes += $defaultLen;
                    }
                }

                $pct = $workdayMinutes > 0 ? min(100, round(($minutes / $workdayMinutes) * 100)) : 0;

                return [
                    'booked_minutes' => (int) $minutes,
                    'utilization_percent' => (int) $pct,
                    'appointment_count' => $rows->count(),
                ];
            });

        $staffIds = $staffUtilization->keys()->filter(fn ($id) => (int) $id > 0)->all();
        $staffNames = $staffIds !== []
            ? User::query()->whereIn('id', $staffIds)->pluck('name', 'id')
            : collect();

        $staffUtilizationRows = $staffUtilization->map(function (array $data, $staffId) use ($staffNames) {
            $id = (int) $staffId;

            return [
                'staff_id' => $id,
                'staff_name' => $id > 0 ? (string) ($staffNames[$id] ?? 'Unknown') : 'Unassigned',
                ...$data,
            ];
        })->values()->all();

        return [
            'clinicSettings' => $clinic,
            'metricsTimezone' => $tz,
            'metricsDateLabel' => now($tz)->format('l, M j, Y'),
            'todaysRevenue' => $todaysRevenue,
            'noShowsToday' => $noShowsToday,
            'waitlistDepth' => $waitlistDepth,
            'staffUtilizationRows' => $staffUtilizationRows,
            'operationsPanelOrder' => $request->user()->dashboardPanelOrder('operations'),
        ];
    }

    public function updateAppointmentPolicy(Request $request): RedirectResponse|JsonResponse
    {
        if ($request->input('max_bookings_per_day') === '' || $request->input('max_bookings_per_day') === null) {
            $request->merge(['max_bookings_per_day' => null]);
        }
        if ($request->input('default_deposit_amount') === '') {
            $request->merge(['default_deposit_amount' => null]);
        }

        $validated = $request->validate([
            'appointment_cancellation_hours' => ['required', 'integer', 'min:0', 'max:8760'],
            'deposit_required' => ['sometimes', 'boolean'],
            'default_deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'max_bookings_per_day' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $clinic = ClinicSetting::current();
        $old = [
            'appointment_cancellation_hours' => $clinic->appointment_cancellation_hours,
            'deposit_required' => $clinic->deposit_required,
            'default_deposit_amount' => $clinic->default_deposit_amount,
            'max_bookings_per_day' => $clinic->max_bookings_per_day,
        ];

        $clinic->update([
            'appointment_cancellation_hours' => (int) $validated['appointment_cancellation_hours'],
            'deposit_required' => $request->boolean('deposit_required'),
            'default_deposit_amount' => $validated['default_deposit_amount'] ?? null,
            'max_bookings_per_day' => $validated['max_bookings_per_day'] ?? null,
        ]);

        AdminAuditLog::record(
            $request,
            (int) $request->user()->id,
            'admin.operations.appointment_policy_updated',
            'clinic_settings',
            $clinic->id,
            $old,
            [
                'appointment_cancellation_hours' => $clinic->appointment_cancellation_hours,
                'deposit_required' => $clinic->deposit_required,
                'default_deposit_amount' => $clinic->default_deposit_amount,
                'max_bookings_per_day' => $clinic->max_bookings_per_day,
            ],
        );

        return $this->respondAdminAction(
            $request,
            'Appointment policy saved.',
            'admin.operations.index',
            [],
            ['clinicSettings' => ClinicSetting::current()],
        );
    }

    public function updateFeatureFlags(Request $request): RedirectResponse|JsonResponse
    {
        Gate::authorize('manage-feature-flags');

        $validated = $request->validate([
            'experimental_ui' => ['sometimes', 'boolean'],
        ]);

        $clinic = ClinicSetting::current();
        $flags = $clinic->feature_flags ?? [];
        $old = ['feature_flags' => $flags];

        $flags['experimental_ui'] = $request->boolean('experimental_ui');
        $clinic->update(['feature_flags' => $flags]);

        AdminAuditLog::record(
            $request,
            (int) $request->user()->id,
            'admin.operations.feature_flags_updated',
            'clinic_settings',
            $clinic->id,
            $old,
            ['feature_flags' => $clinic->feature_flags],
        );

        return $this->respondAdminAction(
            $request,
            'Feature flags saved.',
            'admin.operations.index',
            [],
            ['clinicSettings' => ClinicSetting::current()],
        );
    }
}
