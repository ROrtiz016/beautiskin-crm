<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;

class CustomerActivity extends Model
{
    public const EVENT_TASK_CREATED = 'task_created';

    public const EVENT_TASK_COMPLETED = 'task_completed';

    public const EVENT_TASK_CANCELLED = 'task_cancelled';

    public const EVENT_TASK_UPDATED = 'task_updated';

    public const EVENT_NOTE_ADDED = 'note_added';

    public const EVENT_APPOINTMENT_CREATED = 'appointment_created';

    public const EVENT_APPOINTMENT_UPDATED = 'appointment_updated';

    public const EVENT_APPOINTMENT_STATUS = 'appointment_status_changed';

    public const EVENT_APPOINTMENT_RESCHEDULED = 'appointment_rescheduled';

    public const EVENT_PAYMENT_COMPLETED_VISIT = 'payment_completed_visit';

    public const EVENT_CALL_LOGGED = 'call_logged';

    public const EVENT_EMAIL_LOGGED = 'email_logged';

    public const EVENT_SMS_LOGGED = 'sms_logged';

    public const EVENT_EMAIL_SENT = 'email_sent';

    public const EVENT_EMAIL_RECEIVED = 'email_received';

    public const EVENT_SMS_SENT = 'sms_sent';

    public const EVENT_SMS_RECEIVED = 'sms_received';

    public const EVENT_OPPORTUNITY_CREATED = 'opportunity_created';

    public const EVENT_OPPORTUNITY_STAGE_CHANGED = 'opportunity_stage_changed';

    public const EVENT_OPPORTUNITY_REMOVED = 'opportunity_removed';

    public const CATEGORY_NOTE = 'note';

    public const CATEGORY_TASK = 'task';

    public const CATEGORY_APPOINTMENT = 'appointment';

    public const CATEGORY_PAYMENT = 'payment';

    public const CATEGORY_COMMUNICATION = 'communication';

    public const CATEGORY_SYSTEM = 'system';

    public const CATEGORY_SALES = 'sales';

    protected $fillable = [
        'customer_id',
        'user_id',
        'event_type',
        'category',
        'summary',
        'meta',
        'related_task_id',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function categoryLabels(): array
    {
        return [
            self::CATEGORY_NOTE => 'Notes',
            self::CATEGORY_TASK => 'Tasks',
            self::CATEGORY_APPOINTMENT => 'Appointments',
            self::CATEGORY_PAYMENT => 'Payments',
            self::CATEGORY_COMMUNICATION => 'Calls / email / SMS',
            self::CATEGORY_SALES => 'Sales / pipeline',
            self::CATEGORY_SYSTEM => 'System',
        ];
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeTimelineFilter(Builder $query, Request $request): Builder
    {
        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $like = '%'.addcslashes($q, '%_\\').'%';
            $query->where('summary', 'like', $like);
        }

        $category = (string) $request->query('category', '');
        $allowed = array_keys(self::categoryLabels());
        if ($category !== '' && in_array($category, $allowed, true)) {
            $query->where('category', $category);
        }

        $from = $request->query('from');
        if (is_string($from) && $from !== '') {
            $query->whereDate('created_at', '>=', $from);
        }

        $to = $request->query('to');
        if (is_string($to) && $to !== '') {
            $query->whereDate('created_at', '<=', $to);
        }

        return $query;
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function relatedTask(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'related_task_id');
    }
}
