<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Enums\MrvStatus;
use App\Enums\StockMovementType;
use App\Enums\VenueStatus;
use App\Models\Brand;
use App\Models\Event;
use App\Models\EventTeamMember;
use App\Models\Fjp;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\StockRequest;
use App\Models\User;
use App\Models\Venue;
use App\Services\LotStockService;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\MiloDancowProductSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
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

    public function test_mrv_create_does_not_require_events(): void
    {
        $this->seedWorkspaceData();
        $warehouse = $this->user('warehouse@indosakti.com');

        $this->actingAs($warehouse)
            ->get(route('warehouse.mrv.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Warehouse/Mrv/Form')
                ->where('activityDate', today()->toDateString())
                ->has('products')
                ->has('materialCategories')
                ->has('teamMembers', 3));
    }

    public function test_mrv_form_prefills_the_fjp_and_saves_its_school_and_assigned_team_member(): void
    {
        $this->seedWorkspaceData();
        $pic = $this->user('pic@indosakti.com');
        $teamMember = $this->user('spg@indosakti.com');
        $event = $this->createSchoolEvent($pic, $teamMember);

        $form = $this->actingAs($pic)
            ->get(route('warehouse.mrv.create', ['event' => $event->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Warehouse/Mrv/Form')
                ->where('initialEventId', $event->id));
        $eventOption = collect($form->inertiaProps('events'))->firstWhere('id', $event->id);
        $this->assertSame([$teamMember->id], collect($eventOption['members'])->pluck('id')->all());

        $this->actingAs($pic)
            ->post(route('warehouse.mrv.store'), [
                'event_id' => $event->id,
                'activity_type' => 'Other',
                'activity_date' => '2030-07-12',
                'jug_quantity' => 1,
                'team_member_id' => $teamMember->id,
                'items' => [[
                    'product_id' => $this->product()->id,
                    'quantity_requested' => 5,
                ]],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $mrv = StockRequest::query()->where('event_id', $event->id)->firstOrFail();
        $this->assertSame('Sampling', $mrv->activity_type);
        $this->assertSame('2030-07-12', $mrv->activity_date?->toDateString());
        $this->assertSame('MILO', $mrv->brand_name);
        $this->assertSame('SDN Jakarta Uji MRV', $mrv->venue_name);
        $this->assertSame(['SDN Jakarta Uji MRV'], $mrv->school_names);
        $this->assertSame($teamMember->id, $mrv->team_member_id);
        $this->assertSame($teamMember->name, $mrv->team_member_name);
    }

    public function test_bulk_mrv_uses_sampling_and_cup_products_with_student_quantities_and_auto_lots(): void
    {
        $this->seedWorkspaceData();
        $pic = $this->user('pic@indosakti.com');
        $warehouse = $this->user('warehouse@indosakti.com');
        $teamLeader = $this->user('tl@indosakti.com');
        $spg = $this->user('spg@indosakti.com');
        $activityDate = '2030-07-12';
        $firstTeamGroupId = '11111111-1111-4111-8111-111111111111';
        $secondTeamGroupId = '22222222-2222-4222-8222-222222222222';
        $this->createMiloTeamGroup($pic, [$teamLeader, $spg], $firstTeamGroupId, 'Tim A', [100, 80], $activityDate);
        $this->createMiloTeamGroup($pic, [$teamLeader, $spg], $secondTeamGroupId, 'Tim B', [60], $activityDate);

        $samplingProduct = $this->createBulkMrvProduct('MILO 3IN1 ACTIV-GO', '12583797', [
            ['SAMPLE-LOT-A', 200],
            ['SAMPLE-LOT-B', 100],
        ]);
        $supportingProduct = $this->createBulkMrvProduct('MILO CUP 4OZ', '12583798', [
            ['CUP-LOT-A', 150],
            ['CUP-LOT-B', 200],
        ]);

        $bulkPage = $this->actingAs($pic)
            ->get(route('warehouse.mrv.bulk.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Warehouse/Mrv/BulkForm')
                ->has('teamGroups')
                ->where('samplingProduct.sku', '12583797')
                ->where('supportingProduct.sku', '12583798'));
        $teamGroupOptions = collect($bulkPage->inertiaProps('teamGroups'))->keyBy('id');
        $this->assertSame(180, $teamGroupOptions[$firstTeamGroupId]['total_students']);
        $this->assertSame(60, $teamGroupOptions[$secondTeamGroupId]['total_students']);

        $payload = [
            'activity_date' => $activityDate,
            'team_group_ids' => [$firstTeamGroupId, $secondTeamGroupId],
        ];

        $this->actingAs($pic)
            ->post(route('warehouse.mrv.bulk.store'), $payload)
            ->assertRedirect(route('warehouse.mrv.index'))
            ->assertSessionHasNoErrors();

        $firstTeamRequest = StockRequest::where('team_no', $firstTeamGroupId)->firstOrFail();
        $secondTeamRequest = StockRequest::where('team_no', $secondTeamGroupId)->firstOrFail();
        $this->assertSame(
            2,
            StockRequest::where('requested_by', $pic->id)->whereDate('activity_date', $activityDate)->count(),
        );
        $this->assertSame(['SDN Uji Tim A 1', 'SDN Uji Tim A 2'], $firstTeamRequest->school_names);
        $this->assertSame(['SDN Uji Tim B 1'], $secondTeamRequest->school_names);
        $this->assertSame(180, (int) $firstTeamRequest->items()->where('product_id', $samplingProduct->id)->sum('quantity_requested'));
        $this->assertSame(60, (int) $secondTeamRequest->items()->where('product_id', $samplingProduct->id)->sum('quantity_requested'));
        $this->assertSame(180, (int) $firstTeamRequest->items()->where('product_id', $supportingProduct->id)->sum('quantity_requested'));
        $this->assertSame(60, (int) $secondTeamRequest->items()->where('product_id', $supportingProduct->id)->sum('quantity_requested'));
        $this->assertSame(
            [['SAMPLE-LOT-A', 180]],
            $firstTeamRequest->items()->where('product_id', $samplingProduct->id)->orderBy('id')->get()
                ->map(fn ($item) => [$item->lot_no, $item->quantity_requested])->all(),
        );
        $this->assertSame(
            [['SAMPLE-LOT-A', 20], ['SAMPLE-LOT-B', 40]],
            $secondTeamRequest->items()->where('product_id', $samplingProduct->id)->orderBy('id')->get()
                ->map(fn ($item) => [$item->lot_no, $item->quantity_requested])->all(),
        );
        $this->assertSame(
            [['CUP-LOT-A', 150], ['CUP-LOT-B', 30]],
            $firstTeamRequest->items()->where('product_id', $supportingProduct->id)->orderBy('id')->get()
                ->map(fn ($item) => [$item->lot_no, $item->quantity_requested])->all(),
        );
        $this->assertSame(
            [['CUP-LOT-B', 60]],
            $secondTeamRequest->items()->where('product_id', $supportingProduct->id)->orderBy('id')->get()
                ->map(fn ($item) => [$item->lot_no, $item->quantity_requested])->all(),
        );
        $this->assertSame(300, $this->onHand($samplingProduct->id));
        $this->assertSame(350, $this->onHand($supportingProduct->id));

        $this->actingAs($warehouse)->post(route('warehouse.mrv.approve', $firstTeamRequest))->assertRedirect();
        $this->actingAs($warehouse)->post(route('warehouse.mrv.approve', $secondTeamRequest))->assertRedirect();

        $this->assertSame(60, $this->onHand($samplingProduct->id));
        $this->assertSame(110, $this->onHand($supportingProduct->id));
        $this->assertSame(0, app(LotStockService::class)->availableForLot($samplingProduct, 'SAMPLE-LOT-A'));
        $this->assertSame(60, app(LotStockService::class)->availableForLot($samplingProduct, 'SAMPLE-LOT-B'));
        $this->assertSame(0, app(LotStockService::class)->availableForLot($supportingProduct, 'CUP-LOT-A'));
        $this->assertSame(110, app(LotStockService::class)->availableForLot($supportingProduct, 'CUP-LOT-B'));
    }

    public function test_bulk_mrv_uses_expired_lots_after_active_lots_when_active_stock_is_insufficient(): void
    {
        $this->seedWorkspaceData();
        $pic = $this->user('pic@indosakti.com');
        $teamMember = $this->user('spg@indosakti.com');
        $activityDate = '2030-07-12';
        $teamGroupId = '44444444-4444-4444-8444-444444444444';
        $this->createMiloTeamGroup($pic, [$teamMember], $teamGroupId, 'Tim Lot Kedaluwarsa', [100], $activityDate);

        $expiredDate = now()->subYear()->toDateString();
        $samplingProduct = $this->createBulkMrvProduct('MILO 3IN1 ACTIV-GO', '12583797', [
            ['SAMPLE-ACTIVE', 40],
            ['SAMPLE-EXPIRED', 80, $expiredDate],
        ]);
        $supportingProduct = $this->createBulkMrvProduct('MILO CUP 4OZ', '12583798', [
            ['CUP-ACTIVE', 100],
        ]);

        $this->actingAs($pic)
            ->get(route('warehouse.mrv.bulk.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('samplingProduct.active_quantity', 40)
                ->where('samplingProduct.expired_quantity', 80)
                ->where('samplingProduct.lots.0.lot_code', 'SAMPLE-ACTIVE')
                ->where('samplingProduct.lots.0.is_expired', false)
                ->where('samplingProduct.lots.1.lot_code', 'SAMPLE-EXPIRED')
                ->where('samplingProduct.lots.1.is_expired', true));

        $this->actingAs($pic)
            ->post(route('warehouse.mrv.bulk.store'), [
                'activity_date' => $activityDate,
                'team_group_ids' => [$teamGroupId],
            ])
            ->assertRedirect(route('warehouse.mrv.index'))
            ->assertSessionHasNoErrors();

        $request = StockRequest::where('team_no', $teamGroupId)->firstOrFail();

        $this->assertSame(
            [['SAMPLE-ACTIVE', 40], ['SAMPLE-EXPIRED', 60]],
            $request->items()->where('product_id', $samplingProduct->id)->orderBy('id')->get()
                ->map(fn ($item) => [$item->lot_no, (int) $item->quantity_requested])->all(),
        );
        $this->assertSame(
            [['CUP-ACTIVE', 100]],
            $request->items()->where('product_id', $supportingProduct->id)->orderBy('id')->get()
                ->map(fn ($item) => [$item->lot_no, (int) $item->quantity_requested])->all(),
        );
    }

    public function test_bulk_mrv_rejects_if_either_required_product_has_insufficient_valid_lot_stock(): void
    {
        $this->seedWorkspaceData();
        $pic = $this->user('pic@indosakti.com');
        $teamMember = $this->user('spg@indosakti.com');
        $activityDate = '2030-07-12';
        $teamGroupId = '33333333-3333-4333-8333-333333333333';
        $this->createMiloTeamGroup($pic, [$teamMember], $teamGroupId, 'Tim Stok Kurang', [80], $activityDate);

        $samplingProduct = $this->createBulkMrvProduct('MILO 3IN1 ACTIV-GO', '12583797', [
            ['SAMPLE-LOT', 80],
        ]);
        $supportingProduct = $this->createBulkMrvProduct('MILO CUP 4OZ', '12583798', [
            ['CUP-LOT', 79],
        ]);

        $this->actingAs($pic)
            ->from(route('warehouse.mrv.bulk.create'))
            ->post(route('warehouse.mrv.bulk.store'), [
                'activity_date' => $activityDate,
                'team_group_ids' => [$teamGroupId],
            ])
            ->assertRedirect(route('warehouse.mrv.bulk.create'))
            ->assertSessionHasErrors('team_group_ids');

        $this->assertSame(0, StockRequest::where('team_no', $teamGroupId)->count());
        $this->assertSame(80, $this->onHand($samplingProduct->id));
        $this->assertSame(79, $this->onHand($supportingProduct->id));
    }

    public function test_mrv_rejects_a_team_member_not_assigned_to_the_selected_fjp(): void
    {
        $this->seedWorkspaceData();
        $pic = $this->user('pic@indosakti.com');
        $assignedMember = $this->user('spg@indosakti.com');
        $unassignedMember = $this->user('tl@indosakti.com');
        $event = $this->createSchoolEvent($pic, $assignedMember);

        $this->actingAs($pic)
            ->from(route('warehouse.mrv.create', ['event' => $event->id]))
            ->post(route('warehouse.mrv.store'), [
                'event_id' => $event->id,
                'activity_type' => 'Sampling',
                'activity_date' => '2030-07-12',
                'jug_quantity' => 1,
                'team_member_id' => $unassignedMember->id,
                'items' => [[
                    'product_id' => $this->product()->id,
                    'quantity_requested' => 5,
                ]],
            ])
            ->assertRedirect(route('warehouse.mrv.create', ['event' => $event->id]))
            ->assertSessionHasErrors([
                'team_member_id' => 'Anggota tim harus berasal dari tim yang ditugaskan pada FJP ini.',
            ]);

        $this->assertDatabaseMissing('stock_requests', ['event_id' => $event->id]);
    }

    public function test_permitter_can_create_and_view_own_mrv_without_accessing_the_warehouse_catalog(): void
    {
        $this->seedWorkspaceData();
        $permitter = $this->user('permitter@indosakti.com');
        $pic = $this->user('pic@indosakti.com');
        $product = $this->product();

        $this->actingAs($permitter)
            ->get(route('warehouse.mrv.create'))
            ->assertOk();

        $this->actingAs($permitter)
            ->get(route('warehouse.products.index'))
            ->assertForbidden();

        $this->actingAs($permitter)
            ->post(route('warehouse.mrv.store'), [
                'activity_type' => 'Sampling',
                'activity_date' => '2026-11-20',
                'jug_quantity' => 1,
                'items' => [['product_id' => $product->id, 'quantity_requested' => 10]],
            ])
            ->assertRedirect();

        $mrv = StockRequest::where('requested_by', $permitter->id)->latest('id')->firstOrFail();

        $this->actingAs($permitter)
            ->get(route('warehouse.mrv.show', $mrv))
            ->assertOk();

        $this->actingAs($pic)
            ->get(route('warehouse.mrv.show', $mrv))
            ->assertNotFound();

        $this->actingAs($permitter)
            ->post(route('warehouse.mrv.approve', $mrv))
            ->assertForbidden();

        $this->actingAs($pic)
            ->post(route('warehouse.mrv.approve', $mrv))
            ->assertForbidden();
    }

    public function test_mrv_store_requires_activity_type_and_date(): void
    {
        $this->seedWorkspaceData();
        $warehouse = $this->user('warehouse@indosakti.com');
        $product = $this->product();
        $existingRequests = StockRequest::where('requested_by', $warehouse->id)->count();

        $this->actingAs($warehouse)
            ->post(route('warehouse.mrv.store'), [
                'jug_quantity' => 1,
                'items' => [['product_id' => $product->id, 'quantity_requested' => 5]],
            ])
            ->assertSessionHasErrors(['activity_type', 'activity_date']);

        $this->assertSame($existingRequests, StockRequest::where('requested_by', $warehouse->id)->count());
    }

    public function test_milo_activ_go_quantity_is_derived_from_jug_count(): void
    {
        $this->seedWorkspaceData();
        $this->seed(MiloDancowProductSeeder::class);
        $pic = $this->user('pic@indosakti.com');
        $warehouse = $this->user('warehouse@indosakti.com');
        $product = Product::where('sku', 'MILO-ACTIV-GO')->firstOrFail();
        $before = $this->onHand($product->id);

        $this->actingAs($pic)
            ->post(route('warehouse.mrv.store'), [
                'activity_type' => 'Sampling',
                'activity_date' => '2026-11-20',
                'jug_quantity' => 2,
                'items' => [[
                    'product_id' => $product->id,
                    'quantity_requested' => 1,
                    'cups' => 1,
                ]],
            ])
            ->assertRedirect();

        $mrv = StockRequest::where('requested_by', $pic->id)->latest('id')->firstOrFail();
        $item = $mrv->items()->firstOrFail();

        $this->assertSame(500, $item->quantity_requested);
        $this->assertSame('500.00', $item->cups);

        $this->actingAs($warehouse)
            ->post(route('warehouse.mrv.approve', $mrv))
            ->assertRedirect();

        $this->assertSame($before - 500, $this->onHand($product->id));
    }

    public function test_mrv_store_allows_multiple_independent_requests_for_one_activity_date(): void
    {
        $this->seedWorkspaceData();
        $warehouse = $this->user('warehouse@indosakti.com');
        $product = $this->product();
        $payload = [
            'activity_type' => 'Sampling',
            'activity_date' => '2026-11-20',
            'jug_quantity' => 1,
            'items' => [['product_id' => $product->id, 'quantity_requested' => 5]],
        ];

        $this->actingAs($warehouse)
            ->post(route('warehouse.mrv.store'), $payload)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->actingAs($warehouse)
            ->post(route('warehouse.mrv.store'), $payload)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(2, StockRequest::whereNull('event_id')->whereDate('activity_date', '2026-11-20')->count());
    }

    public function test_mrv_store_rejects_duplicate_product_lines(): void
    {
        $this->seedWorkspaceData();
        $warehouse = $this->user('warehouse@indosakti.com');
        $product = $this->product();

        $this->actingAs($warehouse)
            ->post(route('warehouse.mrv.store'), [
                'activity_type' => 'Sampling',
                'activity_date' => '2026-11-20',
                'jug_quantity' => 1,
                'items' => [
                    ['product_id' => $product->id, 'quantity_requested' => 5],
                    ['product_id' => $product->id, 'quantity_requested' => 3],
                ],
            ])
            ->assertSessionHasErrors();

        $this->assertDatabaseCount('stock_requests', 2);
    }

    public function test_mrv_store_persists_item_notes_and_show_exposes_voucher_period(): void
    {
        $this->seedWorkspaceData();
        $warehouse = $this->user('warehouse@indosakti.com');
        $product = $this->product();

        $this->actingAs($warehouse)
            ->post(route('warehouse.mrv.store'), [
                'activity_type' => 'Sampling',
                'activity_date' => '2026-10-14',
                'jug_quantity' => 1,
                'notes' => 'Ambil sebelum pukul 08.00.',
                'items' => [[
                    'product_id' => $product->id,
                    'quantity_requested' => 7,
                    'notes' => 'Untuk booth sekolah.',
                ]],
            ])
            ->assertRedirect();

        $mrv = StockRequest::whereNull('event_id')
            ->where('activity_type', 'Sampling')
            ->whereDate('activity_date', '2026-10-14')
            ->latest('id')
            ->firstOrFail();

        $this->assertDatabaseHas('stock_request_items', [
            'stock_request_id' => $mrv->id,
            'notes' => 'Untuk booth sekolah.',
        ]);

        $this->actingAs($warehouse)
            ->get(route('warehouse.mrv.show', $mrv))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Warehouse/Mrv/Show')
                ->where('mrv.activity_type', 'Sampling')
                ->where('mrv.activity_date', '2026-10-14')
                ->where('mrv.period_month', 'Oktober')
                ->where('mrv.period_label', 'Oktober · Minggu 42')
                ->where('items.0.notes', 'Untuk booth sekolah.'));
    }

    public function test_mrv_store_persists_full_voucher_fields(): void
    {
        $this->seedWorkspaceData();
        $warehouse = $this->user('warehouse@indosakti.com');
        $teamMember = $this->user('spg@indosakti.com');
        $product = Product::where('sku', 'KITKAT-MINI')->firstOrFail();
        $supportingProduct = Product::where('sku', 'MILO-SACHET')->firstOrFail();

        $this->actingAs($warehouse)
            ->post(route('warehouse.mrv.store'), [
                'mrv_number' => 'MRV-MANUAL-2026-014',
                'activity_type' => 'Sampling',
                'activity_date' => '2026-10-14',
                'week_label' => 'Minggu 42 - revisi PIC',
                'jug_quantity' => 1,
                'brand_name' => 'MILO',
                'venue_name' => 'SDN Bantar Gebang V',
                'team_member_id' => $teamMember->id,
                'jug_details' => [
                    ['jug_number' => 'JUG-01', 'temperature' => 4.5],
                    ['jug_number' => 'JUG-02', 'temperature' => 5],
                ],
                'sampling_target_wet' => 410,
                'sampling_target_dry' => 15,
                'team_no' => 'FJP-20261014-001',
                'material_category' => 'wet_sample',
                'material_categories' => ['wet_sample', 'dry_sample'],
                'pickup_time' => '07:30',
                'notes' => 'Ambil dari gudang utama.',
                'material_prepared_by' => 'Petugas Gudang',
                'material_received_by' => 'PIC Sekolah',
                'prepared_by_name' => 'PIC Manual',
                'approved_by_name' => 'Warehouse Manual',
                'wet_received_by' => 'Penerima Cooler',
                'dry_received_by' => 'Tim Sampling',
                'supporting_items' => [
                    [
                        'product_id' => $supportingProduct->id,
                        'item' => 'Ice Gel',
                        'lot_no' => 'ICE-GEL-LOT-001',
                        'qty' => 2,
                        'uom' => 'pcs',
                        'remarks' => 'Cold chain',
                    ],
                    ['item' => '', 'qty' => '', 'uom' => '', 'remarks' => ''],
                ],
                'venue_names' => ['SDN Bantar Gebang V'],
                'items' => [[
                    'product_id' => $product->id,
                    'lot_no' => 'LOT-001',
                    'quantity_requested' => 12,
                    'cold' => 4,
                    'lbs' => 10.5,
                    'packing' => 'box',
                    'on_the_spot' => 2,
                    'd1' => 1,
                    'd2' => 1,
                    'cups' => 50,
                    'spoon' => 50,
                    'notes' => 'Material utama.',
                ]],
            ])
            ->assertRedirect();

        $mrv = StockRequest::whereNull('event_id')
            ->where('activity_type', 'Sampling')
            ->whereDate('activity_date', '2026-10-14')
            ->latest('id')
            ->firstOrFail();

        $this->assertNull($mrv->event_id);
        $this->assertSame('MRV-MANUAL-2026-014', $mrv->mrv_number);
        $this->assertSame('Minggu 42 - revisi PIC', $mrv->week_label);
        $this->assertSame(['SDN Bantar Gebang V'], $mrv->school_names);
        $this->assertSame('FJP-20261014-001', $mrv->team_no);
        $this->assertSame('MILO', $mrv->brand_name);
        $this->assertSame('SDN Bantar Gebang V', $mrv->venue_name);
        $this->assertSame($teamMember->id, $mrv->team_member_id);
        $this->assertSame(2, $mrv->jug_quantity);
        $this->assertSame([
            ['jug_number' => 'JUG-01', 'temperature' => 4.5],
            ['jug_number' => 'JUG-02', 'temperature' => 5],
        ], $mrv->jug_details);
        $this->assertSame(410, $mrv->sampling_target_wet);
        $this->assertSame(15, $mrv->sampling_target_dry);
        $this->assertSame('wet_sample', $mrv->material_category);
        $this->assertSame(['wet_sample', 'dry_sample'], $mrv->material_categories);
        $this->assertSame('07:30', substr((string) $mrv->pickup_time, 0, 5));
        $this->assertCount(1, $mrv->supporting_items);
        $this->assertDatabaseHas('stock_request_items', [
            'stock_request_id' => $mrv->id,
            'product_id' => $supportingProduct->id,
            'lot_no' => 'ICE-GEL-LOT-001',
            'quantity_requested' => 2,
        ]);
        $this->assertDatabaseHas('stock_request_items', [
            'stock_request_id' => $mrv->id,
            'lot_no' => 'LOT-001',
            'packing' => 'box',
        ]);

        $this->actingAs($warehouse)
            ->get(route('warehouse.mrv.show', $mrv))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Warehouse/Mrv/Show')
                ->where('mrv.mrv_number', 'MRV-MANUAL-2026-014')
                ->where('mrv.activity_type', 'Sampling')
                ->where('mrv.jug_quantity', 2)
                ->where('mrv.brand', 'MILO')
                ->where('mrv.venue', 'SDN Bantar Gebang V')
                ->where('mrv.team_member', 'SPG Demo')
                ->where('mrv.jug_details.0.jug_number', 'JUG-01')
                ->where('mrv.sampling_target_wet', 410)
                ->where('mrv.sampling_target_dry', 15)
                ->where('mrv.team_no', 'FJP-20261014-001')
                ->where('mrv.material_category_label', 'Wet Sample, Dry Sample')
                ->where('mrv.pickup_time', '07:30')
                ->where('mrv.material_prepared_by', 'Petugas Gudang')
                ->where('mrv.material_received_by', 'PIC Sekolah')
                ->where('mrv.period_week_label', 'Minggu 42 - revisi PIC')
                ->where('mrv.prepared_by_name', 'PIC Manual')
                ->where('mrv.approved_by_name', 'Warehouse Manual')
                ->where('mrv.wet_received_by', 'Penerima Cooler')
                ->where('mrv.dry_received_by', 'Tim Sampling')
                ->where('mrv.supporting_items.0.item', $supportingProduct->name)
                ->where('items.0.lot_no', 'LOT-001')
                ->where('items.0.cups', '50.00')
                ->where('items.0.spoon', '50.00'));
    }

    public function test_supporting_items_must_reference_a_product_from_the_master_catalog(): void
    {
        $this->seedWorkspaceData();
        $warehouse = $this->user('warehouse@indosakti.com');

        $this->actingAs($warehouse)
            ->from(route('warehouse.mrv.create'))
            ->post(route('warehouse.mrv.store'), [
                'activity_type' => 'Sampling',
                'activity_date' => '2026-11-20',
                'jug_quantity' => 1,
                'supporting_items' => [[
                    'item' => 'Ice Gel',
                    'qty' => 2,
                    'uom' => 'pcs',
                ]],
                'items' => [[
                    'product_id' => $this->product()->id,
                    'quantity_requested' => 1,
                ]],
            ])
            ->assertRedirect(route('warehouse.mrv.create'))
            ->assertSessionHasErrors('supporting_items.0.product_id');

        $this->assertDatabaseMissing('stock_requests', ['requested_by' => $warehouse->id]);
    }

    public function test_supporting_item_stock_is_deducted_when_mrv_is_issued(): void
    {
        $this->seedWorkspaceData();
        $warehouse = $this->user('warehouse@indosakti.com');
        $mainProduct = $this->product();
        $supportingProduct = $this->createBulkMrvProduct('Supporting Ice Gel', 'TEST-SUPPORT-001', [
            ['SUPPORT-LOT-001', 10],
        ]);

        $this->actingAs($warehouse)
            ->post(route('warehouse.mrv.store'), [
                'mrv_number_manual' => false,
                'activity_type' => 'Sampling',
                'activity_date' => '2026-11-20',
                'jug_quantity' => 1,
                'supporting_items' => [[
                    'product_id' => $supportingProduct->id,
                    'lot_no' => 'SUPPORT-LOT-001',
                    'qty' => 3,
                    'uom' => 'pcs',
                    'remarks' => 'Cold chain',
                ]],
                'items' => [[
                    'product_id' => $mainProduct->id,
                    'quantity_requested' => 1,
                ]],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $mrv = StockRequest::where('requested_by', $warehouse->id)->latest('id')->firstOrFail();
        $this->assertSame(2, $mrv->items()->count());
        $this->assertSame(10, $this->onHand($supportingProduct->id));

        $this->actingAs($warehouse)
            ->post(route('warehouse.mrv.approve', $mrv))
            ->assertRedirect();

        $this->assertSame(7, $this->onHand($supportingProduct->id));
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $supportingProduct->id,
            'reference_type' => StockRequest::class,
            'reference_id' => $mrv->id,
            'type' => 'stock_out',
            'quantity' => 3,
            'lot_code' => 'SUPPORT-LOT-001',
        ]);
    }

    public function test_mrv_auto_number_uses_brand_date_and_first_available_suffix(): void
    {
        $this->seedWorkspaceData();
        $warehouse = $this->user('warehouse@indosakti.com');
        $brand = Brand::create([
            'name' => 'Numbering Test',
            'slug' => 'numbering-test',
            'code' => 'TST',
            'is_active' => true,
        ]);
        $product = Product::create([
            'name' => 'Numbering Product',
            'sku' => 'NUMBERING-TEST-001',
            'brand_id' => $brand->id,
            'unit' => 'pcs',
            'is_active' => true,
        ]);

        $payload = [
            'mrv_number_manual' => false,
            'activity_type' => 'Sampling',
            'activity_date' => '2026-11-20',
            'jug_quantity' => 1,
            'items' => [[
                'product_id' => $product->id,
                'quantity_requested' => 1,
            ]],
        ];

        $this->actingAs($warehouse)
            ->post(route('warehouse.mrv.store'), $payload)
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('stock_requests', [
            'requested_by' => $warehouse->id,
            'mrv_number' => 'JKT/2011/TST/01',
        ]);

        StockRequest::create([
            'mrv_number' => 'JKT/2011/TST/03',
            'requested_by' => $warehouse->id,
            'activity_type' => 'Sampling',
            'activity_date' => '2026-11-20',
        ]);

        $this->actingAs($warehouse)
            ->post(route('warehouse.mrv.store'), $payload)
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('stock_requests', [
            'requested_by' => $warehouse->id,
            'mrv_number' => 'JKT/2011/TST/02',
        ]);
    }

    public function test_mrv_manual_sku_resolves_to_master_product_before_stock_issue(): void
    {
        $this->seedWorkspaceData();
        $warehouse = $this->user('warehouse@indosakti.com');
        $selectedProduct = Product::where('sku', 'KITKAT-MINI')->firstOrFail();
        $resolvedProduct = Product::where('sku', 'MILO-SACHET')->firstOrFail();
        $inventory = Inventory::firstOrCreate(
            ['product_id' => $resolvedProduct->id],
            ['qty_on_hand' => 0, 'qty_reserved' => 0],
        );
        $inventory->update(['qty_on_hand' => 500, 'qty_reserved' => 0]);

        $this->actingAs($warehouse)
            ->post(route('warehouse.mrv.store'), [
                'mrv_number' => 'MRV-SKU-MAPPING-001',
                'activity_type' => 'Sampling',
                'activity_date' => '2026-11-20',
                'week_label' => 'Week 47',
                'jug_quantity' => 1,
                'team_member_name' => 'Sampler Manual',
                'items' => [[
                    'product_id' => $selectedProduct->id,
                    'sku' => $resolvedProduct->sku,
                    'quantity_requested' => 5,
                ]],
            ])
            ->assertRedirect();

        $mrv = StockRequest::where('mrv_number', 'MRV-SKU-MAPPING-001')->firstOrFail();
        $this->assertSame('Sampler Manual', $mrv->team_member_name);
        $this->assertNull($mrv->team_member_id);
        $this->assertSame($resolvedProduct->id, $mrv->items()->firstOrFail()->product_id);
        $this->assertSame(250, $mrv->items()->firstOrFail()->quantity_requested);

        $this->actingAs($warehouse)
            ->get(route('warehouse.mrv.show', $mrv))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Warehouse/Mrv/Show')
                ->where('mrv.team_member', 'Sampler Manual'));

        $this->actingAs($warehouse)
            ->post(route('warehouse.mrv.approve', $mrv))
            ->assertRedirect();

        $this->assertSame(250, $inventory->fresh()->qty_on_hand);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $resolvedProduct->id,
            'reference_type' => StockRequest::class,
            'reference_id' => $mrv->id,
            'type' => 'stock_out',
            'quantity' => 250,
        ]);
    }

    public function test_mrv_number_must_be_unique(): void
    {
        $this->seedWorkspaceData();
        $warehouse = $this->user('warehouse@indosakti.com');
        $existingMrv = StockRequest::firstOrFail();
        $product = Product::where('sku', 'KITKAT-MINI')->firstOrFail();

        $this->actingAs($warehouse)
            ->post(route('warehouse.mrv.store'), [
                'mrv_number' => $existingMrv->mrv_number,
                'activity_type' => 'Sampling',
                'activity_date' => '2026-11-20',
                'jug_quantity' => 1,
                'items' => [[
                    'product_id' => $product->id,
                    'quantity_requested' => 5,
                ]],
            ])
            ->assertSessionHasErrors('mrv_number');
    }

    public function test_mrv_rejects_a_sku_outside_the_active_product_master(): void
    {
        $this->seedWorkspaceData();
        $warehouse = $this->user('warehouse@indosakti.com');
        $product = Product::where('sku', 'KITKAT-MINI')->firstOrFail();

        $this->actingAs($warehouse)
            ->post(route('warehouse.mrv.store'), [
                'mrv_number' => 'MRV-UNKNOWN-SKU-001',
                'activity_type' => 'Sampling',
                'activity_date' => '2026-11-20',
                'jug_quantity' => 1,
                'items' => [[
                    'product_id' => $product->id,
                    'sku' => 'NOT-IN-MASTER',
                    'quantity_requested' => 5,
                ]],
            ])
            ->assertSessionHasErrors('items.0.sku');

        $this->assertDatabaseMissing('stock_requests', [
            'mrv_number' => 'MRV-UNKNOWN-SKU-001',
        ]);
    }

    public function test_mrv_team_member_must_have_the_team_role(): void
    {
        $this->seedWorkspaceData();
        $pic = $this->user('pic@indosakti.com');
        $product = Product::where('sku', 'KITKAT-MINI')->firstOrFail();
        $existingRequests = StockRequest::where('requested_by', $pic->id)->count();

        $this->actingAs($pic)
            ->post(route('warehouse.mrv.store'), [
                'activity_type' => 'Sampling',
                'activity_date' => '2026-11-20',
                'jug_quantity' => 1,
                'team_member_id' => $pic->id,
                'items' => [[
                    'product_id' => $product->id,
                    'quantity_requested' => 5,
                ]],
            ])
            ->assertSessionHasErrors('team_member_id');

        $this->assertSame(
            $existingRequests,
            StockRequest::where('requested_by', $pic->id)->count(),
        );
    }

    public function test_mrv_team_member_must_be_active(): void
    {
        $this->seedWorkspaceData();
        $pic = $this->user('pic@indosakti.com');
        $inactiveTeamMember = $this->user('spg@indosakti.com');
        $inactiveTeamMember->update(['is_active' => false]);
        $product = Product::where('sku', 'KITKAT-MINI')->firstOrFail();

        $this->actingAs($pic)
            ->post(route('warehouse.mrv.store'), [
                'activity_type' => 'Sampling',
                'activity_date' => '2026-11-20',
                'jug_quantity' => 1,
                'team_member_id' => $inactiveTeamMember->id,
                'items' => [[
                    'product_id' => $product->id,
                    'quantity_requested' => 5,
                ]],
            ])
            ->assertSessionHasErrors('team_member_id');

        $this->assertDatabaseMissing('stock_requests', [
            'requested_by' => $pic->id,
            'team_member_id' => $inactiveTeamMember->id,
        ]);
    }

    public function test_approving_mrv_deducts_stock_exactly_once(): void
    {
        $this->seedWorkspaceData();
        $warehouse = $this->user('warehouse@indosakti.com');
        $product = Product::where('sku', 'KITKAT-MINI')->firstOrFail();
        $before = $this->onHand($product->id);

        $this->actingAs($warehouse)->post(route('warehouse.mrv.store'), [
            'activity_type' => 'Sampling',
            'activity_date' => '2026-11-20',
            'jug_quantity' => 1,
            'items' => [['product_id' => $product->id, 'quantity_requested' => 25]],
        ]);

        $mrv = StockRequest::whereNull('event_id')
            ->where('requested_by', $warehouse->id)
            ->latest('id')
            ->firstOrFail();

        $this->actingAs($warehouse)->post(route('warehouse.mrv.approve', $mrv))->assertRedirect();
        $this->assertSame($before - 25, $this->onHand($product->id));
        $this->assertSame(MrvStatus::ISSUED, $mrv->fresh()->status);
        $this->assertSame(25, $mrv->fresh()->items->first()->quantity_issued);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => 'stock_out',
            'quantity' => 25,
            'reference_id' => $mrv->id,
        ]);

        $this->actingAs($warehouse)->post(route('warehouse.mrv.issue', $mrv))->assertRedirect();
        $this->assertSame($before - 25, $this->onHand($product->id));

        $this->actingAs($warehouse)
            ->post(route('warehouse.mrv.approve', $mrv))
            ->assertSessionHasErrors('status');
        $this->assertSame($before - 25, $this->onHand($product->id));
        $this->assertSame(1, StockMovement::where('reference_id', $mrv->id)->where('type', 'stock_out')->count());
    }

    public function test_approval_with_insufficient_stock_keeps_all_items_and_movements_unchanged(): void
    {
        $this->seedWorkspaceData();
        $warehouse = $this->user('warehouse@indosakti.com');
        $availableProduct = $this->product();
        $unavailableProduct = Product::where('sku', 'KITKAT-MINI')->firstOrFail();
        $unavailableProduct->inventory()->update(['qty_on_hand' => 0]);
        $availableBefore = $this->onHand($availableProduct->id);
        $movementsBefore = StockMovement::count();

        $this->actingAs($warehouse)->post(route('warehouse.mrv.store'), [
            'activity_type' => 'Sampling',
            'activity_date' => '2026-11-20',
            'jug_quantity' => 1,
            'items' => [
                ['product_id' => $availableProduct->id, 'quantity_requested' => 10],
                ['product_id' => $unavailableProduct->id, 'quantity_requested' => 1],
            ],
        ])->assertRedirect();

        $mrv = StockRequest::where('requested_by', $warehouse->id)->latest('id')->firstOrFail();

        $this->actingAs($warehouse)
            ->post(route('warehouse.mrv.approve', $mrv))
            ->assertSessionHasErrors('stock');

        $this->assertSame(MrvStatus::PENDING, $mrv->fresh()->status);
        $this->assertSame($availableBefore, $this->onHand($availableProduct->id));
        $this->assertSame(0, $this->onHand($unavailableProduct->id));
        $this->assertSame($movementsBefore, StockMovement::count());
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

    /**
     * @param  array<int, User>  $members
     * @param  array<int, int>  $studentCounts
     */
    private function createMiloTeamGroup(
        User $pic,
        array $members,
        string $teamGroupId,
        string $teamLabel,
        array $studentCounts,
        string $activityDate,
    ): void {
        $brand = Brand::where('slug', 'milo')->firstOrFail();

        foreach ($studentCounts as $index => $studentCount) {
            $schoolNumber = $index + 1;
            $schoolName = 'SDN Uji '.$teamLabel.' '.$schoolNumber;
            $venue = Venue::create([
                'name' => $schoolName,
                'is_school' => true,
                'city' => 'Jakarta Selatan',
                'province' => 'DKI Jakarta',
                'status' => VenueStatus::APPROVED,
            ]);
            $fjp = Fjp::create([
                'fjp_number' => Fjp::generateNumber(),
                'event_name' => $schoolName,
                'brand_id' => $brand->id,
                'venue_id' => $venue->id,
                'school_name' => $schoolName,
                'event_date' => $activityDate,
                'jumlah_siswa_sekolah' => $studentCount,
                'status' => Fjp::STATUS_CONFIRM,
            ]);
            $event = Event::create([
                'event_code' => 'EVT-MRV-BULK-'.$teamLabel.'-'.$schoolNumber,
                'event_name' => 'MILO School Sampling - '.$schoolName,
                'fjp_id' => $fjp->id,
                'brand_id' => $brand->id,
                'venue_id' => $venue->id,
                'event_date' => $activityDate,
                'status' => EventStatus::TEAM_ASSIGNMENT,
                'pic_id' => $pic->id,
                'team_group_id' => $teamGroupId,
            ]);

            foreach ($members as $memberIndex => $member) {
                EventTeamMember::create([
                    'event_id' => $event->id,
                    'user_id' => $member->id,
                    'position' => $memberIndex === 0 ? 'Team Leader' : 'SPG',
                    'status' => 'assigned',
                    'assigned_by' => $pic->id,
                ]);
            }
        }
    }

    private function createSchoolEvent(User $pic, User $teamMember): Event
    {
        $brand = Brand::where('slug', 'milo')->firstOrFail();
        $venue = Venue::create([
            'name' => 'SDN Jakarta Uji MRV',
            'npsn' => '20999201',
            'is_school' => true,
            'city' => 'Jakarta Selatan',
            'province' => 'DKI Jakarta',
            'status' => VenueStatus::APPROVED,
        ]);
        $fjp = Fjp::create([
            'fjp_number' => Fjp::generateNumber(),
            'event_name' => 'SDN Jakarta Uji MRV',
            'brand_id' => $brand->id,
            'venue_id' => $venue->id,
            'school_name' => $venue->name,
            'event_date' => '2030-07-12',
            'status' => Fjp::STATUS_CONFIRM,
        ]);
        $event = Event::create([
            'event_code' => 'EVT-MRV-'.uniqid(),
            'event_name' => 'MILO School Sampling - '.$venue->name,
            'fjp_id' => $fjp->id,
            'brand_id' => $brand->id,
            'venue_id' => $venue->id,
            'event_date' => $fjp->event_date,
            'pic_id' => $pic->id,
        ]);

        EventTeamMember::create([
            'event_id' => $event->id,
            'user_id' => $teamMember->id,
            'position' => 'SPG',
            'status' => 'assigned',
            'assigned_by' => $pic->id,
        ]);

        return $event;
    }

    private function product(): Product
    {
        return Product::where('sku', 'MILO-SACHET')->firstOrFail();
    }

    /**
     * @param  list<array{0: string, 1: int}>  $lots
     */
    private function createBulkMrvProduct(string $name, string $sku, array $lots): Product
    {
        $product = Product::create([
            'name' => $name,
            'sku' => $sku,
            'brand_id' => Brand::where('slug', 'milo')->value('id'),
            'unit' => 'PCS',
            'minimum_stock' => 0,
            'is_active' => true,
        ]);
        $balance = 0;

        foreach ($lots as $lot) {
            [$lotCode, $quantity] = $lot;
            $expiredDate = $lot[2] ?? '2031-12-31';
            $balance += $quantity;
            StockMovement::create([
                'product_id' => $product->id,
                'type' => StockMovementType::STOCK_IN,
                'quantity' => $quantity,
                'balance_after' => $balance,
                'lot_code' => $lotCode,
                'expired_date' => $expiredDate,
            ]);
        }

        Inventory::create([
            'product_id' => $product->id,
            'qty_on_hand' => $balance,
            'qty_reserved' => 0,
        ]);

        return $product;
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
