<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\ClinicSetting;
use App\Models\Customer;

final class CustomerMessagingTemplateService
{
    /**
     * @return array<string, string>
     */
    public static function buildReplacements(Customer $customer, ?Appointment $appointment, ClinicSetting $settings): array
    {
        $tz = $settings->clinic_timezone ?: (string) config('app.timezone');
        $customerName = trim($customer->first_name.' '.$customer->last_name);

        $serviceNames = '';
        $date = '—';
        $startTime = '—';
        $endTime = '—';
        $staffName = '—';

        if ($appointment) {
            $appointment->loadMissing(['services', 'staffUser']);
            $serviceNames = $appointment->services->pluck('service_name')->filter()->implode(', ') ?: '—';
            $date = optional($appointment->scheduled_at)->timezone($tz)->format('M j, Y') ?: '—';
            $startTime = optional($appointment->scheduled_at)->timezone($tz)->format('g:i A') ?: '—';
            $endTime = optional($appointment->ends_at)->timezone($tz)->format('g:i A') ?: '—';
            $staffName = $appointment->staffUser?->name ?: '—';
        }

        return [
            'clinic_name' => (string) $settings->clinic_name,
            'customer_name' => $customerName !== '' ? $customerName : 'Customer',
            'first_name' => (string) $customer->first_name,
            'last_name' => (string) $customer->last_name,
            'date' => $date,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'staff_name' => $staffName,
            'services' => $serviceNames,
        ];
    }

    /**
     * @return array{subject: ?string, email_body: string, sms_body: string}
     */
    public static function render(string $templateKey, Customer $customer, ?Appointment $appointment, ClinicSetting $settings): array
    {
        $replacements = self::buildReplacements($customer, $appointment, $settings);

        return match ($templateKey) {
            'reminder' => [
                'subject' => $settings->email_templates_enabled
                    ? $settings->renderTemplate(
                        $settings->reminder_email_subject_template,
                        $replacements,
                        'Appointment reminder for {{clinic_name}}',
                    )
                    : 'Appointment reminder',
                'email_body' => $settings->email_templates_enabled
                    ? $settings->renderTemplate(
                        $settings->reminder_email_body_template,
                        $replacements,
                        "Hello {{customer_name}},\n\nThis is a reminder for your appointment at {{clinic_name}}.",
                    )
                    : "Hello {$replacements['customer_name']},\n\nThis is a reminder for your upcoming appointment.",
                'sms_body' => $settings->sms_templates_enabled
                    ? $settings->renderTemplate(
                        $settings->reminder_sms_template,
                        $replacements,
                        'Reminder: {{clinic_name}} on {{date}} at {{start_time}}.',
                    )
                    : "Reminder: {$replacements['clinic_name']} on {$replacements['date']} at {$replacements['start_time']}.",
            ],
            'follow_up' => [
                'subject' => $settings->email_templates_enabled
                    ? $settings->renderTemplate(
                        $settings->followup_email_subject_template,
                        $replacements,
                        'Following up from {{clinic_name}}',
                    )
                    : 'Following up',
                'email_body' => $settings->email_templates_enabled
                    ? $settings->renderTemplate(
                        $settings->followup_email_body_template,
                        $replacements,
                        "Hello {{customer_name}},\n\nWe wanted to follow up from {{clinic_name}}. Reply to this email or call us anytime.\n\nThank you,\n{{clinic_name}}",
                    )
                    : "Hello {$replacements['customer_name']},\n\nWe wanted to follow up from {$replacements['clinic_name']}.",
                'sms_body' => $settings->sms_templates_enabled
                    ? $settings->renderTemplate(
                        $settings->followup_sms_template,
                        $replacements,
                        'Hi {{first_name}}, it is {{clinic_name}}. Let us know if you would like to rebook.',
                    )
                    : "Hi {$replacements['first_name']}, it's {$replacements['clinic_name']}. Reply if you'd like to rebook.",
            ],
            'no_show' => [
                'subject' => $settings->email_templates_enabled
                    ? $settings->renderTemplate(
                        $settings->no_show_email_subject_template,
                        $replacements,
                        'We missed you at {{clinic_name}}',
                    )
                    : 'We missed you',
                'email_body' => $settings->email_templates_enabled
                    ? $settings->renderTemplate(
                        $settings->no_show_email_body_template,
                        $replacements,
                        "Hello {{customer_name}},\n\nWe missed you at your recent visit at {{clinic_name}}. If you would like to reschedule, just reply or call us.\n\n— {{clinic_name}}",
                    )
                    : "Hello {$replacements['customer_name']},\n\nWe missed you at your recent visit. We'd love to help you reschedule.",
                'sms_body' => $settings->sms_templates_enabled
                    ? $settings->renderTemplate(
                        $settings->no_show_sms_template,
                        $replacements,
                        'Hi {{first_name}}, we missed you at {{clinic_name}}. Reply to reschedule.',
                    )
                    : "Hi {$replacements['first_name']}, we missed you at {$replacements['clinic_name']}. Reply to reschedule.",
            ],
            default => [
                'subject' => 'Message from '.$replacements['clinic_name'],
                'email_body' => 'Hello '.$replacements['customer_name'].",\n\n",
                'sms_body' => 'Hello '.$replacements['first_name'].', ',
            ],
        };
    }
}
