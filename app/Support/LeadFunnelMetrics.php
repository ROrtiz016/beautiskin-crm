<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\CustomerMembership;
use App\Models\WaitlistEntry;
use App\Services\AppointmentPolicyEnforcer;

final class LeadFunnelMetrics
{
    /** Rolling window for “new” counts (clinic start-of-day → app timezone). */
    public const ROLLING_DAYS = 30;

    /**
     * @return array{
     *     leadFunnelNewLeads: int,
     *     leadFunnelContacted: int,
     *     leadFunnelNewCustomers: int,
     *     leadFunnelNewMemberships: int,
     *     leadFunnelRollingDays: int
     * }
     */
    public static function snapshot(): array
    {
        $tz = AppointmentPolicyEnforcer::clinicTimezone();
        $leadSince = now($tz)
            ->subDays(self::ROLLING_DAYS)
            ->startOfDay()
            ->timezone(config('app.timezone'));

        return [
            'leadFunnelNewLeads' => WaitlistEntry::query()
                ->where('created_at', '>=', $leadSince)
                ->where('status', 'waiting')
                ->count(),
            'leadFunnelContacted' => WaitlistEntry::query()
                ->where('status', 'contacted')
                ->count(),
            'leadFunnelNewCustomers' => Customer::query()
                ->where('created_at', '>=', $leadSince)
                ->whereNull('gdpr_deleted_at')
                ->count(),
            'leadFunnelNewMemberships' => CustomerMembership::query()
                ->where('created_at', '>=', $leadSince)
                ->count(),
            'leadFunnelRollingDays' => self::ROLLING_DAYS,
        ];
    }
}
