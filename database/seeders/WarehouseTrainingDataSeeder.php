<?php

namespace Database\Seeders;

use App\Enums\MrvStatus;
use App\Enums\StockMovementType;
use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\StockReceiptSlip;
use App\Models\StockReceiptSlipItem;
use App\Models\StockRequest;
use App\Models\StockRequestItem;
use App\Services\StockService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use RuntimeException;

class WarehouseTrainingDataSeeder extends Seeder
{
    public function run(): void
    {
        throw new LogicException('Gunakan command warehouse:replace-training-data agar penggantian terkonfirmasi.');
    }

    /**
     * @return array{products: int, mrs: int, mrv: int, stock_movements: int, stock_returns: int}
     */
    public function replace(string $confirmation): array
    {
        $database = DB::connection()->getDatabaseName();

        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Penggantian data warehouse hanya diizinkan pada environment local atau testing.');
        }

        if ($confirmation !== "REPLACE-WAREHOUSE-DATA:{$database}") {
            throw new RuntimeException('Konfirmasi penggantian data tidak cocok dengan nama database aktif.');
        }

        return DB::transaction(function (): array {
            $oldCounts = $this->warehouseCounts();
            $this->clearWarehouseData();

            $newCounts = $this->seedTrainingData();

            AuditLog::create([
                'action' => 'REPLACE_WAREHOUSE_TRAINING_DATA',
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
        ];
    }

    private function clearWarehouseData(): void
    {
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
     * @return array{products: int, mrs: int, mrv: int, stock_movements: int, stock_returns: int}
     */
    private function seedTrainingData(): array
    {
        $brands = [];
        foreach (['BEAR BRAND', 'DANCOW', 'KITKAT', 'MILO'] as $name) {
            $brands[$name] = Brand::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'is_active' => true],
            );
        }

        $categories = [];
        foreach (['Minuman', 'Alat Promosi'] as $name) {
            $categories[$name] = ProductCategory::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name],
            );
        }

        $stockService = app(StockService::class);

        foreach ($this->products() as $index => $data) {
            $product = Product::create([
                'name' => $data['name'],
                'sku' => $data['sku'],
                'brand_id' => $brands[$data['brand']]->id,
                'product_category_id' => $categories[$data['category']]->id,
                'unit' => $data['unit'],
                'minimum_stock' => $data['minimum_stock'],
                'is_active' => true,
            ]);

            $receivedDate = today()->subDays(40 - $index);
            $issuedDate = $receivedDate->copy()->addDays(2);
            $lotCode = sprintf('LOT-LATIHAN-%03d', $index + 1);
            $expiryDate = $receivedDate->copy()->addYear();
            $mrsNumber = sprintf('MRS-LATIHAN-%03d', $index + 1);
            $mrvNumber = sprintf('MRV-LATIHAN-%03d', $index + 1);

            $slip = StockReceiptSlip::create([
                'mrs_number' => $mrsNumber,
                'receipt_type' => 'Penerimaan Latihan',
                'do_number' => sprintf('DO-LATIHAN-%03d', $index + 1),
                'shipper' => 'Distributor Simulasi',
                'received_date' => $receivedDate,
                'release_date' => $receivedDate->copy()->addDay(),
                'release_time' => '09:30',
                'authorized_by' => 'Simulasi Data Latihan',
                'quantity_released' => $data['received_quantity'],
                'quantity_assurance' => $data['received_quantity'],
                'received_by_name' => 'Simulasi Data Latihan',
                'received_by_role' => 'Data Latihan',
                'approved_by_name' => 'Simulasi Data Latihan',
                'approved_by_role' => 'Data Latihan',
                'operational_manager_by_name' => 'Simulasi Data Latihan',
                'operational_manager_by_role' => 'Data Latihan',
                'quality_assurance_by_name' => 'Simulasi Data Latihan',
                'quality_assurance_by_role' => 'Data Latihan',
                'notes' => '[DATA LATIHAN] Dokumen sintetis, bukan transaksi nyata.',
            ]);

            $receiptItem = StockReceiptSlipItem::create([
                'stock_receipt_slip_id' => $slip->id,
                'product_id' => $product->id,
                'sku' => $product->sku,
                'item_name' => $product->name,
                'lot_code' => $lotCode,
                'description' => '[DATA LATIHAN] Penerimaan barang simulasi.',
                'received_quantity' => $data['received_quantity'],
                'uom' => $product->unit,
                'expired_date' => $expiryDate,
                'status' => 'Baik',
                'remark' => '[DATA LATIHAN]',
            ]);

            $receiptMovement = $stockService->receive($product, $data['received_quantity'], [
                'reference' => $receiptItem,
                'reason' => '[DATA LATIHAN] Penerimaan simulasi.',
                'lot_code' => $lotCode,
                'expired_date' => $expiryDate,
            ]);
            $this->setMovementDate($receiptMovement->id, $receivedDate->copy()->setTime(8, 0));

            $request = StockRequest::create([
                'mrv_number' => $mrvNumber,
                'activity_type' => 'Pemakaian gudang simulasi',
                'activity_date' => $issuedDate,
                'jug_quantity' => $data['issued_quantity'],
                'week_label' => sprintf('LATIHAN-MINGGU-%02d', intdiv($index, 5) + 1),
                'brand_name' => $data['brand'],
                'venue_name' => 'Gudang Simulasi Indosakti',
                'team_member_name' => 'Tim Simulasi',
                'material_categories' => [$data['category']],
                'sampling_target_wet' => $data['issued_quantity'],
                'sampling_target_dry' => 0,
                'type' => 'issue',
                'status' => MrvStatus::APPROVED,
                'material_category' => $data['category'],
                'notes' => '[DATA LATIHAN] MRV sintetis untuk simulasi pergerakan stok.',
                'approved_by_name' => 'Simulasi Data Latihan',
                'prepared_by_name' => 'Simulasi Data Latihan',
                'approved_at' => $issuedDate->copy()->setTime(9, 0),
            ]);

            StockRequestItem::create([
                'stock_request_id' => $request->id,
                'product_id' => $product->id,
                'lot_no' => $lotCode,
                'quantity_requested' => $data['issued_quantity'],
                'quantity_issued' => 0,
                'quantity_returned' => 0,
                'quantity_damaged' => 0,
                'notes' => '[DATA LATIHAN] Pengeluaran simulasi.',
            ]);

            $stockService->issue($request);
            $issueMovementId = DB::table('stock_movements')
                ->where('reference_type', $request->getMorphClass())
                ->where('reference_id', $request->id)
                ->where('type', StockMovementType::STOCK_OUT->value)
                ->value('id');

            $this->setMovementDate($issueMovementId, $issuedDate->copy()->setTime(10, 0));
        }

