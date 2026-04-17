<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('waitlist_entries', function (Blueprint $table) {
            $table->string('lead_source', 48)->default('unknown')->after('status');
            $table->index('lead_source', 'waitlist_entries_lead_source_index');
        });
    }

    public function down(): void
    {
        Schema::table('waitlist_entries', function (Blueprint $table) {
            $table->dropIndex('waitlist_entries_lead_source_index');
            $table->dropColumn('lead_source');
        });
    }
};
