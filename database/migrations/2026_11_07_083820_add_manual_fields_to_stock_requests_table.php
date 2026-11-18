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
            $table->string('week_label', 50)->nullable()->after('activity_date');
            $table->string('team_member_name')->nullable()->after('team_member_id');
            $table->string('prepared_by_name')->nullable()->after('material_received_by');
            $table->string('approved_by_name')->nullable()->after('prepared_by_name');
            $table->string('wet_received_by')->nullable()->after('approved_by_name');
            $table->string('dry_received_by')->nullable()->after('wet_received_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_requests', function (Blueprint $table) {
            $table->dropColumn([
                'week_label',
                'team_member_name',
                'prepared_by_name',
                'approved_by_name',
                'wet_received_by',
                'dry_received_by',
            ]);
        });
    }
};
