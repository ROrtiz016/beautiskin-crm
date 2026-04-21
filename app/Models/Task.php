<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const KIND_CALLBACK = 'callback';

    public const KIND_FOLLOW_UP = 'follow_up';

    public const KIND_PREP = 'prep';

    public const KIND_VISIT_PREP = 'visit_prep';

    public const KIND_GENERAL = 'general';

    protected $fillable = [
        'customer_id',
        'opportunity_id',
        'assigned_to_user_id',
        'created_by_user_id',
        'completed_by_user_id',
        'kind',
        'title',
        'description',
        'due_at',
        'remind_at',
        'status',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'remind_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function kindLabels(): array
    {
        return [
            self::KIND_CALLBACK => 'Callback',
            self::KIND_FOLLOW_UP => 'Follow-up',
            self::KIND_PREP => 'Prep',
            self::KIND_VISIT_PREP => 'Visit prep',
            self::KIND_GENERAL => 'General',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_user_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
