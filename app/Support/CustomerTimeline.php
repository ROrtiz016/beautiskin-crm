<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\CustomerActivity;

final class CustomerTimeline
{
    public static function record(
        Customer $customer,
        string $eventType,
        string $summary,
        ?int $userId = null,
        ?int $relatedTaskId = null,
        ?string $category = null,
        ?array $meta = null,
    ): CustomerActivity {
        $category ??= self::inferCategory($eventType);

        return CustomerActivity::create([
            'customer_id' => $customer->id,
            'user_id' => $userId,
            'event_type' => $eventType,
            'category' => $category,
            'summary' => $summary,
            'meta' => $meta,
            'related_task_id' => $relatedTaskId,
        ]);
    }

    public static function inferCategory(string $eventType): string
    {
        return match (true) {
            str_starts_with($eventType, 'task_') => CustomerActivity::CATEGORY_TASK,
            str_starts_with($eventType, 'appointment_') => CustomerActivity::CATEGORY_APPOINTMENT,
            str_starts_with($eventType, 'opportunity_') => CustomerActivity::CATEGORY_SALES,
            $eventType === CustomerActivity::EVENT_NOTE_ADDED => CustomerActivity::CATEGORY_NOTE,
            $eventType === CustomerActivity::EVENT_PAYMENT_COMPLETED_VISIT => CustomerActivity::CATEGORY_PAYMENT,
            in_array($eventType, [
                CustomerActivity::EVENT_CALL_LOGGED,
                CustomerActivity::EVENT_EMAIL_LOGGED,
                CustomerActivity::EVENT_SMS_LOGGED,
                CustomerActivity::EVENT_EMAIL_SENT,
                CustomerActivity::EVENT_EMAIL_RECEIVED,
                CustomerActivity::EVENT_SMS_SENT,
                CustomerActivity::EVENT_SMS_RECEIVED,
            ], true) => CustomerActivity::CATEGORY_COMMUNICATION,
            default => CustomerActivity::CATEGORY_SYSTEM,
        };
    }
}
