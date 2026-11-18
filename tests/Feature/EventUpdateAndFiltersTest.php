<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Enums\PermitStatus;
use App\Enums\ReportStatus;
use App\Models\Brand;
use App\Models\Event;
use App\Models\EventReport;
use App\Models\Fjp;
use App\Models\User;
use App\Models\Venue;
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
                ->has('confirmedFjps')
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

    public function test_pic_can_delete_their_own_event_without_operational_data(): void
    {
        $this->seedWorkspaceData();
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();

        $event = Event::create([
            'event_code' => Event::generateCode(),
            'event_name' => 'Event Simulasi Hapus',
            'event_date' => now()->addDays(5)->toDateString(),
            'status' => EventStatus::TEAM_ASSIGNMENT,
            'pic_id' => $pic->id,
            'created_by' => $pic->id,
        ]);

        $this->actingAs($pic)
            ->delete(route('events.destroy', $event))
            ->assertRedirect(route('events.index'));

        $this->assertSoftDeleted($event);
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

    public function test_event_with_an_unconfirmed_fjp_can_still_be_updated(): void
    {
        $this->seedWorkspaceData();
        $admin = User::where('email', 'admin@indosakti.com')->firstOrFail();
        $event = Event::whereNotNull('fjp_id')->firstOrFail();
        $event->fjp->update(['status' => 'plan']);

        $this->actingAs($admin)
            ->put(route('events.update', $event), [
                'event_name' => $event->event_name,
                'fjp_id' => $event->fjp_id,
                'brand_id' => $event->brand_id,
                'event_type_id' => $event->event_type_id,
                'venue_id' => $event->venue_id,
                'event_date' => $event->event_date?->toDateString(),
                'start_time' => $event->start_time,
                'end_time' => $event->end_time,
                'sampling_target' => $event->sampling_target,
                'notes' => 'Catatan event lama',
                'jug_required' => (bool) $event->jug_required,
                'pic_id' => $event->pic_id,
            ])
            ->assertRedirect(route('events.show', $event));

        $this->assertSame('Catatan event lama', $event->fresh()->notes);
    }

    public function test_event_cannot_switch_to_an_unconfirmed_fjp(): void
    {
        $this->seedWorkspaceData();
        $admin = User::where('email', 'admin@indosakti.com')->firstOrFail();
        $event = Event::whereNotNull('fjp_id')->firstOrFail();
        $otherFjp = Fjp::whereKeyNot($event->fjp_id)->firstOrFail();
        $otherFjp->update(['status' => 'plan']);

        $this->actingAs($admin)
            ->from(route('events.edit', $event))
            ->put(route('events.update', $event), [
                'event_name' => $event->event_name,
                'fjp_id' => $otherFjp->id,
            ])
            ->assertRedirect(route('events.edit', $event))
            ->assertSessionHasErrors('fjp_id');
    }

    public function test_event_form_lists_only_venues_with_a_confirmed_fjp(): void
    {
        $this->seedWorkspaceData();
        $admin = User::where('email', 'admin@indosakti.com')->firstOrFail();

        $confirmedVenue = $this->makeVenue('Lokasi Confirm');
        $plannedVenue = $this->makeVenue('Lokasi Plan');
        $this->makeFjp($confirmedVenue, Fjp::STATUS_CONFIRM);
        $this->makeFjp($plannedVenue, 'plan');

        $response = $this->actingAs($admin)->get(route('events.create'));

        $venues = collect($response->viewData('page')['props']['venues']);
        $this->assertTrue($venues->contains('id', $confirmedVenue->id));
        $this->assertFalse($venues->contains('id', $plannedVenue->id));

        $confirmedFjps = collect($response->viewData('page')['props']['confirmedFjps']);
        $this->assertTrue($confirmedFjps->contains('venue_id', $confirmedVenue->id));
        $this->assertFalse($confirmedFjps->contains('venue_id', $plannedVenue->id));
    }

    public function test_event_can_be_scheduled_from_an_approved_fjp_without_a_venue(): void
    {
        $this->seedWorkspaceData();
        $admin = User::where('email', 'admin@indosakti.com')->firstOrFail();
        $source = Event::firstOrFail();
        $venue = $this->makeVenue('Venue FJP Disetujui');
        $fjp = Fjp::create([
            'fjp_number' => Fjp::generateNumber(),
            'event_name' => 'FJP Disetujui Tanpa Venue',
            'brand_id' => $source->brand_id,
            'event_type_id' => $source->event_type_id,
            'event_date' => now()->addDays(14)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '15:00',
            'sampling_target' => 150,
            'permit_status' => PermitStatus::APPROVED,
            'status' => Fjp::STATUS_CONFIRM,
        ]);

        $response = $this->actingAs($admin)->get(route('events.create'));
        $fjps = collect($response->viewData('page')['props']['confirmedFjps']);

        $this->assertTrue($fjps->contains('id', $fjp->id));

        $this->actingAs($admin)
            ->post(route('events.store'), [
                'fjp_id' => $fjp->id,
                'venue_id' => $venue->id,
            ])
            ->assertRedirect();

        $event = Event::where('fjp_id', $fjp->id)->firstOrFail();
        $this->assertSame($venue->id, $event->venue_id);
        $this->assertSame($venue->id, $fjp->fresh()->venue_id);
    }

    public function test_event_edit_keeps_the_current_venue_when_its_fjp_is_not_confirmed(): void
    {
        $this->seedWorkspaceData();
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();
        $event = Event::where('pic_id', $pic->id)->firstOrFail();
        $venue = $this->makeVenue('Lokasi Lama');
        $event->update(['venue_id' => $venue->id]);
        $this->makeFjp($venue, 'plan');

        $response = $this->actingAs($pic)->get(route('events.edit', $event));

        $venues = collect($response->viewData('page')['props']['venues']);
        $this->assertTrue($venues->contains('id', $venue->id));
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

    public function test_index_exposes_events_nested_by_brand_event_type_and_date(): void
    {
        $this->seedWorkspaceData();
        $admin = User::where('email', 'admin@indosakti.com')->firstOrFail();
        $event = Event::whereNotNull('brand_id')->whereNotNull('event_type_id')->firstOrFail();

        $response = $this->actingAs($admin)->get(route('events.index'));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Events/Index')
            ->has('eventGroups'));

        $brandGroup = collect($response->inertiaProps('eventGroups'))
            ->firstWhere('brand_id', $event->brand_id);
        $this->assertNotNull($brandGroup);

        $typeGroup = collect($brandGroup['event_types'])
            ->firstWhere('event_type_id', $event->event_type_id);
        $this->assertNotNull($typeGroup);

        $dateGroup = collect($typeGroup['dates'])
            ->firstWhere('date', $event->event_date?->toDateString());
        $this->assertNotNull($dateGroup);
        $this->assertTrue(collect($dateGroup['events'])->contains('id', $event->id));
    }

    public function test_shared_event_brands_include_only_brands_with_accessible_events(): void
    {
        $this->seedWorkspaceData();
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();
        $otherPic = User::where('email', 'admin@indosakti.com')->firstOrFail();
        $ownedBrand = Brand::create(['name' => 'PIC Scope Brand', 'slug' => 'pic-scope-brand']);
        $hiddenBrand = Brand::create(['name' => 'Hidden Scope Brand', 'slug' => 'hidden-scope-brand']);

        foreach ([[$ownedBrand, $pic], [$hiddenBrand, $otherPic]] as [$brand, $eventPic]) {
            Event::create([
                'event_code' => Event::generateCode(),
                'event_name' => 'Scope event '.$brand->name,
                'brand_id' => $brand->id,
                'event_date' => now()->addDays(2)->toDateString(),
                'status' => EventStatus::PLANNING,
                'pic_id' => $eventPic->id,
                'created_by' => $eventPic->id,
            ]);
        }

        $response = $this->actingAs($pic)->get(route('events.index'));
        $eventBrands = collect($response->inertiaProps('eventBrands'));

        $this->assertTrue($eventBrands->contains('id', $ownedBrand->id));
        $this->assertFalse($eventBrands->contains('id', $hiddenBrand->id));
    }

    public function test_calendar_month_does_not_overflow_past_the_last_day_of_a_month(): void
    {
        $this->seedWorkspaceData();
        $admin = User::where('email', 'admin@indosakti.com')->firstOrFail();

        $event = Event::create([
            'event_code' => Event::generateCode(),
            'event_name' => 'Event Akhir Bulan',
            'event_date' => '2026-01-31',
            'status' => EventStatus::PLANNING,
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get(route('events.index', ['month' => '2026-01']));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Events/Index')
            ->where('month', '2026-01'));

        $ids = collect($response->viewData('page')['props']['calendarEvents'])->pluck('id');
        $this->assertTrue($ids->contains($event->id));
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

    private function makeVenue(string $name): Venue
    {
        return Venue::create(['name' => $name]);
    }

    private function makeFjp(Venue $venue, string $status): Fjp
    {
        return Fjp::create([
            'fjp_number' => Fjp::generateNumber(),
            'event_name' => 'FJP '.$venue->name,
            'venue_id' => $venue->id,
            'event_date' => now()->addDays(7)->toDateString(),
            'permit_status' => PermitStatus::DRAFT,
            'status' => $status,
        ]);
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
