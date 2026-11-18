<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->string('code', 20)->nullable()->unique()->after('slug');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');
            $table->boolean('uses_jug_conversion')->default(false)->after('minimum_stock');
            $table->unsignedInteger('sachets_per_jug')->nullable()->after('uses_jug_conversion');
        });

        Schema::table('stock_receipt_slips', function (Blueprint $table) {
            $table->string('supplier')->nullable()->after('shipper');
            $table->string('document_status', 50)->nullable()->default('GOOD')->after('notes');
        });

        Schema::table('stock_requests', function (Blueprint $table) {
            $table->string('shift', 50)->nullable()->after('week_label');
            $table->json('school_names')->nullable()->after('venue_name');
        });

        Schema::create('out_material_forms', function (Blueprint $table) {
            $table->id();
            $table->string('omf_number')->unique();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->date('issued_date');
            $table->string('department', 150)->nullable();
            $table->string('recipient', 150)->nullable();
            $table->text('notes')->nullable();
            $table->string('received_by_name')->nullable();
            $table->string('received_by_role')->nullable();
            $table->string('approved_by_name')->nullable();
            $table->string('approved_by_role')->nullable();
            $table->string('operational_manager_by_name')->nullable();
            $table->string('operational_manager_by_role')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('out_material_form_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('out_material_form_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('sku', 100);
            $table->string('item_name');
            $table->string('lot_code', 100)->nullable();
            $table->date('expired_date')->nullable();
            $table->unsignedInteger('quantity');
            $table->string('uom', 30);
            $table->text('remark')->nullable();
            $table->timestamps();

            $table->index(['out_material_form_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('out_material_form_items');
        Schema::dropIfExists('out_material_forms');

        Schema::table('stock_requests', function (Blueprint $table) {
            $table->dropColumn(['shift', 'school_names']);
        });

        Schema::table('stock_receipt_slips', function (Blueprint $table) {
            $table->dropColumn(['supplier', 'document_status']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['description', 'uses_jug_conversion', 'sachets_per_jug']);
        });

        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn('code');
        });
    }
};
