<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductCategory;
use Database\Seeders\MiloDancowProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MiloDancowProductSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_milo_and_dancow_products_in_every_category(): void
    {
        $this->seed(MiloDancowProductSeeder::class);

        $milo = Brand::where('slug', 'milo')->firstOrFail();
        $dancow = Brand::where('slug', 'dancow')->firstOrFail();
        $beverage = ProductCategory::where('slug', 'minuman')->firstOrFail();
        $promotion = ProductCategory::where('slug', 'alat-promosi')->firstOrFail();

        $this->assertSame(16, Product::whereIn('brand_id', [$milo->id, $dancow->id])->count());
        $this->assertTrue(Product::where('brand_id', $milo->id)->where('product_category_id', $beverage->id)->exists());
        $this->assertTrue(Product::where('brand_id', $milo->id)->where('product_category_id', $promotion->id)->exists());
        $this->assertTrue(Product::where('brand_id', $dancow->id)->where('product_category_id', $beverage->id)->exists());
        $this->assertTrue(Product::where('brand_id', $dancow->id)->where('product_category_id', $promotion->id)->exists());

        $this->assertDatabaseHas('products', ['sku' => 'MILO-RTD-240', 'brand_id' => $milo->id]);
        $this->assertDatabaseHas('products', ['sku' => 'MILO-ACTIV-GO', 'brand_id' => $milo->id]);
        $this->assertDatabaseHas('products', ['sku' => 'DANCOW-4PLUS-800', 'brand_id' => $dancow->id]);
        $this->assertSame(0, Product::whereDoesntHave('inventory')->count());
    }

    public function test_reseeding_does_not_duplicate_products_or_reset_existing_stock(): void
    {
        $this->seed(MiloDancowProductSeeder::class);
        $product = Product::where('sku', 'MILO-RTD-240')->firstOrFail();
        $product->inventory->update(['qty_on_hand' => 5]);

        $this->seed(MiloDancowProductSeeder::class);

        $this->assertSame(16, Product::count());
        $this->assertSame(5, $product->fresh()->inventory->qty_on_hand);
    }
}