        return [
            'products' => Product::query()->count(),
            'mrs' => StockReceiptSlip::query()->count(),
            'mrv' => StockRequest::query()->count(),
            'stock_movements' => DB::table('stock_movements')->count(),
            'stock_returns' => DB::table('stock_returns')->count(),
        ];
    }

    private function setMovementDate(int $movementId, Carbon $date): void
    {
        DB::table('stock_movements')
            ->where('id', $movementId)
            ->update([
                'created_at' => $date,
                'updated_at' => $date,
            ]);
    }

    /**
     * @return list<array{name: string, sku: string, brand: string, category: string, unit: string, minimum_stock: int, received_quantity: int, issued_quantity: int}>
     */
    private function products(): array
    {
        return [
            ['name' => 'Bear Brand Sterilized 189ml', 'sku' => 'LAT-BEAR-STERIL-189', 'brand' => 'BEAR BRAND', 'category' => 'Minuman', 'unit' => 'kaleng', 'minimum_stock' => 30, 'received_quantity' => 240, 'issued_quantity' => 36],
            ['name' => 'Bear Brand Gold White Tea 140ml', 'sku' => 'LAT-BEAR-GOLD-140', 'brand' => 'BEAR BRAND', 'category' => 'Minuman', 'unit' => 'kaleng', 'minimum_stock' => 24, 'received_quantity' => 180, 'issued_quantity' => 28],
            ['name' => 'Bear Brand Gold White Malt 140ml', 'sku' => 'LAT-BEAR-MALT-140', 'brand' => 'BEAR BRAND', 'category' => 'Minuman', 'unit' => 'kaleng', 'minimum_stock' => 24, 'received_quantity' => 180, 'issued_quantity' => 30],
            ['name' => 'Bear Brand Display Stand', 'sku' => 'LAT-BEAR-DISPLAY-STAND', 'brand' => 'BEAR BRAND', 'category' => 'Alat Promosi', 'unit' => 'unit', 'minimum_stock' => 2, 'received_quantity' => 24, 'issued_quantity' => 4],
            ['name' => 'Bear Brand Sampling Cup', 'sku' => 'LAT-BEAR-SAMPLING-CUP', 'brand' => 'BEAR BRAND', 'category' => 'Alat Promosi', 'unit' => 'pcs', 'minimum_stock' => 50, 'received_quantity' => 600, 'issued_quantity' => 120],
            ['name' => 'Dancow FortiGro Cokelat 200g', 'sku' => 'LAT-DANCOW-FORTIGRO-200', 'brand' => 'DANCOW', 'category' => 'Minuman', 'unit' => 'sachet', 'minimum_stock' => 40, 'received_quantity' => 300, 'issued_quantity' => 48],
            ['name' => 'Dancow 1+ Madu 200g', 'sku' => 'LAT-DANCOW-1PLUS-200', 'brand' => 'DANCOW', 'category' => 'Minuman', 'unit' => 'sachet', 'minimum_stock' => 36, 'received_quantity' => 240, 'issued_quantity' => 42],
            ['name' => 'Dancow UHT 110ml', 'sku' => 'LAT-DANCOW-UHT-110', 'brand' => 'DANCOW', 'category' => 'Minuman', 'unit' => 'pcs', 'minimum_stock' => 48, 'received_quantity' => 360, 'issued_quantity' => 60],
            ['name' => 'Dancow Kids Backdrop', 'sku' => 'LAT-DANCOW-BACKDROP', 'brand' => 'DANCOW', 'category' => 'Alat Promosi', 'unit' => 'unit', 'minimum_stock' => 1, 'received_quantity' => 12, 'issued_quantity' => 2],
            ['name' => 'Dancow Sampling Kit', 'sku' => 'LAT-DANCOW-SAMPLING-KIT', 'brand' => 'DANCOW', 'category' => 'Alat Promosi', 'unit' => 'set', 'minimum_stock' => 4, 'received_quantity' => 40, 'issued_quantity' => 8],
            ['name' => 'KitKat 4 Finger 35g', 'sku' => 'LAT-KITKAT-4FINGER-35', 'brand' => 'KITKAT', 'category' => 'Minuman', 'unit' => 'pcs', 'minimum_stock' => 40, 'received_quantity' => 300, 'issued_quantity' => 54],
            ['name' => 'KitKat Chunky 38g', 'sku' => 'LAT-KITKAT-CHUNKY-38', 'brand' => 'KITKAT', 'category' => 'Minuman', 'unit' => 'pcs', 'minimum_stock' => 36, 'received_quantity' => 240, 'issued_quantity' => 44],
            ['name' => 'KitKat Bites 42g', 'sku' => 'LAT-KITKAT-BITES-42', 'brand' => 'KITKAT', 'category' => 'Minuman', 'unit' => 'pcs', 'minimum_stock' => 24, 'received_quantity' => 180, 'issued_quantity' => 32],
            ['name' => 'KitKat Counter Display', 'sku' => 'LAT-KITKAT-COUNTER-DISPLAY', 'brand' => 'KITKAT', 'category' => 'Alat Promosi', 'unit' => 'unit', 'minimum_stock' => 2, 'received_quantity' => 20, 'issued_quantity' => 3],
            ['name' => 'KitKat Sampling Tray', 'sku' => 'LAT-KITKAT-SAMPLING-TRAY', 'brand' => 'KITKAT', 'category' => 'Alat Promosi', 'unit' => 'unit', 'minimum_stock' => 3, 'received_quantity' => 30, 'issued_quantity' => 6],
            ['name' => 'Milo Activ-Go 22g', 'sku' => 'LAT-MILO-ACTIV-GO-22', 'brand' => 'MILO', 'category' => 'Minuman', 'unit' => 'sachet', 'minimum_stock' => 60, 'received_quantity' => 420, 'issued_quantity' => 72],
            ['name' => 'Milo 3in1 35g', 'sku' => 'LAT-MILO-3IN1-35', 'brand' => 'MILO', 'category' => 'Minuman', 'unit' => 'sachet', 'minimum_stock' => 48, 'received_quantity' => 360, 'issued_quantity' => 60],
            ['name' => 'Milo UHT 180ml', 'sku' => 'LAT-MILO-UHT-180', 'brand' => 'MILO', 'category' => 'Minuman', 'unit' => 'pcs', 'minimum_stock' => 48, 'received_quantity' => 360, 'issued_quantity' => 66],
            ['name' => 'Milo Booth Kit', 'sku' => 'LAT-MILO-BOOTH-KIT', 'brand' => 'MILO', 'category' => 'Alat Promosi', 'unit' => 'set', 'minimum_stock' => 2, 'received_quantity' => 16, 'issued_quantity' => 3],
            ['name' => 'Milo Event Banner A2', 'sku' => 'LAT-MILO-BANNER-A2', 'brand' => 'MILO', 'category' => 'Alat Promosi', 'unit' => 'pcs', 'minimum_stock' => 5, 'received_quantity' => 50, 'issued_quantity' => 10],
        ];
    }
}
