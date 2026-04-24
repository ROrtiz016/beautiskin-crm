<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\ClinicSetting;
use App\Models\CommunicationLog;
use App\Notifications\AppointmentReminderNotification;
use App\Services\CommunicationRecorder;
use App\Services\CustomerMessagingTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppointmentReminderController extends Controller
{
    public function store(Request $request, Appointment $appointment): JsonResponse
    {
        $appointment->loadMissing(['customer', 'services', 'staffUser']);

        if (! $appointment->customer?->email) {
            return response()->json([
                'message' => 'This customer does not have an email address on file.',
            ], 422);
        }

        $appointment->customer->notify(new AppointmentReminderNotification($appointment));

        $settings = ClinicSetting::current();
        $rendered = CustomerMessagingTemplateService::render('reminder', $appointment->customer, $appointment, $settings);
        $from = $settings->email_from_address ? (string) $settings->email_from_address : (string) config('mail.from.address');

        CommunicationRecorder::recordStructured(
            $appointment->customer,
            CommunicationLog::CHANNEL_EMAIL,
            CommunicationLog::DIRECTION_OUTBOUND,
            CommunicationLog::PROVIDER_CRM,
            null,
            'reminder',
            (string) ($rendered['subject'] ?? 'Appointment reminder'),
            (string) ($rendered['email_body'] ?? ''),
            $from,
            (string) $appointment->customer->email,
            'sent',
            (int) $request->user()->id,
            $appointment->id,
            ['trigger' => 'appointment_reminder_email'],
        );

        $appointment->update([
            'email_reminder_sent_at' => now(),
        ]);

        $appointment->refresh();

        return response()->json([
            'message' => 'Appointment reminder email sent.',
            'email_reminder_sent_at' => $appointment->email_reminder_sent_at,
        ]);
    }
}
