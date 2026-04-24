<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Service;
use App\Services\AppointmentPolicyEnforcer;
use App\Support\FrontendAppUrl;
use App\Support\LeadFunnelMetrics;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class HomeController extends Controller
{
    private const BESTSELLER_DAYS = 90;

    public function index(): View
    {
        return view('welcome', $this->homeWelcomePayload());
    }

    /**
     * @return array<string, mixed>
     */
    protected function homeWelcomePayload(): array
    {
        $todaysAppointmentCount = null;
        $clinicTodayLabel = null;
        $appointmentsTodayUrl = null;
        $topServices = collect();
        $topMemberships = collect();
        $topProducts = collect();
        $leadFunnel = [
            'leadFunnelNewLeads' => 0,
            'leadFunnelContacted' => 0,
            'leadFunnelNewCustomers' => 0,
            'leadFunnelNewMemberships' => 0,
            'leadFunnelRollingDays' => LeadFunnelMetrics::ROLLING_DAYS,
        ];

        if (auth()->check()) {
            $tz = AppointmentPolicyEnforcer::clinicTimezone();
            $todayKey = now($tz)->toDateString();
            [$dayStart, $dayEnd] = AppointmentPolicyEnforcer::clinicDayBounds($todayKey);

            $todaysAppointmentCount = Appointment::query()
                ->whereBetween('scheduled_at', [$dayStart, $dayEnd])
                ->whereNotIn('status', ['cancelled'])
                ->count();

            $clinicTodayLabel = now($tz)->format('l, M j, Y');
            $appointmentsTodayQuery = [
                'month' => now($tz)->format('Y-m'),
                'date' => $todayKey,
            ];
            $appointmentsTodayUrl = FrontendAppUrl::toSpaOrRoute(
                '/appointments',
                'appointments.index',
                [],
                $appointmentsTodayQuery,
            );

            $since = $this->bestsellerWindowStart($tz);

            $topServices = $this->topSellingServices($since, includeProductCategories: false);
            $topProducts = $this->topSellingServices($since, includeProductCategories: true);
            $topMemberships = $this->topSellingMemberships($since);

            $leadFunnel = LeadFunnelMetrics::snapshot();
        }

        return [
            'todaysAppointmentCount' => $todaysAppointmentCount,
            'clinicTodayLabel' => $clinicTodayLabel,
            'appointmentsTodayUrl' => $appointmentsTodayUrl,
            'topServices' => $topServices,
            'topMemberships' => $topMemberships,
            'topProducts' => $topProducts,
            'bestsellerDays' => self::BESTSELLER_DAYS,
            ...$leadFunnel,
        ];
    }

    protected function bestsellerWindowStart(string $clinicTz): Carbon
    {
        return now($clinicTz)
            ->subDays(self::BESTSELLER_DAYS)
            ->startOfDay()
            ->timezone(config('app.timezone'));
    }

    /**
     * @return Collection<int, object{name: string, revenue: float, units: float|int}>
     */
    protected function topSellingServices(Carbon $since, bool $includeProductCategories): Collection
    {
        $productCategories = Service::retailCategoryKeys();

        $query = DB::table('appointment_services')
            ->join('appointments', 'appointment_services.appointment_id', '=', 'appointments.id')
            ->join('services', 'appointment_services.service_id', '=', 'services.id')
            ->where('appointments.scheduled_at', '>=', $since)
            ->whereNotIn('appointments.status', ['cancelled']);

        if ($includeProductCategories) {
            $query->whereRaw('LOWER(TRIM(COALESCE(services.category, ""))) IN (?, ?, ?)', $productCategories);
        } else {
            $query->whereRaw('LOWER(TRIM(COALESCE(services.category, ""))) NOT IN (?, ?, ?)', $productCategories);
        }

        return $query
            ->groupBy('services.id', 'services.name')
            ->orderByDesc(DB::raw('SUM(appointment_services.line_total)'))
            ->selectRaw('services.name as name, SUM(appointment_services.line_total) as revenue, SUM(appointment_services.quantity) as units')
            ->limit(8)
            ->get();
    }

    /**
     * @return Collection<int, object{name: string, sold_count: int, revenue: float}>
     */
    protected function topSellingMemberships(Carbon $since): Collection
    {
        return DB::table('customer_memberships')
            ->join('memberships', 'customer_memberships.membership_id', '=', 'memberships.id')
            ->where('customer_memberships.created_at', '>=', $since)
            ->groupBy('memberships.id', 'memberships.name')
            ->orderByDesc(DB::raw('SUM(memberships.monthly_price)'))
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->selectRaw('memberships.name as name, COUNT(*) as sold_count, SUM(memberships.monthly_price) as revenue')
            ->limit(8)
            ->get();
    }
}
