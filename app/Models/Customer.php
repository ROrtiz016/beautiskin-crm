<?php

namespace App\Models;

use App\Support\AppointmentFormLookupCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;

class Customer extends Model
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'date_of_birth',
        'gender',
        'notes',
        'gdpr_deleted_at',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'gdpr_deleted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saved(static fn () => AppointmentFormLookupCache::forgetCustomers());
        static::deleted(static fn () => AppointmentFormLookupCache::forgetCustomers());
        static::restored(static fn () => AppointmentFormLookupCache::forgetCustomers());
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(CustomerMembership::class);
    }

    public function waitlistEntries(): HasMany
    {
        return $this->hasMany(WaitlistEntry::class);
    }
}
