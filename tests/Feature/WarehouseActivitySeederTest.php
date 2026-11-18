<?php

namespace Tests\Feature;

use App\Enums\MrvStatus;
use App\Models\Brand;
use App\Models\Product;
use App\Models\StockRequest;
use App\Models\User;
use App\Services\LotStockService;
use Database\Seeders\WarehouseActivitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehouseActivitySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_ten_of_each_document_without_duplicate_stock_movements(): void
    {
        $this->travelTo('2026-11-10 12:00:00');
        $milo = Brand::query()->create([
            'name' => 'MILO',
            'slug' => 'milo',
            'code' => 'MLO',
            'is_active' => true,
        ]);
        Brand::query()->create([
            'name' => 'DANCOW',
            'slug' => 'dancow',
            'code' => 'DCW',
            'is_active' => true,
        ]);
        User::factory()->create(['email' => 'warehouse@indosakti.com']);
        Product::query()->create([
            'name' => 'MILO CUP 4OZ',
            'sku' => '12583798',
            'brand_id' => $milo->id,
            'unit' => 'PCS',
            'is_active' => true,
        ]);
        StockRequest::query()->create([
            'mrv_number' => 'JKT/1011/DCW/02',
            'activity_type' => 'school_sampling',
            'activity_date' => today()->toDateString(),
            'jug_quantity' => 1,
            'type' => 'issue',
            'status' => MrvStatus::PENDING,
        ]);

        $this->seed(WarehouseActivitySeeder::class);
        $this->seed(WarehouseActivitySeeder::class);

        $this->assertDatabaseCount('stock_receipt_slips', 10);
        $this->assertDatabaseCount('stock_requests', 11);
        $this->assertDatabaseCount('out_material_forms', 10);
        $this->assertDatabaseCount('stock_movements', 31);
        $this->assertSame(
            'JKT/1011/DCW/03',
            StockRequest::query()->where('notes', 'like', '%SIM-ACTIVITY:MRV:08%')->value('mrv_number'),
        );

        $product = Product::query()->where('sku', '12583797')->firstOrFail();
        $this->assertSame(
            226,
            app(LotStockService::class)->availableForLot($product, 'SIM-20261110-01'),
        );
    }
}
