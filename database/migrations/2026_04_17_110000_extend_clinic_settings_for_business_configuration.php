<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinic_settings', function (Blueprint $table) {
            $table->string('clinic_name')->default('BeautiSkin CRM')->after('id');
            $table->string('clinic_timezone', 100)->default('UTC')->after('clinic_name');
            $table->text('business_hours')->nullable()->after('clinic_timezone');
            $table->unsignedInteger('default_appointment_length_minutes')->default(60)->after('business_hours');
            $table->unsignedInteger('reminder_email_lead_minutes')->default(1440)->after('default_appointment_length_minutes');
            $table->unsignedInteger('reminder_sms_lead_minutes')->default(120)->after('reminder_email_lead_minutes');
            $table->string('email_from_address')->nullable()->after('reminder_sms_lead_minutes');
            $table->string('email_from_name')->nullable()->after('email_from_address');
            $table->boolean('email_templates_enabled')->default(true)->after('email_from_name');
            $table->boolean('sms_templates_enabled')->default(false)->after('email_templates_enabled');
            $table->string('reminder_email_subject_template')->nullable()->after('sms_templates_enabled');
            $table->text('reminder_email_body_template')->nullable()->after('reminder_email_subject_template');
            $table->text('reminder_sms_template')->nullable()->after('reminder_email_body_template');
        });
    }

    public function down(): void
    {
        Schema::table('clinic_settings', function (Blueprint $table) {
            $table->dropColumn([
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
            ]);
        });
    }
};
