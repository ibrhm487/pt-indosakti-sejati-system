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
            $table->string('kecamatan', 100)->nullable()->after('province');
            $table->string('kelurahan', 100)->nullable()->after('kecamatan');
            $table->unsignedInteger('permitter_total_outlet_canteen_sell_drink')->nullable()->after('notes');
            $table->unsignedInteger('permitter_quantity_with_milo')->nullable()->after('permitter_total_outlet_canteen_sell_drink');
            $table->string('permitter_status', 50)->nullable()->after('permitter_quantity_with_milo');
            $table->unsignedInteger('permitter_quantity_without_milo')->nullable()->after('permitter_status');
            $table->unsignedInteger('team_sampling_quantity_with_milo')->nullable()->after('permitter_quantity_without_milo');
            $table->unsignedInteger('team_sampling_quantity_without_milo')->nullable()->after('team_sampling_quantity_with_milo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fjp', function (Blueprint $table) {
            $table->dropColumn([
                'kecamatan',
                'kelurahan',
                'permitter_total_outlet_canteen_sell_drink',
                'permitter_quantity_with_milo',
                'permitter_status',
                'permitter_quantity_without_milo',
                'team_sampling_quantity_with_milo',
                'team_sampling_quantity_without_milo',
            ]);
        });
    }
};
