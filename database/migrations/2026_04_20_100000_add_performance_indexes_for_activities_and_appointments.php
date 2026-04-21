<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasIndex('customer_activities', 'customer_activities_category_created_idx')) {
            Schema::table('customer_activities', function (Blueprint $table) {
                $table->index(['category', 'created_at'], 'customer_activities_category_created_idx');
            });
        }

        if (! Schema::hasIndex('appointments', 'appointments_scheduled_at_idx')) {
            Schema::table('appointments', function (Blueprint $table) {
                $table->index('scheduled_at', 'appointments_scheduled_at_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('customer_activities', 'customer_activities_category_created_idx')) {
            Schema::table('customer_activities', function (Blueprint $table) {
                $table->dropIndex('customer_activities_category_created_idx');
            });
        }

        if (Schema::hasIndex('appointments', 'appointments_scheduled_at_idx')) {
            Schema::table('appointments', function (Blueprint $table) {
                $table->dropIndex('appointments_scheduled_at_idx');
            });
        }
    }
};
