<?php

namespace Tests\Feature;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WarehouseProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_creation_persists_product_metadata_without_creating_stock(): void
    {
        $this->seedWorkspaceData();
        $warehouse = User::query()->where('email', 'warehouse@indosakti.com')->firstOrFail();

        $response = $this->actingAs($warehouse)->post(route('warehouse.products.store'), [
            'name' => 'MILO Test Product',
            'sku' => 'TEST-ITEM-01',
            'package_sku' => '12X990gr',
            'product_type' => 'FGR',
            'brand_id' => null,
            'product_category_id' => null,
            'unit' => 'PCH',
            'units_per_case' => 12,
            'default_lot_code' => 'LOT-MASTER-001',
            'minimum_stock' => 0,
            'uses_jug_conversion' => false,
            'sachets_per_jug' => null,
            'is_active' => true,
        ]);

        $response->assertRedirect(route('warehouse.products.index'));
        $this->assertDatabaseHas('products', [
            'sku' => 'TEST-ITEM-01',
            'package_sku' => '12X990gr',
            'product_type' => 'FGR',
            'unit' => 'PCH',
            'units_per_case' => 12,
            'default_lot_code' => 'LOT-MASTER-001',
        ]);
        $product = Product::where('sku', 'TEST-ITEM-01')->firstOrFail();

        $this->assertNull($product->inventory);
        $this->assertSame(12, $product->units_per_case);
        $this->assertDatabaseMissing('stock_movements', ['product_id' => $product->id]);
    }

    public function test_product_creation_ignores_legacy_initial_stock_fields(): void
    {
        $this->seedWorkspaceData();
        $warehouse = User::query()->where('email', 'warehouse@indosakti.com')->firstOrFail();

        $this->actingAs($warehouse)->post(route('warehouse.products.store'), [
            'name' => 'MILO Legacy Stock Fields Product',
            'sku' => 'TEST-ITEM-LEGACY',
            'unit' => 'PCH',
            'minimum_stock' => 0,
            'uses_jug_conversion' => false,
            'is_active' => true,
            'initial_lot_code' => 'LEGACY-LOT',
            'initial_quantity' => 24,
            'initial_expired_date' => '2027-12-31',
        ])->assertRedirect(route('warehouse.products.index'));

        $product = Product::where('sku', 'TEST-ITEM-LEGACY')->firstOrFail();

        $this->assertNull($product->inventory);
        $this->assertDatabaseMissing('stock_movements', ['product_id' => $product->id]);
    }

    public function test_product_creation_does_not_require_a_lot_for_ignored_stock_quantity(): void
    {
        $this->seedWorkspaceData();
        $warehouse = User::query()->where('email', 'warehouse@indosakti.com')->firstOrFail();

        $this->actingAs($warehouse)->post(route('warehouse.products.store'), [
            'name' => 'MILO Stock Quantity Without Lot',
            'sku' => 'TEST-ITEM-NO-LOT',
            'unit' => 'PCH',
            'minimum_stock' => 0,
            'uses_jug_conversion' => false,
            'is_active' => true,
            'initial_quantity' => 5,
        ])->assertRedirect(route('warehouse.products.index'));

        $product = Product::where('sku', 'TEST-ITEM-NO-LOT')->firstOrFail();

        $this->assertNull($product->inventory);
        $this->assertDatabaseMissing('stock_movements', ['product_id' => $product->id]);
    }

    public function test_editing_product_does_not_create_stock_from_legacy_stock_fields(): void
    {
        $this->seedWorkspaceData();
        $warehouse = User::query()->where('email', 'warehouse@indosakti.com')->firstOrFail();
        $product = Product::create([
            'name' => 'MILO Existing Lot Product',
            'sku' => 'TEST-LOT-EDIT-01',
            'unit' => 'PCH',
            'minimum_stock' => 0,
            'is_active' => true,
        ]);
        Inventory::create(['product_id' => $product->id, 'qty_on_hand' => 10, 'qty_reserved' => 0]);
        $oldMovement = StockMovement::create([
            'product_id' => $product->id,
            'type' => 'stock_in',
            'quantity' => 10,
            'balance_after' => 10,
            'lot_code' => 'LOT-OLD-01',
        ]);

        $this->actingAs($warehouse)->put(route('warehouse.products.update', $product), [
            'name' => $product->name,
            'sku' => $product->sku,
            'unit' => 'PCH',
            'minimum_stock' => 0,
            'uses_jug_conversion' => false,
            'is_active' => true,
            'initial_lot_code' => 'LOT-NEW-01',
            'initial_quantity' => 5,
            'default_lot_code' => 'LOT-NEW-01',
        ])->assertRedirect(route('warehouse.products.index'));

        $this->assertSame(10, (int) $product->fresh()->inventory->qty_on_hand);
        $this->assertSame('LOT-NEW-01', $product->fresh()->default_lot_code);
        $this->assertSame('LOT-OLD-01', $oldMovement->fresh()->lot_code);
        $this->assertSame(1, StockMovement::where('product_id', $product->id)->count());
        $this->assertDatabaseMissing('stock_movements', ['lot_code' => 'LOT-NEW-01']);
    }

    public function test_product_creation_rejects_package_sku_longer_than_one_hundred_characters(): void
    {
        $this->seedWorkspaceData();
        $warehouse = User::query()->where('email', 'warehouse@indosakti.com')->firstOrFail();

        $response = $this->actingAs($warehouse)->post(route('warehouse.products.store'), [
            'name' => 'MILO Invalid Product',
            'sku' => 'TEST-ITEM-INVALID',
            'package_sku' => str_repeat('X', 101),
            'product_type' => 'FGR',
            'unit' => 'PCH',
            'minimum_stock' => 0,
            'uses_jug_conversion' => false,
            'is_active' => true,
        ]);

        $response->assertSessionHasErrors('package_sku');
        $this->assertDatabaseMissing('products', ['sku' => 'TEST-ITEM-INVALID']);
    }

    public function test_product_list_can_be_searched_by_package_sku(): void
    {
        $this->seedWorkspaceData();
        $warehouse = User::query()->where('email', 'warehouse@indosakti.com')->firstOrFail();
        Product::query()->create([
            'name' => 'MILO Test Product',
            'sku' => 'TEST-ITEM-SEARCH',
            'package_sku' => '12X990gr',
            'product_type' => 'FGR',
            'unit' => 'PCH',
            'minimum_stock' => 0,
            'is_active' => true,
        ]);

        $this->actingAs($warehouse)
            ->get(route('warehouse.products.index', ['search' => '12X990gr']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Warehouse/Products/Index')
                ->has('products.data', 1)
                ->where('products.data.0.package_sku', '12X990gr'));
    }

    private function seedWorkspaceData(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(DemoDataSeeder::class);
    }
}
