<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communication_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('channel', 16);
            $table->string('direction', 16);
            $table->string('provider', 24);
            $table->string('provider_message_id', 128)->nullable();
            $table->string('template_key', 64)->nullable();
            $table->string('subject', 512)->nullable();
            $table->text('body')->nullable();
            $table->string('from_address', 255)->nullable();
            $table->string('to_address', 255)->nullable();
            $table->string('status', 32)->default('recorded');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'created_at'], 'communication_logs_customer_created_idx');
            $table->unique(['provider', 'provider_message_id'], 'comm_logs_provider_msg_uidx');
        });

        Schema::table('clinic_settings', function (Blueprint $table) {
            $table->string('webhook_inbound_token', 128)->nullable()->after('reminder_sms_template');
            $table->string('followup_email_subject_template', 255)->nullable();
            $table->text('followup_email_body_template')->nullable();
            $table->string('followup_sms_template', 500)->nullable();
            $table->string('no_show_email_subject_template', 255)->nullable();
            $table->text('no_show_email_body_template')->nullable();
            $table->string('no_show_sms_template', 500)->nullable();
        });

        $token = Str::random(48);
        $row = DB::table('clinic_settings')->orderBy('id')->first();
        if ($row && empty($row->webhook_inbound_token)) {
            DB::table('clinic_settings')->where('id', $row->id)->update([
                'webhook_inbound_token' => $token,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('clinic_settings', function (Blueprint $table) {
            $table->dropColumn([
                'webhook_inbound_token',
                'followup_email_subject_template',
                'followup_email_body_template',
                'followup_sms_template',
                'no_show_email_subject_template',
                'no_show_email_body_template',
                'no_show_sms_template',
            ]);
        });

        Schema::dropIfExists('communication_logs');
    }
};
