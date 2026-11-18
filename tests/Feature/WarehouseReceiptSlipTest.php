<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\StockReceipt;
use App\Models\StockReceiptSlip;
use App\Models\StockReceiptSlipItem;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WarehouseReceiptSlipTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_page_displays_the_mrs_form_and_active_products(): void
    {
        $this->seedWorkspaceData();
        $warehouse = $this->user('warehouse@indosakti.com');

        $this->actingAs($warehouse)
            ->get(route('warehouse.receipts.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Warehouse/Receipts/Form')
                ->has('products')
                ->where('mrsNumber', fn (string $number) => str_starts_with($number, 'JKT/'))
                ->where('today', today()->toDateString()));
    }

    public function test_receipts_index_filters_mrs_and_legacy_receipts_by_brand(): void
    {
        $this->seedWorkspaceData();
        $warehouse = $this->user('warehouse@indosakti.com');
        $milo = Brand::create([
            'name' => 'Filter Milo',
            'slug' => 'filter-milo',
            'code' => 'FMLO',
            'is_active' => true,
        ]);
        $dancow = Brand::create([
            'name' => 'Filter Dancow',
            'slug' => 'filter-dancow',
            'code' => 'FDNC',
            'is_active' => true,
        ]);
        $miloProduct = $this->product('FILTER-MILO-001', 'Milo Filter Product', 'pcs');
        $miloProduct->update(['brand_id' => $milo->id]);
        $dancowProduct = $this->product('FILTER-DANCOW-001', 'Dancow Filter Product', 'pcs');
        $dancowProduct->update(['brand_id' => $dancow->id]);

        foreach ([
            [$miloProduct, 'MRS-FILTER-MILO-001', 'DO-FILTER-MILO-001'],
            [$dancowProduct, 'MRS-FILTER-DANCOW-001', 'DO-FILTER-DANCOW-001'],
        ] as [$product, $number, $doNumber]) {
            $slip = StockReceiptSlip::create([
                'mrs_number' => $number,
                'receipt_type' => 'Sampling',
                'do_number' => $doNumber,
                'received_date' => '2026-11-14',
            ]);
            $slip->items()->create([
                'product_id' => $product->id,
                'sku' => $product->sku,
                'item_name' => $product->name,
                'lot_code' => 'LOT-'.$product->sku,
                'received_quantity' => 5,
                'uom' => $product->unit,
            ]);
            StockReceipt::create([
                'receipt_number' => 'RCV-'.$product->sku,
                'product_id' => $product->id,
                'quantity' => 5,
                'received_date' => '2026-11-14',
            ]);
        }

        $this->actingAs($warehouse)
            ->get(route('warehouse.receipts.index', ['brand_id' => $milo->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Warehouse/Receipts/Index')
                ->where('filters.brand_id', $milo->id)
                ->where('slips.data', fn ($slips) => count($slips) === 1
                    && $slips[0]['mrs_number'] === 'MRS-FILTER-MILO-001'
                    && $slips[0]['brand'] === $milo->name)
                ->where('receipts.data', fn ($receipts) => count($receipts) === 1
                    && $receipts[0]['sku'] === $miloProduct->sku
                    && $receipts[0]['brand'] === $milo->name));
    }

    public function test_mrs_stores_multiple_items_and_increases_stock_for_each_line(): void
    {
        $this->seedWorkspaceData();
        $this->travelTo('2026-11-07 10:11:12');
        $warehouse = $this->user('warehouse@indosakti.com');
        $firstProduct = $this->product('MRS-TEA-001', 'Tea Powder', 'kg');
        $secondProduct = $this->product('MRS-CUP-001', 'Paper Cup', 'pcs');
        Inventory::create(['product_id' => $firstProduct->id, 'qty_on_hand' => 8, 'qty_reserved' => 0]);
        Inventory::create(['product_id' => $secondProduct->id, 'qty_on_hand' => 40, 'qty_reserved' => 0]);

        $response = $this->actingAs($warehouse)->post(route('warehouse.receipts.store'), [
            'mrs_number' => 'MRS-20261107-TEST01',
            'receipt_type' => 'Sampling',
            'do_number' => 'DO-2026-001',
            'shipper' => 'Kurir Uji',
            'received_date' => '2026-11-07',
            'release_date' => '2026-11-08',
            'release_time' => '15:30',
            'authorized_by' => 'Budi Gudang',
            'quantity_released' => 6,
            'quantity_assurance' => 2,
            'received_by_name' => 'Ani',
            'received_by_role' => 'Warehouse',
            'approved_by_name' => 'Budi',
            'approved_by_role' => 'Supervisor',
            'operational_manager_by_name' => 'Citra',
            'operational_manager_by_role' => 'Operational Manager',
            'items' => [
                [
                    'product_id' => $firstProduct->id,
                    'sku' => 'MRS-TEA-001',
                    'item_name' => 'Tea Powder',
                    'lot_code' => 'LOT-A1',
                    'description' => 'Green tea',
                    'received_quantity' => 5,
                    'uom' => 'kg',
                    'expired_date' => '2027-10-01',
                    'status' => 'Good',
                    'remark' => 'Sealed',
                ],
                [
                    'product_id' => $secondProduct->id,
                    'sku' => 'MRS-CUP-001',
                    'item_name' => 'Paper Cup',
                    'lot_code' => 'LOT-B2',
                    'description' => '12 oz',
                    'received_quantity' => 9,
                    'uom' => 'pcs',
                    'expired_date' => null,
                    'status' => 'Good',
                    'remark' => null,
                ],
            ],
        ]);
        $slip = StockReceiptSlip::query()->firstOrFail();

        $response->assertRedirect(route('warehouse.receipts.show', $slip));
        $this->assertDatabaseHas('stock_receipt_slips', [
            'id' => $slip->id,
            'receipt_type' => 'Sampling',
            'do_number' => 'DO-2026-001',
            'authorized_by' => 'Budi Gudang',
            'quantity_released' => 6,
            'quantity_assurance' => 2,
            'release_time' => '15:30',
            'operational_manager_by_name' => 'Citra',
        ]);
        $this->assertDatabaseHas('stock_receipt_slip_items', [
            'stock_receipt_slip_id' => $slip->id,
            'sku' => 'MRS-TEA-001',
            'lot_code' => 'LOT-A1',
            'received_quantity' => 5,
            'uom' => 'kg',
        ]);
        $movementWithLot = StockMovement::query()
            ->where('product_id', $firstProduct->id)
            ->where('lot_code', 'LOT-A1')
            ->firstOrFail();
        $this->assertSame('2027-10-01', $movementWithLot->expired_date->toDateString());
        $this->assertSame(13, (int) Inventory::where('product_id', $firstProduct->id)->value('qty_on_hand'));
        $this->assertSame(49, (int) Inventory::where('product_id', $secondProduct->id)->value('qty_on_hand'));

        $items = StockReceiptSlipItem::where('stock_receipt_slip_id', $slip->id)->get();
        $this->assertSame(2, StockMovement::query()
            ->where('reference_type', (new StockReceiptSlipItem)->getMorphClass())
            ->whereIn('reference_id', $items->pluck('id'))
            ->count());

        $this->actingAs($warehouse)
            ->get(route('warehouse.receipts.show', $slip))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Warehouse/Receipts/Show')
                ->where('slip.mrs_number', 'MRS-20261107-TEST01')
                ->where('slip.release_time', '15:30')
                ->where('slip.operational_manager_by_name', 'Citra')
                ->where('slip.operational_manager_by_role', 'Operational Manager')
                ->where('slip.recorded_at', '07/11/2026 10:11')
                ->has('slip.items', 2));
    }

    public function test_release_date_requires_a_release_time(): void
    {
        $this->seedWorkspaceData();
        $warehouse = $this->user('warehouse@indosakti.com');
        $product = $this->product('MRS-TEA-003', 'Tea Powder', 'kg');

        $this->actingAs($warehouse)
            ->post(route('warehouse.receipts.store'), [
                'mrs_number' => 'MRS-20261107-NOTIME',
                'receipt_type' => 'Sampling',
                'do_number' => 'DO-NOTIME',
                'received_date' => '2026-11-07',
                'release_date' => '2026-11-08',
                'items' => [
                    [
                        'product_id' => $product->id,
                        'sku' => $product->sku,
                        'item_name' => $product->name,
                        'received_quantity' => 1,
                        'uom' => $product->unit,
                    ],
                ],
            ])
            ->assertSessionHasErrors('release_time');

        $this->assertDatabaseCount('stock_receipt_slips', 0);
        $this->assertDatabaseMissing('inventory', ['product_id' => $product->id]);
    }

    public function test_invalid_mrs_item_is_rejected_without_recording_or_changing_stock(): void
    {
        $this->seedWorkspaceData();
        $warehouse = $this->user('warehouse@indosakti.com');
        $product = $this->product('MRS-TEA-002', 'Tea Powder', 'kg');

        $this->actingAs($warehouse)
            ->post(route('warehouse.receipts.store'), [
                'mrs_number' => 'MRS-20261107-INVALID',
                'receipt_type' => 'Sampling',
                'do_number' => 'DO-INVALID',
                'received_date' => '2026-11-07',
                'items' => [
                    [
                        'product_id' => $product->id,
                        'sku' => $product->sku,
                        'item_name' => $product->name,
                        'received_quantity' => 0,
                        'uom' => $product->unit,
                    ],
                ],
            ])
            ->assertSessionHasErrors('items.0.received_quantity');

        $this->assertDatabaseCount('stock_receipt_slips', 0);
        $this->assertDatabaseCount('stock_receipt_slip_items', 0);
        $this->assertDatabaseMissing('inventory', ['product_id' => $product->id]);
    }

    public function test_auto_mrs_number_uses_the_single_brand_and_mixed_brand_slips_are_rejected(): void
    {
        $this->seedWorkspaceData();
        $this->travelTo('2026-11-07 10:11:12');
        $warehouse = $this->user('warehouse@indosakti.com');
        $brand = Brand::create([
            'name' => 'MRS Milo',
            'slug' => 'mrs-milo',
            'code' => 'MLO',
            'is_active' => true,
        ]);
        $otherBrand = Brand::create([
            'name' => 'MRS Other',
            'slug' => 'mrs-other',
            'code' => 'OTH',
            'is_active' => true,
        ]);
        $firstProduct = $this->product('MRS-MILO-001', 'Milo Powder', 'kg');
        $firstProduct->update(['brand_id' => $brand->id]);
        $otherProduct = $this->product('MRS-OTHER-001', 'Other Powder', 'kg');
        $otherProduct->update(['brand_id' => $otherBrand->id]);

        $payload = [
            'mrs_number_manual' => false,
            'receipt_type' => 'Sampling',
            'do_number' => 'DO-MRS-AUTO',
            'received_date' => '2026-11-07',
            'items' => [[
                'product_id' => $firstProduct->id,
                'sku' => $firstProduct->sku,
                'item_name' => $firstProduct->name,
                'lot_code' => 'MILO-LOT-001',
                'received_quantity' => 5,
                'uom' => $firstProduct->unit,
            ]],
        ];

        $this->actingAs($warehouse)
            ->from(route('warehouse.receipts.create'))
            ->post(route('warehouse.receipts.store'), [
                ...$payload,
                'items' => [
                    $payload['items'][0],
                    [
                        'product_id' => $otherProduct->id,
                        'sku' => $otherProduct->sku,
                        'item_name' => $otherProduct->name,
                        'lot_code' => 'OTHER-LOT-001',
                        'received_quantity' => 5,
                        'uom' => $otherProduct->unit,
                    ],
                ],
            ])
            ->assertRedirect(route('warehouse.receipts.create'))
            ->assertSessionHasErrors('items');

        $this->assertDatabaseCount('stock_receipt_slips', 0);

        $this->actingAs($warehouse)
            ->post(route('warehouse.receipts.store'), $payload)
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('stock_receipt_slips', [
            'mrs_number' => 'JKT/0711/MLO/01',
        ]);
        $this->assertSame(
            '2026-11-07',
            StockReceiptSlip::where('mrs_number', 'JKT/0711/MLO/01')->firstOrFail()->received_date?->toDateString(),
        );

        $this->actingAs($warehouse)
            ->post(route('warehouse.receipts.store'), $payload)
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('stock_receipt_slips', [
            'mrs_number' => 'JKT/0711/MLO/02',
        ]);
    }

    private function product(string $sku, string $name, string $unit): Product
    {
        return Product::create([
            'name' => $name,
            'sku' => $sku,
            'unit' => $unit,
            'is_active' => true,
        ]);
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    private function seedWorkspaceData(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(DemoDataSeeder::class);
    }
}
