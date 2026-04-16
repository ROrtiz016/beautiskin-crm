<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->index('scheduled_at', 'appointments_scheduled_at_idx');
            $table->index('status', 'appointments_status_idx');
            $table->index('arrived_confirmed', 'appointments_arrived_confirmed_idx');
            $table->index('customer_id', 'appointments_customer_id_idx');
            $table->index('staff_user_id', 'appointments_staff_user_id_idx');
            $table->index(['staff_user_id', 'scheduled_at'], 'appointments_staff_scheduled_idx');
            $table->index(['status', 'scheduled_at'], 'appointments_status_scheduled_idx');
            $table->index(['arrived_confirmed', 'scheduled_at'], 'appointments_arrived_scheduled_idx');
        });

        Schema::table('appointment_services', function (Blueprint $table) {
            $table->index('service_id', 'appointment_services_service_id_idx');
            $table->index(['service_id', 'appointment_id'], 'appointment_services_service_appointment_idx');
        });

        Schema::table('waitlist_entries', function (Blueprint $table) {
            $table->index('preferred_date', 'waitlist_entries_preferred_date_idx');
            $table->index('status', 'waitlist_entries_status_idx');
            $table->index(['preferred_date', 'status'], 'waitlist_entries_date_status_idx');
            $table->index(['preferred_date', 'preferred_start_time'], 'waitlist_entries_date_start_time_idx');
            $table->index('staff_user_id', 'waitlist_entries_staff_user_id_idx');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->index('first_name', 'customers_first_name_idx');
            $table->index('last_name', 'customers_last_name_idx');
            $table->index('phone', 'customers_phone_idx');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('customers_first_name_idx');
            $table->dropIndex('customers_last_name_idx');
            $table->dropIndex('customers_phone_idx');
        });

        Schema::table('waitlist_entries', function (Blueprint $table) {
            $table->dropIndex('waitlist_entries_preferred_date_idx');
            $table->dropIndex('waitlist_entries_status_idx');
            $table->dropIndex('waitlist_entries_date_status_idx');
            $table->dropIndex('waitlist_entries_date_start_time_idx');
            $table->dropIndex('waitlist_entries_staff_user_id_idx');
        });

        Schema::table('appointment_services', function (Blueprint $table) {
            $table->dropIndex('appointment_services_service_id_idx');
            $table->dropIndex('appointment_services_service_appointment_idx');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex('appointments_scheduled_at_idx');
            $table->dropIndex('appointments_status_idx');
            $table->dropIndex('appointments_arrived_confirmed_idx');
            $table->dropIndex('appointments_customer_id_idx');
            $table->dropIndex('appointments_staff_user_id_idx');
            $table->dropIndex('appointments_staff_scheduled_idx');
            $table->dropIndex('appointments_status_scheduled_idx');
            $table->dropIndex('appointments_arrived_scheduled_idx');
        });
    }
};
