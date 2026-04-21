<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('opportunity_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind', 32)->default('general');
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('due_at');
            $table->dateTime('remind_at')->nullable();
            $table->string('status', 24)->default('pending');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['assigned_to_user_id', 'status', 'due_at'], 'tasks_assignee_status_due_idx');
            $table->index(['customer_id', 'status'], 'tasks_customer_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
