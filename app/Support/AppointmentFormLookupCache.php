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
 *
 * Only plain arrays are persisted in the cache store so unserialization never yields
 * __PHP_Incomplete_Class for Eloquent models (e.g. after deploy or in constrained workers).
 */
final class AppointmentFormLookupCache
{
    private const CUSTOMERS_KEY = 'crm.form_lookup.customers.v1';

    private const SERVICES_KEY = 'crm.form_lookup.services_active.v1';

    private const STAFF_KEY = 'crm.form_lookup.staff_users.v1';

    private const TTL_SECONDS = 86400;

    public static function customers(): Collection
    {
        $rows = Cache::remember(self::CUSTOMERS_KEY, self::TTL_SECONDS, function (): array {
            return Customer::query()
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get(['id', 'first_name', 'last_name', 'email', 'phone'])
                ->map(fn (Customer $c) => [
                    'id' => $c->id,
                    'first_name' => $c->first_name,
                    'last_name' => $c->last_name,
                    'email' => $c->email,
                    'phone' => $c->phone,
                ])
                ->values()
                ->all();
        });

        return self::toRowCollection($rows);
    }

    public static function activeServices(): Collection
    {
        $rows = Cache::remember(self::SERVICES_KEY, self::TTL_SECONDS, function (): array {
            return Service::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'price'])
                ->map(fn (Service $s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'price' => (string) $s->price,
                ])
                ->values()
                ->all();
        });

        return self::toRowCollection($rows);
    }

    public static function staffUsers(): Collection
    {
        $rows = Cache::remember(self::STAFF_KEY, self::TTL_SECONDS, function (): array {
            return User::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (User $u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                ])
                ->values()
                ->all();
        });

        return self::toRowCollection($rows);
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

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return Collection<int, object>
     */
    private static function toRowCollection(array $rows): Collection
    {
        return collect($rows)->map(fn (array $row) => (object) $row);
    }
}
