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
        Schema::table('stock_receipt_slips', function (Blueprint $table) {
            $table->time('release_time')->nullable()->after('release_date');
            $table->string('operational_manager_by_name')->nullable()->after('approved_by_role');
            $table->string('operational_manager_by_role')->nullable()->after('operational_manager_by_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_receipt_slips', function (Blueprint $table) {
            $table->dropColumn([
                'release_time',
                'operational_manager_by_name',
                'operational_manager_by_role',
            ]);
        });
    }
};
