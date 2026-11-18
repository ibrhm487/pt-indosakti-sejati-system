<?php

namespace Database\Seeders;

use App\Enums\MrvStatus;
use App\Enums\StockMovementType;
use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\OutMaterialForm;
use App\Models\OutMaterialFormItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\StockMovement;
use App\Models\StockReceiptSlip;
use App\Models\StockRequest;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class WarehouseRealDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedRealData();
    }

    /**
     * @return array{products: int, mrs: int, mrv: int, omf: int, stock_movements: int}
     */
    public function replace(string $confirmation): array
    {
        $database = DB::connection()->getDatabaseName();

        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Penggantian data warehouse hanya diizinkan pada environment lokal atau testing.');
        }

        if ($confirmation !== "REPLACE-WAREHOUSE-REAL-DATA:{$database}") {
            throw new RuntimeException('Konfirmasi penggantian data tidak cocok dengan nama database aktif.');
        }

        return DB::transaction(function (): array {
            $oldCounts = $this->warehouseCounts();
            $this->clearWarehouseData();
            $newCounts = $this->seedRealData();

            AuditLog::create([
                'action' => 'REPLACE_WAREHOUSE_REAL_DATA',
                'module' => 'warehouse',
                'old_values' => $oldCounts,
                'new_values' => $newCounts,
            ]);

            return $newCounts;
        });
    }

    /**
     * @return array<string, int>
     */
    private function warehouseCounts(): array
    {
        return [
            'products' => DB::table('products')->count(),
            'inventory' => DB::table('inventory')->count(),
            'stock_movements' => DB::table('stock_movements')->count(),
            'stock_requests' => DB::table('stock_requests')->count(),
            'stock_request_items' => DB::table('stock_request_items')->count(),
            'stock_receipt_slips' => DB::table('stock_receipt_slips')->count(),
            'stock_receipt_slip_items' => DB::table('stock_receipt_slip_items')->count(),
            'stock_receipts' => DB::table('stock_receipts')->count(),
            'stock_returns' => DB::table('stock_returns')->count(),
            'stock_return_items' => DB::table('stock_return_items')->count(),
            'out_material_forms' => DB::table('out_material_forms')->count(),
            'out_material_form_items' => DB::table('out_material_form_items')->count(),
        ];
    }

    private function clearWarehouseData(): void
    {
        DB::table('out_material_form_items')->delete();
        DB::table('out_material_forms')->delete();
        DB::table('stock_request_items')->delete();
        DB::table('stock_return_items')->delete();
        DB::table('stock_receipt_slip_items')->delete();
        DB::table('stock_returns')->delete();
        DB::table('stock_receipts')->delete();
        DB::table('stock_receipt_slips')->delete();
        DB::table('stock_movements')->delete();
        DB::table('stock_requests')->delete();
        DB::table('inventory')->delete();
        DB::table('products')->delete();
    }

    /**
     * @return array{products: int, mrs: int, mrv: int, omf: int, stock_movements: int}
     */
    private function seedRealData(): array
    {
        $stock = app(StockService::class);
        $brands = $this->ensureBrands();
        $categories = $this->ensureCategories();

        $products = [];
        $receiptLines = [];

        foreach ($this->catalog() as $data) {
            $product = Product::create([
                'name' => $data['name'],
                'description' => $data['description'],
                'sku' => $data['sku'],
                'brand_id' => $brands[$data['brand']]->id,
                'product_category_id' => $categories[$data['category']]->id,
                'unit' => $data['unit'],
                'minimum_stock' => $data['minimum_stock'],
                'uses_jug_conversion' => $data['uses_jug_conversion'],
                'sachets_per_jug' => $data['sachets_per_jug'],
                'is_active' => true,
            ]);

            $products[$product->sku] = $product;

            foreach ($data['lots'] as $index => [$lotCode, $quantity, $expiredDate]) {
                $receiptLines[] = [
                    'product' => $product,
                    'brand_code' => $brands[$data['brand']]->code,
                    'lot_code' => $lotCode,
                    'quantity' => $quantity,
                    'expired_date' => $expiredDate,
                    'sequence' => $index,
                ];
            }
        }

        $groups = array_chunk($receiptLines, 3);
        $receiptDate = Carbon::parse('2026-09-20');
        $receiptTypes = ['Free Goods/FGR', 'Cups/Spoon', 'Premium/Gimmick', 'POP/POSM'];

        foreach ($groups as $groupIndex => $lines) {
            $date = $receiptDate->copy()->addDays($groupIndex * 2);
            $first = $lines[0];
            $total = array_sum(array_column($lines, 'quantity'));

            $slip = StockReceiptSlip::create([
                'mrs_number' => StockReceiptSlip::suggestNumber($first['brand_code'], $date),
                'receipt_type' => $receiptTypes[$groupIndex % count($receiptTypes)],
                'do_number' => sprintf('DO/INDF/2026/%03d', $groupIndex + 1),
                'shipper' => 'Indosakti Logistics',
                'supplier' => 'PT Nestlé Indonesia',
                'received_date' => $date,
                'release_date' => $date,
                'release_time' => '09:30',
                'authorized_by' => 'Rina Kartika',
                'quantity_released' => $total,
                'quantity_assurance' => $total,
                'received_by_name' => 'Dewi Anggraini',
                'received_by_role' => 'Warehouse',
                'approved_by_name' => 'Budi Santoso',
                'approved_by_role' => 'PIC',
                'operational_manager_by_name' => 'Rina Kartika',
                'operational_manager_by_role' => 'Forwarder',
                'quality_assurance_by_name' => 'Siti Nurhaliza',
                'quality_assurance_by_role' => 'Quality Assurance',
                'document_status' => 'GOOD',
                'notes' => 'Penerimaan rutin gudang Indosakti.',
            ]);

            foreach ($lines as $line) {
                /** @var Product $product */
                $product = $line['product'];

                $item = $slip->items()->create([
                    'product_id' => $product->id,
                    'sku' => $product->sku,
                    'item_name' => $product->name,
                    'lot_code' => $line['lot_code'],
                    'description' => $product->description,
                    'received_quantity' => $line['quantity'],
                    'uom' => $product->unit,
                    'expired_date' => $line['expired_date'],
                    'status' => 'GOOD',
                    'remark' => 'Penerimaan dari PT Nestlé Indonesia',
                ]);

                $movement = $stock->receive($product, $line['quantity'], [
                    'reference' => $item,
                ]);
                $this->setMovementDate($movement, $date->copy()->setTime(8, 15));
            }

            $this->setTimestamp(StockReceiptSlip::class, $slip->id, $date->copy()->setTime(8, 0));
        }

        $this->seedMrv($products, $stock);
        $this->seedOmf($products, $stock);

        return [
            'products' => Product::query()->count(),
            'mrs' => StockReceiptSlip::query()->count(),
            'mrv' => StockRequest::query()->count(),
            'omf' => OutMaterialForm::query()->count(),
            'stock_movements' => DB::table('stock_movements')->count(),
        ];
    }

    /**
     * @param  array<string, Product>  $products
     */
    private function seedMrv(array $products, StockService $stock): void
    {
        $requesterId = User::query()->value('id');

        foreach ($this->mrvDefinitions() as $definition) {
            $date = Carbon::parse($definition['date']);
            $brandCode = $definition['brand_code'];
            $schools = $definition['schools'];

            $request = StockRequest::create([
                'mrv_number' => StockRequest::suggestNumber($brandCode, $date),
                'activity_type' => 'Sampling',
                'activity_date' => $date,
                'week_label' => 'Minggu '.$date->isoWeek,
                'shift' => $definition['shift'],
                'brand_name' => $definition['brand'],
                'venue_name' => $schools[0] ?? null,
                'venue_names' => $schools,
                'team_member_name' => $definition['team'],
                'jug_quantity' => $definition['jugs'],
                'material_categories' => ['wet_sample'],
                'material_category' => 'wet_sample',
                'sampling_target_wet' => $definition['jugs'] * 250,
                'sampling_target_dry' => 0,
                'type' => 'issue',
                'status' => $definition['pending'] ? MrvStatus::PENDING : MrvStatus::APPROVED,
                'notes' => 'Permintaan material untuk kegiatan sampling sekolah.',
                'material_prepared_by' => 'Dewi Anggraini',
                'material_received_by' => $definition['team'],
                'prepared_by_name' => 'Dewi Anggraini',
                'approved_by_name' => 'Budi Santoso',
                'wet_received_by' => 'Andi Pratama',
                'dry_received_by' => $definition['team'],
                'requested_by' => $requesterId,
                'approved_by' => $definition['pending'] ? null : $requesterId,
                'approved_at' => $definition['pending'] ? null : $date->copy()->setTime(7, 45),
            ]);

            foreach ($definition['items'] as [$sku, $lotCode, $quantity]) {
                /** @var Product $product */
                $product = $products[$sku];

                $request->items()->create([
                    'product_id' => $product->id,
                    'lot_no' => $lotCode,
                    'quantity_requested' => $quantity,
                    'notes' => 'Material sampling shift '.$definition['shift'],
                ]);
            }

            if (! $definition['pending']) {
                $stock->issue($request);

                StockMovement::query()
                    ->where('reference_type', $request->getMorphClass())
                    ->where('reference_id', $request->id)
                    ->where('type', StockMovementType::STOCK_OUT)
                    ->update([
                        'created_at' => $date->copy()->setTime(8, 30),
                        'updated_at' => $date->copy()->setTime(8, 30),
                    ]);

                DB::table('stock_requests')
                    ->where('id', $request->id)
                    ->update(['issued_at' => $date->copy()->setTime(8, 30)]);
            }

            $this->setTimestamp(StockRequest::class, $request->id, $date->copy()->setTime(7, 30));
        }
    }

    /**
     * @param  array<string, Product>  $products
     */
    private function seedOmf(array $products, StockService $stock): void
    {
        $recorderId = User::query()->value('id');

        foreach ($this->omfDefinitions() as $index => $definition) {
            $date = Carbon::parse($definition['date']);
            $brandCode = $definition['brand_code'];

            $form = OutMaterialForm::create([
                'omf_number' => OutMaterialForm::suggestNumber($brandCode, $date),
                'brand_id' => Brand::query()->where('code', $brandCode)->value('id'),
                'issued_date' => $date,
                'department' => $definition['department'],
                'recipient' => $definition['recipient'],
                'notes' => 'Pengeluaran barang sesuai permintaan departemen.',
                'received_by_name' => $definition['recipient'],
                'received_by_role' => 'Warehouse',
                'approved_by_name' => 'Budi Santoso',
                'approved_by_role' => 'PIC',
                'operational_manager_by_name' => 'Rina Kartika',
                'operational_manager_by_role' => 'Forwarder',
                'recorded_by' => $recorderId,
            ]);

            $itemIds = [];

            foreach ($definition['items'] as [$sku, $lotCode, $quantity, $remark]) {
                /** @var Product $product */
                $product = $products[$sku];

                $item = $form->items()->create([
                    'product_id' => $product->id,
                    'sku' => $product->sku,
                    'item_name' => $product->name,
                    'lot_code' => $lotCode,
                    'expired_date' => $this->expiryFor($product, $lotCode),
                    'quantity' => $quantity,
                    'uom' => $product->unit,
                    'remark' => $remark,
                ]);

                $itemIds[] = $item->id;
            }

            $form->load('items.product');
            $stock->issueOutMaterialForm($form);

            StockMovement::query()
                ->where('reference_type', (new OutMaterialFormItem)->getMorphClass())
                ->whereIn('reference_id', $itemIds)
                ->update([
                    'created_at' => $date->copy()->setTime(10, 0),
                    'updated_at' => $date->copy()->setTime(10, 0),
                ]);

            $this->setTimestamp(OutMaterialForm::class, $form->id, $date->copy()->setTime(9, 45));
        }
    }

    private function expiryFor(Product $product, string $lotCode): ?string
    {
        $movement = StockMovement::query()
            ->where('product_id', $product->id)
            ->where('lot_code', $lotCode)
            ->whereNotNull('expired_date')
            ->first();

        return $movement?->expired_date?->toDateString();
    }

    private function setMovementDate(StockMovement $movement, Carbon $date): void
    {
        DB::table('stock_movements')
            ->where('id', $movement->id)
            ->update([
                'created_at' => $date,
                'updated_at' => $date,
            ]);
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function setTimestamp(string $model, int $id, Carbon $date): void
    {
        DB::table((new $model)->getTable())
            ->where('id', $id)
            ->update([
                'created_at' => $date,
                'updated_at' => $date,
            ]);
    }

    /**
     * @return array<string, Brand>
     */
    private function ensureBrands(): array
    {
        $brands = [];

        foreach ([
            'MILO' => 'MLO',
            'DANCOW' => 'DCW',
            'KITKAT' => 'KKT',
            'BEAR BRAND' => 'BBR',
        ] as $name => $code) {
            $brands[$name] = Brand::updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'code' => $code, 'is_active' => true],
            );
        }

        return $brands;
    }

    /**
     * @return array<string, ProductCategory>
     */
    private function ensureCategories(): array
    {
        $categories = [];

        foreach (['Minuman', 'Alat Promosi'] as $name) {
            $categories[$name] = ProductCategory::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name],
            );
        }

        return $categories;
    }

    /**
     * @return list<array{name: string, description: string, sku: string, brand: string, category: string, unit: string, minimum_stock: int, uses_jug_conversion: bool, sachets_per_jug: ?int, lots: list<array{0: string, 1: int, 2: string}>}>
     */
    private function catalog(): array
    {
        return [
            ['name' => 'MILO 3IN1 ACTIV-GO', 'description' => 'Minuman serbuk MILO 3in1 Activ-Go', 'sku' => '12583797', 'brand' => 'MILO', 'category' => 'Minuman', 'unit' => 'PCS', 'minimum_stock' => 200, 'uses_jug_conversion' => true, 'sachets_per_jug' => 250, 'lots' => [['41496761C1', 1200, '2027-06-30'], ['41576761C2', 1500, '2027-09-30'], ['41697761C3', 800, '2027-11-30']]],
            ['name' => 'MILO CUP 4OZ', 'description' => 'Cup MILO 4oz untuk sampling', 'sku' => '12583798', 'brand' => 'MILO', 'category' => 'Minuman', 'unit' => 'PCS', 'minimum_stock' => 150, 'uses_jug_conversion' => false, 'sachets_per_jug' => null, 'lots' => [['41601761C1', 900, '2027-08-31']]],
            ['name' => 'MILO ACTIV-GO SACHET 22G', 'description' => 'Sachet MILO Activ-Go 22 gram', 'sku' => '12583799', 'brand' => 'MILO', 'category' => 'Minuman', 'unit' => 'PCS', 'minimum_stock' => 200, 'uses_jug_conversion' => true, 'sachets_per_jug' => 250, 'lots' => [['41702761C1', 1000, '2027-07-31']]],
            ['name' => 'MILO 3IN1 SACHET 35G', 'description' => 'Sachet MILO 3in1 35 gram', 'sku' => '12583800', 'brand' => 'MILO', 'category' => 'Minuman', 'unit' => 'PCS', 'minimum_stock' => 150, 'uses_jug_conversion' => false, 'sachets_per_jug' => null, 'lots' => [['41703761C1', 800, '2027-10-31']]],
            ['name' => 'MILO RTD 240ML', 'description' => 'MILO ready to drink 240 ml', 'sku' => '12583801', 'brand' => 'MILO', 'category' => 'Minuman', 'unit' => 'BOTOL', 'minimum_stock' => 100, 'uses_jug_conversion' => false, 'sachets_per_jug' => null, 'lots' => [['50305127 P1', 600, '2027-12-31']]],
            ['name' => 'MILO NUTRI CEREAL 90G', 'description' => 'MILO Nutri Cereal 90 gram', 'sku' => '12583802', 'brand' => 'MILO', 'category' => 'Minuman', 'unit' => 'PCS', 'minimum_stock' => 60, 'uses_jug_conversion' => false, 'sachets_per_jug' => null, 'lots' => [['50306127 P1', 400, '2027-11-30']]],
            ['name' => 'MILO BOOTH KIT', 'description' => 'Perangkat booth kegiatan MILO', 'sku' => '12583803', 'brand' => 'MILO', 'category' => 'Alat Promosi', 'unit' => 'SET', 'minimum_stock' => 3, 'uses_jug_conversion' => false, 'sachets_per_jug' => null, 'lots' => [['50307127 P1', 20, '2028-12-31']]],
            ['name' => 'MILO BANNER', 'description' => 'Banner promosi MILO', 'sku' => '12583804', 'brand' => 'MILO', 'category' => 'Alat Promosi', 'unit' => 'PCS', 'minimum_stock' => 6, 'uses_jug_conversion' => false, 'sachets_per_jug' => null, 'lots' => [['50308127 P1', 40, '2028-12-31']]],
            ['name' => 'MILO SAMPLING KIT', 'description' => 'Perangkat sampling MILO', 'sku' => '12583805', 'brand' => 'MILO', 'category' => 'Alat Promosi', 'unit' => 'SET', 'minimum_stock' => 5, 'uses_jug_conversion' => false, 'sachets_per_jug' => null, 'lots' => [['50309127 P1', 30, '2028-12-31']]],

            ['name' => 'DEW FORTIGRO MILKY SHAKE HAZELNUT', 'description' => 'Dancow FortiGro Milky Shake rasa hazelnut', 'sku' => '12594630', 'brand' => 'DANCOW', 'category' => 'Minuman', 'unit' => 'PCS', 'minimum_stock' => 80, 'uses_jug_conversion' => false, 'sachets_per_jug' => null, 'lots' => [['50302127 P1', 500, '2027-05-31'], ['50302127 P2', 300, '2027-07-31']]],
            ['name' => 'DEW FORTIGRO MILKY SHAKE VANILLA', 'description' => 'Dancow FortiGro Milky Shake rasa vanila', 'sku' => '12594631', 'brand' => 'DANCOW', 'category' => 'Minuman', 'unit' => 'PCS', 'minimum_stock' => 80, 'uses_jug_conversion' => false, 'sachets_per_jug' => null, 'lots' => [['50303127 P1', 500, '2027-05-31']]],
            ['name' => 'DANCOW FORTIGRO COKELAT 200G', 'description' => 'Dancow FortiGro cokelat 200 gram', 'sku' => '12506660', 'brand' => 'DANCOW', 'category' => 'Minuman', 'unit' => 'PCS', 'minimum_stock' => 100, 'uses_jug_conversion' => false, 'sachets_per_jug' => null, 'lots' => [['50304127 P1', 600, '2027-06-30']]],
            ['name' => 'DANCOW 1+ MADU 200G', 'description' => 'Dancow 1+ madu 200 gram', 'sku' => '12506661', 'brand' => 'DANCOW', 'category' => 'Minuman', 'unit' => 'PCS', 'minimum_stock' => 100, 'uses_jug_conversion' => false, 'sachets_per_jug' => null, 'lots' => [['50305127 P2', 600, '2027-07-31']]],
            ['name' => 'DANCOW 3+ VANILA 800G', 'description' => 'Dancow 3+ vanila 800 gram', 'sku' => '12506662', 'brand' => 'DANCOW', 'category' => 'Minuman', 'unit' => 'BOX', 'minimum_stock' => 24, 'uses_jug_conversion' => false, 'sachets_per_jug' => null, 'lots' => [['50306127 P2', 240, '2027-12-31']]],
            ['name' => 'COOKIES', 'description' => 'Cookies pendamping produk Dancow', 'sku' => '12506663', 'brand' => 'DANCOW', 'category' => 'Minuman', 'unit' => 'PCS', 'minimum_stock' => 60, 'uses_jug_conversion' => false, 'sachets_per_jug' => null, 'lots' => [['52540227 P1', 400, '2027-04-30'], ['52540227 P2', 250, '2027-08-31']]],
            ['name' => 'DANCOW UHT 250ML', 'description' => 'Dancow UHT 250 ml', 'sku' => '12506664', 'brand' => 'DANCOW', 'category' => 'Minuman', 'unit' => 'PCS', 'minimum_stock' => 120, 'uses_jug_conversion' => false, 'sachets_per_jug' => null, 'lots' => [['52541227 P1', 720, '2027-08-31']]],
            ['name' => 'DANCOW BOOTH KIT', 'description' => 'Perangkat booth kegiatan Dancow', 'sku' => '12506665', 'brand' => 'DANCOW', 'category' => 'Alat Promosi', 'unit' => 'SET', 'minimum_stock' => 3, 'uses_jug_conversion' => false, 'sachets_per_jug' => null, 'lots' => [['52542227 P1', 15, '2028-12-31']]],
            ['name' => 'DANCOW BANNER', 'description' => 'Banner promosi Dancow', 'sku' => '12506666', 'brand' => 'DANCOW', 'category' => 'Alat Promosi', 'unit' => 'PCS', 'minimum_stock' => 5, 'uses_jug_conversion' => false, 'sachets_per_jug' => null, 'lots' => [['52543227 P1', 30, '2028-12-31']]],
            ['name' => 'DANCOW SAMPLING KIT', 'description' => 'Perangkat sampling Dancow', 'sku' => '12506667', 'brand' => 'DANCOW', 'category' => 'Alat Promosi', 'unit' => 'SET', 'minimum_stock' => 5, 'uses_jug_conversion' => false, 'sachets_per_jug' => null, 'lots' => [['52544227 P1', 25, '2028-12-31']]],
            ['name' => 'DANCOW RAK MINI', 'description' => 'Rak mini display Dancow', 'sku' => '12506668', 'brand' => 'DANCOW', 'category' => 'Alat Promosi', 'unit' => 'UNIT', 'minimum_stock' => 3, 'uses_jug_conversion' => false, 'sachets_per_jug' => null, 'lots' => [['52545227 P1', 18, '2028-12-31']]],

            ['name' => 'KITKAT 4 FINGER 35G', 'description' => 'KitKat 4 finger 35 gram', 'sku' => '12512660', 'brand' => 'KITKAT', 'category' => 'Minuman', 'unit' => 'PCS', 'minimum_stock' => 150, 'uses_jug_conversion' => false, 'sachets_per_jug' => null, 'lots' => [['50304127 K1', 800, '2027-11-30'], ['50304127 K2', 400, '2027-12-31']]],
            ['name' => 'KITKAT CHUNKY 38G', 'description' => 'KitKat Chunky 38 gram', 'sku' => '12512661', 'brand' => 'KITKAT', 'category' => 'Minuman', 'unit' => 'PCS', 'minimum_stock' => 120, 'uses_jug_conversion' => false, 'sachets_per_jug' => null, 'lots' => [['50305127 K1', 600, '2027-12-31']]],
            ['name' => 'KITKAT BITES 42G', 'description' => 'KitKat Bites 42 gram', 'sku' => '12512662', 'brand' => 'KITKAT', 'category' => 'Minuman', 'unit' => 'PCS', 'minimum_stock' => 100, 'uses_jug_conversion' => false, 'sachets_per_jug' => null, 'lots' => [['50306127 K1', 500, '2028-01-31']]],
            ['name' => 'KITKAT COUNTER DISPLAY', 'description' => 'Counter display KitKat', 'sku' => '12512663', 'brand' => 'KITKAT', 'category' => 'Alat Promosi', 'unit' => 'UNIT', 'minimum_stock' => 3, 'uses_jug_conversion' => false, 'sachets_per_jug' => null, 'lots' => [['50307127 K1', 20, '2028-12-31']]],
            ['name' => 'KITKAT SAMPLING TRAY', 'description' => 'Tray sampling KitKat', 'sku' => '12512664', 'brand' => 'KITKAT', 'category' => 'Alat Promosi', 'unit' => 'UNIT', 'minimum_stock' => 3, 'uses_jug_conversion' => false, 'sachets_per_jug' => null, 'lots' => [['50308127 K1', 24, '2028-12-31']]],

            ['name' => 'BEAR BRAND STERILIZED 189ML', 'description' => 'Bear Brand sterilized 189 ml', 'sku' => '12516660', 'brand' => 'BEAR BRAND', 'category' => 'Minuman', 'unit' => 'KALENG', 'minimum_stock' => 120, 'uses_jug_conversion' => false, 'sachets_per_jug' => null, 'lots' => [['50304127 B1', 720, '2027-09-30'], ['50304127 B2', 400, '2027-11-30']]],
            ['name' => 'BEAR BRAND GOLD WHITE TEA 140ML', 'description' => 'Bear Brand Gold White Tea 140 ml', 'sku' => '12516661', 'brand' => 'BEAR BRAND', 'category' => 'Minuman', 'unit' => 'KALENG', 'minimum_stock' => 100, 'uses_jug_conversion' => false, 'sachets_per_jug' => null, 'lots' => [['50305127 B1', 600, '2027-10-31']]],
            ['name' => 'BEAR BRAND GOLD WHITE MALT 140ML', 'description' => 'Bear Brand Gold White Malt 140 ml', 'sku' => '12516662', 'brand' => 'BEAR BRAND', 'category' => 'Minuman', 'unit' => 'KALENG', 'minimum_stock' => 100, 'uses_jug_conversion' => false, 'sachets_per_jug' => null, 'lots' => [['50306127 B1', 600, '2027-10-31']]],
            ['name' => 'BEAR BRAND DISPLAY STAND', 'description' => 'Display stand Bear Brand', 'sku' => '12516663', 'brand' => 'BEAR BRAND', 'category' => 'Alat Promosi', 'unit' => 'UNIT', 'minimum_stock' => 2, 'uses_jug_conversion' => false, 'sachets_per_jug' => null, 'lots' => [['50307127 B1', 12, '2028-12-31']]],
            ['name' => 'BEAR BRAND SAMPLING CUP', 'description' => 'Cup sampling Bear Brand', 'sku' => '12516664', 'brand' => 'BEAR BRAND', 'category' => 'Alat Promosi', 'unit' => 'PCS', 'minimum_stock' => 100, 'uses_jug_conversion' => false, 'sachets_per_jug' => null, 'lots' => [['50308127 B1', 1000, '2028-12-31']]],
        ];
    }

    /**
     * @return list<array{date: string, brand: string, brand_code: string, shift: string, team: string, jugs: int, schools: list<string>, pending: bool, items: list<array{0: string, 1: string, 2: int}>}>
     */
    private function mrvDefinitions(): array
    {
        return [
            ['date' => '2026-10-15', 'brand' => 'MILO', 'brand_code' => 'MLO', 'shift' => 'Pagi', 'team' => 'Tim Sampling Milo 1', 'jugs' => 2, 'schools' => ['SDN BANTAR GEBANG V', 'SDN BANTAR GEBANG II', 'SDN PEDURENAN'], 'pending' => false, 'items' => [['12583797', '41496761C1', 300], ['12583798', '41601761C1', 410]]],
            ['date' => '2026-10-17', 'brand' => 'MILO', 'brand_code' => 'MLO', 'shift' => 'Siang', 'team' => 'Tim Sampling Milo 2', 'jugs' => 3, 'schools' => ['SDN CIMAHPAR 1', 'SDN CIMAHPAR 3'], 'pending' => false, 'items' => [['12583797', '41576761C2', 336], ['12583799', '41702761C1', 250]]],
            ['date' => '2026-10-20', 'brand' => 'DANCOW', 'brand_code' => 'DCW', 'shift' => 'Pagi', 'team' => 'Tim Sampling Dancow 1', 'jugs' => 2, 'schools' => ['SDN SUKAMANTRI', 'SDN SUKAMANTRI 2'], 'pending' => false, 'items' => [['12594630', '50302127 P1', 60], ['12506663', '52540227 P1', 40]]],
            ['date' => '2026-10-22', 'brand' => 'DANCOW', 'brand_code' => 'DCW', 'shift' => 'Siang', 'team' => 'Tim Sampling Dancow 2', 'jugs' => 2, 'schools' => ['SDN KOTA BATU 1', 'SDN KOTA BATU 2', 'SDN JUNREJO'], 'pending' => false, 'items' => [['12506660', '50304127 P1', 120], ['12506661', '50305127 P2', 90]]],
            ['date' => '2026-10-25', 'brand' => 'KITKAT', 'brand_code' => 'KKT', 'shift' => 'Pagi', 'team' => 'Tim Sampling KitKat', 'jugs' => 1, 'schools' => ['SDN BANTAR GEBANG V', 'SDN PEDURENAN'], 'pending' => false, 'items' => [['12512660', '50304127 K1', 150], ['12512661', '50305127 K1', 100]]],
            ['date' => '2026-10-27', 'brand' => 'BEAR BRAND', 'brand_code' => 'BBR', 'shift' => 'Pagi', 'team' => 'Tim Sampling Bear Brand', 'jugs' => 1, 'schools' => ['SDN CIMAHPAR 1', 'SDN SUKAMANTRI'], 'pending' => false, 'items' => [['12516660', '50304127 B1', 120], ['12516661', '50305127 B1', 96]]],
            ['date' => '2026-10-30', 'brand' => 'MILO', 'brand_code' => 'MLO', 'shift' => 'Pagi', 'team' => 'Tim Sampling Milo 3', 'jugs' => 2, 'schools' => ['SDN BANTAR GEBANG II', 'SDN JUNREJO', 'SDN KOTA BATU 1'], 'pending' => true, 'items' => [['12583801', '50305127 P1', 120], ['12583800', '41703761C1', 200]]],
            ['date' => '2026-11-02', 'brand' => 'DANCOW', 'brand_code' => 'DCW', 'shift' => 'Siang', 'team' => 'Tim Sampling Dancow 3', 'jugs' => 2, 'schools' => ['SDN PEDURENAN', 'SDN CIMAHPAR 3'], 'pending' => true, 'items' => [['12594631', '50303127 P1', 75], ['12506663', '52540227 P2', 35]]],
        ];
    }

    /**
     * @return list<array{date: string, brand_code: string, department: string, recipient: string, items: list<array{0: string, 1: string, 2: int, 3: string}>}>
     */
    private function omfDefinitions(): array
    {
        return [
            ['date' => '2026-10-18', 'brand_code' => 'DCW', 'department' => 'Marketing', 'recipient' => 'Andi Prasetyo', 'items' => [['12594630', '50302127 P1', 5, 'PT INDOMAR VISUAL MANADO'], ['12506663', '52540227 P1', 5, 'PT INDOMAR VISUAL MANADO']]],
            ['date' => '2026-10-21', 'brand_code' => 'MLO', 'department' => 'Event', 'recipient' => 'Rizky Maulana', 'items' => [['12583797', '41576761C2', 20, 'Event SDN Bantar Gebang V']]],
            ['date' => '2026-10-24', 'brand_code' => 'KKT', 'department' => 'Sampling', 'recipient' => 'Fajar Ramadhan', 'items' => [['12512660', '50304127 K1', 12, 'Sampling kampus']]],
            ['date' => '2026-10-28', 'brand_code' => 'BBR', 'department' => 'Event', 'recipient' => 'Nadia Putri', 'items' => [['12516660', '50304127 B1', 24, 'Event Posyandu']]],
            ['date' => '2026-10-31', 'brand_code' => 'DCW', 'department' => 'Sales', 'recipient' => 'Yoga Saputra', 'items' => [['12506660', '50304127 P1', 30, 'PT Sumber Alfaria']]],
            ['date' => '2026-11-05', 'brand_code' => 'MLO', 'department' => 'Event', 'recipient' => 'Ayu Lestari', 'items' => [['12583798', '41601761C1', 50, 'Event Indomaret']]],
        ];
    }
}
