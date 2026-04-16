<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AppointmentWebController extends Controller
{
    public function index(Request $request): View
    {
        $today = Carbon::today();
        $selectedDate = $this->parseDate((string) $request->query('date', $today->toDateString()), $today);
        $monthBase = $this->parseMonth((string) $request->query('month', $selectedDate->format('Y-m')), $selectedDate);
        $status = (string) $request->query('status', '');
        $customerId = (int) $request->query('customer_id', 0);
        $serviceId = (int) $request->query('service_id', 0);
        $arrived = (string) $request->query('arrived', '');
        $staffUserId = (int) $request->query('staff_user_id', 0);

        $monthStart = $monthBase->copy()->startOfMonth();
        $monthEnd = $monthBase->copy()->endOfMonth();
        $calendarStart = $monthStart->copy()->startOfWeek(Carbon::SUNDAY);
        $calendarEnd = $monthEnd->copy()->endOfWeek(Carbon::SATURDAY);

        $baseQuery = Appointment::query()
            ->with(['customer.memberships.membership', 'services', 'staffUser'])
            ->when(in_array($status, ['booked', 'completed', 'cancelled', 'no_show'], true), function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->when($customerId > 0, function ($query) use ($customerId) {
                $query->where('customer_id', $customerId);
            })
            ->when($serviceId > 0, function ($query) use ($serviceId) {
                $query->whereHas('services', function ($serviceQuery) use ($serviceId) {
                    $serviceQuery->where('service_id', $serviceId);
                });
            })
            ->when(in_array($arrived, ['yes', 'no'], true), function ($query) use ($arrived) {
                $query->where('arrived_confirmed', $arrived === 'yes');
            })
            ->when($staffUserId > 0, function ($query) use ($staffUserId) {
                $query->where('staff_user_id', $staffUserId);
            });

        $calendarAppointments = (clone $baseQuery)
            ->whereBetween('scheduled_at', [$calendarStart, $calendarEnd])
            ->orderBy('scheduled_at')
            ->get();

        $appointmentsByDate = $calendarAppointments->groupBy(
            fn (Appointment $appointment) => optional($appointment->scheduled_at)->toDateString()
        );

        $selectedAppointments = $appointmentsByDate->get($selectedDate->toDateString(), collect());

        $todayStr = $today->toDateString();
        if ($today->between($calendarStart->copy()->startOfDay(), $calendarEnd->copy()->endOfDay())) {
            $todaysAppointments = $calendarAppointments
                ->filter(fn (Appointment $appointment) => optional($appointment->scheduled_at)?->toDateString() === $todayStr)
                ->sortBy('scheduled_at')
                ->values();
        } else {
            $todaysAppointments = (clone $baseQuery)
                ->whereDate('scheduled_at', $today)
                ->orderBy('scheduled_at')
                ->get();
        }

        $weeks = [];
        $cursor = $calendarStart->copy();
        while ($cursor->lte($calendarEnd)) {
            $week = [];
            for ($i = 0; $i < 7; $i++) {
                $dateKey = $cursor->toDateString();
                $week[] = [
                    'date' => $cursor->copy(),
                    'in_month' => $cursor->month === $monthBase->month,
                    'is_selected' => $dateKey === $selectedDate->toDateString(),
                    'is_today' => $dateKey === $today->toDateString(),
                    'count' => $appointmentsByDate->get($dateKey, collect())->count(),
                ];
                $cursor->addDay();
            }
            $weeks[] = $week;
        }

        return view('appointments.index', [
            'today' => $today,
            'selectedDate' => $selectedDate,
            'monthBase' => $monthBase,
            'weeks' => $weeks,
            'todaysAppointments' => $todaysAppointments,
            'selectedAppointments' => $selectedAppointments,
            'customers' => Customer::query()->orderBy('first_name')->orderBy('last_name')->get(['id', 'first_name', 'last_name']),
            'services' => Service::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'price']),
            'staffUsers' => User::query()->orderBy('name')->get(['id', 'name']),
            'filters' => [
                'status' => $status,
                'customer_id' => $customerId,
                'service_id' => $serviceId,
                'arrived' => $arrived,
                'staff_user_id' => $staffUserId,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'staff_user_id' => ['nullable', 'exists:users,id'],
            'scheduled_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:scheduled_at'],
            'notes' => ['nullable', 'string'],
            'services' => ['required', 'array', 'min:1'],
            'services.*.service_id' => ['required', 'exists:services,id'],
            'services.*.quantity' => ['nullable', 'integer', 'min:1'],
        ]);

        $appointment = Appointment::query()->create([
            'customer_id' => $validated['customer_id'],
            'staff_user_id' => $validated['staff_user_id'] ?? null,
            'scheduled_at' => $validated['scheduled_at'],
            'ends_at' => $validated['ends_at'] ?? null,
            'status' => 'booked',
            'arrived_confirmed' => false,
            'notes' => $validated['notes'] ?? null,
            'total_amount' => number_format(0, 2, '.', ''),
        ]);

        $this->syncAppointmentServices($appointment, $validated['services']);

        return redirect()
            ->route('appointments.index', [
                'month' => Carbon::parse($validated['scheduled_at'])->format('Y-m'),
                'date' => Carbon::parse($validated['scheduled_at'])->toDateString(),
            ])
            ->with('status', 'Appointment created successfully.');
    }

    public function updateArrival(Request $request, Appointment $appointment): RedirectResponse
    {
        $validated = $request->validate([
            'arrived_confirmed' => ['required', 'boolean'],
        ]);

        $appointment->update([
            'arrived_confirmed' => (bool) $validated['arrived_confirmed'],
        ]);

        return redirect()
            ->route('appointments.index', [
                'month' => optional($appointment->scheduled_at)->format('Y-m'),
                'date' => optional($appointment->scheduled_at)->toDateString(),
            ])
            ->with('status', 'Arrival confirmation updated.');
    }

    public function updateStaff(Request $request, Appointment $appointment): RedirectResponse
    {
        $validated = $request->validate([
            'staff_user_id' => ['nullable', 'exists:users,id'],
        ]);

        $appointment->update([
            'staff_user_id' => $validated['staff_user_id'] ?? null,
        ]);

        return redirect()
            ->route('appointments.index', [
                'month' => optional($appointment->scheduled_at)->format('Y-m'),
                'date' => optional($appointment->scheduled_at)->toDateString(),
            ])
            ->with('status', 'Staff assignment updated.');
    }

    private function syncAppointmentServices(Appointment $appointment, array $serviceLines): void
    {
        $appointment->services()->delete();
        $total = 0.0;

        foreach ($serviceLines as $line) {
            $service = Service::query()->findOrFail((int) $line['service_id']);
            $quantity = (int) ($line['quantity'] ?? 1);
            $lineTotal = round(((float) $service->price) * $quantity, 2);
            $total = round($total + $lineTotal, 2);

            $appointment->services()->create([
                'service_id' => $service->id,
                'service_name' => $service->name,
                'duration_minutes' => $service->duration_minutes,
                'quantity' => $quantity,
                'unit_price' => $service->price,
                'line_total' => number_format($lineTotal, 2, '.', ''),
            ]);
        }

        $appointment->update(['total_amount' => number_format($total, 2, '.', '')]);
    }

    private function parseDate(string $raw, Carbon $fallback): Carbon
    {
        try {
            return Carbon::parse($raw);
        } catch (\Throwable) {
            return $fallback->copy();
        }
    }

    private function parseMonth(string $raw, Carbon $fallback): Carbon
    {
        $month = Carbon::createFromFormat('Y-m', $raw);
        return $month ?: $fallback->copy();
    }
}
