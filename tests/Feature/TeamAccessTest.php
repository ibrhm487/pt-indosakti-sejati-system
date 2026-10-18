<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Event;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TeamAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_only_sees_their_own_attendance_rows(): void
    {
        $this->seedWorkspaceData();
        $team = $this->user('team@indosakti.com');

        $this->actingAs($team)
            ->get(route('attendance.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Attendance/Index')
                ->has('attendances.data')
                ->where('can.payroll', false)
                ->where('currentUser.id', $team->id));

        $rows = $this->inertiaData('attendance.index', $team, 'attendances')['data'];
        $this->assertNotEmpty($rows);
        foreach ($rows as $row) {
            $this->assertSame($team->id, $row['user_id']);
        }
    }

    public function test_team_cannot_access_payroll(): void
    {
        $this->seedWorkspaceData();
        $team = $this->user('team@indosakti.com');

        $this->actingAs($team)
            ->get(route('attendance.payroll'))
            ->assertForbidden();
    }

    public function test_team_store_forces_attendance_to_themselves(): void
    {
        $this->seedWorkspaceData();
        $team = $this->user('team@indosakti.com');
        $other = $this->user('pic@indosakti.com');
        $event = Event::whereHas('teamMembers', fn ($q) => $q->where('user_id', $team->id))->firstOrFail();

        $this->actingAs($team)
            ->post(route('attendance.store'), [
                'event_id' => $event->id,
                'user_id' => $other->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('attendances', [
            'event_id' => $event->id,
            'user_id' => $team->id,
        ]);
        $this->assertDatabaseMissing('attendances', [
            'event_id' => $event->id,
            'user_id' => $other->id,
        ]);
    }

    public function test_team_cannot_check_in_for_another_user(): void
    {
        $this->seedWorkspaceData();
        $team = $this->user('team@indosakti.com');
        $other = $this->user('pic@indosakti.com');
        $event = Event::firstOrFail();
        $attendance = Attendance::create([
            'event_id' => $event->id,
            'user_id' => $other->id,
            'status' => 'planned',
        ]);

        $this->actingAs($team)
            ->post(route('attendance.check-in', $attendance))
            ->assertForbidden();

        $this->assertNull($attendance->fresh()->check_in_at);
    }

    public function test_pic_only_sees_attendance_for_their_own_events(): void
    {
        $this->seedWorkspaceData();
        $pic = $this->user('pic@indosakti.com');
        $otherPic = User::factory()->create();
        $outsider = User::factory()->create();

        $otherEvent = Event::create([
            'event_code' => 'EVT-OTHER-'.uniqid(),
            'event_name' => 'Event PIC lain',
            'pic_id' => $otherPic->id,
        ]);
        $foreign = Attendance::create([
            'event_id' => $otherEvent->id,
            'user_id' => $outsider->id,
            'status' => 'planned',
        ]);

        $response = $this->actingAs($pic)->get(route('attendance.index'));
        $response->assertOk();

        $ids = collect($response->viewData('page')['props']['attendances']['data'])->pluck('id');
        $this->assertFalse($ids->contains($foreign->id));
    }

    public function test_pic_can_access_payroll_scoped_to_their_events(): void
    {
        $this->seedWorkspaceData();
        $pic = $this->user('pic@indosakti.com');
        $otherPic = User::factory()->create();
        $outsider = User::factory()->create();

        $otherEvent = Event::create([
            'event_code' => 'EVT-PAY-'.uniqid(),
            'event_name' => 'Event payroll lain',
            'pic_id' => $otherPic->id,
        ]);
        $foreign = Attendance::create([
            'event_id' => $otherEvent->id,
            'user_id' => $outsider->id,
            'status' => 'completed',
            'check_in_at' => now(),
            'check_out_at' => now(),
        ]);

        $response = $this->actingAs($pic)->get(route('attendance.payroll'));
        $response->assertOk();

        $ids = collect($response->viewData('page')['props']['rows'])->pluck('id');
        $this->assertFalse($ids->contains($foreign->id));
    }

    public function test_team_event_detail_hides_sensitive_tabs_and_payloads(): void
    {
        $this->seedWorkspaceData();
        $team = $this->user('team@indosakti.com');
        $event = Event::whereHas('teamMembers', fn ($q) => $q->where('user_id', $team->id))->firstOrFail();

        $this->actingAs($team)
            ->get(route('events.show', $event))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Events/Show')
                ->where('can.view_fjp', false)
                ->where('can.view_mrv', false)
                ->where('can.view_audit', false)
                ->where('fjp', null)
                ->where('permit', null)
                ->has('auditTrail', 0)
                ->has('stockRequests', 0));
    }

    public function test_admin_event_still_receives_the_audit_trail(): void
    {
        $this->seedWorkspaceData();
        $adminEvent = $this->user('admin.event@indosakti.com');
        $event = Event::firstOrFail();

        $this->actingAs($adminEvent)
            ->get(route('events.show', $event))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Events/Show')
                ->where('can.view_audit', true)
                ->has('auditTrail'));
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    private function inertiaData(string $routeName, User $user, string $prop): array
    {
        $response = $this->actingAs($user)->get(route($routeName));

        return $response->viewData('page')['props'][$prop];
    }

    private function seedWorkspaceData(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(DemoDataSeeder::class);
    }
}
