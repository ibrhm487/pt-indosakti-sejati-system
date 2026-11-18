<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_requests', function (Blueprint $table) {
            $table->string('team_no', 100)->nullable()->after('event_id');
            $table->string('material_category', 30)->nullable()->after('status');
            $table->time('pickup_time')->nullable()->after('notes');
            $table->json('supporting_items')->nullable()->after('pickup_time');
            $table->string('material_prepared_by')->nullable()->after('recipient');
            $table->string('material_received_by')->nullable()->after('material_prepared_by');
        });

        Schema::table('stock_request_items', function (Blueprint $table) {
            $table->string('lot_no', 100)->nullable()->after('product_id');
            $table->decimal('cold', 12, 2)->nullable()->after('quantity_requested');
            $table->decimal('lbs', 12, 2)->nullable()->after('cold');
            $table->string('packing', 100)->nullable()->after('lbs');
            $table->decimal('on_the_spot', 12, 2)->nullable()->after('packing');
            $table->decimal('d1', 12, 2)->nullable()->after('on_the_spot');
            $table->decimal('d2', 12, 2)->nullable()->after('d1');
            $table->decimal('cups', 12, 2)->nullable()->after('d2');
            $table->decimal('spoon', 12, 2)->nullable()->after('cups');
        });
    }

    public function down(): void
    {
        Schema::table('stock_request_items', function (Blueprint $table) {
            $table->dropColumn([
                'lot_no', 'cold', 'lbs', 'packing', 'on_the_spot',
                'd1', 'd2', 'cups', 'spoon',
            ]);
        });

        Schema::table('stock_requests', function (Blueprint $table) {
            $table->dropColumn([
                'team_no', 'material_category', 'pickup_time', 'supporting_items',
                'material_prepared_by', 'material_received_by',
            ]);
        });
    }
};
