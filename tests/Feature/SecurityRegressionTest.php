<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Enums\KitchenStatus;
use App\Enums\ReportStatus;
use App\Enums\RoleSlug;
use App\Models\Event;
use App\Models\EventReport;
use App\Models\EventTeamMember;
use App\Models\KitchenReport;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecurityRegressionTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------------------
    // P0-2: IDOR pada operasi event
    // ------------------------------------------------------------------

    public function test_pic_cannot_update_status_of_another_pics_event(): void
    {
        $this->seedWorkspaceData();
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();
        $foreignEvent = $this->createForeignEvent();

        $this->actingAs($pic)
            ->patch(route('events.status', $foreignEvent), ['status' => EventStatus::CANCELLED->value])
            ->assertForbidden();

        $this->assertSame(EventStatus::PLANNING, $foreignEvent->fresh()->status);
    }

    public function test_pic_cannot_assign_team_to_another_pics_event(): void
    {
        $this->seedWorkspaceData();
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();
        $team = User::where('email', 'team@indosakti.com')->firstOrFail();
        $foreignEvent = $this->createForeignEvent();

        $this->actingAs($pic)
            ->post(route('events.team.assign', $foreignEvent), [
                'user_id' => $team->id,
                'position' => 'SPG',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('event_team_members', [
            'event_id' => $foreignEvent->id,
            'user_id' => $team->id,
        ]);
    }

    public function test_pic_cannot_remove_team_member_from_another_pics_event(): void
    {
        $this->seedWorkspaceData();
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();
        $team = User::where('email', 'team@indosakti.com')->firstOrFail();
        $foreignEvent = $this->createForeignEvent();
        $member = EventTeamMember::create([
            'event_id' => $foreignEvent->id,
            'user_id' => $team->id,
            'position' => 'SPG',
        ]);

        $this->actingAs($pic)
            ->delete(route('events.team.remove', [$foreignEvent, $member]))
            ->assertForbidden();

        $this->assertDatabaseHas('event_team_members', ['id' => $member->id]);
    }

    public function test_pic_can_still_update_status_of_their_own_event(): void
    {
        $this->seedWorkspaceData();
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();
        $ownEvent = Event::where('pic_id', $pic->id)->firstOrFail();
        $ownEvent->update(['status' => EventStatus::PLANNING]);

        $this->actingAs($pic)
            ->patch(route('events.status', $ownEvent), ['status' => EventStatus::TEAM_ASSIGNMENT->value])
            ->assertRedirect();

        $this->assertSame(EventStatus::TEAM_ASSIGNMENT, $ownEvent->fresh()->status);
    }

    // ------------------------------------------------------------------
    // P0-3: login & sesi akun nonaktif
    // ------------------------------------------------------------------

    public function test_inactive_user_cannot_login(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['is_active' => false]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_active_session_is_terminated_when_account_is_deactivated(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $role = Role::where('slug', RoleSlug::SYSTEM_ADMIN->value)->firstOrFail();
        $user = User::factory()->create(['is_active' => true, 'active_role_id' => $role->id]);
        $user->roles()->attach($role);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk();

        $user->update(['is_active' => false]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    // ------------------------------------------------------------------
    // P1-4: laporan tidak boleh dipindahkan ke event di luar scope
    // ------------------------------------------------------------------

    public function test_pic_cannot_move_their_report_to_another_pics_event(): void
    {
        $this->seedWorkspaceData();
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();
        $ownEvent = Event::where('pic_id', $pic->id)->firstOrFail();
        $foreignEvent = $this->createForeignEvent();

        $report = EventReport::create([
            'event_id' => $ownEvent->id,
            'sampling_target' => 100,
            'actual_sampling' => 80,
            'status' => ReportStatus::DRAFT,
            'submitted_by' => $pic->id,
        ]);

        $this->actingAs($pic)
            ->put(route('reports.update', $report), [
                'event_id' => $foreignEvent->id,
                'actual_sampling' => 80,
            ])
            ->assertForbidden();

        $this->assertSame($ownEvent->id, $report->fresh()->event_id);
    }

    public function test_pic_can_keep_their_report_on_their_own_event(): void
    {
        $this->seedWorkspaceData();
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();
        $ownEvent = Event::where('pic_id', $pic->id)->firstOrFail();

        $report = EventReport::create([
            'event_id' => $ownEvent->id,
            'sampling_target' => 100,
            'actual_sampling' => 50,
            'status' => ReportStatus::DRAFT,
            'submitted_by' => $pic->id,
        ]);

        $this->actingAs($pic)
            ->put(route('reports.update', $report), [
                'event_id' => $ownEvent->id,
                'actual_sampling' => 75,
            ])
            ->assertRedirect();

        $report->refresh();
        $this->assertSame(75, $report->actual_sampling);
    }

    // ------------------------------------------------------------------
    // P0-1: foto kitchen punya endpoint berotorisasi
    // ------------------------------------------------------------------

    public function test_kitchen_photo_requires_login(): void
    {
        $this->seedWorkspaceData();
        Storage::fake('local');
        $report = $this->createKitchenReportWithPhoto();

        $this->get(route('kitchen.reports.photo', $report))
            ->assertRedirect(route('login'));
    }

    public function test_team_member_out_of_event_scope_cannot_open_kitchen_photo(): void
    {
        $this->seedWorkspaceData();
        Storage::fake('local');
        $team = User::where('email', 'team@indosakti.com')->firstOrFail();

        // Team member who is not on any team of the kitchen's event.
        $foreignEvent = $this->createForeignEvent();
        $kitchen = $foreignEvent->kitchenRequest()->create([
            'jug_quantity' => 5,
            'status' => KitchenStatus::PREPARING,
        ]);
        Storage::disk('local')->put('kitchen/reports/bukti.jpg', 'foto');
        $report = $kitchen->reports()->create([
            'prepared_quantity' => 5,
            'prepared_by' => $team->id,
            'prepared_at' => now(),
            'photo_path' => 'kitchen/reports/bukti.jpg',
        ]);

        $this->actingAs($team)
            ->get(route('kitchen.reports.photo', $report))
            ->assertForbidden();
    }

    public function test_kitchen_staff_can_open_kitchen_photo(): void
    {
        $this->seedWorkspaceData();
        Storage::fake('local');
        $kitchenUser = User::where('email', 'kitchen@indosakti.com')->firstOrFail();
        $report = $this->createKitchenReportWithPhoto();

        $this->actingAs($kitchenUser)
            ->get(route('kitchen.reports.photo', $report))
            ->assertOk();
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

    private function createKitchenReportWithPhoto(): KitchenReport
    {
        $event = Event::firstOrFail();
        $kitchen = $event->kitchenRequest()->firstOrCreate(
            ['event_id' => $event->id],
            ['jug_quantity' => 5, 'status' => KitchenStatus::PREPARING],
        );

        Storage::disk('local')->put('kitchen/reports/bukti.jpg', 'foto');

        return $kitchen->reports()->create([
            'prepared_quantity' => 5,
            'prepared_by' => $event->pic_id,
            'prepared_at' => now(),
            'photo_path' => 'kitchen/reports/bukti.jpg',
        ]);
    }

    private function seedWorkspaceData(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(DemoDataSeeder::class);
    }
}
