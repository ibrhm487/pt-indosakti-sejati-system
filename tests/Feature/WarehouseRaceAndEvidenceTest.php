<?php

namespace Tests\Feature;

use App\Enums\MrvStatus;
use App\Models\Event;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\StockReceipt;
use App\Models\StockRequest;
use App\Models\StockReturn;
use App\Models\User;
use App\Services\StockService;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WarehouseRaceAndEvidenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_reject_is_only_allowed_for_pending_mrv(): void
    {
        $this->seedWorkspaceData();
        $warehouse = $this->user('warehouse@indosakti.com');
        [$event, $product] = $this->eventAndProduct();
        $mrv = $this->createMrv($event, $product, 10, MrvStatus::PENDING);

        $this->actingAs($warehouse)->post(route('warehouse.mrv.approve', $mrv))->assertRedirect();

        $issuedMrv = $this->createMrv($this->newEvent(), $product, 5, MrvStatus::ISSUED);

        $this->actingAs($warehouse)
            ->post(route('warehouse.mrv.reject', $issuedMrv), ['reason' => 'Tidak relevan lagi.'])
            ->assertSessionHasErrors('status');

        $this->assertSame(MrvStatus::ISSUED, $issuedMrv->fresh()->status);
    }

    public function test_double_approve_second_attempt_fails(): void
    {
        $this->seedWorkspaceData();
        $warehouse = $this->user('warehouse@indosakti.com');
        [$event, $product] = $this->eventAndProduct();
        $mrv = $this->createMrv($event, $product, 10, MrvStatus::PENDING);

        $this->actingAs($warehouse)->post(route('warehouse.mrv.approve', $mrv))->assertRedirect();
        $this->assertSame(MrvStatus::APPROVED, $mrv->fresh()->status);

        $this->actingAs($warehouse)
            ->post(route('warehouse.mrv.approve', $mrv))
            ->assertSessionHasErrors('status');

        $this->assertSame(MrvStatus::APPROVED, $mrv->fresh()->status);
    }

    public function test_issue_service_revalidates_status_inside_transaction(): void
    {
        $this->seedWorkspaceData();
        [$event, $product] = $this->eventAndProduct();
        $mrv = $this->createMrv($event, $product, 10, MrvStatus::APPROVED);
        $before = $this->onHand($product->id);

        $stock = app(StockService::class);
        $stock->issue($mrv);

        $this->assertSame($before - 10, $this->onHand($product->id));
        $this->assertSame(MrvStatus::ISSUED, $mrv->fresh()->status);

        try {
            $stock->issue($mrv);
            $this->fail('Issue kedua dengan instance stale seharusnya melempar DomainException.');
        } catch (DomainException) {
            // expected — in-transaction re-validation saw ISSUED.
        }

        $this->assertSame($before - 10, $this->onHand($product->id));
    }

    public function test_return_service_revalidates_status_inside_transaction(): void
    {
        $this->seedWorkspaceData();
        [$event, $product] = $this->eventAndProduct();
        $mrv = $this->createMrv($event, $product, 20, MrvStatus::ISSUED);
        $before = $this->onHand($product->id);

        $return = StockReturn::create([
            'return_number' => StockReturn::generateNumber(),
            'stock_request_id' => $mrv->id,
            'event_id' => $event->id,
            'status' => 'pending',
        ]);
        $return->items()->create([
            'product_id' => $product->id,
            'quantity_good' => 8,
            'quantity_damaged' => 2,
        ]);

        $stock = app(StockService::class);
        $stock->processReturn($return);

        $this->assertSame($before + 8, $this->onHand($product->id));

        try {
            $stock->processReturn($return);
            $this->fail('Proses kedua dengan instance stale seharusnya melempar DomainException.');
        } catch (DomainException) {
            // expected — in-transaction re-validation saw processed.
        }

        $this->assertSame($before + 8, $this->onHand($product->id));
    }

    public function test_receipt_with_photo_stores_private_evidence(): void
    {
        Storage::fake('local');
        $this->seedWorkspaceData();
        $warehouse = $this->user('warehouse@indosakti.com');
        [, $product] = $this->eventAndProduct();
        $before = $this->onHand($product->id);

        $this->actingAs($warehouse)
            ->post(route('warehouse.receipts.store'), [
                'product_id' => $product->id,
                'quantity' => 30,
                'supplier' => 'Pemasok Uji',
                'photo' => UploadedFile::fake()->image('barang-masuk.jpg'),
            ])
            ->assertRedirect(route('warehouse.receipts.index'));

        $this->assertSame($before + 30, $this->onHand($product->id));

        $receipt = StockReceipt::latest('id')->firstOrFail();
        $this->assertNotNull($receipt->photo_path);
        Storage::disk('local')->assertExists($receipt->photo_path);

        $this->actingAs($warehouse)
            ->get(route('warehouse.receipts.photo', $receipt))
            ->assertOk();
    }

    public function test_issue_with_photo_and_recipient_stores_evidence(): void
    {
        Storage::fake('local');
        $this->seedWorkspaceData();
        $warehouse = $this->user('warehouse@indosakti.com');
        [$event, $product] = $this->eventAndProduct();
        $mrv = $this->createMrv($event, $product, 10, MrvStatus::APPROVED);
        $before = $this->onHand($product->id);

        $this->actingAs($warehouse)
            ->post(route('warehouse.mrv.issue', $mrv), [
                'recipient' => 'Andi',
                'photo' => UploadedFile::fake()->image('barang-keluar.jpg'),
            ])
            ->assertRedirect();

        $this->assertSame($before - 10, $this->onHand($product->id));

        $fresh = $mrv->fresh();
        $this->assertSame('Andi', $fresh->recipient);
        $this->assertNotNull($fresh->photo_path);
        Storage::disk('local')->assertExists($fresh->photo_path);

        $this->actingAs($warehouse)
            ->get(route('warehouse.mrv.photo', $mrv))
            ->assertOk();
    }

    public function test_return_with_photo_stores_evidence(): void
    {
        Storage::fake('local');
        $this->seedWorkspaceData();
        $warehouse = $this->user('warehouse@indosakti.com');
        [$event, $product] = $this->eventAndProduct();
        $mrv = $this->createMrv($event, $product, 15, MrvStatus::ISSUED);

        $this->actingAs($warehouse)
            ->post(route('warehouse.returns.store'), [
                'stock_request_id' => $mrv->id,
                'items' => [['product_id' => $product->id, 'quantity_good' => 10, 'quantity_damaged' => 5]],
                'photo' => UploadedFile::fake()->image('pengembalian.jpg'),
            ])
            ->assertRedirect(route('warehouse.returns.index'));

        $return = StockReturn::latest('id')->firstOrFail();
        $this->assertNotNull($return->photo_path);
        Storage::disk('local')->assertExists($return->photo_path);

        $this->actingAs($warehouse)
            ->get(route('warehouse.returns.photo', $return))
            ->assertOk();
    }

    public function test_guest_cannot_download_warehouse_photo(): void
    {
        $this->seedWorkspaceData();
        [$event, $product] = $this->eventAndProduct();
        $mrv = $this->createMrv($event, $product, 5, MrvStatus::ISSUED);
        $mrv->forceFill(['photo_path' => 'warehouse/issue/x.jpg'])->save();

        $this->get(route('warehouse.mrv.photo', $mrv))->assertRedirect(route('login'));
    }

    public function test_movements_summary_reports_opening_and_totals(): void
    {
        $this->seedWorkspaceData();
        $warehouse = $this->user('warehouse@indosakti.com');
        $product = Product::create([
            'name' => 'Produk Rekap',
            'sku' => 'REKAP-'.uniqid(),
            'unit' => 'pcs',
        ]);

        $stock = app(StockService::class);
        $stock->receive($product, 50, []);
        $stock->adjust($product, -5, 'Koreksi rekap uji');

        $this->actingAs($warehouse)
            ->get(route('warehouse.inventory.movements', ['product_id' => $product->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Warehouse/Inventory/Movements')
                ->where('summary.product', 'Produk Rekap')
                ->where('summary.opening', 0)
                ->where('summary.stock_in', 50)
                ->where('summary.stock_out', 0)
                ->where('summary.returned', 0)
                ->where('summary.damaged', 0)
                ->where('summary.adjustments', 5)
                ->where('summary.on_hand', 45)
                ->where('summary.available', 45));
    }

    public function test_movements_summary_is_null_without_product_filter(): void
    {
        $this->seedWorkspaceData();
        $warehouse = $this->user('warehouse@indosakti.com');

        $this->actingAs($warehouse)
            ->get(route('warehouse.inventory.movements'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('summary', null));
    }

    private function createMrv(Event $event, Product $product, int $quantity, MrvStatus $status): StockRequest
    {
        $mrv = StockRequest::create([
            'mrv_number' => StockRequest::generateNumber(),
            'event_id' => $event->id,
            'type' => 'issue',
            'status' => $status,
        ]);

        $mrv->items()->create([
            'product_id' => $product->id,
            'quantity_requested' => $quantity,
            'quantity_issued' => $status === MrvStatus::ISSUED ? $quantity : 0,
        ]);

        if ($status === MrvStatus::ISSUED) {
            $mrv->forceFill(['issued_at' => now()])->save();
        }

        return $mrv;
    }

    private function newEvent(): Event
    {
        return Event::create([
            'event_code' => 'EVT-WH-'.uniqid(),
            'event_name' => 'Event Uji Gudang',
        ]);
    }

    /**
     * @return array{0: Event, 1: Product}
     */
    private function eventAndProduct(): array
    {
        $product = Product::create([
            'name' => 'Produk Race',
            'sku' => 'RACE-'.uniqid(),
            'unit' => 'pcs',
        ]);

        Inventory::create([
            'product_id' => $product->id,
            'qty_on_hand' => 100,
            'qty_reserved' => 0,
        ]);

        return [$this->newEvent(), $product];
    }

    private function onHand(int $productId): int
    {
        return (int) Inventory::where('product_id', $productId)->value('qty_on_hand');
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
