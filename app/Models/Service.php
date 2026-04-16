<?php

namespace App\Models;

use App\Support\AppointmentFormLookupCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Service extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'category',
        'duration_minutes',
        'price',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(static fn () => AppointmentFormLookupCache::forgetActiveServices());
        static::deleted(static fn () => AppointmentFormLookupCache::forgetActiveServices());
    }

    public function appointmentServices(): HasMany
    {
        return $this->hasMany(AppointmentService::class);
    }

    public function staffUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function coveredByMemberships(): BelongsToMany
    {
        return $this->belongsToMany(Membership::class)->withTimestamps();
    }

    public function scheduledPriceChanges(): MorphMany
    {
        return $this->morphMany(ScheduledPriceChange::class, 'changeable');
    }
}
