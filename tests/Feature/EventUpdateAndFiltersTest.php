<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Enums\ReportStatus;
use App\Models\Brand;
use App\Models\Event;
use App\Models\EventReport;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EventUpdateAndFiltersTest extends TestCase
{
    use RefreshDatabase;

    public function test_pic_can_open_the_edit_page_for_their_own_event(): void
    {
        $this->seedWorkspaceData();
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();
        $event = Event::where('pic_id', $pic->id)->firstOrFail();

        $this->actingAs($pic)
            ->get(route('events.edit', $event))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Events/Edit')
                ->where('event.id', $event->id)
                ->has('brands')
                ->has('venues')
                ->has('pics'));
    }

    public function test_pic_can_update_an_event_and_it_is_audited(): void
    {
        $this->seedWorkspaceData();
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();
        $event = Event::where('pic_id', $pic->id)->firstOrFail();

        $this->actingAs($pic)
            ->put(route('events.update', $event), [
                'event_name' => 'Nama Event Diperbarui',
                'brand_id' => $event->brand_id,
                'event_type_id' => $event->event_type_id,
                'venue_id' => $event->venue_id,
                'event_date' => $event->event_date?->toDateString(),
                'start_time' => $event->start_time,
                'end_time' => $event->end_time,
                'sampling_target' => 321,
                'notes' => 'Catatan uji',
                'jug_required' => (bool) $event->jug_required,
                'pic_id' => $pic->id,
            ])
            ->assertRedirect(route('events.show', $event));

        $event->refresh();
        $this->assertSame('Nama Event Diperbarui', $event->event_name);
        $this->assertSame(321, $event->sampling_target);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'UPDATE_EVENT',
            'record_type' => Event::class,
            'record_id' => $event->id,
        ]);
    }

    public function test_user_without_events_update_permission_cannot_edit_or_update(): void
    {
        $this->seedWorkspaceData();
        $team = User::where('email', 'team@indosakti.com')->firstOrFail();
        $event = Event::whereHas('teamMembers', fn ($q) => $q->where('user_id', $team->id))->firstOrFail();

        $this->actingAs($team)
            ->get(route('events.edit', $event))
            ->assertForbidden();

        $this->actingAs($team)
            ->put(route('events.update', $event), ['event_name' => 'Tidak Boleh'])
            ->assertForbidden();
    }

    public function test_terminal_event_cannot_be_updated(): void
    {
        $this->seedWorkspaceData();
        $admin = User::where('email', 'admin@indosakti.com')->firstOrFail();
        $event = Event::firstOrFail();
        $event->update(['status' => EventStatus::COMPLETED]);
        $originalName = $event->event_name;

        $this->actingAs($admin)
            ->from(route('events.edit', $event))
            ->put(route('events.update', $event), ['event_name' => 'Diubah'])
            ->assertRedirect(route('events.edit', $event))
            ->assertSessionHasErrors('event_name');

        $this->assertSame($originalName, $event->fresh()->event_name);
    }

    public function test_index_exposes_calendar_events_for_the_requested_month(): void
    {
        $this->seedWorkspaceData();
        $admin = User::where('email', 'admin@indosakti.com')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('events.index', ['month' => now()->format('Y-m')]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Events/Index')
                ->where('month', now()->format('Y-m'))
                ->has('calendarEvents'));
    }

    public function test_report_index_filters_by_event_date_range(): void
    {
        $this->seedWorkspaceData();
        $admin = User::where('email', 'admin@indosakti.com')->firstOrFail();
        $brand = Brand::first();

        $inside = $this->makeEventWithReport($admin, $brand, now()->subDays(3), ReportStatus::SUBMITTED);
        $outside = $this->makeEventWithReport($admin, $brand, now()->subDays(40), ReportStatus::SUBMITTED);

        $response = $this->actingAs($admin)->get(route('reports.index', [
            'date_from' => now()->subDays(7)->toDateString(),
            'date_to' => now()->toDateString(),
        ]));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Reports/Index')
            ->where('filters.date_from', now()->subDays(7)->toDateString())
            ->where('filters.date_to', now()->toDateString()));

        $ids = collect($response->viewData('page')['props']['reports']['data'])->pluck('id');
        $this->assertTrue($ids->contains($inside->id));
        $this->assertFalse($ids->contains($outside->id));
    }

    private function seedWorkspaceData(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(DemoDataSeeder::class);
    }

    private function makeEventWithReport(User $user, ?Brand $brand, \DateTimeInterface $date, ReportStatus $status): EventReport
    {
        $event = Event::create([
            'event_code' => Event::generateCode(),
            'event_name' => 'Event Filter '.uniqid(),
            'brand_id' => $brand?->id,
            'event_date' => $date,
            'status' => EventStatus::COMPLETED,
            'created_by' => $user->id,
        ]);

        return EventReport::create([
            'event_id' => $event->id,
            'sampling_target' => 100,
            'actual_sampling' => 90,
            'achievement_percentage' => 90,
            'status' => $status,
            'submitted_by' => $user->id,
            'submitted_at' => now(),
        ]);
    }
}
