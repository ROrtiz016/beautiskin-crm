<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Appointment extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'staff_user_id',
        'customer_membership_id',
        'scheduled_at',
        'ends_at',
        'status',
        'arrived_confirmed',
        'email_reminder_sent_at',
        'total_amount',
        'deposit_amount',
        'deposit_paid',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'ends_at' => 'datetime',
            'arrived_confirmed' => 'boolean',
            'email_reminder_sent_at' => 'datetime',
            'total_amount' => 'decimal:2',
            'deposit_amount' => 'decimal:2',
            'deposit_paid' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function staffUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_user_id');
    }

    public function customerMembership(): BelongsTo
    {
        return $this->belongsTo(CustomerMembership::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(AppointmentService::class);
    }
}
