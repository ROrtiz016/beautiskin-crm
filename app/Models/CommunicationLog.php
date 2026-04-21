<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunicationLog extends Model
{
    public const CHANNEL_EMAIL = 'email';

    public const CHANNEL_SMS = 'sms';

    public const CHANNEL_CALL = 'call';

    public const DIRECTION_INBOUND = 'inbound';

    public const DIRECTION_OUTBOUND = 'outbound';

    public const PROVIDER_CRM = 'crm';

    public const PROVIDER_SENDGRID = 'sendgrid';

    public const PROVIDER_TWILIO = 'twilio';

    protected $fillable = [
        'customer_id',
        'appointment_id',
        'user_id',
        'channel',
        'direction',
        'provider',
        'provider_message_id',
        'template_key',
        'subject',
        'body',
        'from_address',
        'to_address',
        'status',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
