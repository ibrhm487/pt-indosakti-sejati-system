<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Enums\ReportStatus;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Event;
use App\Models\EventReport;
use App\Models\EventTeamMember;
use App\Models\Personnel;
use App\Models\User;
use App\Models\Venue;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AttendancePayrollTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(DemoDataSeeder::class);
    }

    public function test_pic_cannot_assign_a_duplicate_team_member(): void
    {
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();
        $team = User::where('email', 'team@indosakti.com')->firstOrFail();
        $event = Event::firstOrFail();

        $this->actingAs($pic)
            ->from(route('events.show', $event))
            ->post(route('events.team.assign', $event), [
                'user_id' => $team->id,
                'position' => 'Sampler',
            ])
            ->assertRedirect(route('events.show', $event))
            ->assertSessionHasErrors('user_id');

        $this->assertSame(1, $event->teamMembers()->where('user_id', $team->id)->count());
    }

    public function test_pic_cannot_assign_duplicate_personnel(): void
    {
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();
        $event = Event::firstOrFail();
        $personnel = Personnel::where('is_active', true)->firstOrFail();

        EventTeamMember::create([
            'event_id' => $event->id,
            'personnel_id' => $personnel->id,
            'position' => 'SPG',
            'status' => 'assigned',
            'assigned_by' => $pic->id,
        ]);

        $this->actingAs($pic)
            ->from(route('events.show', $event))
            ->post(route('events.team.assign', $event), [
                'personnel_id' => $personnel->id,
                'position' => 'Crew',
            ])
            ->assertRedirect(route('events.show', $event))
            ->assertSessionHasErrors('user_id');

        $this->assertSame(1, $event->teamMembers()->where('personnel_id', $personnel->id)->count());
    }

    public function test_pic_cannot_assign_team_to_another_pic_event(): void
    {
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();
        $team = User::where('email', 'team@indosakti.com')->firstOrFail();
        $otherEvent = Event::create([
            'event_code' => 'EVT-TEST-'.uniqid(),
            'event_name' => 'Event PIC Lain '.uniqid(),
            'status' => EventStatus::READY,
            'pic_id' => User::factory()->create()->id,
        ]);

        $this->actingAs($pic)
            ->post(route('events.team.assign', $otherEvent), [
                'user_id' => $team->id,
                'position' => 'Sampler',
            ])
            ->assertForbidden();

        $this->assertSame(0, $otherEvent->teamMembers()->count());
    }

    public function test_check_in_before_the_event_date_is_rejected(): void
    {
        $team = User::where('email', 'team@indosakti.com')->firstOrFail();
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();
        $event = Event::create([
            'event_code' => 'EVT-TEST-'.uniqid(),
            'event_name' => 'Event Masa Depan '.uniqid(),
            'status' => EventStatus::READY,
            'pic_id' => $pic->id,
            'event_date' => now()->addDay(),
        ]);
        $attendance = Attendance::create([
            'event_id' => $event->id,
            'user_id' => $team->id,
            'status' => 'planned',
        ]);

        $this->actingAs($team)
            ->from(route('attendance.index'))
            ->post(route('attendance.check-in', $attendance))
            ->assertSessionHas('error');

        $this->assertNull($attendance->fresh()->check_in_at);
    }

    public function test_check_in_on_the_event_date_succeeds(): void
    {
        $team = User::where('email', 'team@indosakti.com')->firstOrFail();
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();
        $event = Event::create([
            'event_code' => 'EVT-TEST-'.uniqid(),
            'event_name' => 'Event Hari Ini '.uniqid(),
            'status' => EventStatus::ONGOING,
            'pic_id' => $pic->id,
            'event_date' => now()->toDateString(),
        ]);
        $attendance = Attendance::create([
            'event_id' => $event->id,
            'user_id' => $team->id,
            'status' => 'planned',
        ]);

        $this->actingAs($team)
            ->from(route('attendance.index'))
            ->post(route('attendance.check-in', $attendance))
            ->assertSessionHas('success');

        $fresh = $attendance->fresh();
        $this->assertNotNull($fresh->check_in_at);
        $this->assertSame('in_progress', $fresh->status);
    }

    public function test_zero_duration_attendance_is_not_eligible_for_pay(): void
    {
        $team = User::where('email', 'team@indosakti.com')->firstOrFail();
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();
        $event = Event::create([
            'event_code' => 'EVT-TEST-'.uniqid(),
            'event_name' => 'Event Durasi Nol '.uniqid(),
            'status' => EventStatus::COMPLETED,
            'pic_id' => $pic->id,
            'event_date' => now()->subDay()->toDateString(),
        ]);
        $same = now()->subHours(3);
        $attendance = Attendance::create([
            'event_id' => $event->id,
            'user_id' => $team->id,
            'check_in_at' => $same,
            'check_out_at' => $same,
            'status' => 'completed',
        ]);

        $this->assertFalse($attendance->qualifiesForPay());
        $this->assertSame(0, $attendance->durationMinutes());

        $admin = User::where('email', 'admin@indosakti.com')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('attendance.payroll', ['event_id' => $event->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Attendance/Payroll')
                ->where('summary.eligible_shifts', 0)
                ->where('summary.incomplete', 1)
                ->where('rows.0.qualifies', false)
                ->where('rows.0.duration_minutes', 0));
    }

    public function test_team_member_cannot_correct_attendance(): void
    {
        $team = User::where('email', 'team@indosakti.com')->firstOrFail();
        $attendance = Attendance::where('user_id', $team->id)->firstOrFail();

        $this->actingAs($team)
            ->patch(route('attendance.correct', $attendance), [
                'check_in_at' => now()->subHours(5)->toIso8601String(),
                'check_out_at' => now()->subHours(1)->toIso8601String(),
                'correction_note' => 'Jam salah input di lapangan.',
            ])
            ->assertForbidden();
    }

    public function test_pic_cannot_correct_attendance_of_another_pic_event(): void
    {
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();
        $otherPic = User::factory()->create();
        $otherTeam = User::factory()->create();
        $event = Event::create([
            'event_code' => 'EVT-TEST-'.uniqid(),
            'event_name' => 'Event PIC Lain '.uniqid(),
            'status' => EventStatus::COMPLETED,
            'pic_id' => $otherPic->id,
            'event_date' => now()->subDay()->toDateString(),
        ]);
        $attendance = Attendance::create([
            'event_id' => $event->id,
            'user_id' => $otherTeam->id,
            'check_in_at' => now()->subHours(5),
            'check_out_at' => now()->subHours(1),
            'status' => 'completed',
        ]);

        $this->actingAs($pic)
            ->patch(route('attendance.correct', $attendance), [
                'check_in_at' => now()->subHours(6)->toIso8601String(),
                'check_out_at' => now()->subHours(2)->toIso8601String(),
                'correction_note' => 'Koreksi lintas PIC.',
            ])
            ->assertForbidden();

        $this->assertNull($attendance->fresh()->correction_note);
        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'CORRECT_ATTENDANCE',
            'record_id' => $attendance->id,
        ]);
    }

    public function test_correcting_attendance_records_an_audit_trail_and_recomputes_status(): void
    {
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();
        $attendance = Attendance::firstOrFail();
        $attendance->update([
            'check_in_at' => now()->setTime(8, 0),
            'check_out_at' => now()->setTime(12, 0),
            'status' => 'completed',
        ]);

        $this->actingAs($pic)
            ->from(route('attendance.index'))
            ->patch(route('attendance.correct', $attendance), [
                'check_in_at' => now()->setTime(7, 30)->toIso8601String(),
                'check_out_at' => now()->setTime(13, 0)->toIso8601String(),
                'correction_note' => 'Jam pulang terlambat dicatat manual.',
            ])
            ->assertRedirect(route('attendance.index'))
            ->assertSessionHasNoErrors();

        $fresh = $attendance->fresh();
        $this->assertSame(7, $fresh->check_in_at->hour);
        $this->assertSame(13, $fresh->check_out_at->hour);
        $this->assertSame('completed', $fresh->status);
        $this->assertSame('Jam pulang terlambat dicatat manual.', $fresh->correction_note);

        $log = AuditLog::where('action', 'CORRECT_ATTENDANCE')
            ->where('record_type', Attendance::class)
            ->where('record_id', $attendance->id)
            ->firstOrFail();

        $this->assertSame(8, (int) date('G', strtotime($log->old_values['check_in_at'])));
        $this->assertSame('Jam pulang terlambat dicatat manual.', $log->new_values['correction_note']);
    }

    public function test_correction_rejects_check_out_before_check_in(): void
    {
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();
        $attendance = Attendance::firstOrFail();
        $attendance->update([
            'check_in_at' => now()->setTime(8, 0),
            'check_out_at' => now()->setTime(12, 0),
            'status' => 'completed',
        ]);

        $this->actingAs($pic)
            ->from(route('attendance.index'))
            ->patch(route('attendance.correct', $attendance), [
                'check_in_at' => now()->setTime(12, 0)->toIso8601String(),
                'check_out_at' => now()->setTime(8, 0)->toIso8601String(),
                'correction_note' => 'Tukar jam secara tidak sengaja.',
            ])
            ->assertSessionHasErrors('check_out_at');

        $this->assertSame(8, $attendance->fresh()->check_in_at->hour);
    }

    public function test_payroll_summary_breaks_down_by_user(): void
    {
        $admin = User::where('email', 'admin@indosakti.com')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('attendance.payroll'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Attendance/Payroll')
                ->has('summary.by_user')
                ->where('summary.by_user.0.user', 'Tim Lapangan')
                ->where('summary.by_user.0.eligible_shifts', 2)
                ->where('summary.by_user.0.total', 2 * 125000)
                ->has('rows.0.event')
                ->has('rows.0.duration_minutes'));
    }

    public function test_events_index_filters_by_brand(): void
    {
        $admin = User::where('email', 'admin@indosakti.com')->firstOrFail();
        $milo = Brand::where('slug', 'milo')->firstOrFail();
        $venue = Venue::where('name', 'SDN 01 Menteng')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('events.index', ['brand_id' => $milo->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Events/Index')
                ->where('filters.brand_id', (string) $milo->id)
                ->has('events.data', 1)
                ->where('events.data.0.brand', $milo->name)
                ->has('calendarEvents', 1));

        $this->actingAs($admin)
            ->get(route('events.index', ['venue_id' => $venue->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Events/Index')
                ->has('events.data', 1)
                ->where('events.data.0.venue', $venue->name));
    }

    public function test_event_summary_reports_no_data_until_a_report_is_verified(): void
    {
        $adminEvent = User::where('email', 'admin.event@indosakti.com')->firstOrFail();
        $event = Event::firstOrFail();

        $this->actingAs($adminEvent)
            ->get(route('events.show', $event))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Events/Show')
                ->where('summary.has_data', false)
                ->where('summary.realisasi', null));

        $report = EventReport::where('event_id', $event->id)->firstOrFail();
        $report->update([
            'status' => ReportStatus::VERIFIED,
            'verified_by' => $adminEvent->id,
            'verified_at' => now(),
        ]);

        $this->actingAs($adminEvent)
            ->get(route('events.show', $event))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Events/Show')
                ->where('summary.has_data', true)
                ->where('summary.target', 500)
                ->where('summary.realisasi', 450)
                ->where('summary.achievement', 90));
    }
}
