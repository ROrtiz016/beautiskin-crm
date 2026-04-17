<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('waitlist_entries', function (Blueprint $table) {
            $table->timestamp('contacted_at')->nullable()->after('lead_source');
            $table->string('contact_method', 32)->nullable()->after('contacted_at');
            $table->text('contact_notes')->nullable()->after('contact_method');
            $table->foreignId('contacted_by_user_id')->nullable()->after('contact_notes')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('waitlist_entries', function (Blueprint $table) {
            $table->dropForeign(['contacted_by_user_id']);
            $table->dropColumn(['contacted_at', 'contact_method', 'contact_notes', 'contacted_by_user_id']);
        });
    }
};
