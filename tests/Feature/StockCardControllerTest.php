<?php

namespace Tests\Feature;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\StockReceiptSlip;
use App\Models\StockRequest;
use App\Models\User;
use App\Services\StockService;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StockCardControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_view_stock_card(): void
    {
        $product = $this->product('SC-001', 'Stock Card Item', 'pcs');

        $this->get(route('warehouse.inventory.stock-card', $product))
            ->assertRedirect(route('login'));
    }

    public function test_stock_card_incoming_shows_movements_totals_and_receipt_type(): void
    {
        $this->seedWorkspaceData();
        $warehouse = $this->user('warehouse@indosakti.com');
        $product = $this->product('SC-001', 'Stock Card Item', 'pcs');
        $this->receiveStock($product, 'MRS/JKT/2009/SC1/01', 'Free Goods/FGR', 12, 4);

        $this->actingAs($warehouse)
            ->get(route('warehouse.inventory.stock-card', $product))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Warehouse/Inventory/StockCard')
                ->where('product.sku', 'SC-001')
                ->where('selected_lot', 'LOT-A')
                ->where('lot_expired_date', '2027-09-20')
                ->where('direction', 'incoming')
                ->where('type_key', 'Free Goods/FGR')
                ->where('type_raw', 'Free Goods/FGR')
                ->has('type_options', 5)
                ->where('totals.incoming', 12)
                ->where('totals.outgoing', 4)
                ->where('totals.damage', 0)
                ->where('summary.beginning', 0)
                ->where('summary.final', 8)
                ->has('rows', 2)
                ->where('rows.0.beginning', 0)
                ->where('rows.0.incoming_document', 'MRS/JKT/2009/SC1/01')
                ->where('rows.0.incoming_quantity', 12)
                ->where('rows.1.beginning', 12)
                ->where('rows.1.outgoing_document', 'MRV/JKT/1510/SC1/01')
                ->has('outgoing_details', 1)
                ->where('outgoing_details.0.document_number', 'MRV/JKT/1510/SC1/01')
                ->where('outgoing_details.0.balance', 8));
    }

    public function test_stock_card_outgoing_direction_marks_unknown_type_as_other(): void
    {
        $this->seedWorkspaceData();
        $warehouse = $this->user('warehouse@indosakti.com');
        $product = $this->product('SC-002', 'Custom Stock Item', 'box');
        $this->receiveStock($product, 'MRS/JKT/2010/SC2/01', 'Sampling', 9, 2);

        $this->actingAs($warehouse)
            ->get(route('warehouse.inventory.stock-card', [
                'product' => $product,
                'direction' => 'outgoing',
                'lot' => 'LOT-A',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('direction', 'outgoing')
                ->where('type_key', 'Other')
                ->where('type_raw', 'Sampling')
                ->where('summary.final', 7));
    }

    public function test_unknown_lot_falls_back_to_the_first_available_lot(): void
    {
        $this->seedWorkspaceData();
        $warehouse = $this->user('warehouse@indosakti.com');
        $product = $this->product('SC-003', 'Fallback Stock Item', 'pcs');
        $this->receiveStock($product, 'MRS/JKT/2011/SC3/01', 'Cups/Spoon', 5, 0);

        $this->actingAs($warehouse)
            ->get(route('warehouse.inventory.stock-card', [
                'product' => $product,
                'lot' => 'LOT-NOT-EXISTS',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('selected_lot', 'LOT-A')
                ->has('rows', 1));
    }

    private function receiveStock(
        Product $product,
        string $mrsNumber,
        string $receiptType,
        int $receivedQuantity,
        int $issuedQuantity,
        string $mrvNumber = 'MRV/JKT/1510/SC1/01',
    ): void {
        $slip = StockReceiptSlip::create([
            'mrs_number' => $mrsNumber,
            'receipt_type' => $receiptType,
            'do_number' => 'DO/SC/'.str_replace('/', '-', $mrsNumber),
            'received_date' => '2026-09-20',
        ]);

        $item = $slip->items()->create([
            'product_id' => $product->id,
            'sku' => $product->sku,
            'item_name' => $product->name,
            'lot_code' => 'LOT-A',
            'received_quantity' => $receivedQuantity,
            'uom' => $product->unit,
            'expired_date' => '2027-09-20',
        ]);

        app(StockService::class)->receive($product, $receivedQuantity, [
            'reference' => $item,
            'lot_code' => 'LOT-A',
            'expired_date' => '2027-09-20',
        ]);

        if ($issuedQuantity <= 0) {
            return;
        }

        $request = StockRequest::create([
            'mrv_number' => $mrvNumber,
        ]);

        StockMovement::create([
            'product_id' => $product->id,
            'type' => StockMovementType::STOCK_OUT,
            'quantity' => $issuedQuantity,
            'balance_after' => $receivedQuantity - $issuedQuantity,
            'reference_type' => StockRequest::class,
            'reference_id' => $request->id,
            'lot_code' => 'LOT-A',
            'expired_date' => '2027-09-20',
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
