<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\StockRequest;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WarehouseMiosTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_adjustment_reconciles_the_selected_lot_and_mios_balance(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $warehouse = User::where('email', 'warehouse@indosakti.com')->firstOrFail();
        $brand = Brand::create(['name' => 'Adjustment Milo', 'slug' => 'adjustment-milo', 'is_active' => true]);
        $product = Product::create([
            'name' => 'Milo Lot Adjustment',
            'sku' => 'MIOS-ADJUST-001',
            'brand_id' => $brand->id,
            'unit' => 'PCS',
            'is_active' => true,
        ]);
        Inventory::create(['product_id' => $product->id, 'qty_on_hand' => 15, 'qty_reserved' => 0]);
        $this->movement($product, 'stock_in', 10, 10, now()->toDateTimeString(), null, 'LOT-ADJUST-001');
        $this->movement($product, 'stock_in', 5, 15, now()->toDateTimeString(), null, 'LOT-ADJUST-002');

        $this->actingAs($warehouse)
            ->post(route('warehouse.inventory.adjust'), [
                'product_id' => $product->id,
                'lot_code' => 'LOT-ADJUST-001',
                'actual_quantity' => 14,
                'reason' => 'Koreksi hitung fisik',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(19, (int) $product->fresh()->inventory->qty_on_hand);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => 'adjustment',
            'quantity' => 4,
            'lot_code' => 'LOT-ADJUST-001',
        ]);

        $this->actingAs($warehouse)
            ->post(route('warehouse.inventory.adjust'), [
                'product_id' => $product->id,
                'lot_code' => 'LOT-ADJUST-001',
                'actual_quantity' => 6,
                'reason' => 'Koreksi hitung fisik',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(11, (int) $product->fresh()->inventory->qty_on_hand);
        $movementCount = StockMovement::where('product_id', $product->id)->count();

        $this->actingAs($warehouse)
            ->post(route('warehouse.inventory.adjust'), [
                'product_id' => $product->id,
                'lot_code' => 'LOT-ADJUST-001',
                'actual_quantity' => 6,
                'reason' => 'Koreksi hitung fisik',
            ])
            ->assertRedirect()
            ->assertSessionHas('info', 'Saldo fisik sudah sesuai; tidak ada penyesuaian yang diperlukan.');

        $this->assertSame($movementCount, StockMovement::where('product_id', $product->id)->count());
        $this->actingAs($warehouse)
            ->get(route('warehouse.inventory.mios', [
                'brand_id' => $brand->id,
                'start_date' => today()->toDateString(),
                'end_date' => today()->toDateString(),
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Warehouse/Inventory/Mios')
                ->has('rows', 1)
                ->where('rows.0.lots.0.lot_code', 'LOT-ADJUST-001')
                ->where('rows.0.lots.0.incoming_new', 10)
                ->where('rows.0.lots.0.adjustments', -4)
                ->where('rows.0.lots.0.balance', 6)
                ->where('rows.0.lots.1.lot_code', 'LOT-ADJUST-002')
                ->where('rows.0.lots.1.balance', 5)
                ->where('rows.0.incoming_new', 15)
                ->where('rows.0.adjustments', -4)
                ->where('rows.0.balance', 11));
    }

    public function test_stock_adjustment_rejects_a_lot_from_another_product(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $warehouse = User::where('email', 'warehouse@indosakti.com')->firstOrFail();
        $brand = Brand::create(['name' => 'Adjustment Other', 'slug' => 'adjustment-other', 'is_active' => true]);
        $product = Product::create([
            'name' => 'Product without requested lot',
            'sku' => 'MIOS-ADJUST-002',
            'brand_id' => $brand->id,
            'unit' => 'PCS',
            'is_active' => true,
        ]);
        Inventory::create(['product_id' => $product->id, 'qty_on_hand' => 0, 'qty_reserved' => 0]);

        $this->actingAs($warehouse)
            ->from(route('warehouse.inventory.index'))
            ->post(route('warehouse.inventory.adjust'), [
                'product_id' => $product->id,
                'lot_code' => 'LOT-NOT-ON-PRODUCT',
                'actual_quantity' => 5,
                'reason' => 'Koreksi hitung fisik',
            ])
            ->assertRedirect(route('warehouse.inventory.index'))
            ->assertSessionHasErrors('lot_code');

        $this->assertSame(0, (int) $product->fresh()->inventory->qty_on_hand);
        $this->assertDatabaseMissing('stock_movements', [
            'product_id' => $product->id,
            'type' => 'adjustment',
        ]);
    }

    public function test_mios_summarizes_lot_movements_for_the_selected_brand_and_period(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(DemoDataSeeder::class);

        $warehouse = User::where('email', 'warehouse@indosakti.com')->firstOrFail();
        $milo = Brand::create(['name' => 'MIOS Milo', 'slug' => 'mios-milo', 'is_active' => true]);
        $otherBrand = Brand::create(['name' => 'MIOS Other', 'slug' => 'mios-other', 'is_active' => true]);
        $product = Product::create([
            'name' => 'Milo Active Go',
            'sku' => 'MIOS-MILO-001',
            'brand_id' => $milo->id,
            'unit' => 'PCS',
            'is_active' => true,
        ]);
        Product::create([
            'name' => 'Other Brand Product',
            'sku' => 'MIOS-OTHER-001',
            'brand_id' => $otherBrand->id,
            'unit' => 'PCS',
            'is_active' => true,
        ]);

        $this->movement(
            $product,
            'stock_in',
            100,
            100,
            '2026-01-01 09:00:00',
            null,
            'LOT-001',
            '2027-10-01',
        );
        $this->movement($product, 'stock_in', 20, 120, '2026-01-03 09:00:00', null, 'LOT-001');
        $this->movement(
            $product,
            'stock_out',
            15,
            105,
            '2026-01-04 09:00:00',
            (new StockRequest)->getMorphClass(),
            'LOT-001',
        );
        $this->movement($product, 'stock_out', 4, 101, '2026-01-05 09:00:00', 'App\\Models\\StockReceipt', 'LOT-001');
        $this->movement($product, 'return', 2, 103, '2026-01-06 09:00:00', null, 'LOT-001');
        $this->movement($product, 'damage', 3, 103, '2026-01-06 10:00:00', null, 'LOT-001');
        $this->movement($product, 'adjustment', 5, 108, '2026-01-06 11:00:00', null, 'LOT-001');

        $this->actingAs($warehouse)
            ->get(route('warehouse.inventory.mios', [
                'brand_id' => $milo->id,
                'start_date' => '2026-01-02',
                'end_date' => '2026-01-06',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Warehouse/Inventory/Mios')
                ->where('selectedBrandId', $milo->id)
                ->where('selectedBrandName', 'MIOS Milo')
                ->where('filters.start_date', '2026-01-02')
                ->where('filters.end_date', '2026-01-06')
                ->has('rows', 1)
                ->where('rows.0.description', 'Milo Active Go')
                ->where('rows.0.item_number', 'MIOS-MILO-001')
                ->where('rows.0.sku', 'MIOS-MILO-001')
                ->where('rows.0.lots.0.lot_code', 'LOT-001')
                ->where('rows.0.lots.0.expired_date', '2027-10-01')
                ->where('rows.0.beginning_stock', 100)
                ->where('rows.0.incoming_new', 20)
                ->where('rows.0.incoming_return', 2)
                ->where('rows.0.outgoing_mrv', 15)
                ->where('rows.0.outgoing_omf', 4)
                ->where('rows.0.outgoing_damage', 3)
                ->where('rows.0.adjustments', 5)
                ->where('rows.0.balance', 108));
    }

    public function test_mios_groups_lots_but_keeps_similar_product_skus_separate(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $warehouse = User::where('email', 'warehouse@indosakti.com')->firstOrFail();
        $milo = Brand::create(['name' => 'MIOS Grouped Milo', 'slug' => 'mios-grouped-milo', 'is_active' => true]);
        $samplingProduct = Product::create([
            'name' => 'MILO 3IN1 ACTIV-GO',
            'sku' => '12583797',
            'package_sku' => '12X990gr',
            'product_type' => 'FGR',
            'brand_id' => $milo->id,
            'unit' => 'PCS',
            'units_per_case' => 12,
            'is_active' => true,
        ]);
        $pouchProduct = Product::create([
            'name' => 'MILO 3IN1 ACTIV GO NEW POUCH',
            'sku' => '12565642',
            'package_sku' => '12X990gr',
            'product_type' => 'FGR',
            'brand_id' => $milo->id,
            'unit' => 'PCH',
            'is_active' => true,
        ]);
        $this->movement($samplingProduct, 'stock_in', 10, 10, today()->setTime(8, 0)->toDateTimeString(), null, 'SAMPLE-LOT-1');
        $this->movement($samplingProduct, 'stock_in', 5, 15, today()->setTime(8, 1)->toDateTimeString(), null, 'SAMPLE-LOT-2');
        $this->movement($pouchProduct, 'stock_in', 20, 20, today()->setTime(8, 2)->toDateTimeString(), null, 'POUCH-LOT-1');

        $response = $this->actingAs($warehouse)
            ->get(route('warehouse.inventory.mios', [
                'brand_id' => $milo->id,
                'start_date' => today()->toDateString(),
                'end_date' => today()->toDateString(),
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Warehouse/Inventory/Mios')
                ->has('rows', 2));
        $rowsBySku = collect($response->inertiaProps('rows'))->keyBy('sku');

        $this->assertSame(2, count($rowsBySku['12583797']['lots']));
        $this->assertSame(15, $rowsBySku['12583797']['balance']);
        $this->assertSame(3, $rowsBySku['12583797']['balance_pack_units']);
        $this->assertSame(1, $rowsBySku['12583797']['balance_cases']);
        $this->assertSame(1, count($rowsBySku['12565642']['lots']));
        $this->assertSame(20, $rowsBySku['12565642']['balance']);
        $this->assertSame(20, $rowsBySku['12565642']['balance_pack_units']);
        $this->assertNull($rowsBySku['12565642']['balance_cases']);
    }

    public function test_mios_can_show_products_from_all_brands_including_unbranded_products(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $warehouse = User::where('email', 'warehouse@indosakti.com')->firstOrFail();
        $brandA = Brand::create(['name' => 'MIOS Brand A', 'slug' => 'mios-brand-a', 'is_active' => true]);
        $brandB = Brand::create(['name' => 'MIOS Brand B', 'slug' => 'mios-brand-b', 'is_active' => true]);
        $products = collect([
            Product::create(['name' => 'Brand A Item', 'sku' => 'MIOS-ALL-A', 'brand_id' => $brandA->id, 'unit' => 'PCS', 'is_active' => true]),
            Product::create(['name' => 'Brand B Item', 'sku' => 'MIOS-ALL-B', 'brand_id' => $brandB->id, 'unit' => 'PCS', 'is_active' => true]),
            Product::create(['name' => 'No Brand Item', 'sku' => 'MIOS-ALL-N', 'unit' => 'PCS', 'is_active' => true]),
        ]);

        foreach ($products as $index => $product) {
            $this->movement(
                $product,
                'stock_in',
                $index + 1,
                $index + 1,
                today()->setTime(8, $index)->toDateTimeString(),
                null,
                'ALL-LOT-'.$index,
            );
        }

        $this->actingAs($warehouse)
            ->get(route('warehouse.inventory.mios', [
                'start_date' => today()->toDateString(),
                'end_date' => today()->toDateString(),
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Warehouse/Inventory/Mios')
                ->where('selectedBrandId', null)
                ->where('selectedBrandName', null)
                ->has('rows', 3));
    }

    private function movement(
        Product $product,
        string $type,
        int $quantity,
        int $balanceAfter,
        string $createdAt,
        ?string $referenceType,
        ?string $lotCode,
        ?string $expiredDate = null,
    ): void {
        $movement = new StockMovement([
            'product_id' => $product->id,
            'type' => $type,
            'quantity' => $quantity,
            'balance_after' => $balanceAfter,
            'reference_type' => $referenceType,
            'reference_id' => $referenceType ? 1 : null,
            'lot_code' => $lotCode,
            'expired_date' => $expiredDate,
        ]);
        $movement->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save();
    }
}
