<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Models\Attendance;
use App\Models\Event;
use App\Models\EventTeamMember;
use App\Models\EventType;
use App\Models\Fjp;
use App\Models\QuickReport;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
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
        $this->assertDatabaseMissing('attendances', [
            'event_id' => $event->id,
            'user_id' => $team->id,
            'status' => 'completed',
        ]);
        Storage::disk('local')->assertExists($report->photo_path);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'CREATE_QUICK_REPORT',
            'record_id' => $report->id,
        ]);
    }

    public function test_school_sampling_report_requires_every_member_attendance_and_completes_the_fjp(): void
    {
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');
        $teamLeader = User::where('email', 'tl@indosakti.com')->firstOrFail();
        $spg = User::where('email', 'spg@indosakti.com')->firstOrFail();
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();
        $eventType = EventType::create([
            'name' => 'MILO School Sampling',
            'slug' => 'milo-school-sampling',
            'is_active' => true,
        ]);
        $fjp = Fjp::create([
            'fjp_number' => Fjp::generateNumber(),
            'event_name' => 'SDN Uji Sampling',
            'jumlah_siswa_sekolah' => 320,
            'status' => Fjp::STATUS_CONFIRM,
        ]);
        $event = $this->createEvent($pic, 'EVT-SCHOOL-QUICK-REPORT');
        $event->update([
            'fjp_id' => $fjp->id,
            'event_type_id' => $eventType->id,
            'status' => EventStatus::PLANNING,
            'sampling_target' => 320,
        ]);

        foreach ([$teamLeader, $spg] as $member) {
            EventTeamMember::create(['event_id' => $event->id, 'user_id' => $member->id]);
        }

        $leaderAttendance = Attendance::create([
            'event_id' => $event->id,
            'user_id' => $teamLeader->id,
            'check_in_at' => '2030-05-11 08:00:00',
            'check_out_at' => '2030-05-11 12:00:00',
            'status' => 'completed',
        ]);
        $leaderAttendance->media()->create([
            'type' => 'photo',
            'stage' => 'pre_departure',
            'path' => 'attendance/pre-departure/leader.jpg',
            'mime' => 'image/jpeg',
            'size' => 100,
            'original_name' => 'leader.jpg',
        ]);

        $this->actingAs($teamLeader)
            ->post(route('quick-reports.store'), [
                'event_id' => $event->id,
                'activity_date' => '2030-05-11',
                'activity_start_time' => '08:00',
                'activity_end_time' => '12:00',
                'photos' => [UploadedFile::fake()->image('school.jpg')],
                'videos' => [UploadedFile::fake()->create('sampling.mp4', 1024, 'video/mp4')],
            ])
            ->assertSessionHasErrors('event_id');

        $spgAttendance = Attendance::create([
            'event_id' => $event->id,
            'user_id' => $spg->id,
            'check_in_at' => '2030-05-11 08:00:00',
            'check_out_at' => '2030-05-11 12:00:00',
            'status' => 'completed',
        ]);
        $spgAttendance->media()->create([
            'type' => 'photo',
            'stage' => 'pre_departure',
            'path' => 'attendance/pre-departure/spg.jpg',
            'mime' => 'image/jpeg',
            'size' => 100,
            'original_name' => 'spg.jpg',
        ]);

        $this->actingAs($teamLeader)
            ->post(route('quick-reports.store'), [
                'event_id' => $event->id,
                'activity_date' => '2030-05-11',
                'activity_start_time' => '08:00',
                'activity_end_time' => '12:00',
                'sampling_target' => 320,
                'sampling_actual' => 300,
                'learning' => 'Siswa antusias mengikuti sampling.',
                'photos' => [
                    UploadedFile::fake()->image('school-1.jpg'),
                    UploadedFile::fake()->image('school-2.jpg'),
                ],
                'videos' => [
                    UploadedFile::fake()->create('sampling-1.mp4', 1024, 'video/mp4'),
                    UploadedFile::fake()->create('sampling-2.mp4', 1024, 'video/mp4'),
                ],
            ])
            ->assertRedirect(route('quick-reports.index'));

        $report = QuickReport::query()->where('event_id', $event->id)->firstOrFail();
        $this->assertSame(2, $report->media()->where('type', 'photo')->count());
        $this->assertSame(2, $report->media()->where('type', 'video')->count());
        $this->assertSame(EventStatus::COMPLETED, $event->fresh()->status);
        $this->assertSame(Fjp::STATUS_DONE, $fjp->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'UPDATE_FJP_STATUS',
            'record_id' => $fjp->id,
        ]);
    }

    public function test_quick_report_stores_kpi_selling_packages_and_learning(): void
    {
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');
        $team = User::where('email', 'team@indosakti.com')->firstOrFail();
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();
        $event = $this->createEvent($pic, 'EVT-QUICK-REPORT-KPI');
        EventTeamMember::create(['event_id' => $event->id, 'user_id' => $team->id]);

        $this->actingAs($team)->post(route('quick-reports.store'), [
            'event_id' => $event->id,
            'sampling_target' => 400,
            'sampling_actual' => 400,
            'selling_target' => 10000000,
            'selling_actual' => 8160000,
            'booth_package_1' => 58,
            'booth_package_2' => 134,
            'booth_package_3' => 37,
            'store_package_1' => 0,
            'store_package_2' => 19,
            'store_package_3' => 7,
            'learning' => "1. Pengunjung pasar kebanyakan pedagang\n2. Pasar ramai di pinggir jalan raya",
            'photo' => UploadedFile::fake()->image('pasar.jpg'),
        ])->assertRedirect(route('quick-reports.index'));

        $report = QuickReport::query()->firstOrFail();

        $this->assertSame($event->event_name, $report->title);
        $this->assertSame(400, $report->sampling_target);
        $this->assertSame(400, $report->sampling_actual);
        $this->assertEquals(10000000, (float) $report->selling_target);
        $this->assertEquals(8160000, (float) $report->selling_actual);
        $this->assertSame(58, $report->booth_package_1);
        $this->assertSame(134, $report->booth_package_2);
        $this->assertSame(37, $report->booth_package_3);
        $this->assertSame(0, $report->store_package_1);
        $this->assertSame(19, $report->store_package_2);
        $this->assertSame(7, $report->store_package_3);
        $this->assertSame(
            "1. Pengunjung pasar kebanyakan pedagang\n2. Pasar ramai di pinggir jalan raya",
            $report->learning,
        );
    }

    public function test_quick_report_falls_back_to_the_event_sampling_target(): void
    {
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');
        $team = User::where('email', 'team@indosakti.com')->firstOrFail();
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();
        $event = $this->createEvent($pic, 'EVT-QUICK-REPORT-TARGET');
        $event->update(['sampling_target' => 250]);
        EventTeamMember::create(['event_id' => $event->id, 'user_id' => $team->id]);

        $this->actingAs($team)->post(route('quick-reports.store'), [
            'event_id' => $event->id,
            'sampling_actual' => 220,
            'photo' => UploadedFile::fake()->image('target.jpg'),
        ])->assertRedirect(route('quick-reports.index'));

        $this->assertSame(250, QuickReport::query()->firstOrFail()->sampling_target);
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

    public function test_quick_report_options_expose_the_event_type_variant(): void
    {
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');
        $team = User::where('email', 'team@indosakti.com')->firstOrFail();
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();
        $type = EventType::create([
            'name' => 'MILO InStore',
            'slug' => 'milo-instore',
            'is_active' => true,
        ]);
        $event = $this->createEvent($pic, 'EVT-QUICK-VARIANT');
        $event->update(['event_type_id' => $type->id]);
        EventTeamMember::create(['event_id' => $event->id, 'user_id' => $team->id]);

        $response = $this->actingAs($team)->get(route('quick-reports.create'));
        $row = collect($response->inertiaProps('events'))->firstWhere('id', $event->id);

        $this->assertSame($event->id, $response->inertiaProps('initialEventId'));
        $this->assertSame('milo-instore', $row['event_type_slug']);
        $this->assertSame('instore', $row['variant']);

        QuickReport::create([
            'event_id' => $event->id,
            'user_id' => $team->id,
            'title' => 'Laporan InStore',
            'photo_path' => 'quick-reports/'.$event->id.'/store.jpg',
        ]);

        $index = $this->actingAs($team)->get(route('quick-reports.history'));
        $index->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('QuickReports/History'));
        $report = collect($index->inertiaProps('reports.data'))->firstWhere('event', $event->event_name);

        $this->assertSame('instore', $report['variant']);
    }

    public function test_quick_report_menu_lists_assigned_tasks_with_submission_status(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $team = User::where('email', 'team@indosakti.com')->firstOrFail();
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();
        $pendingEvent = $this->createEvent($pic, 'EVT-QUICK-TASK-PENDING');
        $submittedEvent = $this->createEvent($pic, 'EVT-QUICK-TASK-SUBMITTED');

        foreach ([$pendingEvent, $submittedEvent] as $event) {
            EventTeamMember::create(['event_id' => $event->id, 'user_id' => $team->id]);
        }

        $report = QuickReport::create([
            'event_id' => $submittedEvent->id,
            'user_id' => $team->id,
            'title' => 'Laporan selesai',
        ]);

        $response = $this->actingAs($team)->get(route('quick-reports.index'));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('QuickReports/Index'));
        $tasks = collect($response->inertiaProps('tasks'))->keyBy('id');
        $this->assertSame('pending', $tasks[$pendingEvent->id]['status']);
        $this->assertSame('submitted', $tasks[$submittedEvent->id]['status']);
        $this->assertSame($report->id, $tasks[$submittedEvent->id]['report_id']);
        $this->assertSame([$pendingEvent->id], collect($response->inertiaProps('events'))
            ->pluck('id')
            ->all());
    }

    public function test_quick_report_create_page_prefills_the_selected_event_when_multiple_events_are_assigned(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $team = User::where('email', 'team@indosakti.com')->firstOrFail();
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();
        $firstEvent = $this->createEvent($pic, 'EVT-QUICK-FORM-FIRST');
        $firstEvent->update(['sampling_target' => 120]);
        $secondEvent = $this->createEvent($pic, 'EVT-QUICK-FORM-SECOND');
        $secondEvent->update([
            'sampling_target' => 340,
            'event_date' => '2030-06-15',
        ]);
        EventTeamMember::create([
            'event_id' => $firstEvent->id,
            'user_id' => $team->id,
            'schedule_date' => '2030-06-10',
            'start_time' => '08:00',
            'end_time' => '12:00',
        ]);
        EventTeamMember::create([
            'event_id' => $secondEvent->id,
            'user_id' => $team->id,
            'schedule_date' => '2030-06-16',
            'start_time' => '09:00',
            'end_time' => '13:00',
        ]);

        $response = $this->actingAs($team)->get(route('quick-reports.create', [
            'event' => $secondEvent->id,
        ]));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('QuickReports/Create')
                ->where('initialEventId', $secondEvent->id)
                ->has('events', 2));
        $events = collect($response->inertiaProps('events'))->keyBy('id');
        $this->assertSame(120, $events[$firstEvent->id]['sampling_target']);
        $this->assertSame(340, $events[$secondEvent->id]['sampling_target']);
        $this->assertSame('2030-06-16', $events[$secondEvent->id]['activity_date']);
        $this->assertSame('09:00', $events[$secondEvent->id]['activity_start_time']);
        $this->assertSame('13:00', $events[$secondEvent->id]['activity_end_time']);
    }

    public function test_quick_report_prefills_fjp_target_and_assignment_schedule_without_creating_attendance(): void
    {
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();
        $teamLeader = User::where('email', 'tl@indosakti.com')->firstOrFail();
        $spg = User::where('email', 'spg@indosakti.com')->firstOrFail();
        $fjp = Fjp::create([
            'fjp_number' => Fjp::generateNumber(),
            'event_name' => 'FJP Sekolah Otomatis',
            'jumlah_siswa_sekolah' => 620,
            'sampling_target' => 300,
        ]);
        $event = $this->createEvent($pic, 'EVT-QUICK-FJP-AUTOFILL');
        $event->update([
            'fjp_id' => $fjp->id,
            'sampling_target' => 620,
            'event_date' => '2030-05-10',
            'start_time' => '08:00',
            'end_time' => '12:00',
        ]);
        EventTeamMember::create([
            'event_id' => $event->id,
            'user_id' => $teamLeader->id,
            'schedule_date' => '2030-05-11',
            'start_time' => '08:30',
            'end_time' => '12:30',
        ]);
        EventTeamMember::create([
            'event_id' => $event->id,
            'user_id' => $spg->id,
            'schedule_date' => '2030-05-12',
            'start_time' => '09:00',
            'end_time' => '13:00',
        ]);

        $form = $this->actingAs($teamLeader)
            ->get(route('quick-reports.create', ['event' => $event->id]));
        $form->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('QuickReports/Create')
                ->where('initialEventId', $event->id));
        $option = collect($form->inertiaProps('events'))->firstWhere('id', $event->id);
        $this->assertSame(620, $option['sampling_target']);
        $this->assertSame('2030-05-11', $option['activity_date']);
        $this->assertSame('08:30', $option['activity_start_time']);
        $this->assertSame('12:30', $option['activity_end_time']);

        $leaderPayload = [
            'event_id' => $event->id,
            'sampling_target' => 500,
            'activity_date' => '2030-05-11',
            'activity_start_time' => '08:45',
            'activity_end_time' => '12:45',
            'photos' => [UploadedFile::fake()->image('leader.jpg')],
        ];
        $this->actingAs($teamLeader)
            ->post(route('quick-reports.store'), $leaderPayload)
            ->assertRedirect(route('quick-reports.index'));
        $this->actingAs($spg)
            ->post(route('quick-reports.store'), [
                'event_id' => $event->id,
                'photos' => [UploadedFile::fake()->image('spg.jpg')],
            ])
            ->assertSessionHasErrors([
                'event_id' => 'Quick Report untuk event ini sudah dikirim.',
            ]);
        $this->actingAs($teamLeader)
            ->post(route('quick-reports.store'), $leaderPayload)
            ->assertSessionHasErrors([
                'event_id' => 'Quick Report untuk event ini sudah dikirim.',
            ]);

        $this->assertSame(500, QuickReport::query()->where('user_id', $teamLeader->id)->firstOrFail()->sampling_target);
        $this->assertSame(1, QuickReport::query()->where('event_id', $event->id)->count());
        $this->assertSame(0, Attendance::query()->where('event_id', $event->id)->count());
        $this->assertDatabaseMissing('attendances', ['event_id' => $event->id]);
        $this->assertNull(Attendance::query()
            ->where('event_id', $event->id)
            ->where('user_id', $teamLeader->id)
            ->value('check_in_at'));
    }

    public function test_quick_report_rejects_an_end_time_before_start_without_recording_attendance(): void
    {
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');
        $team = User::where('email', 'team@indosakti.com')->firstOrFail();
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();
        $event = $this->createEvent($pic, 'EVT-QUICK-INVALID-SCHEDULE');
        EventTeamMember::create(['event_id' => $event->id, 'user_id' => $team->id]);

        $this->actingAs($team)
            ->from(route('quick-reports.create', ['event' => $event->id]))
            ->post(route('quick-reports.store'), [
                'event_id' => $event->id,
                'activity_date' => now()->toDateString(),
                'activity_start_time' => '12:00',
                'activity_end_time' => '08:00',
                'photo' => UploadedFile::fake()->image('invalid.jpg'),
            ])
            ->assertSessionHasErrors('activity_end_time');

        $this->assertDatabaseMissing('quick_reports', ['event_id' => $event->id]);
        $this->assertDatabaseMissing('attendances', [
            'event_id' => $event->id,
            'user_id' => $team->id,
        ]);
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

    public function test_non_team_quick_report_menu_opens_the_report_history(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();

        $this->actingAs($pic)
            ->get(route('quick-reports.index'))
            ->assertRedirect(route('quick-reports.history'));
    }

    public function test_team_index_shows_quick_reports_for_assigned_events(): void
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
        $other = QuickReport::create([
            'event_id' => $event->id,
            'user_id' => $pic->id,
            'title' => 'Laporan orang lain',
            'photo_path' => 'quick-reports/'.$event->id.'/other.jpg',
        ]);
        Storage::disk('local')->put($other->photo_path, 'photo');
        $media = $other->media()->create([
            'type' => 'photo',
            'path' => 'quick-reports/'.$event->id.'/other-media.jpg',
            'mime' => 'image/jpeg',
            'size' => 5,
            'original_name' => 'other-media.jpg',
        ]);
        Storage::disk('local')->put($media->path, 'photo');

        $response = $this->actingAs($team)->get(route('quick-reports.history'));

        $response->assertOk();
        $rows = collect($response->inertiaProps('reports.data'));
        $this->assertCount(2, $rows);
        $this->assertTrue($rows->pluck('id')->contains($own->id));
        $this->assertTrue($rows->pluck('id')->contains($other->id));

        $this->actingAs($team)
            ->get(route('quick-reports.show', $own))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('QuickReports/Show')
                ->where('report.id', $own->id));
        $this->actingAs($team)
            ->get(route('quick-reports.show', $other))
            ->assertOk();
        $this->actingAs($team)
            ->get(route('quick-reports.photo', $other))
            ->assertOk();
        $this->actingAs($team)
            ->get(route('quick-reports.media', [$other, $media]))
            ->assertOk();
    }

    public function test_quick_report_details_show_saved_kpis_and_attendance(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $team = User::where('email', 'team@indosakti.com')->firstOrFail();
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();
        $event = $this->createEvent($pic, 'EVT-QUICK-DETAIL-SAVED');
        EventTeamMember::create(['event_id' => $event->id, 'user_id' => $team->id]);
        $report = QuickReport::create([
            'event_id' => $event->id,
            'user_id' => $team->id,
            'title' => 'Detail laporan event',
            'sampling_target' => 420,
            'sampling_actual' => 385,
            'selling_target' => 1250000,
            'selling_actual' => 980000,
            'learning' => 'Pengunjung ramai setelah jam makan siang.',
        ]);
        Attendance::create([
            'event_id' => $event->id,
            'user_id' => $team->id,
            'planned_start' => '2030-07-12 08:00:00',
            'planned_end' => '2030-07-12 12:00:00',
            'check_in_at' => '2030-07-12 08:10:00',
            'check_out_at' => '2030-07-12 12:20:00',
            'status' => 'completed',
        ]);

        $response = $this->actingAs($team)->get(route('quick-reports.show', $report));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('QuickReports/Show')
                ->where('report.id', $report->id)
                ->where('report.event', $event->event_name)
                ->where('report.sampling_target', 420)
                ->where('report.sampling_actual', 385)
                ->where('report.selling_target', '1250000.00')
                ->where('report.selling_actual', '980000.00')
                ->where('report.activity_date', '2030-07-12')
                ->where('report.activity_start_time', '08:10')
                ->where('report.activity_end_time', '12:20')
                ->where('report.attendance_status', 'completed')
                ->where('report.learning', 'Pengunjung ramai setelah jam makan siang.'));
    }

    public function test_team_can_submit_multiple_photos_and_an_activity_video(): void
    {
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');
        $team = User::where('email', 'team@indosakti.com')->firstOrFail();
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();
        $event = $this->createEvent($pic, 'EVT-QUICK-MEDIA');
        EventTeamMember::create(['event_id' => $event->id, 'user_id' => $team->id]);

        $this->actingAs($team)->post(route('quick-reports.store'), [
            'event_id' => $event->id,
            'photos' => [
                UploadedFile::fake()->image('kegiatan-1.jpg'),
                UploadedFile::fake()->image('kegiatan-2.jpg'),
            ],
            'video' => UploadedFile::fake()->create('aktivitas.mp4', 2048, 'video/mp4'),
        ])->assertRedirect(route('quick-reports.index'));

        $report = QuickReport::query()->firstOrFail();

        $this->assertSame(2, $report->media()->where('type', 'photo')->count());
        $video = $report->media()->where('type', 'video')->firstOrFail();
        Storage::disk('local')->assertExists($video->path);

        $this->actingAs($team)
            ->get(route('quick-reports.media', [$report, $video]))
            ->assertOk();
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

        // The team member is not the owner and is out of scope: access is hidden with 404.
        $this->actingAs($team)
            ->get(route('quick-reports.photo', $report))
            ->assertNotFound();
    }

    private function createEvent(User $pic, string $eventCode): Event
    {
        return Event::create([
            'event_code' => $eventCode,
            'event_name' => 'Quick report event',
            'status' => EventStatus::ONGOING,
            'pic_id' => $pic->id,
            'event_date' => now()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '12:00',
        ]);
    }
}
