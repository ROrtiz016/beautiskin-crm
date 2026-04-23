<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_payment_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('entry_type', 32);
            $table->string('note', 500)->nullable();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $now = now();

        $appointments = DB::table('appointments')
            ->where('deposit_paid', true)
            ->whereNotNull('deposit_amount')
            ->where('deposit_amount', '>', 0)
            ->select('id', 'deposit_amount')
            ->get();

        foreach ($appointments as $row) {
            $exists = DB::table('appointment_payment_entries')
                ->where('appointment_id', $row->id)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('appointment_payment_entries')->insert([
                'appointment_id' => $row->id,
                'amount' => $row->deposit_amount,
                'entry_type' => 'deposit',
                'note' => 'Imported from legacy deposit record',
                'recorded_by_user_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_payment_entries');
    }
};
