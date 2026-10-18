<?php

namespace Tests\Feature;

use App\Enums\MrvStatus;
use App\Models\Event;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\StockRequest;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehouseStockIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_return_requires_an_issued_mrv(): void
    {
        $this->seedWorkspaceData();
        $warehouse = $this->user('warehouse@indosakti.com');
        $mrv = StockRequest::where('status', MrvStatus::APPROVED)->firstOrFail();
        $item = $mrv->items()->firstOrFail();
        $before = $this->onHand($item->product_id);

        $this->actingAs($warehouse)
            ->post(route('warehouse.returns.store'), [
                'stock_request_id' => $mrv->id,
                'items' => [['product_id' => $item->product_id, 'quantity_good' => 10, 'quantity_damaged' => 0]],
            ])
            ->assertSessionHasErrors('stock_request_id');

        $this->assertDatabaseCount('stock_returns', 0);
        $this->assertSame($before, $this->onHand($item->product_id));
    }

    public function test_return_cannot_exceed_issued_quantity(): void
    {
        $this->seedWorkspaceData();
        $warehouse = $this->user('warehouse@indosakti.com');
        $event = $this->newEvent();
        $product = $this->product();
        $mrv = $this->issuedMrv($event, $product, 50);
        $before = $this->onHand($product->id);

        $this->actingAs($warehouse)
            ->post(route('warehouse.returns.store'), [
                'stock_request_id' => $mrv->id,
                'items' => [['product_id' => $product->id, 'quantity_good' => 40, 'quantity_damaged' => 11]],
            ])
            ->assertSessionHasErrors('items');

        $this->assertDatabaseCount('stock_returns', 0);
        $this->assertSame($before, $this->onHand($product->id));
    }

    public function test_return_rejects_products_not_in_the_mrv(): void
    {
        $this->seedWorkspaceData();
        $warehouse = $this->user('warehouse@indosakti.com');
        $event = $this->newEvent();
        $product = $this->product();
        $other = Product::where('id', '!=', $product->id)->firstOrFail();
        $mrv = $this->issuedMrv($event, $product, 20);

        $this->actingAs($warehouse)
            ->post(route('warehouse.returns.store'), [
                'stock_request_id' => $mrv->id,
                'items' => [['product_id' => $other->id, 'quantity_good' => 5, 'quantity_damaged' => 0]],
            ])
            ->assertSessionHasErrors('items');

        $this->assertDatabaseCount('stock_returns', 0);
    }

    public function test_return_adds_good_stock_once_and_cannot_be_repeated(): void
    {
        $this->seedWorkspaceData();
        $warehouse = $this->user('warehouse@indosakti.com');
        $event = $this->newEvent();
        $product = $this->product();
        $mrv = $this->issuedMrv($event, $product, 40);
        $before = $this->onHand($product->id);

        $payload = [
            'stock_request_id' => $mrv->id,
            'items' => [['product_id' => $product->id, 'quantity_good' => 30, 'quantity_damaged' => 10]],
        ];

        $this->actingAs($warehouse)
            ->post(route('warehouse.returns.store'), $payload)
            ->assertRedirect(route('warehouse.returns.index'));

        $this->assertSame($before + 30, $this->onHand($product->id));
        $this->assertSame(MrvStatus::RETURNED, $mrv->fresh()->status);

        $this->actingAs($warehouse)
            ->post(route('warehouse.returns.store'), $payload)
            ->assertSessionHasErrors('stock_request_id');

        $this->assertSame($before + 30, $this->onHand($product->id));
        $this->assertDatabaseCount('stock_returns', 1);
    }

    public function test_mrv_store_prevents_duplicate_pending_for_the_same_event(): void
    {
        $this->seedWorkspaceData();
        $warehouse = $this->user('warehouse@indosakti.com');
        $event = StockRequest::whereIn('status', [MrvStatus::PENDING->value, MrvStatus::APPROVED->value])->firstOrFail()->event;
        $product = $this->product();

        $this->actingAs($warehouse)
            ->post(route('warehouse.mrv.store'), [
                'event_id' => $event->id,
                'items' => [['product_id' => $product->id, 'quantity_requested' => 5]],
            ])
            ->assertSessionHasErrors('event_id');

        $this->assertSame(
            1,
            StockRequest::where('event_id', $event->id)->count(),
        );
    }

    public function test_mrv_store_rejects_duplicate_product_lines(): void
    {
        $this->seedWorkspaceData();
        $warehouse = $this->user('warehouse@indosakti.com');
        $event = $this->newEvent();
        $product = $this->product();

        $this->actingAs($warehouse)
            ->post(route('warehouse.mrv.store'), [
                'event_id' => $event->id,
                'items' => [
                    ['product_id' => $product->id, 'quantity_requested' => 5],
                    ['product_id' => $product->id, 'quantity_requested' => 3],
                ],
            ])
            ->assertSessionHasErrors();

        $this->assertDatabaseCount('stock_requests', 2);
    }

    public function test_issuing_stock_deducts_exactly_once(): void
    {
        $this->seedWorkspaceData();
        $warehouse = $this->user('warehouse@indosakti.com');
        $event = $this->newEvent();
        $product = $this->product();
        $before = $this->onHand($product->id);

        $this->actingAs($warehouse)->post(route('warehouse.mrv.store'), [
            'event_id' => $event->id,
            'items' => [['product_id' => $product->id, 'quantity_requested' => 25]],
        ]);

        $mrv = StockRequest::where('event_id', $event->id)->firstOrFail();

        $this->actingAs($warehouse)->post(route('warehouse.mrv.approve', $mrv))->assertRedirect();
        $this->assertSame($before, $this->onHand($product->id));

        $this->actingAs($warehouse)->post(route('warehouse.mrv.issue', $mrv))->assertRedirect();
        $this->assertSame($before - 25, $this->onHand($product->id));

        $this->actingAs($warehouse)
            ->post(route('warehouse.mrv.issue', $mrv))
            ->assertSessionHasErrors('status');
        $this->assertSame($before - 25, $this->onHand($product->id));
    }

    public function test_product_with_stock_history_cannot_be_deleted(): void
    {
        $this->seedWorkspaceData();
        $warehouse = $this->user('warehouse@indosakti.com');
        $product = Product::whereHas('requestItems')->firstOrFail();

        $this->actingAs($warehouse)
            ->delete(route('warehouse.products.destroy', $product))
            ->assertSessionHasErrors('product');

        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_pic_without_return_permission_cannot_record_a_return(): void
    {
        $this->seedWorkspaceData();
        $pic = $this->user('pic@indosakti.com');
        $event = $this->newEvent();
        $product = $this->product();
        $mrv = $this->issuedMrv($event, $product, 10);

        $this->actingAs($pic)
            ->post(route('warehouse.returns.store'), [
                'stock_request_id' => $mrv->id,
                'items' => [['product_id' => $product->id, 'quantity_good' => 5, 'quantity_damaged' => 0]],
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('stock_returns', 0);
    }

    private function issuedMrv(Event $event, Product $product, int $quantityIssued): StockRequest
    {
        $mrv = StockRequest::create([
            'mrv_number' => StockRequest::generateNumber(),
            'event_id' => $event->id,
            'type' => 'issue',
            'status' => MrvStatus::ISSUED,
            'issued_at' => now(),
        ]);

        $mrv->items()->create([
            'product_id' => $product->id,
            'quantity_requested' => $quantityIssued,
            'quantity_issued' => $quantityIssued,
        ]);

        return $mrv;
    }

    private function newEvent(): Event
    {
        return Event::create([
            'event_code' => 'EVT-WH-'.uniqid(),
            'event_name' => 'Event Uji Gudang',
        ]);
    }

    private function product(): Product
    {
        return Product::where('sku', 'MILO-SACHET')->firstOrFail();
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
