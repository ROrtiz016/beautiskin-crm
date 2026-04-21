<?php

namespace App\Http\Controllers;

use App\Models\ClinicSetting;
use App\Models\CommunicationLog;
use App\Models\Customer;
use App\Services\CommunicationRecorder;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class CommunicationWebhookController extends Controller
{
    public function twilioInboundSms(Request $request): Response
    {
        if (! $this->validWebhookToken($request)) {
            return response('Forbidden', 403);
        }

        $from = (string) $request->input('From', '');
        $to = (string) $request->input('To', '');
        $body = (string) $request->input('Body', '');
        $messageSid = (string) $request->input('MessageSid', '');

        if ($messageSid === '') {
            return response('Bad Request', 400);
        }

        try {
            if (CommunicationLog::query()->where('provider', CommunicationLog::PROVIDER_TWILIO)->where('provider_message_id', $messageSid)->exists()) {
                return response('OK', 200);
            }

            $customer = Customer::findByInboundSmsFrom($from);

            CommunicationRecorder::recordStructured(
                $customer,
                CommunicationLog::CHANNEL_SMS,
                CommunicationLog::DIRECTION_INBOUND,
                CommunicationLog::PROVIDER_TWILIO,
                $messageSid,
                'inbound_sms',
                null,
                $body,
                $from,
                $to,
                'received',
                null,
                null,
                ['twilio_form' => $request->except(['Body'])],
            );
        } catch (QueryException) {
            return response('OK', 200);
        }

        return response('OK', 200);
    }

    public function sendgridInbound(Request $request): Response
    {
        if (! $this->validWebhookToken($request)) {
            return response('Forbidden', 403);
        }

        $from = (string) $request->input('from', '');
        $envelope = $request->input('envelope');
        if (is_string($envelope) && str_starts_with(trim($envelope), '{')) {
            $decoded = json_decode($envelope, true);
            if (is_array($decoded) && ! empty($decoded['from'])) {
                $from = (string) $decoded['from'];
            }
        }
        if ($from === '') {
            return response('Bad Request', 400);
        }
        if (str_contains($from, '<')) {
            if (preg_match('/<([^>]+)>/', $from, $m)) {
                $from = $m[1];
            }
        }
        $from = trim($from);

        $to = (string) $request->input('to', '');
        $subject = (string) $request->input('subject', '');
        $text = (string) $request->input('text', '');
        $html = (string) $request->input('html', '');
        $body = $text !== '' ? $text : trim(strip_tags($html));

        $messageId = (string) $request->input('message-id', $request->input('Message-Id', ''));
        if ($messageId === '') {
            $messageId = 'sg-'.sha1($from.'|'.$to.'|'.$subject.'|'.Str::limit($body, 500));
        }

        try {
            if (CommunicationLog::query()->where('provider', CommunicationLog::PROVIDER_SENDGRID)->where('provider_message_id', $messageId)->exists()) {
                return response('OK', 200);
            }

            $customer = Customer::findByEmailAddress($from);

            CommunicationRecorder::recordStructured(
                $customer,
                CommunicationLog::CHANNEL_EMAIL,
                CommunicationLog::DIRECTION_INBOUND,
                CommunicationLog::PROVIDER_SENDGRID,
                $messageId,
                'inbound_email',
                $subject !== '' ? $subject : null,
                $body,
                $from,
                $to !== '' ? $to : null,
                'received',
                null,
                null,
                ['headers' => Str::limit((string) $request->input('headers', ''), 4000)],
            );
        } catch (QueryException) {
            return response('OK', 200);
        }

        return response('OK', 200);
    }

    private function validWebhookToken(Request $request): bool
    {
        $expected = ClinicSetting::current()->webhook_inbound_token;
        if (! $expected) {
            return false;
        }

        $given = (string) ($request->query('token') ?: $request->header('X-Beautiskin-Webhook-Token', ''));

        return hash_equals((string) $expected, $given);
    }
}
