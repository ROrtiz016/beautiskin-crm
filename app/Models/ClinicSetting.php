<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClinicSetting extends Model
{
    private static ?self $currentCache = null;

    protected $fillable = [
        'clinic_name',
        'clinic_timezone',
        'business_hours',
        'default_appointment_length_minutes',
        'reminder_email_lead_minutes',
        'reminder_sms_lead_minutes',
        'email_from_address',
        'email_from_name',
        'email_templates_enabled',
        'sms_templates_enabled',
        'reminder_email_subject_template',
        'reminder_email_body_template',
        'reminder_sms_template',
        'webhook_inbound_token',
        'followup_email_subject_template',
        'followup_email_body_template',
        'followup_sms_template',
        'no_show_email_subject_template',
        'no_show_email_body_template',
        'no_show_sms_template',
        'default_tax_rate',
        'price_rounding_rule',
        'appointment_cancellation_hours',
        'deposit_required',
        'default_deposit_amount',
        'max_bookings_per_day',
        'feature_flags',
    ];

    protected function casts(): array
    {
        return [
            'default_tax_rate' => 'decimal:6',
            'email_templates_enabled' => 'boolean',
            'sms_templates_enabled' => 'boolean',
            'deposit_required' => 'boolean',
            'default_deposit_amount' => 'decimal:2',
            'feature_flags' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (): void {
            static::$currentCache = null;
        });

        static::deleted(function (): void {
            static::$currentCache = null;
        });
    }

    public static function current(): self
    {
        if (static::$currentCache instanceof self) {
            return static::$currentCache;
        }

        return static::$currentCache = static::query()->firstOrCreate(
            ['id' => 1],
            [
                'clinic_name' => 'BeautiSkin CRM',
                'clinic_timezone' => config('app.timezone', 'UTC'),
                'business_hours' => "Mon-Fri: 9:00 AM - 6:00 PM\nSat: 10:00 AM - 3:00 PM\nSun: Closed",
                'default_appointment_length_minutes' => 60,
                'reminder_email_lead_minutes' => 1440,
                'reminder_sms_lead_minutes' => 120,
                'email_from_address' => config('mail.from.address'),
                'email_from_name' => config('mail.from.name'),
                'email_templates_enabled' => true,
                'sms_templates_enabled' => false,
                'reminder_email_subject_template' => 'Appointment reminder for {{clinic_name}}',
                'reminder_email_body_template' => "Hello {{customer_name}},\n\nThis is a reminder for your appointment at {{clinic_name}}.\nDate: {{date}}\nTime: {{start_time}} - {{end_time}}\nStaff: {{staff_name}}\nServices: {{services}}\n\nIf you need to reschedule, please contact us.",
                'reminder_sms_template' => 'Reminder: {{clinic_name}} appointment on {{date}} at {{start_time}} with {{staff_name}}.',
                'followup_email_subject_template' => 'Following up from {{clinic_name}}',
                'followup_email_body_template' => "Hello {{customer_name}},\n\nWe wanted to follow up from {{clinic_name}}. If you would like to book again or have questions, just reply to this email.\n\nThank you,\n{{clinic_name}}",
                'followup_sms_template' => 'Hi {{first_name}}, it is {{clinic_name}}. Reply if you would like to rebook or need anything.',
                'no_show_email_subject_template' => 'We missed you at {{clinic_name}}',
                'no_show_email_body_template' => "Hello {{customer_name}},\n\nWe missed you at a recent appointment at {{clinic_name}}. We would love to help you reschedule — reply to this email or call us.\n\n— {{clinic_name}}",
                'no_show_sms_template' => 'Hi {{first_name}}, we missed you at {{clinic_name}}. Reply to reschedule anytime.',
                'default_tax_rate' => 0,
                'price_rounding_rule' => 'half_up',
                'appointment_cancellation_hours' => 0,
                'deposit_required' => false,
                'default_deposit_amount' => null,
                'max_bookings_per_day' => null,
                'feature_flags' => [],
            ]
        );
    }

    /**
     * Clear the in-memory singleton (e.g. after tests or long-running workers).
     */
    public static function forgetCurrentCache(): void
    {
        static::$currentCache = null;
    }

    public function experimentalUiEnabled(): bool
    {
        return (bool) ($this->feature_flags['experimental_ui'] ?? false);
    }

    /**
     * @param  array<string, string>  $replacements
     */
    public function renderTemplate(?string $template, array $replacements, string $fallback): string
    {
        $resolved = trim((string) ($template ?: $fallback));

        foreach ($replacements as $key => $value) {
            $resolved = str_replace('{{'.$key.'}}', $value, $resolved);
        }

        return $resolved;
    }
}
