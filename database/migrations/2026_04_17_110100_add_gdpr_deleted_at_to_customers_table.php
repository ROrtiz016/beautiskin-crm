<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dateTime('gdpr_deleted_at')->nullable()->after('deleted_at');
            $table->index('gdpr_deleted_at');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['gdpr_deleted_at']);
            $table->dropColumn('gdpr_deleted_at');
        });
    }
};
