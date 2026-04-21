<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Opportunity extends Model
{
    public const STAGE_NEW = 'new';

    public const STAGE_QUALIFIED = 'qualified';

    public const STAGE_PROPOSAL = 'proposal';

    public const STAGE_NEGOTIATION = 'negotiation';

    public const STAGE_WON = 'won';

    public const STAGE_LOST = 'lost';

    protected $fillable = [
        'customer_id',
        'owner_user_id',
        'title',
        'stage',
        'amount',
        'expected_close_date',
        'loss_reason',
        'won_at',
        'lost_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'expected_close_date' => 'date',
            'won_at' => 'datetime',
            'lost_at' => 'datetime',
        ];
    }

    /**
     * @return list<string>
     */
    public static function pipelineStages(): array
    {
        return [
            self::STAGE_NEW,
            self::STAGE_QUALIFIED,
            self::STAGE_PROPOSAL,
            self::STAGE_NEGOTIATION,
        ];
    }

    /**
     * @return list<string>
     */
    public static function closedStages(): array
    {
        return [self::STAGE_WON, self::STAGE_LOST];
    }

    /**
     * @return array<string, string>
     */
    public static function stageLabels(): array
    {
        return [
            self::STAGE_NEW => 'New',
            self::STAGE_QUALIFIED => 'Qualified',
            self::STAGE_PROPOSAL => 'Proposal',
            self::STAGE_NEGOTIATION => 'Negotiation',
            self::STAGE_WON => 'Won',
            self::STAGE_LOST => 'Lost',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function isOpen(): bool
    {
        return in_array($this->stage, self::pipelineStages(), true);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}
