<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->text('cancellation_reason')->nullable()->after('notes');
            $table->foreignId('cancelled_by_user_id')->nullable()->after('cancellation_reason')->constrained('users')->nullOnDelete();
            $table->boolean('sales_follow_up_needed')->default(false)->after('cancelled_by_user_id');
            $table->timestamp('cancelled_at')->nullable()->after('sales_follow_up_needed');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelled_by_user_id');
            $table->dropColumn(['cancellation_reason', 'sales_follow_up_needed', 'cancelled_at']);
        });
    }
};
