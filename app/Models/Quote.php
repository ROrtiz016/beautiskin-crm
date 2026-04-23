<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quote extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SENT = 'sent';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_DECLINED = 'declined';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'customer_id',
        'status',
        'title',
        'valid_until',
        'discount_amount',
        'tax_amount',
        'subtotal_amount',
        'total_amount',
        'notes',
        'accepted_at',
        'converted_appointment_id',
    ];

    protected function casts(): array
    {
        return [
            'valid_until' => 'date',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'subtotal_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'accepted_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(QuoteLine::class)->orderBy('id');
    }

    public function convertedAppointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'converted_appointment_id');
    }

    public function appointmentsLinked(): HasMany
    {
        return $this->hasMany(Appointment::class, 'quote_id');
    }
}
