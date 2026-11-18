<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Enums\PermitStatus;
use App\Models\Brand;
use App\Models\Event;
use App\Models\EventType;
use App\Models\Fjp;
use App\Models\User;
use App\Models\Venue;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BrandScopedEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_milo_and_dancow_have_their_own_event_types(): void
    {
        $this->seedWorkspaceData();

        $milo = Brand::where('slug', 'milo')->firstOrFail();
        $dancow = Brand::where('slug', 'dancow')->firstOrFail();

        $this->assertDatabaseHas('event_types', [
            'brand_id' => $milo->id,
            'name' => 'MILO School Sampling',
            'requires_school' => true,
        ]);
        $this->assertDatabaseHas('event_types', [
            'brand_id' => $milo->id,
            'name' => 'MILO InStore',
            'requires_school' => false,
        ]);
        $this->assertDatabaseHas('event_types', [
            'brand_id' => $dancow->id,
            'name' => 'DANCOW School Sampling',
            'requires_school' => true,
        ]);
        $this->assertDatabaseHas('event_types', [
            'brand_id' => $dancow->id,
            'name' => 'DANCOW InStore',
            'requires_school' => false,
        ]);
    }

    public function test_event_name_is_composed_from_brand_type_and_venue(): void
    {
        $this->seedWorkspaceData();
        $admin = User::where('email', 'admin@indosakti.com')->firstOrFail();
        $milo = Brand::where('slug', 'milo')->firstOrFail();
        $type = EventType::where('brand_id', $milo->id)->where('requires_school', true)->firstOrFail();
        $venue = Venue::where('is_school', true)->firstOrFail();

        $this->actingAs($admin)
            ->post(route('events.store'), [
                'event_name' => '',
                'brand_id' => $milo->id,
                'event_type_id' => $type->id,
                'venue_id' => $venue->id,
                'event_date' => now()->addDay()->toDateString(),
                'start_time' => '08:00',
                'end_time' => '12:00',
                'sampling_target' => 150,
                'notes' => '',
                'jug_required' => false,
                'pic_id' => '',
            ])
            ->assertRedirect();

        $event = Event::where('brand_id', $milo->id)
            ->where('venue_id', $venue->id)
            ->where('event_type_id', $type->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame("MILO School Sampling - {$venue->name}", $event->event_name);
    }

    public function test_event_type_must_match_the_selected_brand(): void
    {
        $this->seedWorkspaceData();
        $admin = User::where('email', 'admin@indosakti.com')->firstOrFail();
        $milo = Brand::where('slug', 'milo')->firstOrFail();
        $dancow = Brand::where('slug', 'dancow')->firstOrFail();
        $dancowType = EventType::where('brand_id', $dancow->id)->firstOrFail();
        $venue = Venue::where('is_school', true)->firstOrFail();

        $this->actingAs($admin)
            ->from(route('events.create'))
            ->post(route('events.store'), [
                'event_name' => '',
                'brand_id' => $milo->id,
                'event_type_id' => $dancowType->id,
                'venue_id' => $venue->id,
                'event_date' => now()->addDay()->toDateString(),
                'jug_required' => false,
            ])
            ->assertRedirect(route('events.create'))
            ->assertSessionHasErrors('event_type_id');
    }

    public function test_event_location_is_taken_from_the_selected_confirmed_fjp(): void
    {
        $this->seedWorkspaceData();
        $admin = User::where('email', 'admin@indosakti.com')->firstOrFail();
        $milo = Brand::where('slug', 'milo')->firstOrFail();
        $type = EventType::where('brand_id', $milo->id)->where('requires_school', true)->firstOrFail();
        $venue = Venue::where('is_school', true)->firstOrFail();
        $eventDate = now()->addDays(5)->toDateString();

        $fjp = Fjp::create([
            'fjp_number' => Fjp::generateNumber(),
            'event_name' => 'Nama FJP Lama',
            'brand_id' => $milo->id,
            'event_type_id' => $type->id,
            'venue_id' => $venue->id,
            'school_name' => $venue->name,
            'event_date' => $eventDate,
            'start_time' => '09:00',
            'end_time' => '15:00',
            'sampling_target' => 90,
            'jug_required' => true,
            'permit_status' => PermitStatus::APPROVED,
            'status' => Fjp::STATUS_CONFIRM,
        ]);

        $this->actingAs($admin)
            ->post(route('events.store'), [
                'event_name' => '',
                'fjp_id' => $fjp->id,
            ])
            ->assertRedirect();

        $event = Event::where('fjp_id', $fjp->id)->firstOrFail();

        $this->assertSame($venue->id, $event->venue_id);
        $this->assertSame($milo->id, $event->brand_id);
        $this->assertSame($type->id, $event->event_type_id);
        $this->assertSame($eventDate, $event->event_date?->toDateString());
        $this->assertSame('09:00', $event->start_time);
        $this->assertSame('15:00', $event->end_time);
        $this->assertSame(90, $event->sampling_target);
        $this->assertTrue($event->jug_required);
        $this->assertSame("MILO School Sampling - {$venue->name}", $event->event_name);
    }

    public function test_event_rejects_an_fjp_that_is_not_confirmed(): void
    {
        $this->seedWorkspaceData();
        $admin = User::where('email', 'admin@indosakti.com')->firstOrFail();
        $venue = Venue::where('is_school', true)->firstOrFail();

        $fjp = Fjp::create([
            'fjp_number' => Fjp::generateNumber(),
            'event_name' => 'FJP Belum Confirm',
            'venue_id' => $venue->id,
            'event_date' => now()->addDays(5)->toDateString(),
            'permit_status' => PermitStatus::DRAFT,
            'status' => 'plan',
        ]);

        $this->actingAs($admin)
            ->from(route('events.create'))
            ->post(route('events.store'), [
                'event_name' => '',
                'fjp_id' => $fjp->id,
            ])
            ->assertRedirect(route('events.create'))
            ->assertSessionHasErrors('fjp_id');

        $this->assertDatabaseMissing('events', ['fjp_id' => $fjp->id]);
    }

    public function test_event_index_filters_by_brand_and_event_type(): void
    {
        $this->seedWorkspaceData();
        $admin = User::where('email', 'admin@indosakti.com')->firstOrFail();
        $dancow = Brand::where('slug', 'dancow')->firstOrFail();
        $type = EventType::where('brand_id', $dancow->id)->firstOrFail();

        $event = Event::create([
            'event_code' => Event::generateCode(),
            'event_name' => 'DANCOW School Sampling - Uji',
            'brand_id' => $dancow->id,
            'event_type_id' => $type->id,
            'event_date' => now()->toDateString(),
            'status' => EventStatus::PLANNING,
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get(route('events.index', [
            'brand_id' => $dancow->id,
            'event_type_id' => $type->id,
        ]));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Events/Index')
            ->where('filters.brand_id', (string) $dancow->id)
            ->where('filters.event_type_id', (string) $type->id));

        $ids = collect($response->viewData('page')['props']['events']['data'])->pluck('id');
        $this->assertTrue($ids->contains($event->id));
        $this->assertFalse($ids->contains(Event::where('brand_id', '!=', $dancow->id)->value('id')));
    }

    private function seedWorkspaceData(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(DemoDataSeeder::class);
    }
}
