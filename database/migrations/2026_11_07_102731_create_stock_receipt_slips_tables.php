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
        Schema::create('stock_receipt_slips', function (Blueprint $table) {
            $table->id();
            $table->string('mrs_number')->unique();
            $table->string('receipt_type', 100);
            $table->string('do_number', 100);
            $table->string('shipper')->nullable();
            $table->date('received_date');
            $table->date('release_date')->nullable();
            $table->string('authorized_by')->nullable();
            $table->unsignedInteger('quantity_released')->nullable();
            $table->unsignedInteger('quantity_assurance')->nullable();
            $table->string('received_by_name')->nullable();
            $table->string('received_by_role')->nullable();
            $table->string('approved_by_name')->nullable();
            $table->string('approved_by_role')->nullable();
            $table->string('quality_assurance_by_name')->nullable();
            $table->string('quality_assurance_by_role')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('stock_receipt_slip_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_receipt_slip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('sku', 100);
            $table->string('item_name');
            $table->string('lot_code', 100)->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('received_quantity');
            $table->string('uom', 30);
            $table->date('expired_date')->nullable();
            $table->string('status', 50)->nullable();
            $table->text('remark')->nullable();
            $table->timestamps();

            $table->index(['stock_receipt_slip_id', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_receipt_slip_items');
        Schema::dropIfExists('stock_receipt_slips');
    }
};
