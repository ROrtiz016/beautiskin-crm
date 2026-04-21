<?php

namespace App\Models;

use App\Support\AppointmentFormLookupCache;
use Illuminate\Database\Eloquent\Builder;
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
        'track_inventory',
        'stock_quantity',
        'reorder_level',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
            'track_inventory' => 'boolean',
            'stock_quantity' => 'integer',
            'reorder_level' => 'integer',
        ];
    }

    /**
     * @return list<string>
     */
    public static function retailCategoryKeys(): array
    {
        return ['product', 'products', 'retail'];
    }

    public static function normalizedCategory(?string $category): string
    {
        return strtolower(trim((string) $category));
    }

    public static function categoryIsRetailLike(?string $category): bool
    {
        $normalized = self::normalizedCategory($category);

        return $normalized !== '' && in_array($normalized, self::retailCategoryKeys(), true);
    }

    public function isRetailLikeCategory(): bool
    {
        return self::categoryIsRetailLike($this->category);
    }

    /**
     * Sellable as an add-on line on a completed visit (in-room retail).
     */
    public function eligibleForRetailSaleOnVisit(): bool
    {
        return $this->isRetailLikeCategory() || $this->track_inventory;
    }

    public function scopeLowStock(Builder $query): Builder
    {
        return $query
            ->where('track_inventory', true)
            ->whereColumn('stock_quantity', '<=', 'reorder_level');
    }

    public function scopeInventoryDashboard(Builder $query): Builder
    {
        return $query->where(function (Builder $inner) {
            $inner
                ->where('track_inventory', true)
                ->orWhere(function (Builder $q) {
                    $keys = self::retailCategoryKeys();
                    $q->whereRaw(
                        'LOWER(TRIM(COALESCE(category, ""))) IN ('.implode(',', array_fill(0, count($keys), '?')).')',
                        $keys
                    );
                });
        });
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
