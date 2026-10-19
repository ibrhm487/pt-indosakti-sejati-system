<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\EventTeamMember;
use App\Models\QuickReport;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class QuickReportControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_can_submit_a_photo_report_for_an_assigned_event(): void
    {
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');
        $team = User::where('email', 'team@indosakti.com')->firstOrFail();
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();
        $event = $this->createEvent($pic, 'EVT-QUICK-REPORT-ASSIGNED');
        EventTeamMember::create(['event_id' => $event->id, 'user_id' => $team->id]);

        $response = $this->actingAs($team)->post(route('quick-reports.store'), [
            'event_id' => $event->id,
            'title' => 'Sampling pagi',
            'body' => 'Kegiatan berjalan lancar.',
            'photo' => UploadedFile::fake()->image('sampling.jpg'),
        ]);

        $response->assertRedirect(route('quick-reports.index'));
        $report = QuickReport::query()->firstOrFail();
        $this->assertDatabaseHas('quick_reports', [
            'id' => $report->id,
            'event_id' => $event->id,
            'user_id' => $team->id,
            'title' => 'Sampling pagi',
            'body' => 'Kegiatan berjalan lancar.',
        ]);
        Storage::disk('local')->assertExists($report->photo_path);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'CREATE_QUICK_REPORT',
            'record_id' => $report->id,
        ]);
    }

    public function test_team_cannot_submit_a_quick_report_for_an_unassigned_event(): void
    {
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');
        $team = User::where('email', 'team@indosakti.com')->firstOrFail();
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();
        $event = $this->createEvent($pic, 'EVT-QUICK-REPORT-OTHER');

        $this->actingAs($team)->post(route('quick-reports.store'), [
            'event_id' => $event->id,
            'title' => 'Laporan tidak berhak',
            'photo' => UploadedFile::fake()->image('sampling.jpg'),
        ])->assertNotFound();

        $this->assertDatabaseMissing('quick_reports', ['event_id' => $event->id]);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_quick_report_requires_a_valid_photo(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $team = User::where('email', 'team@indosakti.com')->firstOrFail();
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();
        $event = $this->createEvent($pic, 'EVT-QUICK-REPORT-PHOTO');
        EventTeamMember::create(['event_id' => $event->id, 'user_id' => $team->id]);

        $this->actingAs($team)
            ->from(route('quick-reports.create'))
            ->post(route('quick-reports.store'), [
                'event_id' => $event->id,
                'title' => 'Tanpa foto',
            ])
            ->assertSessionHasErrors('photo');

        $this->assertDatabaseMissing('quick_reports', ['event_id' => $event->id]);
    }

    public function test_user_without_quick_report_permission_cannot_open_the_report_list(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $permitter = User::where('email', 'permitter@indosakti.com')->firstOrFail();

        $this->actingAs($permitter)
            ->get(route('quick-reports.index'))
            ->assertForbidden();
    }

    public function test_team_index_shows_only_their_own_quick_reports(): void
    {
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');
        $team = User::where('email', 'team@indosakti.com')->firstOrFail();
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();
        $event = $this->createEvent($pic, 'EVT-QUICK-INDEX-SCOPING');
        EventTeamMember::create(['event_id' => $event->id, 'user_id' => $team->id]);

        $own = QuickReport::create([
            'event_id' => $event->id,
            'user_id' => $team->id,
            'title' => 'Laporan saya',
            'photo_path' => 'quick-reports/'.$event->id.'/mine.jpg',
        ]);
        QuickReport::create([
            'event_id' => $event->id,
            'user_id' => $pic->id,
            'title' => 'Laporan orang lain',
            'photo_path' => 'quick-reports/'.$event->id.'/other.jpg',
        ]);

        $response = $this->actingAs($team)->get(route('quick-reports.index'));

        $response->assertOk();
        $rows = collect($response->inertiaProps('reports.data'));
        $this->assertCount(1, $rows);
        $this->assertSame($own->id, $rows->first()['id']);
    }

    public function test_team_member_out_of_event_scope_cannot_open_quick_report_photo(): void
    {
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');
        $team = User::where('email', 'team@indosakti.com')->firstOrFail();
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();
        $event = $this->createEvent($pic, 'EVT-QUICK-PHOTO-FOREIGN');

        Storage::disk('local')->put('quick-reports/'.$event->id.'/bukti.jpg', 'foto');
        $report = QuickReport::create([
            'event_id' => $event->id,
            'user_id' => $pic->id,
            'title' => 'Laporan asing',
            'photo_path' => 'quick-reports/'.$event->id.'/bukti.jpg',
        ]);

        // The team member is not assigned to this event: visibleEvents must deny.
        $this->actingAs($team)
            ->get(route('quick-reports.photo', $report))
            ->assertForbidden();
    }

    private function createEvent(User $pic, string $eventCode): Event
    {
        return Event::create([
            'event_code' => $eventCode,
            'event_name' => 'Quick report event',
            'status' => EventStatus::ONGOING,
            'pic_id' => $pic->id,
        ]);
    }
}
