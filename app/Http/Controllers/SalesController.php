<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\CustomerMembership;
use App\Services\AppointmentPolicyEnforcer;
use App\Support\ReportDateRange;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SalesController extends Controller
{
    public function index(Request $request): View
    {
        [$from, $to, $rangeStart, $rangeEnd] = ReportDateRange::resolve($request);

        $rangeAgg = Appointment::query()
            ->whereBetween('scheduled_at', [$rangeStart, $rangeEnd])
            ->selectRaw("
                SUM(CASE WHEN status = 'completed' THEN COALESCE(total_amount, 0) ELSE 0 END) as completed_revenue,
                SUM(CASE WHEN status != 'cancelled' THEN 1 ELSE 0 END) as appointment_volume,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as cnt_completed
            ")
            ->first();

        $completedRevenue = (float) ($rangeAgg->completed_revenue ?? 0);
        $appointmentVolume = (int) ($rangeAgg->appointment_volume ?? 0);
        $completedAppointmentCount = (int) ($rangeAgg->cnt_completed ?? 0);

        $lineAgg = AppointmentService::query()
            ->join('appointments', 'appointment_services.appointment_id', '=', 'appointments.id')
            ->whereBetween('appointments.scheduled_at', [$rangeStart, $rangeEnd])
            ->whereNotIn('appointments.status', ['cancelled'])
            ->selectRaw('SUM(appointment_services.line_total) as line_revenue')
            ->first();

        $lineItemRevenue = (float) ($lineAgg->line_revenue ?? 0);

        $newMemberships = CustomerMembership::query()
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
            ->limit(10)
            ->get();

        $tz = AppointmentPolicyEnforcer::clinicTimezone();

        return view('sales.index', [
            'title' => 'Sales · BeautiSkin CRM',
            'clinicTimezone' => $tz,
            'fromDate' => $from->toDateString(),
            'toDate' => $to->toDateString(),
            'rangeLabel' => $from->format('M j, Y').' – '.$to->format('M j, Y'),
            'completedRevenue' => $completedRevenue,
            'appointmentVolume' => $appointmentVolume,
            'completedAppointmentCount' => $completedAppointmentCount,
            'lineItemRevenue' => $lineItemRevenue,
            'newMemberships' => $newMemberships,
            'topServices' => $topServices,
        ]);
    }
}
