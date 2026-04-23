<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TreatmentPackage extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'package_price',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'package_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'treatment_package_services')
            ->withPivot('quantity')
            ->withTimestamps();
    }

    public function quoteLines(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(QuoteLine::class);
    }
}
