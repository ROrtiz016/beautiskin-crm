<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ScheduledPriceChange extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPLIED = 'applied';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'changeable_type',
        'changeable_id',
        'new_price',
        'effective_at',
        'status',
        'requested_by_user_id',
        'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'new_price' => 'decimal:2',
            'effective_at' => 'datetime',
            'applied_at' => 'datetime',
        ];
    }

    public function changeable(): MorphTo
    {
        return $this->morphTo();
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }
}
