<?php

namespace Database\Seeders;

use App\Enums\MrvStatus;
use App\Models\Brand;
use App\Models\OutMaterialForm;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\StockReceiptSlip;
use App\Models\StockRequest;
use App\Models\User;
use App\Services\StockService;
use App\Support\DocumentNumber;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

class WarehouseActivitySeeder extends Seeder
{
    public function run(StockService $stock): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Warehouse activity data can only be seeded in local or testing environments.');
        }

        $warehouseUser = User::query()
            ->where('email', 'warehouse@indosakti.com')
            ->firstOrFail();
        $brands = [
            'MILO' => Brand::query()->where('slug', 'milo')->firstOrFail(),
            'DANCOW' => Brand::query()->where('slug', 'dancow')->firstOrFail(),
        ];
        $category = ProductCategory::query()->firstOrCreate(
            ['slug' => 'minuman'],
            ['name' => 'Minuman'],
        );
        $products = $this->ensureCatalog($brands, $category);

        $products['12583798'] = Product::query()
            ->where('sku', '12583798')
            ->where('is_active', true)
            ->firstOrFail();

        $this->seedReceipts($stock, $warehouseUser, $brands, $products);
        $this->seedRequests($stock, $warehouseUser, $brands, $products);
        $this->seedOutMaterialForms($stock, $warehouseUser, $brands, $products);
    }

    /**
     * @param  array<string, Brand>  $brands
     * @return array<string, Product>
     */
    private function ensureCatalog(array $brands, ProductCategory $category): array
    {
        $catalog = [
            ['sku' => '12583797', 'name' => 'MILO 3IN1 ACTIV-GO NEW POUCH', 'package_sku' => '12X990gr', 'unit' => 'PCH', 'brand' => 'MILO'],
            ['sku' => '12565642', 'name' => 'MILO 3IN1 ACTIV GO NEW POUCH', 'package_sku' => '12X990gr', 'unit' => 'PCH', 'brand' => 'MILO'],
            ['sku' => '12540202', 'name' => 'MILO ACTIVE-GO UHT 110ml', 'package_sku' => '36x110ml', 'unit' => 'PCS', 'brand' => 'MILO'],
            ['sku' => '12579183', 'name' => 'MILO UHT NUTRIACTIV CHOCO CEREAL', 'package_sku' => '36x180ml', 'unit' => 'PCS', 'brand' => 'MILO'],
            ['sku' => '12579191', 'name' => 'MILO UHT NUTRIACTIV CHOCO BANANA', 'package_sku' => '36x180ml', 'unit' => 'PCS', 'brand' => 'MILO'],
            ['sku' => '12594630', 'name' => 'DEW FORTIGRO MILKY SHAKE HAZELNUT', 'package_sku' => '36x180ml', 'unit' => 'PCS', 'brand' => 'DANCOW'],
            ['sku' => '12506663', 'name' => 'DEW FORTIGRO MILKY SHAKE COOKIES', 'package_sku' => '36x180ml', 'unit' => 'PCS', 'brand' => 'DANCOW'],
        ];
        $products = [];

        foreach ($catalog as $item) {
            $product = Product::query()->firstOrNew(['sku' => $item['sku']]);

            if (! $product->exists) {
                $product->fill([
                    'name' => $item['name'],
                    'brand_id' => $brands[$item['brand']]->id,
                    'product_category_id' => $category->id,
                    'unit' => $item['unit'],
                    'minimum_stock' => 0,
                    'is_active' => true,
                ]);
            }

            if (blank($product->package_sku)) {
                $product->package_sku = $item['package_sku'];
            }

            if (blank($product->product_type)) {
                $product->product_type = 'FGR';
            }

            $product->save();

            if (! $product->is_active) {
                throw new LogicException("Product {$item['sku']} is inactive and cannot be used for seeded activities.");
            }

            $products[$item['sku']] = $product;
        }

        return $products;
    }

    /**
     * @param  array<string, Brand>  $brands
     * @param  array<string, Product>  $products
     */
    private function seedReceipts(
        StockService $stock,
        User $warehouseUser,
        array $brands,
        array $products,
    ): void {
        $date = today();
        $rows = [
            ['sku' => '12583797', 'quantity' => 336, 'expiry' => '2027-06-30'],
            ['sku' => '12583797', 'quantity' => 672, 'expiry' => '2027-06-30'],
            ['sku' => '12583797', 'quantity' => 12, 'expiry' => '2027-06-30'],
            ['sku' => '12565642', 'quantity' => 20, 'expiry' => '2027-06-30'],
            ['sku' => '12540202', 'quantity' => 100, 'expiry' => '2027-12-31'],
            ['sku' => '12579183', 'quantity' => 100, 'expiry' => '2027-12-31'],
            ['sku' => '12579191', 'quantity' => 100, 'expiry' => '2027-12-31'],
            ['sku' => '12594630', 'quantity' => 50, 'expiry' => '2027-12-31'],
            ['sku' => '12506663', 'quantity' => 50, 'expiry' => '2027-12-31'],
            ['sku' => '12583798', 'quantity' => 1000, 'expiry' => $date->copy()->addYear()->toDateString()],
        ];

        foreach ($rows as $index => $row) {
            $marker = sprintf('[SIM-ACTIVITY:MRS:%02d]', $index + 1);

            if (StockReceiptSlip::query()->where('notes', 'like', "%{$marker}%")->exists()) {
                continue;
            }

            $product = $products[$row['sku']];
            $brand = in_array($product->sku, ['12594630', '12506663'], true)
                ? $brands['DANCOW']
                : $brands['MILO'];
            $lotCode = sprintf('SIM-%s-%02d', $date->format('Ymd'), $index + 1);

            DB::transaction(function () use ($stock, $warehouseUser, $brand, $date, $index, $marker, $product, $row, $lotCode): void {
                $slip = StockReceiptSlip::query()->create([
                    'mrs_number' => $this->nextNumber('stock_receipt_slips', 'mrs_number', $brand, $date),
                    'receipt_type' => 'FGR',
                    'do_number' => sprintf('SIM-DO-%s-%02d', $date->format('Ymd'), $index + 1),
                    'shipper' => 'DUTA',
                    'supplier' => 'NESTLE INDONESIA',
                    'received_date' => $date->toDateString(),
                    'release_date' => $date->toDateString(),
                    'release_time' => '18:18',
                    'authorized_by' => 'Edy Firdaus (Warehouse)',
                    'quantity_released' => $row['quantity'],
                    'quantity_assurance' => $row['quantity'],
                    'received_by_name' => 'Edy Firdaus',
                    'received_by_role' => 'Warehouse',
                    'approved_by_name' => 'Oki Gio Nugraha',
                    'approved_by_role' => 'Operational Manager',
                    'operational_manager_by_name' => 'Oki Gio Nugraha',
                    'operational_manager_by_role' => 'Operational Manager',
                    'notes' => "{$marker} Data simulasi; lot baru untuk menjaga saldo stok.",
                    'document_status' => 'RECEIVED',
                    'recorded_by' => $warehouseUser->id,
                ]);

                $item = $slip->items()->create([
                    'product_id' => $product->id,
                    'sku' => $product->sku,
                    'item_name' => $product->name,
                    'lot_code' => $lotCode,
                    'description' => $product->name,
                    'received_quantity' => $row['quantity'],
                    'uom' => $product->unit,
                    'expired_date' => $row['expiry'],
                    'status' => 'Good',
                    'remark' => 'SIMULASI - Warehouse',
                ]);

                $stock->receive($product, $row['quantity'], ['reference' => $item]);
            });
        }
    }

    /**
     * @param  array<string, Brand>  $brands
     * @param  array<string, Product>  $products
     */
    private function seedRequests(
        StockService $stock,
        User $warehouseUser,
        array $brands,
        array $products,
    ): void {
        $date = today();
        $rows = [
            [
                ['sku' => '12583797', 'lot' => 'SIM-'.$date->format('Ymd').'-01', 'quantity' => 5],
                ['sku' => '12583798', 'lot' => 'SIM-'.$date->format('Ymd').'-10', 'quantity' => 410],
            ],
            [['sku' => '12583797', 'lot' => 'SIM-'.$date->format('Ymd').'-02', 'quantity' => 5]],
            [['sku' => '12583797', 'lot' => 'SIM-'.$date->format('Ymd').'-03', 'quantity' => 5]],
            [['sku' => '12565642', 'lot' => 'SIM-'.$date->format('Ymd').'-04', 'quantity' => 5]],
            [['sku' => '12540202', 'lot' => 'SIM-'.$date->format('Ymd').'-05', 'quantity' => 5]],
            [['sku' => '12579183', 'lot' => 'SIM-'.$date->format('Ymd').'-06', 'quantity' => 5]],
            [['sku' => '12579191', 'lot' => 'SIM-'.$date->format('Ymd').'-07', 'quantity' => 5]],
            [['sku' => '12594630', 'lot' => 'SIM-'.$date->format('Ymd').'-08', 'quantity' => 5]],
            [['sku' => '12506663', 'lot' => 'SIM-'.$date->format('Ymd').'-09', 'quantity' => 5]],
            [['sku' => '12583797', 'lot' => 'SIM-'.$date->format('Ymd').'-01', 'quantity' => 100]],
        ];

        foreach ($rows as $index => $items) {
            $marker = sprintf('[SIM-ACTIVITY:MRV:%02d]', $index + 1);

            if (StockRequest::query()->where('notes', 'like', "%{$marker}%")->exists()) {
                continue;
            }

            $firstProduct = $products[$items[0]['sku']];
            $brand = in_array($firstProduct->sku, ['12594630', '12506663'], true)
                ? $brands['DANCOW']
                : $brands['MILO'];
            $venue = $index === 0 ? 'SDN BANTAR GEBANG V' : sprintf('SDN SIMULASI %02d', $index + 1);

            DB::transaction(function () use ($stock, $warehouseUser, $brand, $date, $index, $items, $marker, $products, $venue): void {
                $mrv = StockRequest::query()->create([
                    'mrv_number' => $this->nextNumber('stock_requests', 'mrv_number', $brand, $date),
                    'activity_type' => 'school_sampling',
                    'activity_date' => $date->toDateString(),
                    'week_label' => 'Minggu '.$date->isoWeek,
                    'shift' => 'Malam',
                    'jug_quantity' => 1,
                    'brand_name' => $brand->name,
                    'venue_name' => $venue,
                    'venue_names' => [$venue],
                    'school_names' => [$venue],
                    'team_member_name' => 'SPG JAKARTA 3',
                    'sampling_target_wet' => $index === 0 ? 410 : $items[0]['quantity'],
                    'sampling_target_dry' => 0,
                    'type' => 'issue',
                    'status' => MrvStatus::APPROVED,
                    'material_category' => 'wet_sample',
                    'material_categories' => ['wet_sample'],
                    'pickup_time' => '18:18',
                    'notes' => "{$marker} Data simulasi; stok keluar dicatat per lot.",
                    'requested_by' => $warehouseUser->id,
                    'approved_by' => $warehouseUser->id,
                    'approved_at' => now(),
                ]);

                foreach ($items as $row) {
                    $product = $products[$row['sku']];

                    $mrv->items()->create([
                        'product_id' => $product->id,
                        'lot_no' => $row['lot'],
                        'quantity_requested' => $row['quantity'],
                        'notes' => $marker,
                    ]);
                }

                $stock->issue($mrv, recipient: 'SPG JAKARTA 3');
            });
        }
    }

    /**
     * @param  array<string, Brand>  $brands
     * @param  array<string, Product>  $products
     */
    private function seedOutMaterialForms(
        StockService $stock,
        User $warehouseUser,
        array $brands,
        array $products,
    ): void {
        $date = today();
        $rows = [
            ['sku' => '12594630', 'lot' => 'SIM-'.$date->format('Ymd').'-08', 'quantity' => 5],
            ['sku' => '12506663', 'lot' => 'SIM-'.$date->format('Ymd').'-09', 'quantity' => 5],
            ['sku' => '12583797', 'lot' => 'SIM-'.$date->format('Ymd').'-01', 'quantity' => 5],
            ['sku' => '12565642', 'lot' => 'SIM-'.$date->format('Ymd').'-04', 'quantity' => 5],
            ['sku' => '12540202', 'lot' => 'SIM-'.$date->format('Ymd').'-05', 'quantity' => 5],
            ['sku' => '12579183', 'lot' => 'SIM-'.$date->format('Ymd').'-06', 'quantity' => 5],
            ['sku' => '12579191', 'lot' => 'SIM-'.$date->format('Ymd').'-07', 'quantity' => 5],
            ['sku' => '12583798', 'lot' => 'SIM-'.$date->format('Ymd').'-10', 'quantity' => 10],
            ['sku' => '12594630', 'lot' => 'SIM-'.$date->format('Ymd').'-08', 'quantity' => 5],
            ['sku' => '12506663', 'lot' => 'SIM-'.$date->format('Ymd').'-09', 'quantity' => 5],
        ];

        foreach ($rows as $index => $row) {
            $marker = sprintf('[SIM-ACTIVITY:OMF:%02d]', $index + 1);

            if (OutMaterialForm::query()->where('notes', 'like', "%{$marker}%")->exists()) {
                continue;
            }

            $product = $products[$row['sku']];
            $brand = in_array($product->sku, ['12594630', '12506663'], true)
                ? $brands['DANCOW']
                : $brands['MILO'];

            DB::transaction(function () use ($stock, $warehouseUser, $brand, $date, $marker, $product, $row): void {
                $form = OutMaterialForm::query()->create([
                    'omf_number' => $this->nextNumber('out_material_forms', 'omf_number', $brand, $date),
                    'brand_id' => $brand->id,
                    'issued_date' => $date->toDateString(),
                    'department' => 'Warehouse',
                    'recipient' => 'PT INDOMAR - SIMULASI',
                    'notes' => "{$marker} Data simulasi aktivitas barang keluar.",
                    'received_by_name' => 'Receiver Simulasi',
                    'received_by_role' => 'Receiver',
                    'approved_by_name' => 'Oki Gio Nugraha',
                    'approved_by_role' => 'Supervisor',
                    'operational_manager_by_name' => 'Oki Gio Nugraha',
                    'operational_manager_by_role' => 'Operational Manager',
                    'recorded_by' => $warehouseUser->id,
                ]);

                $form->items()->create([
                    'product_id' => $product->id,
                    'sku' => $product->sku,
                    'item_name' => $product->name,
                    'lot_code' => $row['lot'],
                    'quantity' => $row['quantity'],
                    'uom' => $product->unit,
                    'remark' => 'SIMULASI - PT INDOMAR',
                ]);

                $stock->issueOutMaterialForm($form);
            });
        }
    }

    private function nextNumber(string $table, string $column, Brand $brand, CarbonInterface $date): string
    {
        $code = Str::upper(trim((string) $brand->code)) ?: 'GEN';
        $stem = sprintf('JKT/%s/%s/', $date->format('dm'), $code);
        $highestSequence = (int) DB::table($table)
            ->where($column, 'like', $stem.'%')
            ->pluck($column)
            ->map(fn (string $number): int => (int) Str::after($number, $stem))
            ->max();

        return DocumentNumber::suggest($brand->code, $date, fn (): int => $highestSequence);
    }
}
