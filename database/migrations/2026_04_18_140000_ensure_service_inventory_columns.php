<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotent: adds inventory columns if missing (e.g. deploy missed an earlier migration).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('services')) {
            return;
        }

        if (
            Schema::hasColumn('services', 'track_inventory')
            && Schema::hasColumn('services', 'stock_quantity')
            && Schema::hasColumn('services', 'reorder_level')
        ) {
            return;
        }

        Schema::table('services', function (Blueprint $table) {
            if (! Schema::hasColumn('services', 'track_inventory')) {
                $table->boolean('track_inventory')->default(false);
            }
            if (! Schema::hasColumn('services', 'stock_quantity')) {
                $table->unsignedInteger('stock_quantity')->default(0);
            }
            if (! Schema::hasColumn('services', 'reorder_level')) {
                $table->unsignedInteger('reorder_level')->default(5);
            }
        });
    }

    public function down(): void
    {
        // Intentionally empty: dropping columns could destroy data if this migration ran alone.
    }
};
