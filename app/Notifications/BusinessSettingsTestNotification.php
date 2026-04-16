<?php

namespace App\Notifications;

use App\Models\ClinicSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BusinessSettingsTestNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly ClinicSetting $settings)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $replacements = [
            'clinic_name' => (string) $this->settings->clinic_name,
            'customer_name' => 'Test Recipient',
            'date' => now()->timezone($this->settings->clinic_timezone ?: config('app.timezone'))->format('Y-m-d'),
            'start_time' => now()->timezone($this->settings->clinic_timezone ?: config('app.timezone'))->format('g:i A'),
            'end_time' => now()->timezone($this->settings->clinic_timezone ?: config('app.timezone'))->addHour()->format('g:i A'),
            'staff_name' => 'Sample Staff',
            'services' => 'Sample Service',
        ];

        $subject = $this->settings->renderTemplate(
            $this->settings->reminder_email_subject_template,
            $replacements,
            'Appointment reminder for {{clinic_name}}'
        );

        $body = $this->settings->renderTemplate(
            $this->settings->reminder_email_body_template,
            $replacements,
            "Hello {{customer_name}},\n\nThis is a reminder for your appointment at {{clinic_name}}."
        );

        $message = (new MailMessage)
            ->subject($subject)
            ->greeting('Business Configuration Test')
            ->line('This is a test email generated from the Administrator Control Board.')
            ->line('Rendered template preview:')
            ->line($body);

        if ($this->settings->email_from_address) {
            $message->from(
                (string) $this->settings->email_from_address,
                $this->settings->email_from_name ?: (string) $this->settings->clinic_name
            );
        }

        return $message;
    }
}
