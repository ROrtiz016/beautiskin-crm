<?php

use App\Http\Middleware\RedirectWebUiToFrontend;
use App\Support\FrontendAppUrl;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware([])
                ->group(base_path('routes/webhooks.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            RedirectWebUiToFrontend::class,
        ]);

        $middleware->redirectGuestsTo(function (\Illuminate\Http\Request $request) {
            if ($request->is('api/*')) {
                return null;
            }

            if (FrontendAppUrl::isConfigured()) {
                return FrontendAppUrl::spa('/login');
            }

            return route('login');
        });
        $middleware->redirectUsersTo(function () {
            if (FrontendAppUrl::isConfigured()) {
                return FrontendAppUrl::spa('/customers');
            }

            return route('customers.index');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
