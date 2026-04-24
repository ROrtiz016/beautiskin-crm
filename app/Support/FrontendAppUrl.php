<?php

namespace App\Support;

use Illuminate\Http\RedirectResponse;

/**
 * Builds absolute URLs to the Next.js CRM when {@see config('app.frontend_url')} is set.
 */
final class FrontendAppUrl
{
    public static function isConfigured(): bool
    {
        $base = config('app.frontend_url');

        return is_string($base) && trim($base) !== '';
    }

    /**
     * Absolute URL to a path on the SPA (leading slash optional).
     *
     * @param  array<string, mixed>  $query
     */
    public static function spa(string $path, array $query = []): string
    {
        $path = '/'.ltrim($path, '/');
        $url = rtrim((string) config('app.frontend_url'), '/').$path;
        $query = array_filter(
            $query,
            static fn (mixed $v): bool => $v !== null && $v !== ''
        );
        if ($query !== []) {
            $url .= '?'.http_build_query($query);
        }

        return $url;
    }

    /**
     * SPA URL when {@see isConfigured()}, otherwise a Laravel named route URL.
     *
     * @param  array<string, mixed>  $routeParameters  Named route parameters (path / route keys)
     * @param  array<string, mixed>  $query  Query string (for SPA; also appended when falling back to Laravel)
     */
    public static function toSpaOrRoute(string $spaPath, string $routeName, array $routeParameters = [], array $query = []): string
    {
        if (! self::isConfigured()) {
            $base = route($routeName, $routeParameters);
            if ($query === []) {
                return $base;
            }
            $qs = http_build_query(array_filter(
                $query,
                static fn (mixed $v): bool => $v !== null && $v !== ''
            ));

            return $qs === '' ? $base : $base.(str_contains($base, '?') ? '&' : '?').$qs;
        }

        return self::spa($spaPath, $query);
    }

    /**
     * Absolute URL to the SPA appointments calendar (`/appointments`).
     *
     * @param  array<string, mixed>  $query
     */
    public static function appointments(array $query = []): string
    {
        return self::spa('/appointments', $query);
    }

    /**
     * Redirect to the calendar on the SPA when configured; otherwise same-origin Laravel calendar.
     *
     * @param  array<string, mixed>  $query
     */
    public static function redirectToAppointmentsIndex(array $query): RedirectResponse
    {
        if (! self::isConfigured()) {
            return redirect()->route('appointments.index', $query);
        }

        return redirect()->away(self::appointments($query));
    }
}
