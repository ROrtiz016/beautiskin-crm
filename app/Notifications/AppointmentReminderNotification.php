<?php

namespace App\Notifications;

use App\Models\Appointment;
use App\Models\ClinicSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AppointmentReminderNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly Appointment $appointment)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $settings = ClinicSetting::current();
        $customerName = trim(($this->appointment->customer?->first_name ?? '') . ' ' . ($this->appointment->customer?->last_name ?? ''));
        $serviceNames = $this->appointment->services->pluck('service_name')->filter()->implode(', ');

        $replacements = [
            'clinic_name' => (string) $settings->clinic_name,
            'customer_name' => $customerName !== '' ? $customerName : 'Customer',
            'date' => optional($this->appointment->scheduled_at)->timezone($settings->clinic_timezone ?: config('app.timezone'))->format('Y-m-d') ?: 'TBD',
            'start_time' => optional($this->appointment->scheduled_at)->timezone($settings->clinic_timezone ?: config('app.timezone'))->format('g:i A') ?: 'TBD',
            'end_time' => optional($this->appointment->ends_at)->timezone($settings->clinic_timezone ?: config('app.timezone'))->format('g:i A') ?: 'TBD',
            'staff_name' => $this->appointment->staffUser?->name ?: 'To be assigned',
            'services' => $serviceNames !== '' ? $serviceNames : 'To be confirmed',
        ];

        $subject = $settings->email_templates_enabled
            ? $settings->renderTemplate(
                $settings->reminder_email_subject_template,
                $replacements,
                'Appointment reminder for {{clinic_name}}'
            )
            : 'Appointment Reminder';

        $body = $settings->email_templates_enabled
            ? $settings->renderTemplate(
                $settings->reminder_email_body_template,
                $replacements,
                "Hello {{customer_name}},\n\nThis is a reminder for your appointment at {{clinic_name}}."
            )
            : "Hello {$replacements['customer_name']},\n\nThis is a reminder for your upcoming appointment.";

        $message = (new MailMessage)
            ->subject($subject)
            ->greeting('Hello' . ($customerName !== '' ? ' ' . $customerName : '') . ',')
            ->line($body)
            ->line('Date: ' . $replacements['date'])
            ->line('Time: ' . $replacements['start_time'] . ' - ' . $replacements['end_time'])
            ->line('Staff: ' . $replacements['staff_name'])
            ->line('Services: ' . $replacements['services'])
            ->line('If you need to reschedule, please contact the clinic as soon as possible.');

        if ($settings->email_from_address) {
            $message->from(
                (string) $settings->email_from_address,
                $settings->email_from_name ?: (string) $settings->clinic_name
            );
        }

        return $message;
    }
}
