<?php

namespace Tests\Feature;

use App\Enums\StockMovementType;
use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\StockReceiptSlip;
use App\Models\StockReceiptSlipItem;
use App\Models\StockRequest;
use App\Models\StockRequestItem;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Command\Command;
use Tests\TestCase;

class WarehouseTrainingDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_replacement_requires_explicit_apply_confirmation(): void
    {
        $existing = $this->createExistingWarehouseData();

        $this->artisan('warehouse:replace-training-data')
            ->assertExitCode(Command::FAILURE);

        $this->assertModelExists($existing['product']);
        $this->assertDatabaseCount('stock_receipt_slips', 1);
        $this->assertDatabaseCount('stock_requests', 1);
    }

    public function test_replacement_rejects_confirmation_for_another_database(): void
    {
        $existing = $this->createExistingWarehouseData();

        $this->artisan('warehouse:replace-training-data', [
            '--apply' => true,
            '--confirm' => 'REPLACE-WAREHOUSE-DATA:another-database',
        ])->assertExitCode(Command::FAILURE);

        $this->assertModelExists($existing['product']);
        $this->assertDatabaseCount('stock_movements', 1);
    }

    public function test_confirmed_replacement_creates_consistent_training_data_and_preserves_nonwarehouse_records(): void
    {
        $existing = $this->createExistingWarehouseData();
        $confirmation = 'REPLACE-WAREHOUSE-DATA:'.DB::connection()->getDatabaseName();

        $this->artisan('warehouse:replace-training-data', [
            '--apply' => true,
            '--confirm' => $confirmation,
        ])->assertExitCode(Command::SUCCESS);

        $this->assertDatabaseCount('products', 20);
        $this->assertDatabaseCount('inventory', 20);
        $this->assertDatabaseCount('stock_movements', 40);
        $this->assertDatabaseCount('stock_requests', 20);
        $this->assertDatabaseCount('stock_request_items', 20);
        $this->assertDatabaseCount('stock_receipt_slips', 20);
        $this->assertDatabaseCount('stock_receipt_slip_items', 20);
        $this->assertDatabaseCount('stock_receipts', 0);
        $this->assertDatabaseCount('stock_returns', 0);
        $this->assertDatabaseCount('stock_return_items', 0);
        $this->assertSame(20, Product::query()->where('sku', 'like', 'LAT-%')->count());

        $this->assertDatabaseHas('stock_receipt_slips', [
            'mrs_number' => 'MRS-LATIHAN-001',
            'notes' => '[DATA LATIHAN] Dokumen sintetis, bukan transaksi nyata.',
        ]);
        $this->assertDatabaseHas('stock_requests', [
            'mrv_number' => 'MRV-LATIHAN-001',
            'status' => 'issued',
            'approved_by' => null,
            'approved_by_name' => 'Simulasi Data Latihan',
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'lot_code' => 'LOT-LATIHAN-001',
            'notes' => '[DATA LATIHAN] Penerimaan simulasi.',
            'type' => StockMovementType::STOCK_IN->value,
        ]);

        foreach (Product::query()->with(['inventory', 'movements'])->get() as $product) {
            $movements = $product->movements->sortBy([
                ['created_at', 'asc'],
                ['id', 'asc'],
            ])->values();
            $incoming = $movements->firstWhere('type', StockMovementType::STOCK_IN);
            $outgoing = $movements->firstWhere('type', StockMovementType::STOCK_OUT);

            $this->assertCount(2, $movements);
            $this->assertSame($incoming->quantity - $outgoing->quantity, $product->inventory->qty_on_hand);
            $this->assertSame($product->inventory->qty_on_hand, $movements->last()->balance_after);
            $this->assertSame($incoming->lot_code, $outgoing->lot_code);
        }

        $this->assertModelExists($existing['user']);
        $this->assertModelExists($existing['brand']);
        $this->assertModelExists($existing['category']);
        $this->assertModelExists($existing['auditLog']);
        $this->assertDatabaseCount('audit_logs', 2);
    }

    /**
     * @return array{user: User, brand: Brand, category: ProductCategory, product: Product, auditLog: AuditLog}
     */
    private function createExistingWarehouseData(): array
    {
        $user = User::factory()->create();
        $brand = Brand::create(['name' => 'Brand Lama', 'slug' => 'brand-lama', 'is_active' => true]);
        $category = ProductCategory::create(['name' => 'Kategori Lama', 'slug' => 'kategori-lama']);
        $product = Product::create([
            'name' => 'Produk Lama',
            'sku' => 'OLD-PRODUCT',
            'brand_id' => $brand->id,
            'product_category_id' => $category->id,
            'unit' => 'pcs',
            'minimum_stock' => 1,
            'is_active' => true,
        ]);
        $slip = StockReceiptSlip::create([
            'mrs_number' => 'MRS-LAMA-001',
            'receipt_type' => 'Penerimaan lama',
            'do_number' => 'DO-LAMA-001',
            'received_date' => today(),
            'recorded_by' => $user->id,
        ]);
        $receiptItem = StockReceiptSlipItem::create([
            'stock_receipt_slip_id' => $slip->id,
            'product_id' => $product->id,
            'sku' => $product->sku,
            'item_name' => $product->name,
            'received_quantity' => 10,
            'uom' => 'pcs',
        ]);
        app(StockService::class)->receive($product, 10, ['reference' => $receiptItem]);

        $request = StockRequest::create([
            'mrv_number' => 'MRV-LAMA-001',
            'status' => 'pending',
            'requested_by' => $user->id,
        ]);
        StockRequestItem::create([
            'stock_request_id' => $request->id,
            'product_id' => $product->id,
            'quantity_requested' => 1,
        ]);

        $timestamp = now();
        DB::table('stock_receipts')->insert([
            'receipt_number' => 'RECEIPT-LAMA-001',
            'product_id' => $product->id,
            'quantity' => 10,
            'recorded_by' => $user->id,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $returnId = DB::table('stock_returns')->insertGetId([
            'return_number' => 'RETURN-LAMA-001',
            'stock_request_id' => $request->id,
            'status' => 'pending',
            'recorded_by' => $user->id,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        DB::table('stock_return_items')->insert([
            'stock_return_id' => $returnId,
            'product_id' => $product->id,
            'quantity_good' => 0,
            'quantity_damaged' => 0,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        $auditLog = AuditLog::create([
            'action' => 'OLD_WAREHOUSE_ACTION',
            'module' => 'warehouse',
        ]);

        return compact('user', 'brand', 'category', 'product', 'auditLog');
    }
}
