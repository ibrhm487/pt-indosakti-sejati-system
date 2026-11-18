<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Inventory;
use App\Models\OutMaterialForm;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\StockReceiptSlip;
use App\Models\StockRequest;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WarehouseDocumentRevisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_mrs_number_can_be_edited_after_it_is_saved(): void
    {
        $this->seedWorkspaceData();
        $warehouse = $this->user('warehouse@indosakti.com');
        $slip = $this->mrsSlip();

        $this->actingAs($warehouse)
            ->patch(route('warehouse.receipts.number', $slip), [
                'mrs_number' => 'jkt/0911/mlo/07',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('JKT/0911/MLO/07', $slip->fresh()->mrs_number);
    }

    public function test_omf_and_mrv_numbers_can_be_edited_after_saved(): void
    {
        $this->seedWorkspaceData();
        $warehouse = $this->user('warehouse@indosakti.com');
        $omf = $this->omfDocument();
        $mrv = $this->mrvDocument();

        $this->actingAs($warehouse)
            ->patch(route('warehouse.omf.number', $omf), ['omf_number' => 'jkt/0911/mlo/11'])
            ->assertSessionHasNoErrors();

        $this->actingAs($warehouse)
            ->patch(route('warehouse.mrv.number', $mrv), ['mrv_number' => 'jkt/0911/mlo/12'])
            ->assertSessionHasNoErrors();

        $this->assertSame('JKT/0911/MLO/11', $omf->fresh()->omf_number);
        $this->assertSame('JKT/0911/MLO/12', $mrv->fresh()->mrv_number);
    }

    public function test_number_update_is_rejected_when_duplicate_or_not_authorized(): void
    {
        $this->seedWorkspaceData();
        $warehouse = $this->user('warehouse@indosakti.com');
        $first = $this->mrsSlip('JKT/0911/MLO/01');
        $second = $this->mrsSlip('JKT/0911/MLO/02');

        $this->actingAs($warehouse)
            ->patch(route('warehouse.receipts.number', $second), ['mrs_number' => $first->mrs_number])
            ->assertSessionHasErrors('mrs_number');

        $this->actingAs($this->user('team@indosakti.com'))
            ->patch(route('warehouse.receipts.number', $second), ['mrs_number' => 'JKT/0911/MLO/03'])
            ->assertForbidden();

        $this->assertSame('JKT/0911/MLO/02', $second->fresh()->mrs_number);
    }

    public function test_mrv_stores_venue_list_and_exposes_it(): void
    {
        $this->seedWorkspaceData();
        $warehouse = $this->user('warehouse@indosakti.com');
        $product = Product::where('sku', 'KITKAT-MINI')->firstOrFail();

        $this->actingAs($warehouse)
            ->post(route('warehouse.mrv.store'), [
                'mrv_number' => 'JKT/0911/MLO/20',
                'activity_type' => 'Sampling',
                'activity_date' => '2026-11-20',
                'jug_quantity' => 1,
                'venue_names' => ['SDN Bantar Gebang V', 'SDN Bantar Gebang VI'],
                'items' => [[
                    'product_id' => $product->id,
                    'sku' => $product->sku,
                    'quantity_requested' => 5,
                ]],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $mrv = StockRequest::where('mrv_number', 'JKT/0911/MLO/20')->firstOrFail();

        $this->assertSame(
            ['SDN Bantar Gebang V', 'SDN Bantar Gebang VI'],
            $mrv->venue_names,
        );

        $this->actingAs($warehouse)
            ->get(route('warehouse.mrv.show', $mrv))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Warehouse/Mrv/Show')
                ->where('mrv.venues', ['SDN Bantar Gebang V', 'SDN Bantar Gebang VI']));
    }

    public function test_omf_index_filters_documents_by_brand_and_search(): void
    {
        $this->seedWorkspaceData();
        $warehouse = $this->user('warehouse@indosakti.com');
        $milo = Brand::create([
            'name' => 'OMF Filter Milo',
            'slug' => 'omf-filter-milo',
            'code' => 'OFM',
            'is_active' => true,
        ]);
        $dancow = Brand::create([
            'name' => 'OMF Filter Dancow',
            'slug' => 'omf-filter-dancow',
            'code' => 'OFD',
            'is_active' => true,
        ]);
        OutMaterialForm::create([
            'omf_number' => 'OMF-FILTER-MILO-001',
            'brand_id' => $milo->id,
            'issued_date' => '2026-11-14',
            'recipient' => 'Milo Filter Recipient',
        ]);
        OutMaterialForm::create([
            'omf_number' => 'OMF-FILTER-DANCOW-001',
            'brand_id' => $dancow->id,
            'issued_date' => '2026-11-14',
            'recipient' => 'Dancow Filter Recipient',
        ]);

        $this->actingAs($warehouse)
            ->get(route('warehouse.omf.index', [
                'brand_id' => $milo->id,
                'search' => 'Milo Filter',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Warehouse/Omf/Index')
                ->where('filters.brand_id', $milo->id)
                ->where('filters.search', 'Milo Filter')
                ->where('forms.data', fn ($forms) => count($forms) === 1
                    && $forms[0]['omf_number'] === 'OMF-FILTER-MILO-001'
                    && $forms[0]['brand'] === $milo->name));
    }

    public function test_mrv_index_filters_documents_by_brand(): void
    {
        $this->seedWorkspaceData();
        $warehouse = $this->user('warehouse@indosakti.com');
        $milo = Brand::create([
            'name' => 'MRV Filter Milo',
            'slug' => 'mrv-filter-milo',
            'code' => 'RFM',
            'is_active' => true,
        ]);
        $dancow = Brand::create([
            'name' => 'MRV Filter Dancow',
            'slug' => 'mrv-filter-dancow',
            'code' => 'RFD',
            'is_active' => true,
        ]);
        $miloProduct = Product::create([
            'name' => 'MRV Filter Milo Product',
            'sku' => 'MRV-FILTER-MILO-001',
            'brand_id' => $milo->id,
            'unit' => 'pcs',
            'is_active' => true,
        ]);
        $dancowProduct = Product::create([
            'name' => 'MRV Filter Dancow Product',
            'sku' => 'MRV-FILTER-DANCOW-001',
            'brand_id' => $dancow->id,
            'unit' => 'pcs',
            'is_active' => true,
        ]);

        foreach ([
            [$miloProduct, 'JKT/1411/RFM/01'],
            [$dancowProduct, 'JKT/1411/RFD/01'],
        ] as [$product, $number]) {
            $mrv = StockRequest::create([
                'mrv_number' => $number,
                'requested_by' => $warehouse->id,
                'activity_type' => 'Sampling',
                'activity_date' => '2026-11-14',
            ]);
            $mrv->items()->create([
                'product_id' => $product->id,
                'quantity_requested' => 1,
            ]);
        }

        $this->actingAs($warehouse)
            ->get(route('warehouse.mrv.index', ['brand_id' => $milo->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Warehouse/Mrv/Index')
                ->where('filters.brand_id', $milo->id)
                ->where('requests.data', fn ($requests) => count($requests) === 1
                    && $requests[0]['mrv_number'] === 'JKT/1411/RFM/01'
                    && $requests[0]['brand'] === $milo->name));
    }

    public function test_document_number_suggestions_skip_used_suffixes_per_type_brand_and_date(): void
    {
        $this->seedWorkspaceData();
        $date = Carbon::parse('2026-11-09');
        $stem = 'JKT/0911/MLO/';

        StockRequest::create([
            'mrv_number' => $stem.'01',
            'activity_type' => 'Sampling',
            'activity_date' => $date,
        ]);
        StockRequest::create([
            'mrv_number' => $stem.'03',
            'activity_type' => 'Sampling',
            'activity_date' => $date,
        ]);
        StockReceiptSlip::create([
            'mrs_number' => $stem.'01',
            'receipt_type' => 'Sampling',
            'do_number' => 'DO-MRS-01',
            'received_date' => $date,
        ]);
        StockReceiptSlip::create([
            'mrs_number' => $stem.'03',
            'receipt_type' => 'Sampling',
            'do_number' => 'DO-MRS-03',
            'received_date' => $date,
        ]);
        OutMaterialForm::create([
            'omf_number' => $stem.'01',
            'issued_date' => $date,
        ]);
        OutMaterialForm::create([
            'omf_number' => $stem.'03',
            'issued_date' => $date,
        ]);

        $this->assertSame($stem.'02', StockRequest::suggestNumber('MLO', $date));
        $this->assertSame($stem.'02', StockReceiptSlip::suggestNumber('MLO', $date));
        $this->assertSame($stem.'02', OutMaterialForm::suggestNumber('MLO', $date));
        $this->assertSame('JKT/1011/MLO/01', StockRequest::suggestNumber('MLO', Carbon::parse('2026-11-10')));
        $this->assertSame('JKT/0911/OTH/01', StockReceiptSlip::suggestNumber('OTH', $date));
        $this->assertSame('JKT/0911/GEN/01', OutMaterialForm::suggestNumber(null, $date));
    }

    public function test_known_brand_codes_are_used_for_automatic_mrv_mrs_and_omf_numbers(): void
    {
        $this->seedWorkspaceData();
        $date = Carbon::parse('2026-11-09');
        $knownBrands = [
            'milo' => 'MLO',
            'dancow' => 'DCW',
            'kitkat' => 'KKT',
            'bear-brand' => 'BBR',
        ];

        foreach ($knownBrands as $slug => $code) {
            $brand = Brand::where('slug', $slug)->firstOrFail();
            $expectedNumber = 'JKT/0911/'.$code.'/01';

            $this->assertSame($code, $brand->code);
            $this->assertSame($expectedNumber, StockRequest::suggestNumber($brand->code, $date));
            $this->assertSame($expectedNumber, StockReceiptSlip::suggestNumber($brand->code, $date));
            $this->assertSame($expectedNumber, OutMaterialForm::suggestNumber($brand->code, $date));
        }
    }

    public function test_omf_auto_number_uses_the_selected_brand_and_issued_date(): void
    {
        $this->seedWorkspaceData();
        $this->travelTo('2026-11-09 10:11:12');
        $warehouse = $this->user('warehouse@indosakti.com');
        $brand = Brand::where('slug', 'milo')->firstOrFail();
        $product = Product::create([
            'name' => 'OMF Product',
            'sku' => 'OMF-AUTO-001',
            'brand_id' => $brand->id,
            'unit' => 'pcs',
            'is_active' => true,
        ]);
        Inventory::create(['product_id' => $product->id, 'qty_on_hand' => 5, 'qty_reserved' => 0]);
        StockMovement::create([
            'product_id' => $product->id,
            'type' => 'stock_in',
            'quantity' => 5,
            'balance_after' => 5,
            'lot_code' => 'OMF-LOT-001',
        ]);

        $this->actingAs($warehouse)
            ->post(route('warehouse.omf.store'), [
                'omf_number_manual' => false,
                'issued_date' => '2026-11-09',
                'brand_id' => $brand->id,
                'items' => [[
                    'product_id' => $product->id,
                    'lot_code' => 'OMF-LOT-001',
                    'quantity' => 2,
                ]],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('out_material_forms', [
            'omf_number' => 'JKT/0911/MLO/01',
            'brand_id' => $brand->id,
        ]);
        $this->assertSame(
            '2026-11-09',
            OutMaterialForm::where('omf_number', 'JKT/0911/MLO/01')->firstOrFail()->issued_date?->toDateString(),
        );
    }

    private function mrsSlip(string $number = 'JKT/0911/MLO/01'): StockReceiptSlip
    {
        return StockReceiptSlip::create([
            'mrs_number' => $number,
            'receipt_type' => 'Free Goods/FGR',
            'do_number' => 'DO-TEST-001',
            'received_date' => '2026-11-09',
        ]);
    }

    private function omfDocument(): OutMaterialForm
    {
        return OutMaterialForm::create([
            'omf_number' => 'JKT/0911/MLO/10',
            'issued_date' => '2026-11-09',
        ]);
    }

    private function mrvDocument(): StockRequest
    {
        return StockRequest::create([
            'mrv_number' => 'JKT/0911/MLO/11',
            'type' => 'issue',
            'status' => 'pending',
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
