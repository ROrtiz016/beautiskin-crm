<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Cached dropdown / lookup rows for appointment forms (customers, active services, staff).
 * Invalidated from model events when source data changes.
 */
final class AppointmentFormLookupCache
{
    private const CUSTOMERS_KEY = 'crm.form_lookup.customers.v1';

    private const SERVICES_KEY = 'crm.form_lookup.services_active.v1';

    private const STAFF_KEY = 'crm.form_lookup.staff_users.v1';

    private const TTL_SECONDS = 86400;

    public static function customers(): Collection
    {
        return Cache::remember(self::CUSTOMERS_KEY, self::TTL_SECONDS, function () {
            return Customer::query()
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get(['id', 'first_name', 'last_name', 'email', 'phone']);
        });
    }

    public static function activeServices(): Collection
    {
        return Cache::remember(self::SERVICES_KEY, self::TTL_SECONDS, function () {
            return Service::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'price']);
        });
    }

    public static function staffUsers(): Collection
    {
        return Cache::remember(self::STAFF_KEY, self::TTL_SECONDS, function () {
            return User::query()->orderBy('name')->get(['id', 'name']);
        });
    }

    public static function forgetCustomers(): void
    {
        Cache::forget(self::CUSTOMERS_KEY);
    }

    public static function forgetActiveServices(): void
    {
        Cache::forget(self::SERVICES_KEY);
    }

    public static function forgetStaffUsers(): void
    {
        Cache::forget(self::STAFF_KEY);
    }

    public static function forgetAll(): void
    {
        Cache::forget(self::CUSTOMERS_KEY);
        Cache::forget(self::SERVICES_KEY);
        Cache::forget(self::STAFF_KEY);
    }
}
