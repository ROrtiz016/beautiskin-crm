<?php

namespace App\Services;

use App\Models\CommunicationLog;
use App\Models\Customer;
use App\Models\CustomerActivity;
use App\Support\CustomerTimeline;
use Illuminate\Support\Str;

final class CommunicationRecorder
{
    public static function recordManualNote(Customer $customer, int $userId, string $channel, string $summary): void
    {
        $event = match ($channel) {
            'email' => CustomerActivity::EVENT_EMAIL_LOGGED,
            'sms' => CustomerActivity::EVENT_SMS_LOGGED,
            default => CustomerActivity::EVENT_CALL_LOGGED,
        };

        $label = match ($channel) {
            'email' => 'Email',
            'sms' => 'SMS',
            default => 'Phone call',
        };

        $logChannel = match ($channel) {
            'sms' => CommunicationLog::CHANNEL_SMS,
            'email' => CommunicationLog::CHANNEL_EMAIL,
            default => CommunicationLog::CHANNEL_CALL,
        };

        CommunicationLog::query()->create([
            'customer_id' => $customer->id,
            'appointment_id' => null,
            'user_id' => $userId,
            'channel' => $logChannel,
            'direction' => CommunicationLog::DIRECTION_OUTBOUND,
            'provider' => CommunicationLog::PROVIDER_CRM,
            'provider_message_id' => null,
            'template_key' => 'manual_note',
            'subject' => null,
            'body' => $summary,
            'from_address' => null,
            'to_address' => null,
            'status' => 'recorded',
            'meta' => ['kind' => 'manual_timeline_note', 'channel' => $channel],
        ]);

        CustomerTimeline::record(
            $customer,
            $event,
            $label.': '.$summary,
            $userId,
            null,
            CustomerActivity::CATEGORY_COMMUNICATION,
            ['channel' => $channel, 'communication_kind' => 'manual_note'],
        );
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function recordStructured(
        ?Customer $customer,
        string $channel,
        string $direction,
        string $provider,
        ?string $providerMessageId,
        ?string $templateKey,
        ?string $subject,
        string $body,
        ?string $fromAddress,
        ?string $toAddress,
        string $status,
        ?int $userId,
        ?int $appointmentId,
        array $meta = [],
    ): CommunicationLog {
        $log = CommunicationLog::query()->create([
            'customer_id' => $customer?->id,
            'appointment_id' => $appointmentId,
            'user_id' => $userId,
            'channel' => $channel,
            'direction' => $direction,
            'provider' => $provider,
            'provider_message_id' => $providerMessageId,
            'template_key' => $templateKey,
            'subject' => $subject,
            'body' => $body,
            'from_address' => $fromAddress,
            'to_address' => $toAddress,
            'status' => $status,
            'meta' => $meta ?: null,
        ]);

        if ($customer) {
            self::mirrorToTimeline($customer, $log, $userId);
        }

        return $log;
    }

    private static function mirrorToTimeline(Customer $customer, CommunicationLog $log, ?int $userId): void
    {
        $preview = Str::limit(preg_replace('/\s+/', ' ', trim(strip_tags((string) $log->body))), 240);
        $dir = $log->direction === CommunicationLog::DIRECTION_INBOUND ? 'Received' : 'Sent';
        $failed = $log->status === 'failed';

        if ($log->channel === CommunicationLog::CHANNEL_EMAIL) {
            $event = $log->direction === CommunicationLog::DIRECTION_INBOUND
                ? CustomerActivity::EVENT_EMAIL_RECEIVED
                : CustomerActivity::EVENT_EMAIL_SENT;
            $summary = ($failed ? 'Failed to send email' : $dir.' email');
            if ($log->subject) {
                $summary .= ': '.$log->subject;
            }
            if ($preview !== '') {
                $summary .= ' — '.$preview;
            }
        } elseif ($log->channel === CommunicationLog::CHANNEL_SMS) {
            $event = $log->direction === CommunicationLog::DIRECTION_INBOUND
                ? CustomerActivity::EVENT_SMS_RECEIVED
                : CustomerActivity::EVENT_SMS_SENT;
            $summary = ($failed ? 'Failed to send SMS' : $dir.' SMS');
            if ($preview !== '') {
                $summary .= ': '.$preview;
            }
        } else {
            $event = CustomerActivity::EVENT_CALL_LOGGED;
            $summary = $dir.' call: '.$preview;
        }

        CustomerTimeline::record(
            $customer,
            $event,
            $summary,
            $userId,
            null,
            CustomerActivity::CATEGORY_COMMUNICATION,
            [
                'communication_log_id' => $log->id,
                'provider' => $log->provider,
                'template_key' => $log->template_key,
                'channel' => $log->channel,
                'direction' => $log->direction,
            ],
        );
    }
}
