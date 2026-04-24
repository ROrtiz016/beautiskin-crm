<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('address_line1', 255)->nullable()->after('gender');
            $table->string('address_line2', 255)->nullable()->after('address_line1');
            $table->string('city', 120)->nullable()->after('address_line2');
            $table->string('state_region', 120)->nullable()->after('city');
            $table->string('postal_code', 30)->nullable()->after('state_region');
            $table->string('country', 120)->nullable()->after('postal_code');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'address_line1',
                'address_line2',
                'city',
                'state_region',
                'postal_code',
                'country',
            ]);
        });
    }
};
