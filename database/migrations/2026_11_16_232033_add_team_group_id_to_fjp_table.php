<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('fjp', function (Blueprint $table) {
            $table->uuid('team_group_id')->nullable()->after('event_date')->index();
            $table->string('team_group_label', 50)->nullable()->after('team_group_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fjp', function (Blueprint $table) {
            $table->dropIndex(['team_group_id']);
            $table->dropColumn(['team_group_id', 'team_group_label']);
        });
    }
};
