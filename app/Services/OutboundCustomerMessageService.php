<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\ClinicSetting;
use App\Models\CommunicationLog;
use App\Models\Customer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

final class OutboundCustomerMessageService
{
    /**
     * @throws ValidationException
     */
    public static function sendTemplated(
        Customer $customer,
        int $userId,
        string $channel,
        string $templateKey,
        ?int $appointmentId,
    ): void {
        $settings = ClinicSetting::current();
        $appointment = null;
        if ($appointmentId) {
            $appointment = Appointment::query()
                ->where('customer_id', $customer->id)
                ->whereKey($appointmentId)
                ->first();
            if (! $appointment) {
                throw ValidationException::withMessages([
                    'appointment_id' => ['Appointment not found for this customer.'],
                ]);
            }
        }

        if ($templateKey === 'reminder' && ! $appointment) {
            throw ValidationException::withMessages([
                'appointment_id' => ['Reminder template requires an appointment.'],
            ]);
        }

        $rendered = CustomerMessagingTemplateService::render($templateKey, $customer, $appointment, $settings);

        if ($channel === CommunicationLog::CHANNEL_EMAIL) {
            if (! $customer->email) {
                throw ValidationException::withMessages([
                    'channel' => ['Customer has no email address on file.'],
                ]);
            }

            $subject = (string) ($rendered['subject'] ?? 'Message');
            $body = (string) $rendered['email_body'];
            $from = $settings->email_from_address ? (string) $settings->email_from_address : (string) config('mail.from.address');
            $fromName = $settings->email_from_name ? (string) $settings->email_from_name : (string) config('mail.from.name');

            try {
                Mail::raw($body, function ($message) use ($customer, $subject, $from, $fromName) {
                    $message->to($customer->email, trim($customer->first_name.' '.$customer->last_name))
                        ->subject($subject);
                    if ($from !== '') {
                        $message->from($from, $fromName);
                    }
                });
                CommunicationRecorder::recordStructured(
                    $customer,
                    CommunicationLog::CHANNEL_EMAIL,
                    CommunicationLog::DIRECTION_OUTBOUND,
                    CommunicationLog::PROVIDER_CRM,
                    null,
                    $templateKey,
                    $subject,
                    $body,
                    $from,
                    $customer->email,
                    'sent',
                    $userId,
                    $appointment?->id,
                    [],
                );
            } catch (\Throwable $e) {
                CommunicationRecorder::recordStructured(
                    $customer,
                    CommunicationLog::CHANNEL_EMAIL,
                    CommunicationLog::DIRECTION_OUTBOUND,
                    CommunicationLog::PROVIDER_CRM,
                    null,
                    $templateKey,
                    $subject,
                    $body,
                    $from,
                    $customer->email,
                    'failed',
                    $userId,
                    $appointment?->id,
                    ['error' => $e->getMessage()],
                );

                throw ValidationException::withMessages([
                    'channel' => ['Email could not be sent: '.$e->getMessage()],
                ]);
            }

            return;
        }

        if ($channel === CommunicationLog::CHANNEL_SMS) {
            if (! $customer->phone) {
                throw ValidationException::withMessages([
                    'channel' => ['Customer has no phone number on file.'],
                ]);
            }

            $sid = (string) config('services.twilio.account_sid');
            $token = (string) config('services.twilio.auth_token');
            $fromNumber = (string) config('services.twilio.from');

            if ($sid === '' || $token === '' || $fromNumber === '') {
                throw ValidationException::withMessages([
                    'channel' => ['Twilio is not configured (set TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN, TWILIO_FROM_NUMBER in .env).'],
                ]);
            }

            $body = (string) $rendered['sms_body'];
            if (strlen($body) > 1500) {
                $body = substr($body, 0, 1500);
            }

            $response = Http::withBasicAuth($sid, $token)
                ->asForm()
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                    'From' => $fromNumber,
                    'To' => $customer->phone,
                    'Body' => $body,
                ]);

            if (! $response->successful()) {
                CommunicationRecorder::recordStructured(
                    $customer,
                    CommunicationLog::CHANNEL_SMS,
                    CommunicationLog::DIRECTION_OUTBOUND,
                    CommunicationLog::PROVIDER_TWILIO,
                    null,
                    $templateKey,
                    null,
                    $body,
                    $fromNumber,
                    $customer->phone,
                    'failed',
                    $userId,
                    $appointment?->id,
                    ['twilio_response' => $response->body()],
                );

                throw ValidationException::withMessages([
                    'channel' => ['SMS failed: '.$response->body()],
                ]);
            }

            $sidMsg = (string) ($response->json('sid') ?? '');

            CommunicationRecorder::recordStructured(
                $customer,
                CommunicationLog::CHANNEL_SMS,
                CommunicationLog::DIRECTION_OUTBOUND,
                CommunicationLog::PROVIDER_TWILIO,
                $sidMsg !== '' ? $sidMsg : null,
                $templateKey,
                null,
                $body,
                $fromNumber,
                $customer->phone,
                'sent',
                $userId,
                $appointment?->id,
                [],
            );

            return;
        }

        throw ValidationException::withMessages([
            'channel' => ['Unsupported channel.'],
        ]);
    }
}
