<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->index('scheduled_at', 'appointments_scheduled_at_index');
            $table->index(['staff_user_id', 'scheduled_at'], 'appointments_staff_scheduled_index');
            $table->index(['customer_id', 'scheduled_at'], 'appointments_customer_scheduled_index');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->index('created_at', 'customers_created_at_index');
        });

        Schema::table('waitlist_entries', function (Blueprint $table) {
            $table->index('created_at', 'waitlist_entries_created_at_index');
            $table->index('preferred_date', 'waitlist_entries_preferred_date_index');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex('appointments_scheduled_at_index');
            $table->dropIndex('appointments_staff_scheduled_index');
            $table->dropIndex('appointments_customer_scheduled_index');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('customers_created_at_index');
        });

        Schema::table('waitlist_entries', function (Blueprint $table) {
            $table->dropIndex('waitlist_entries_created_at_index');
            $table->dropIndex('waitlist_entries_preferred_date_index');
        });
    }
};
