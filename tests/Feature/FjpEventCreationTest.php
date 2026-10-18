<?php

namespace Tests\Feature;

use App\Enums\PermitStatus;
use App\Models\Event;
use App\Models\Fjp;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FjpEventCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_fjp_detail_exposes_linked_events_and_create_capability(): void
    {
        $this->seedWorkspaceData();
        $admin = User::where('email', 'admin@indosakti.com')->firstOrFail();
        $fjp = Fjp::whereHas('events')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('fjp.show', $fjp))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Fjp/Show')
                ->has('events')
                ->has('pics')
                ->where('can.create_event', true));
    }

    public function test_event_can_be_created_from_an_approved_fjp(): void
    {
        $this->seedWorkspaceData();
        $admin = User::where('email', 'admin@indosakti.com')->firstOrFail();
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();
        $fjp = $this->createFjp(PermitStatus::APPROVED);

        $this->actingAs($admin)
            ->post(route('fjp.create-event', $fjp), ['pic_id' => $pic->id])
            ->assertRedirect();

        $event = Event::where('fjp_id', $fjp->id)->firstOrFail();
        $this->assertSame($pic->id, $event->pic_id);
        $this->assertSame($fjp->brand_id, $event->brand_id);
        $this->assertSame('planning', $event->status->value);
    }

    public function test_event_cannot_be_created_from_a_non_approved_fjp(): void
    {
        $this->seedWorkspaceData();
        $admin = User::where('email', 'admin@indosakti.com')->firstOrFail();
        $fjp = $this->createFjp(PermitStatus::DRAFT);

        $this->actingAs($admin)
            ->from(route('fjp.show', $fjp))
            ->post(route('fjp.create-event', $fjp))
            ->assertRedirect(route('fjp.show', $fjp))
            ->assertSessionHasErrors('event');

        $this->assertDatabaseMissing('events', ['fjp_id' => $fjp->id]);
    }

    public function test_duplicate_event_creation_for_the_same_fjp_is_blocked(): void
    {
        $this->seedWorkspaceData();
        $admin = User::where('email', 'admin@indosakti.com')->firstOrFail();
        $fjp = Fjp::whereHas('events')->firstOrFail();
        $existing = $fjp->events()->count();

        $this->actingAs($admin)
            ->from(route('fjp.show', $fjp))
            ->post(route('fjp.create-event', $fjp))
            ->assertRedirect(route('fjp.show', $fjp))
            ->assertSessionHasErrors('event');

        $this->assertSame($existing, $fjp->events()->count());
    }

    public function test_user_without_events_create_permission_cannot_create_an_event(): void
    {
        $this->seedWorkspaceData();
        $permitter = User::where('email', 'permitter@indosakti.com')->firstOrFail();
        $fjp = Fjp::whereHas('events')->firstOrFail();

        $this->actingAs($permitter)
            ->post(route('fjp.create-event', $fjp))
            ->assertForbidden();
    }

    private function seedWorkspaceData(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(DemoDataSeeder::class);
    }

    private function createFjp(PermitStatus $status): Fjp
    {
        return Fjp::create([
            'fjp_number' => Fjp::generateNumber(),
            'event_name' => 'FJP Uji '.uniqid(),
            'school_name' => 'Sekolah Uji',
            'npsn' => '20101999',
            'event_date' => now()->addDays(10)->toDateString(),
            'start_time' => '08:00',
            'end_time' => '12:00',
            'sampling_target' => 500,
            'jug_required' => false,
            'permit_status' => $status,
            'status' => 'draft',
        ]);
    }
}
