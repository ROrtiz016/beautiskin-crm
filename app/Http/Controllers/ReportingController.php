<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\Customer;
use App\Models\WaitlistEntry;
use App\Services\AppointmentPolicyEnforcer;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportingController extends Controller
{
    public function index(Request $request): View
    {
        [$from, $to, $rangeStart, $rangeEnd] = $this->resolveDateRange($request);

        $rangeAgg = Appointment::query()
            ->whereBetween('scheduled_at', [$rangeStart, $rangeEnd])
            ->selectRaw("
                SUM(CASE WHEN status = 'completed' THEN COALESCE(total_amount, 0) ELSE 0 END) as completed_revenue,
                SUM(CASE WHEN status != 'cancelled' THEN 1 ELSE 0 END) as appointment_volume,
                SUM(CASE WHEN status = 'booked' THEN 1 ELSE 0 END) as cnt_booked,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as cnt_completed,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cnt_cancelled,
                SUM(CASE WHEN status = 'no_show' THEN 1 ELSE 0 END) as cnt_no_show
            ")
            ->first();

        $completedRevenue = (float) ($rangeAgg->completed_revenue ?? 0);
        $appointmentVolume = (int) ($rangeAgg->appointment_volume ?? 0);
        $noShowCount = (int) ($rangeAgg->cnt_no_show ?? 0);
        $cancelledCount = (int) ($rangeAgg->cnt_cancelled ?? 0);
        $statusCounts = collect([
            'booked' => (int) ($rangeAgg->cnt_booked ?? 0),
            'completed' => (int) ($rangeAgg->cnt_completed ?? 0),
            'cancelled' => $cancelledCount,
            'no_show' => $noShowCount,
        ]);

        $newCustomers = Customer::query()
            ->whereBetween('created_at', [$rangeStart, $rangeEnd])
            ->count();

        $waitlistOpened = WaitlistEntry::query()
            ->whereBetween('created_at', [$rangeStart, $rangeEnd])
            ->count();

        $topServices = AppointmentService::query()
            ->join('appointments', 'appointment_services.appointment_id', '=', 'appointments.id')
            ->whereBetween('appointments.scheduled_at', [$rangeStart, $rangeEnd])
            ->whereNotIn('appointments.status', ['cancelled'])
            ->select([
                'appointment_services.service_id',
                'appointment_services.service_name',
                DB::raw('SUM(appointment_services.line_total) as revenue'),
                DB::raw('SUM(appointment_services.quantity) as units'),
            ])
            ->groupBy('appointment_services.service_id', 'appointment_services.service_name')
            ->orderByDesc('revenue')
            ->limit(12)
            ->get();

        $tz = AppointmentPolicyEnforcer::clinicTimezone();
        $dailyRows = $this->buildDailyRows($from, $to, $tz, $rangeStart, $rangeEnd);

        return view('admin.reports', [
            'clinicTimezone' => $tz,
            'fromDate' => $from->toDateString(),
            'toDate' => $to->toDateString(),
            'rangeLabel' => $from->format('M j, Y').' – '.$to->format('M j, Y'),
            'completedRevenue' => $completedRevenue,
            'appointmentVolume' => $appointmentVolume,
            'statusCounts' => $statusCounts,
            'noShowCount' => $noShowCount,
            'cancelledCount' => $cancelledCount,
            'newCustomers' => $newCustomers,
            'waitlistOpened' => $waitlistOpened,
            'topServices' => $topServices,
            'dailyRows' => $dailyRows,
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        [$from, $to, $rangeStart, $rangeEnd] = $this->resolveDateRange($request);
        $tz = AppointmentPolicyEnforcer::clinicTimezone();
        $dailyRows = $this->buildDailyRows($from, $to, $tz, $rangeStart, $rangeEnd);

        $filename = 'beautiskin-report-'.$from->format('Y-m-d').'_to_'.$to->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($dailyRows, $from, $to, $tz): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fputcsv($out, ['BeautiSkin CRM — daily summary']);
            fputcsv($out, ['Timezone', $tz]);
            fputcsv($out, ['From', $from->toDateString(), 'To', $to->toDateString()]);
            fputcsv($out, []);
            fputcsv($out, ['Date', 'Scheduled (non-cancelled)', 'Completed revenue (USD)']);
            foreach ($dailyRows as $row) {
                fputcsv($out, [
                    $row['date'],
                    $row['scheduled_count'],
                    number_format($row['completed_revenue'], 2, '.', ''),
                ]);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: Carbon, 3: Carbon} from, to (clinic tz end of day), rangeStart, rangeEnd (app tz for DB)
     */
    private function resolveDateRange(Request $request): array
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $tz = AppointmentPolicyEnforcer::clinicTimezone();
        $appTz = config('app.timezone');

        $to = $request->filled('to')
            ? Carbon::parse($request->query('to'), $tz)->endOfDay()
            : now($tz)->endOfDay();
        $from = $request->filled('from')
            ? Carbon::parse($request->query('from'), $tz)->startOfDay()
            : $to->copy()->subDays(29)->startOfDay();

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        if ($from->diffInDays($to) > 366) {
            $from = $to->copy()->subDays(366)->startOfDay();
        }

        $rangeStart = $from->copy()->timezone($appTz);
        $rangeEnd = $to->copy()->timezone($appTz);

        return [$from, $to, $rangeStart, $rangeEnd];
    }

    /**
     * @return list<array{date: string, scheduled_count: int, completed_revenue: float}>
     */
    private function buildDailyRows(Carbon $from, Carbon $to, string $tz, Carbon $rangeStart, Carbon $rangeEnd): array
    {
        $byDay = [];
        $cursor = $from->copy()->timezone($tz)->startOfDay();
        $last = $to->copy()->timezone($tz)->startOfDay();
        while ($cursor->lte($last)) {
            $byDay[$cursor->toDateString()] = ['scheduled_count' => 0, 'completed_revenue' => 0.0];
            $cursor->addDay();
        }

        foreach (
            Appointment::query()
                ->select(['id', 'scheduled_at', 'status', 'total_amount'])
                ->whereBetween('scheduled_at', [$rangeStart, $rangeEnd])
                ->lazyById(1000, 'id')
            as $appt
        ) {
            if (! $appt->scheduled_at) {
                continue;
            }
            $dayKey = $appt->scheduled_at->timezone($tz)->toDateString();
            if (! isset($byDay[$dayKey])) {
                continue;
            }
            if ($appt->status !== 'cancelled') {
                $byDay[$dayKey]['scheduled_count']++;
            }
            if ($appt->status === 'completed') {
                $byDay[$dayKey]['completed_revenue'] += (float) $appt->total_amount;
            }
        }

        $rows = [];
        foreach ($byDay as $date => $data) {
            $rows[] = [
                'date' => $date,
                'scheduled_count' => $data['scheduled_count'],
                'completed_revenue' => round($data['completed_revenue'], 2),
            ];
        }

        return $rows;
    }
}
