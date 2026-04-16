<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->boolean('stackable')->default(false)->after('is_active');
            $table->decimal('max_discount_cap', 10, 2)->nullable()->after('stackable');
            $table->decimal('minimum_purchase', 10, 2)->nullable()->after('max_discount_cap');
        });
    }

    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->dropColumn(['stackable', 'max_discount_cap', 'minimum_purchase']);
        });
    }
};
