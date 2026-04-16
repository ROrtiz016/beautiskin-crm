<?php

namespace App\Providers;

use App\Models\ClinicSetting;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('access-admin-board', function (User $user): bool {
            if (session()->has('impersonator_id')) {
                return false;
            }

            return (bool) $user->is_admin || $user->hasAdminPermission('manage_users');
        });

        Gate::define('manage-feature-flags', function (User $user): bool {
            if (session()->has('impersonator_id')) {
                return false;
            }

            return (bool) $user->is_admin;
        });

        Gate::define('view-experimental-ui', function (User $user): bool {
            if (session()->has('impersonator_id')) {
                return false;
            }

            if (! $user->is_admin) {
                return false;
            }

            return ClinicSetting::current()->experimentalUiEnabled();
        });

        Route::bind('adminUser', function (string $value) {
            return User::withTrashed()->whereKey($value)->firstOrFail();
        });
    }
}
