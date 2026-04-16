<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\AppointmentFormLookupCache;
use App\Support\DashboardLayoutRegistry;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'is_admin', 'permissions', 'dashboard_layouts'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'permissions' => 'array',
            'dashboard_layouts' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saved(static fn () => AppointmentFormLookupCache::forgetStaffUsers());
        static::deleted(static fn () => AppointmentFormLookupCache::forgetStaffUsers());
        static::restored(static fn () => AppointmentFormLookupCache::forgetStaffUsers());
    }

    /**
     * @return list<string>
     */
    public function dashboardPanelOrder(string $dashboard): array
    {
        $defaults = match ($dashboard) {
            'operations' => DashboardLayoutRegistry::OPERATIONS_PANELS,
            'control_board' => DashboardLayoutRegistry::CONTROL_BOARD_PANELS,
            default => [],
        };

        $saved = $this->dashboard_layouts[$dashboard] ?? null;

        return DashboardLayoutRegistry::normalizeOrder($defaults, is_array($saved) ? $saved : null);
    }

    public function hasAdminPermission(string $permission): bool
    {
        if ($this->is_admin) {
            return true;
        }

        $permissions = $this->permissions ?? [];
        return in_array($permission, $permissions, true);
    }

    public function providableServices(): BelongsToMany
    {
        return $this->belongsToMany(Service::class)->withTimestamps();
    }

    public function waitlistEntries(): HasMany
    {
        return $this->hasMany(WaitlistEntry::class, 'staff_user_id');
    }
}
