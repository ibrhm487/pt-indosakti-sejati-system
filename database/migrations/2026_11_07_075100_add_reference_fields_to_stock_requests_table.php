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
            $table->string('brand_name', 100)->nullable()->after('activity_date');
            $table->string('venue_name')->nullable()->after('brand_name');
            $table->foreignId('team_member_id')
                ->nullable()
                ->after('venue_name')
                ->constrained('users')
                ->nullOnDelete();
            $table->json('jug_details')->nullable()->after('jug_quantity');
            $table->json('material_categories')->nullable()->after('material_category');
            $table->unsignedInteger('sampling_target_wet')->nullable()->after('jug_details');
            $table->unsignedInteger('sampling_target_dry')->nullable()->after('sampling_target_wet');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('team_member_id');
            $table->dropColumn([
                'brand_name',
                'venue_name',
                'jug_details',
                'material_categories',
                'sampling_target_wet',
                'sampling_target_dry',
            ]);
        });
    }
};
