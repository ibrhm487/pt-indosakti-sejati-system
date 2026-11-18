<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MiloDancowProductSeeder extends Seeder
{
    public function run(): void
    {
        $brands = collect(['MILO', 'DANCOW'])->mapWithKeys(fn (string $name) => [
            $name => Brand::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'is_active' => true],
            ),
        ]);

        $categories = collect(['Minuman', 'Alat Promosi'])->mapWithKeys(fn (string $name) => [
            $name => ProductCategory::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name],
            ),
        ]);

        $products = [
            ['name' => 'MILO Activ Go Sachet', 'sku' => 'MILO-ACTIV-GO', 'brand' => 'MILO', 'category' => 'Minuman', 'unit' => 'pcs', 'minimum_stock' => 100, 'stock' => 1000],
            ['name' => 'MILO Sachet', 'sku' => 'MILO-SACHET', 'brand' => 'MILO', 'category' => 'Minuman', 'unit' => 'pcs', 'minimum_stock' => 100, 'stock' => 1000],
            ['name' => 'MILO 3in1 Sachet', 'sku' => 'MILO-3IN1', 'brand' => 'MILO', 'category' => 'Minuman', 'unit' => 'pcs', 'minimum_stock' => 80, 'stock' => 800],
            ['name' => 'MILO RTD 240ml', 'sku' => 'MILO-RTD-240', 'brand' => 'MILO', 'category' => 'Minuman', 'unit' => 'botol', 'minimum_stock' => 60, 'stock' => 600],
            ['name' => 'MILO Nutri Cereal 90g', 'sku' => 'MILO-CEREAL-90', 'brand' => 'MILO', 'category' => 'Minuman', 'unit' => 'pcs', 'minimum_stock' => 30, 'stock' => 300],
            ['name' => 'MILO Booth Kit', 'sku' => 'MILO-BOOTH', 'brand' => 'MILO', 'category' => 'Alat Promosi', 'unit' => 'set', 'minimum_stock' => 2, 'stock' => 8],
            ['name' => 'MILO Banner', 'sku' => 'MILO-BANNER', 'brand' => 'MILO', 'category' => 'Alat Promosi', 'unit' => 'pcs', 'minimum_stock' => 5, 'stock' => 30],
            ['name' => 'MILO Sampling Kit', 'sku' => 'MILO-SAMPLING-KIT', 'brand' => 'MILO', 'category' => 'Alat Promosi', 'unit' => 'set', 'minimum_stock' => 5, 'stock' => 40],
            ['name' => 'DANCOW Sachet', 'sku' => 'DANCOW-SACHET', 'brand' => 'DANCOW', 'category' => 'Minuman', 'unit' => 'pcs', 'minimum_stock' => 50, 'stock' => 500],
            ['name' => 'DANCOW 1+ 800g', 'sku' => 'DANCOW-1PLUS-800', 'brand' => 'DANCOW', 'category' => 'Minuman', 'unit' => 'box', 'minimum_stock' => 12, 'stock' => 120],
            ['name' => 'DANCOW 3+ 800g', 'sku' => 'DANCOW-3PLUS-800', 'brand' => 'DANCOW', 'category' => 'Minuman', 'unit' => 'box', 'minimum_stock' => 12, 'stock' => 140],
            ['name' => 'DANCOW 4+ 800g', 'sku' => 'DANCOW-4PLUS-800', 'brand' => 'DANCOW', 'category' => 'Minuman', 'unit' => 'box', 'minimum_stock' => 12, 'stock' => 140],
            ['name' => 'DANCOW UHT 250ml', 'sku' => 'DANCOW-UHT-250', 'brand' => 'DANCOW', 'category' => 'Minuman', 'unit' => 'pcs', 'minimum_stock' => 40, 'stock' => 400],
            ['name' => 'DANCOW Booth Kit', 'sku' => 'DANCOW-BOOTH', 'brand' => 'DANCOW', 'category' => 'Alat Promosi', 'unit' => 'set', 'minimum_stock' => 2, 'stock' => 8],
            ['name' => 'DANCOW Banner', 'sku' => 'DANCOW-BANNER', 'brand' => 'DANCOW', 'category' => 'Alat Promosi', 'unit' => 'pcs', 'minimum_stock' => 5, 'stock' => 30],
            ['name' => 'DANCOW Sampling Kit', 'sku' => 'DANCOW-SAMPLING-KIT', 'brand' => 'DANCOW', 'category' => 'Alat Promosi', 'unit' => 'set', 'minimum_stock' => 5, 'stock' => 40],
        ];

        foreach ($products as $data) {
            $product = Product::updateOrCreate(
                ['sku' => $data['sku']],
                [
                    'name' => $data['name'],
                    'brand_id' => $brands[$data['brand']]->id,
                    'product_category_id' => $categories[$data['category']]->id,
                    'unit' => $data['unit'],
                    'minimum_stock' => $data['minimum_stock'],
                    'is_active' => true,
                ],
            );

            Inventory::firstOrCreate(
                ['product_id' => $product->id],
                ['qty_on_hand' => $data['stock'], 'qty_reserved' => 0],
            );
        }
    }
}
