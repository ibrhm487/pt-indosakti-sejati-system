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
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_dashboard_scopes_event_and_report_stats_to_their_own_events(): void
    {
        $this->seedWorkspaceData();
        $team = User::where('email', 'team@indosakti.com')->firstOrFail();
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();

        // An event the team is not assigned to, carrying a submitted report so
        // global counters would differ from scoped ones.
        $foreignEvent = $this->createEvent($pic, 'EVT-DASH-TEAM-FOREIGN');
        EventReport::create([
            'event_id' => $foreignEvent->id,
            'sampling_target' => 500,
            'actual_sampling' => 100,
            'status' => ReportStatus::SUBMITTED,
            'submitted_by' => $pic->id,
            'submitted_at' => now(),
        ]);

        $teamEvents = Event::whereHas('teamMembers', fn ($q) => $q->where('user_id', $team->id));
        $expectedEvents = (clone $teamEvents)->count();
        $expectedPending = EventReport::query()
            ->whereIn('event_id', (clone $teamEvents)->select('id'))
            ->where('status', ReportStatus::SUBMITTED)
            ->count();

        $response = $this->actingAs($team)->get(route('dashboard'));

        $response->assertOk();
        $stats = $response->inertiaProps('stats');

        $this->assertSame($expectedEvents, $stats['total_events']);
        $this->assertLessThan(Event::count(), $stats['total_events'], 'The team scope must be narrower than the company-wide event count.');
        $this->assertSame($expectedPending, $stats['pending_reports']);
        $this->assertLessThan(
            EventReport::where('status', ReportStatus::SUBMITTED)->count(),
            $stats['pending_reports'],
            'The team must not see company-wide report counters.',
        );

        // Cross-module counters outside the team's permissions must not leak.
        $this->assertArrayNotHasKey('mrv_pending', $stats);
        $this->assertArrayNotHasKey('kitchen_pending', $stats);
        $this->assertArrayNotHasKey('pending_permits', $stats);

        $this->assertSame([], $response->inertiaProps('schoolComparison'));
    }

    public function test_pic_dashboard_scopes_report_counters_to_own_events(): void
    {
        $this->seedWorkspaceData();
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();
        $otherPic = User::factory()->create();

        // A submitted report belonging to another PIC's event.
        $foreignEvent = $this->createEvent($otherPic, 'EVT-DASH-PIC-FOREIGN');
        EventReport::create([
            'event_id' => $foreignEvent->id,
            'sampling_target' => 500,
            'actual_sampling' => 100,
            'status' => ReportStatus::SUBMITTED,
            'submitted_by' => $otherPic->id,
            'submitted_at' => now(),
        ]);

        $expectedPending = EventReport::query()
            ->whereIn('event_id', Event::where('pic_id', $pic->id)->select('id'))
            ->where('status', ReportStatus::SUBMITTED)
            ->count();

        $response = $this->actingAs($pic)->get(route('dashboard'));

        $response->assertOk();
        $stats = $response->inertiaProps('stats');

        $this->assertSame($expectedPending, $stats['pending_reports']);
        $this->assertLessThan(
            EventReport::where('status', ReportStatus::SUBMITTED)->count(),
            $stats['pending_reports'],
        );

        // PIC keeps the operational cross-module counters (§6: MRV waiting, etc.).
        $this->assertArrayHasKey('mrv_pending', $stats);
    }

    public function test_school_comparison_is_visible_to_roles_with_fjp_view_permission(): void
    {
        $this->seedWorkspaceData();

        $adminEvent = User::where('email', 'admin.event@indosakti.com')->firstOrFail();
        $adminEventResponse = $this->actingAs($adminEvent)->get(route('dashboard'));
        $adminEventResponse->assertOk();
        $this->assertNotSame([], $adminEventResponse->inertiaProps('schoolComparison'));

        // Stop the acting-as session before switching users.
        $this->app['auth']->forgetGuards();

        $permitter = User::where('email', 'permitter@indosakti.com')->firstOrFail();
        $permitterResponse = $this->actingAs($permitter)->get(route('dashboard'));
        $permitterResponse->assertOk();
        $this->assertNotSame([], $permitterResponse->inertiaProps('schoolComparison'));
    }

    public function test_quarterly_actual_uses_issued_mrv_movements_instead_of_event_reports(): void
    {
        $this->seedWorkspaceData();
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();
        $brand = Brand::create([
            'name' => 'Brand Uji Dashboard '.uniqid(),
            'slug' => 'brand-uji-dashboard-'.uniqid(),
            'quarterly_target_cups' => 1000,
            'is_active' => true,
        ]);
        $event = $this->createEvent($pic, 'EVT-DASH-QUARTERLY');
        $event->update(['brand_id' => $brand->id]);

        // Event reports are intentionally different from warehouse issues.
        EventReport::create([
            'event_id' => $event->id,
            'sampling_target' => 200,
            'actual_sampling' => 100,
            'status' => ReportStatus::VERIFIED,
            'submitted_by' => $pic->id,
            'submitted_at' => now(),
            'verified_by' => $pic->id,
            'verified_at' => now(),
        ]);
        QuarterlyTarget::create([
            'brand_id' => $brand->id,
            'year' => now()->year,
            'quarter' => (int) ceil(now()->month / 3),
            'start_month' => ((int) ceil(now()->month / 3) - 1) * 3 + 1,
            'target_cups' => 1000,
            'target_shifts' => 34,
        ]);
        $product = Product::create([
            'name' => 'Product Dashboard Target',
            'sku' => 'PRODUCT-DASHBOARD-TARGET',
            'unit' => 'cups',
            'is_active' => true,
        ]);
        $mrv = StockRequest::create([
            'mrv_number' => 'MRV-DASHBOARD-TARGET',
            'brand_name' => $brand->name,
            'type' => 'issue',
            'status' => 'issued',
        ]);
        StockMovement::create([
            'product_id' => $product->id,
            'type' => StockMovementType::STOCK_OUT,
            'quantity' => 100,
            'reference_type' => $mrv->getMorphClass(),
            'reference_id' => $mrv->id,
        ]);
        StockMovement::create([
            'product_id' => $product->id,
            'type' => StockMovementType::STOCK_OUT,
            'quantity' => 50,
            'reference_type' => $mrv->getMorphClass(),
            'reference_id' => $mrv->id,
        ]);
        EventReport::create([
            'event_id' => $event->id,
            'sampling_target' => 200,
            'actual_sampling' => 150,
            'status' => ReportStatus::VERIFIED,
            'submitted_by' => $pic->id,
            'submitted_at' => now(),
            'verified_by' => $pic->id,
            'verified_at' => now(),
        ]);

        $admin = User::where('email', 'admin@indosakti.com')->firstOrFail();
        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
        $row = collect($response->inertiaProps('quarterly'))
            ->firstWhere('brand', $brand->name);

        $this->assertNotNull($row, 'The test brand must appear in the quarterly summary.');
        $this->assertSame(150, $row['actual'], 'Only stock quantities issued through the MRV may count.');
        $this->assertSame(1000, $row['target']);
        $this->assertSame(1, $row['actual_shifts']);
        $this->assertSame(34, $row['target_shifts']);
        $this->assertSame(2.9, $row['shift_percentage']);
    }

    private function seedWorkspaceData(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(DemoDataSeeder::class);
    }

    private function createEvent(User $pic, string $eventCode): Event
    {
        return Event::create([
            'event_code' => $eventCode,
            'event_name' => 'Event Uji Dashboard',
            'status' => EventStatus::READY,
            'pic_id' => $pic->id,
            'jug_required' => false,
        ]);
    }
}
