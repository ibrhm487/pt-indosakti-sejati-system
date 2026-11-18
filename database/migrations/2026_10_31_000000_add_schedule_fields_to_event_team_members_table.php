<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_team_members', function (Blueprint $table) {
            $table->date('schedule_date')->nullable()->after('position');
            $table->string('report_time', 5)->nullable()->after('schedule_date');
            $table->string('start_time', 5)->nullable()->after('report_time');
            $table->string('end_time', 5)->nullable()->after('start_time');
            $table->string('task')->nullable()->after('end_time');
            $table->text('schedule_notes')->nullable()->after('task');
        });
    }

    public function down(): void
    {
        Schema::table('event_team_members', function (Blueprint $table) {
            $table->dropColumn(['schedule_date', 'report_time', 'start_time', 'end_time', 'task', 'schedule_notes']);
        });
    }
};
