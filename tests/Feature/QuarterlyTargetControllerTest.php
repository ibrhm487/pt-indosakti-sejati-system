<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Enums\ReportStatus;
use App\Enums\StockMovementType;
use App\Models\Brand;
use App\Models\Event;
use App\Models\EventReport;
use App\Models\Product;
use App\Models\QuarterlyTarget;
use App\Models\StockMovement;
use App\Models\StockRequest;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class QuarterlyTargetControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_permitter_can_update_a_brand_target_for_one_quarter(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $permitter = User::where('email', 'permitter@indosakti.com')->firstOrFail();
        $brand = $this->createBrand();

        $response = $this->actingAs($permitter)
            ->from(route('quarterly-targets.index', ['year' => 2026]))
            ->put(route('quarterly-targets.update', $brand), [
                'year' => 2026,
                'quarter' => 2,
                'target_cups' => 8500,
                'target_shifts' => 34,
            ]);

        $response->assertRedirect(route('quarterly-targets.index', ['year' => 2026]));
        $this->assertDatabaseHas('quarterly_targets', [
            'brand_id' => $brand->id,
            'year' => 2026,
            'quarter' => 2,
            'start_month' => 4,
            'target_cups' => 8500,
            'target_shifts' => 34,
        ]);

        $this->actingAs($permitter)
            ->from(route('quarterly-targets.index', ['year' => 2026]))
            ->put(route('quarterly-targets.update', $brand), [
                'year' => 2026,
                'quarter' => 2,
                'target_cups' => 9000,
            ])
            ->assertRedirect();

        $this->assertSame(
            1,
            QuarterlyTarget::query()
                ->where('brand_id', $brand->id)
                ->where('year', 2026)
                ->where('quarter', 2)
                ->count(),
        );
        $this->assertDatabaseHas('quarterly_targets', [
            'brand_id' => $brand->id,
            'year' => 2026,
            'quarter' => 2,
            'target_cups' => 9000,
            'target_shifts' => 34,
        ]);
    }

    public function test_operational_page_shows_active_brands_with_configured_target_and_actuals(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $permitter = User::where('email', 'permitter@indosakti.com')->firstOrFail();
        $brand = $this->createBrand();
        $event = $this->createEvent($brand, $permitter);
        $team = User::where('email', 'team@indosakti.com')->firstOrFail();
        $product = Product::create([
            'name' => 'Test product',
            'sku' => 'TEST-QUARTER-TARGET',
            'unit' => 'cups',
            'is_active' => true,
        ]);

        $this->createReport($event, $team, $permitter, 175, ReportStatus::VERIFIED, '2026-04-10');
        $this->createReport($event, $team, $permitter, 260, ReportStatus::VERIFIED, '2026-06-20');
        $this->createReport($event, $team, $permitter, 999, ReportStatus::SUBMITTED, '2026-06-25');

        $this->travelTo('2026-04-10 12:00:00');
        $firstMrv = $this->createIssuedMrv($brand, 'MRV-QUARTER-TARGET-001');
        $this->createStockOut($firstMrv, $product, 120);
        $this->createStockOut($firstMrv, $product, 5);
        $secondMrv = $this->createIssuedMrv($brand, 'MRV-QUARTER-TARGET-002');
        $this->createStockOut($secondMrv, $product, 135);
        $this->travelBack();

        $response = $this->actingAs($permitter)
            ->get(route('quarterly-targets.index', ['year' => 2026, 'quarter' => 2]));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Targets/Quarterly')
                ->where('filters.year', 2026)
                ->where('filters.quarter', 2)
                ->has('quarters', 4)
                ->has('brands', 1)
                ->where('brands.0.id', $brand->id)
                ->where('brands.0.name', 'Test Brand')
                ->where('brands.0.target_cups', 1000)
                ->where('brands.0.actual_cups', 260)
                ->where('brands.0.target_shifts', 0)
                ->where('brands.0.actual_shifts', 2)
                ->where('brands.0.shift_percentage', 0)
                ->where('brands.0.percentage', fn ($p) => $p == 26.0)
                ->where('brands.0.is_configured', false)
            );

        $this->actingAs($permitter)
            ->put(route('quarterly-targets.update', $brand), [
                'year' => 2026,
                'quarter' => 2,
                'target_cups' => 1300,
                'target_shifts' => 34,
            ]);

        $this->actingAs($permitter)
            ->get(route('quarterly-targets.index', ['year' => 2026, 'quarter' => 2]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('brands.0.target_cups', 1300)
                ->where('brands.0.percentage', fn ($p) => $p == 20.0)
                ->where('brands.0.target_shifts', 34)
                ->where('brands.0.actual_shifts', 2)
                ->where('brands.0.shift_percentage', fn ($p) => $p == 5.9)
                ->where('brands.0.is_configured', true)
            );
    }

    public function test_admin_page_lists_four_quarters_for_the_selected_brand(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::where('email', 'admin@indosakti.com')->firstOrFail();
        $brand = $this->createBrand();

        QuarterlyTarget::create([
            'brand_id' => $brand->id,
            'year' => 2026,
            'quarter' => 3,
            'start_month' => 7,
            'target_cups' => 5400,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.quarterly-targets.index', ['brand' => $brand->id, 'year' => 2026]));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/QuarterlyTargets/Index')
                ->where('brandId', $brand->id)
                ->where('year', 2026)
                ->has('brands', 1)
                ->has('years', 4)
                ->has('targets', 4)
                ->where('targets.0.quarter', 1)
                ->where('targets.0.start_month', 1)
                ->where('targets.0.target_cups', 0)
                ->where('targets.2.quarter', 3)
                ->where('targets.2.start_month', 7)
                ->where('targets.2.target_cups', 5400)
                ->where('targets.2.target_shifts', 0)
                ->where('targets.3.quarter', 4)
            );
    }

    public function test_admin_can_update_all_quarters_for_a_brand_in_one_request(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::where('email', 'admin@indosakti.com')->firstOrFail();
        $brand = $this->createBrand();

        $response = $this->actingAs($admin)
            ->from(route('admin.quarterly-targets.index', ['brand' => $brand->id, 'year' => 2026]))
            ->put(route('admin.quarterly-targets.update'), [
                'brand_id' => $brand->id,
                'year' => 2026,
                'targets' => [
                    ['quarter' => 1, 'start_month' => 1, 'target_cups' => 100, 'target_shifts' => 1],
                    ['quarter' => 2, 'start_month' => 4, 'target_cups' => 200, 'target_shifts' => 2],
                    ['quarter' => 3, 'start_month' => 7, 'target_cups' => 300, 'target_shifts' => 3],
                    ['quarter' => 4, 'start_month' => 10, 'target_cups' => 400, 'target_shifts' => 4],
                ],
            ]);

        $response->assertSessionHas('success');
        $this->assertSame(4, QuarterlyTarget::query()->where('brand_id', $brand->id)->where('year', 2026)->count());
        $this->assertDatabaseHas('quarterly_targets', [
            'brand_id' => $brand->id,
            'year' => 2026,
            'quarter' => 2,
            'start_month' => 4,
            'target_cups' => 200,
            'target_shifts' => 2,
        ]);
    }

    public function test_user_without_target_permission_cannot_open_target_editor(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $team = User::where('email', 'team@indosakti.com')->firstOrFail();

        $this->actingAs($team)
            ->get(route('quarterly-targets.index'))
            ->assertForbidden();
    }

    public function test_target_update_rejects_a_negative_target_without_persisting_it(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::where('email', 'admin@indosakti.com')->firstOrFail();
        $brand = $this->createBrand();

        $this->actingAs($admin)
            ->from(route('admin.quarterly-targets.index', ['brand' => $brand->id, 'year' => 2026]))
            ->put(route('admin.quarterly-targets.update'), [
                'brand_id' => $brand->id,
                'year' => 2026,
                'targets' => [
                    ['quarter' => 1, 'start_month' => 1, 'target_cups' => 100, 'target_shifts' => -1],
                ],
            ])
            ->assertSessionHasErrors('targets.0.target_shifts');

        $this->assertDatabaseMissing('quarterly_targets', [
            'brand_id' => $brand->id,
            'year' => 2026,
            'quarter' => 1,
        ]);
    }

    private function createBrand(): Brand
    {
        return Brand::create([
            'name' => 'Test Brand',
            'slug' => 'test-brand',
            'quarterly_target_cups' => 1000,
            'is_active' => true,
        ]);
    }

    private function createEvent(Brand $brand, User $pic): Event
    {
        return Event::create([
            'event_code' => 'EVT-QUARTER-TARGET',
            'event_name' => 'Quarter target test event',
            'brand_id' => $brand->id,
            'status' => EventStatus::ONGOING,
            'pic_id' => $pic->id,
        ]);
    }

    private function createReport(
        Event $event,
        User $team,
        User $verifier,
        int $actual,
        ReportStatus $status,
        string $verifiedAt,
    ): EventReport {
        return EventReport::create([
            'event_id' => $event->id,
            'sampling_target' => 1000,
            'actual_sampling' => $actual,
            'status' => $status,
            'submitted_by' => $team->id,
            'verified_by' => $verifier->id,
            'verified_at' => $verifiedAt,
        ]);
    }

    private function createIssuedMrv(Brand $brand, string $mrvNumber): StockRequest
    {
        return StockRequest::create([
            'mrv_number' => $mrvNumber,
            'brand_name' => $brand->name,
            'type' => 'issue',
            'status' => 'issued',
            'activity_date' => '2026-04-10',
        ]);
    }

    private function createStockOut(StockRequest $mrv, Product $product, int $quantity): StockMovement
    {
        return StockMovement::create([
            'product_id' => $product->id,
            'type' => StockMovementType::STOCK_OUT,
            'quantity' => $quantity,
            'reference_type' => $mrv->getMorphClass(),
            'reference_id' => $mrv->id,
        ]);
    }
}
