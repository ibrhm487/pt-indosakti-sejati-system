<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Enums\ReportStatus;
use App\Models\Event;
use App\Models\EventReport;
use App\Models\EventTeamMember;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EventLifecycleControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_detail_renders_the_tabbed_workspace_for_an_authorized_user(): void
    {
        $this->seedWorkspaceData();
        $adminEvent = User::where('email', 'admin.event@indosakti.com')->firstOrFail();
        $event = Event::firstOrFail();

        $this->actingAs($adminEvent)
            ->get(route('events.show', $event))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Events/Show')
                ->has('event')
                ->has('readiness')
                ->has('team')
                ->has('attendances')
                ->has('stockRequests')
                ->has('reports')
                ->has('quickReports')
                ->has('documents')
                ->has('auditTrail')
                ->has('transitions')
                ->has('can.close'));
    }

    public function test_pic_cannot_view_an_event_owned_by_another_pic(): void
    {
        $this->seedWorkspaceData();
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();
        $otherPic = User::factory()->create();
        $event = $this->createEvent($otherPic);

        $this->actingAs($pic)
            ->get(route('events.show', $event))
            ->assertForbidden();
    }

    public function test_team_cannot_view_an_event_they_are_not_assigned_to(): void
    {
        $this->seedWorkspaceData();
        $team = User::where('email', 'team@indosakti.com')->firstOrFail();
        $event = $this->createEvent(
            User::where('email', 'pic@indosakti.com')->firstOrFail(),
        );

        $this->actingAs($team)
            ->get(route('events.show', $event))
            ->assertForbidden();
    }

    public function test_illegal_status_transition_is_rejected(): void
    {
        $this->seedWorkspaceData();
        $admin = User::where('email', 'admin@indosakti.com')->firstOrFail();
        $event = Event::firstOrFail();
        $this->assertSame(EventStatus::ONGOING, $event->status);

        // ONGOING may only move to COMPLETED or CANCELLED.
        $this->actingAs($admin)
            ->from(route('events.show', $event))
            ->patch(route('events.status', $event), ['status' => EventStatus::DRAFT->value])
            ->assertRedirect(route('events.show', $event))
            ->assertSessionHasErrors('status');

        $this->assertSame(EventStatus::ONGOING->value, $event->fresh()->status->value);
    }

    public function test_legal_status_transition_is_applied(): void
    {
        $this->seedWorkspaceData();
        $admin = User::where('email', 'admin@indosakti.com')->firstOrFail();
        $event = Event::firstOrFail();

        $this->actingAs($admin)
            ->from(route('events.show', $event))
            ->patch(route('events.status', $event), ['status' => EventStatus::CANCELLED->value])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(EventStatus::CANCELLED->value, $event->fresh()->status->value);
    }

    public function test_closing_an_event_requires_the_close_permission(): void
    {
        $this->seedWorkspaceData();
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();
        $event = Event::firstOrFail();

        $this->actingAs($pic)
            ->post(route('events.close', $event))
            ->assertForbidden();

        $this->assertSame(EventStatus::ONGOING->value, $event->fresh()->status->value);
    }

    public function test_closing_an_event_is_blocked_while_prerequisites_are_unmet(): void
    {
        $this->seedWorkspaceData();
        $adminEvent = User::where('email', 'admin.event@indosakti.com')->firstOrFail();
        $event = Event::firstOrFail();

        // Demo event: future date and a still-submitted report.
        $this->actingAs($adminEvent)
            ->from(route('events.show', $event))
            ->post(route('events.close', $event))
            ->assertRedirect(route('events.show', $event))
            ->assertSessionHasErrors('close');

        $this->assertSame(EventStatus::ONGOING->value, $event->fresh()->status->value);
    }

    public function test_closing_an_event_succeeds_once_prerequisites_are_met(): void
    {
        $this->seedWorkspaceData();
        $adminEvent = User::where('email', 'admin.event@indosakti.com')->firstOrFail();
        $team = User::where('email', 'team@indosakti.com')->firstOrFail();

        $event = $this->createEvent(
            User::where('email', 'pic@indosakti.com')->firstOrFail(),
            ['status' => EventStatus::ONGOING, 'event_date' => now()->subDay()],
        );

        EventTeamMember::create([
            'event_id' => $event->id,
            'user_id' => $team->id,
            'position' => 'SPG',
            'status' => 'assigned',
        ]);

        EventReport::create([
            'event_id' => $event->id,
            'sampling_target' => 500,
            'actual_sampling' => 480,
            'status' => ReportStatus::VERIFIED,
            'submitted_by' => $team->id,
            'submitted_at' => now(),
            'verified_by' => $adminEvent->id,
            'verified_at' => now(),
        ]);

        $this->actingAs($adminEvent)
            ->from(route('events.show', $event))
            ->post(route('events.close', $event))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(EventStatus::COMPLETED->value, $event->fresh()->status->value);
    }

    public function test_verifying_a_report_does_not_auto_complete_the_event(): void
    {
        $this->seedWorkspaceData();
        $adminEvent = User::where('email', 'admin.event@indosakti.com')->firstOrFail();
        $event = Event::firstOrFail();
        $report = EventReport::where('event_id', $event->id)->firstOrFail();
        $this->assertSame(ReportStatus::SUBMITTED, $report->status);

        $this->actingAs($adminEvent)
            ->from(route('events.show', $event))
            ->post(route('reports.verify', $report))
            ->assertRedirect();

        $this->assertSame(ReportStatus::VERIFIED->value, $report->fresh()->status->value);
        $this->assertSame(EventStatus::ONGOING->value, $event->fresh()->status->value);
    }

    private function seedWorkspaceData(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(DemoDataSeeder::class);
    }

    private function createEvent(User $pic, array $overrides = []): Event
    {
        return Event::create(array_merge([
            'event_code' => 'EVT-TEST-'.uniqid(),
            'event_name' => 'Event Uji '.uniqid(),
            'status' => EventStatus::READY,
            'pic_id' => $pic->id,
        ], $overrides));
    }
}
