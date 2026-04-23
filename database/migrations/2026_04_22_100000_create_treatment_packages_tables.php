<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treatment_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('package_price', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('treatment_package_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('treatment_package_id')->constrained('treatment_packages')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamps();

            $table->unique(['treatment_package_id', 'service_id'], 'tpackage_service_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treatment_package_services');
        Schema::dropIfExists('treatment_packages');
    }
};
