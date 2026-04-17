<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaitlistEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'service_id',
        'staff_user_id',
        'preferred_date',
        'preferred_start_time',
        'preferred_end_time',
        'status',
        'lead_source',
        'contacted_at',
        'contact_method',
        'contact_notes',
        'contacted_by_user_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'preferred_date' => 'date',
            'contacted_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function staffUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_user_id');
    }

    public function contactedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'contacted_by_user_id');
    }
}
