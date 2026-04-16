<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinic_settings', function (Blueprint $table) {
            $table->unsignedInteger('appointment_cancellation_hours')->default(0)->after('price_rounding_rule');
            $table->boolean('deposit_required')->default(false)->after('appointment_cancellation_hours');
            $table->decimal('default_deposit_amount', 10, 2)->nullable()->after('deposit_required');
            $table->unsignedInteger('max_bookings_per_day')->nullable()->after('default_deposit_amount');
            $table->json('feature_flags')->nullable()->after('max_bookings_per_day');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->decimal('deposit_amount', 10, 2)->nullable()->after('total_amount');
            $table->boolean('deposit_paid')->default(false)->after('deposit_amount');
        });
    }

    public function down(): void
    {
        Schema::table('clinic_settings', function (Blueprint $table) {
            $table->dropColumn([
                'appointment_cancellation_hours',
                'deposit_required',
                'default_deposit_amount',
                'max_bookings_per_day',
                'feature_flags',
            ]);
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn(['deposit_amount', 'deposit_paid']);
        });
    }
};
