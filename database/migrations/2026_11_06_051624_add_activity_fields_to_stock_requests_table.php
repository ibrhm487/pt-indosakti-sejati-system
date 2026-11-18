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
        Schema::table('stock_requests', function (Blueprint $table) {
            $table->string('activity_type', 100)->nullable()->after('event_id');
            $table->date('activity_date')->nullable()->after('activity_type');
            $table->unsignedInteger('jug_quantity')->nullable()->after('activity_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_requests', function (Blueprint $table) {
            $table->dropColumn(['activity_type', 'activity_date', 'jug_quantity']);
        });
    }
};
