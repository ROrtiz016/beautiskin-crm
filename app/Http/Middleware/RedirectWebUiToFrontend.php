<?php

namespace App\Http\Middleware;

use App\Support\FrontendAppUrl;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * When {@see config('app.frontend_url')} is set, browser **GET/HEAD** navigations to Laravel
 * web routes are redirected to the same path on the Next.js app.
 *
 * **POST/PATCH/DELETE** (and other methods) are **not** redirected: they continue into Laravel’s
 * web stack (session, CSRF, form controllers). That keeps password reset, login, logout, and any
 * legacy Blade forms working when the request hits the Laravel origin. The SPA should still
 * prefer `/api` + Sanctum for staff mutations, but this avoids silently sending form posts to
 * the SPA root with HTTP 303.
 */
final class RedirectWebUiToFrontend
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! FrontendAppUrl::isConfigured()) {
            return $next($request);
        }

        $path = '/'.ltrim($request->path(), '/');

        if ($path === '/appointments/day' && ! $request->expectsJson()) {
            return redirect()->away(FrontendAppUrl::appointments($request->query()));
        }

        if ($request->expectsJson()) {
            return $next($request);
        }

        if (! in_array($request->method(), ['GET', 'HEAD'], true)) {
            return $next($request);
        }

        $target = $this->spaUrlForRequest($request);

        return redirect()->away($target);
    }

    private function spaUrlForRequest(Request $request): string
    {
        $path = '/'.ltrim($request->path(), '/');

        if ($path === '/appointments/day') {
            return FrontendAppUrl::appointments($request->query());
        }

        return FrontendAppUrl::spa($path, $request->query());
    }
}
