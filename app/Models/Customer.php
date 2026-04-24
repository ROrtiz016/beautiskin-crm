<?php

namespace App\Models;

use App\Support\AppointmentFormLookupCache;
use App\Support\PhoneDigits;
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
        'address_line1',
        'address_line2',
        'city',
        'state_region',
        'postal_code',
        'country',
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

    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(CustomerActivity::class)->latest('created_at');
    }

    public function communicationLogs(): HasMany
    {
        return $this->hasMany(CommunicationLog::class)->latest('created_at');
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class)->latest();
    }

    public static function findByEmailAddress(string $email): ?self
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return null;
        }

        return static::query()->whereRaw('LOWER(email) = ?', [$email])->first();
    }

    public static function findByInboundSmsFrom(string $from): ?self
    {
        $digits = PhoneDigits::normalize($from);
        if ($digits === null) {
            return null;
        }

        $byExact = static::query()->where('phone', $from)->first();
        if ($byExact) {
            return $byExact;
        }

        return static::query()->whereNotNull('phone')->get()->first(function (self $customer) use ($digits) {
            return PhoneDigits::normalize($customer->phone) === $digits;
        });
    }
}
