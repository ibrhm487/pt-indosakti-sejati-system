<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Enums\KitchenStatus;
use App\Enums\MrvStatus;
use App\Models\Event;
use App\Models\KitchenRequest;
use App\Models\Product;
use App\Models\StockRequest;
use App\Models\StockReturn;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EventScopingTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------------------
    // Kitchen
    // ------------------------------------------------------------------

    public function test_pic_cannot_open_kitchen_task_of_foreign_event(): void
    {
        $this->seedWorkspaceData();
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();
        $foreignEvent = $this->createForeignEvent();

        $kitchen = $foreignEvent->kitchenRequest()->create([
            'jug_quantity' => 5,
            'status' => KitchenStatus::PREPARING,
        ]);

        $this->actingAs($pic)
            ->get(route('kitchen.show', $kitchen))
            ->assertForbidden();
    }

    public function test_pic_can_open_kitchen_task_of_their_own_event(): void
    {
        $this->seedWorkspaceData();
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();
        $kitchen = KitchenRequest::whereHas('event', fn ($query) => $query->where('pic_id', $pic->id))->firstOrFail();

        $this->actingAs($pic)
            ->get(route('kitchen.show', $kitchen))
            ->assertOk();
    }

    public function test_kitchen_index_only_lists_requests_for_accessible_events(): void
    {
        $this->seedWorkspaceData();
        $team = User::where('email', 'team@indosakti.com')->firstOrFail();

        $ownKitchen = KitchenRequest::whereHas(
            'event.teamMembers',
            fn ($query) => $query->where('user_id', $team->id),
        )->firstOrFail();

        $foreignEvent = $this->createForeignEvent();
        $foreignKitchen = $foreignEvent->kitchenRequest()->create([
            'jug_quantity' => 9,
            'status' => KitchenStatus::PENDING,
        ]);

        $this->actingAs($team)
            ->get(route('kitchen.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Kitchen/Index')
                ->where('requests.data', fn ($data) => collect($data)->pluck('id')->all() === [$ownKitchen->id]));

        $this->assertNotSame($foreignKitchen->id, $ownKitchen->id);
    }

    // ------------------------------------------------------------------
    // MRV
    // ------------------------------------------------------------------

    public function test_pic_cannot_open_foreign_mrv(): void
    {
        $this->seedWorkspaceData();
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();
        $mrv = $this->createForeignMrv($this->createForeignEvent());

        $this->actingAs($pic)
            ->get(route('warehouse.mrv.show', $mrv))
            ->assertNotFound();
    }

    public function test_pic_can_open_own_mrv(): void
    {
        $this->seedWorkspaceData();
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();
        $mrv = StockRequest::whereHas('event', fn ($query) => $query->where('pic_id', $pic->id))->firstOrFail();

        $this->actingAs($pic)
            ->get(route('warehouse.mrv.show', $mrv))
            ->assertOk();
    }

    public function test_pic_cannot_download_foreign_mrv_photo(): void
    {
        $this->seedWorkspaceData();
        Storage::fake('local');
        Storage::disk('local')->put('warehouse/issue/bukti.jpg', 'foto');

        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();
        $mrv = $this->createForeignMrv($this->createForeignEvent(), photoPath: 'warehouse/issue/bukti.jpg');

        $this->actingAs($pic)
            ->get(route('warehouse.mrv.photo', $mrv))
            ->assertNotFound();
    }

    public function test_warehouse_can_open_foreign_mrv_photo(): void
    {
        $this->seedWorkspaceData();
        Storage::fake('local');
        Storage::disk('local')->put('warehouse/issue/bukti.jpg', 'foto');

        $warehouse = User::where('email', 'warehouse@indosakti.com')->firstOrFail();
        $mrv = $this->createForeignMrv($this->createForeignEvent(), photoPath: 'warehouse/issue/bukti.jpg');

        $this->actingAs($warehouse)
            ->get(route('warehouse.mrv.photo', $mrv))
            ->assertOk();
    }

    public function test_mrv_store_rejects_event_linking(): void
    {
        $this->seedWorkspaceData();
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();
        $foreignEvent = $this->createForeignEvent();
        $product = Product::firstOrFail();

        $this->actingAs($pic)
            ->post(route('warehouse.mrv.store'), [
                'event_id' => $foreignEvent->id,
                'activity_type' => 'Sampling',
                'activity_date' => '2026-11-20',
                'jug_quantity' => 1,
                'items' => [
                    ['product_id' => $product->id, 'quantity_requested' => 1],
                ],
            ])
            ->assertSessionHasErrors('event_id');

        $this->assertDatabaseMissing('stock_requests', ['event_id' => $foreignEvent->id]);
    }

    // ------------------------------------------------------------------
    // Stock returns
    // ------------------------------------------------------------------

    public function test_pic_cannot_download_foreign_return_photo(): void
    {
        $this->seedWorkspaceData();
        Storage::fake('local');
        Storage::disk('local')->put('warehouse/returns/bukti.jpg', 'foto');

        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();
        $return = $this->createForeignReturn($this->createForeignEvent(), 'warehouse/returns/bukti.jpg');

        $this->actingAs($pic)
            ->get(route('warehouse.returns.photo', $return))
            ->assertForbidden();
    }

    public function test_warehouse_can_download_foreign_return_photo(): void
    {
        $this->seedWorkspaceData();
        Storage::fake('local');
        Storage::disk('local')->put('warehouse/returns/bukti.jpg', 'foto');

        $warehouse = User::where('email', 'warehouse@indosakti.com')->firstOrFail();
        $return = $this->createForeignReturn($this->createForeignEvent(), 'warehouse/returns/bukti.jpg');

        $this->actingAs($warehouse)
            ->get(route('warehouse.returns.photo', $return))
            ->assertOk();
    }

    public function test_returns_index_is_scoped_to_accessible_events(): void
    {
        $this->seedWorkspaceData();
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();
        $return = $this->createForeignReturn($this->createForeignEvent());

        $this->actingAs($pic)
            ->get(route('warehouse.returns.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Warehouse/Returns/Index')
                ->where('returns.data', fn ($data) => collect($data)->pluck('id')->doesntContain($return->id)));
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function createForeignEvent(): Event
    {
        $otherPic = User::factory()->create();

        return Event::create([
            'event_code' => 'EVT-FOREIGN-'.uniqid(),
            'event_name' => 'Event PIC lain '.uniqid(),
            'status' => EventStatus::PLANNING,
            'pic_id' => $otherPic->id,
            'jug_required' => true,
        ]);
    }

    private function createForeignMrv(Event $event, ?string $photoPath = null): StockRequest
    {
        return StockRequest::create([
            'mrv_number' => StockRequest::generateNumber(),
            'event_id' => $event->id,
            'type' => 'issue',
            'status' => MrvStatus::PENDING,
            'requested_by' => $event->pic_id,
            'photo_path' => $photoPath,
        ]);
    }

    private function createForeignReturn(Event $event, ?string $photoPath = null): StockReturn
    {
        $mrv = $this->createForeignMrv($event);

        return StockReturn::create([
            'return_number' => StockReturn::generateNumber(),
            'stock_request_id' => $mrv->id,
            'event_id' => $event->id,
            'status' => 'pending',
            'recorded_by' => $event->pic_id,
            'photo_path' => $photoPath,
        ]);
    }

    private function seedWorkspaceData(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(DemoDataSeeder::class);
    }
}
