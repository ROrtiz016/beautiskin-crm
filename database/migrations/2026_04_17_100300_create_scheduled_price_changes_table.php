<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_price_changes', function (Blueprint $table) {
            $table->id();
            $table->morphs('changeable');
            $table->decimal('new_price', 10, 2);
            $table->dateTime('effective_at');
            $table->string('status', 20)->default('pending');
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('applied_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'effective_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_price_changes');
    }
};
