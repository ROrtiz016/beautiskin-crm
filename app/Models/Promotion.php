<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Promotion extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'discount_type',
        'discount_value',
        'applies_to',
        'starts_on',
        'ends_on',
        'is_active',
        'stackable',
        'max_discount_cap',
        'minimum_purchase',
    ];

    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:2',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_active' => 'boolean',
            'stackable' => 'boolean',
            'max_discount_cap' => 'decimal:2',
            'minimum_purchase' => 'decimal:2',
        ];
    }

    public function targetedServices(): BelongsToMany
    {
        return $this->belongsToMany(Service::class)->withTimestamps();
    }

    public function targetedMemberships(): BelongsToMany
    {
        return $this->belongsToMany(Membership::class)->withTimestamps();
    }
}
