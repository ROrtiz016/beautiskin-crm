<?php

use App\Models\CustomerActivity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_activities', function (Blueprint $table) {
            $table->string('category', 32)->default('system')->after('event_type');
            $table->json('meta')->nullable()->after('summary');
        });

        $taskEvents = [
            CustomerActivity::EVENT_TASK_CREATED,
            CustomerActivity::EVENT_TASK_COMPLETED,
            CustomerActivity::EVENT_TASK_CANCELLED,
            CustomerActivity::EVENT_TASK_UPDATED,
        ];
        DB::table('customer_activities')->whereIn('event_type', $taskEvents)->update([
            'category' => CustomerActivity::CATEGORY_TASK,
        ]);

        DB::table('customer_activities')->where('event_type', CustomerActivity::EVENT_NOTE_ADDED)->update([
            'category' => CustomerActivity::CATEGORY_NOTE,
        ]);
    }

    public function down(): void
    {
        Schema::table('customer_activities', function (Blueprint $table) {
            $table->dropColumn(['category', 'meta']);
        });
    }
};
