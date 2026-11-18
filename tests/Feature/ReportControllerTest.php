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

class ReportControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_event_workspace_opens_the_event_report_summary(): void
    {
        $this->seedWorkspaceData();
        $adminEvent = User::where('email', 'admin.event@indosakti.com')->firstOrFail();

        $this->actingAs($adminEvent)
            ->get('/admin-event')
            ->assertRedirect(route('reports.by-event'));
    }

    public function test_admin_workspace_opens_the_event_report_summary(): void
    {
        $this->seedWorkspaceData();
        $admin = User::where('email', 'admin@indosakti.com')->firstOrFail();

        $this->actingAs($admin)
            ->get('/admin')
            ->assertRedirect(route('reports.by-event'));
    }

    public function test_report_summary_shows_only_the_latest_report_for_each_event(): void
    {
        $this->seedWorkspaceData();
        $adminEvent = User::where('email', 'admin.event@indosakti.com')->firstOrFail();
        $event = Event::whereHas('reports')->firstOrFail();
        $latestReport = $this->createReport(
            $event,
            User::where('email', 'team@indosakti.com')->firstOrFail(),
            625,
        );

        $response = $this->actingAs($adminEvent)->get('/reports/by-event');

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Reports/ByEvent'));

        $eventRows = collect($response->inertiaProps('eventGroups'))
            ->flatMap(fn (array $brand) => collect($brand['event_types'])
                ->flatMap(fn (array $type) => collect($type['dates'])
                    ->flatMap(fn (array $date) => $date['events'])))
            ->where('id', $event->id)
            ->values();

        $this->assertCount(1, $eventRows);
        $this->assertSame($latestReport->id, $eventRows->first()['report']['id']);
    }

    public function test_pic_cannot_view_a_report_for_an_event_assigned_to_another_pic(): void
    {
        $this->seedWorkspaceData();
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();
        $otherPic = User::factory()->create();
        $event = $this->createEvent($otherPic);
        $report = $this->createReport(
            $event,
            User::where('email', 'team@indosakti.com')->firstOrFail(),
            400,
        );

        $this->actingAs($pic)
            ->get(route('reports.show', $report))
            ->assertNotFound();
    }

    public function test_team_cannot_view_a_report_for_an_unassigned_event(): void
    {
        $this->seedWorkspaceData();
        $team = User::where('email', 'team@indosakti.com')->firstOrFail();
        $event = $this->createEvent(
            User::where('email', 'pic@indosakti.com')->firstOrFail(),
        );
        $report = $this->createReport(
            $event,
            User::where('email', 'admin.event@indosakti.com')->firstOrFail(),
            400,
        );

        $this->actingAs($team)
            ->get(route('reports.show', $report))
            ->assertNotFound();
    }

    public function test_team_cannot_create_a_report_for_an_unassigned_event(): void
    {
        $this->seedWorkspaceData();
        $team = User::where('email', 'team@indosakti.com')->firstOrFail();
        $event = $this->createEvent(
            User::where('email', 'pic@indosakti.com')->firstOrFail(),
        );

        $this->actingAs($team)
            ->post(route('reports.store'), [
                'event_id' => $event->id,
                'actual_sampling' => 120,
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('event_reports', ['event_id' => $event->id]);
    }

    public function test_team_report_form_preselects_the_assigned_event_from_the_schedule_link(): void
    {
        $this->seedWorkspaceData();
        $team = User::where('email', 'team@indosakti.com')->firstOrFail();
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();
        $event = $this->createEvent($pic);
        EventTeamMember::create(['event_id' => $event->id, 'user_id' => $team->id]);

        $response = $this->actingAs($team)
            ->get(route('reports.create', ['event' => $event->id]));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reports/Create')
                ->where('initialEventId', $event->id));

        $this->assertTrue(
            collect($response->inertiaProps('events'))->contains('id', $event->id),
        );
    }

    public function test_team_cannot_submit_another_users_draft_report(): void
    {
        $this->seedWorkspaceData();
        $team = User::where('email', 'team@indosakti.com')->firstOrFail();
        $event = Event::whereHas('teamMembers', fn ($query) => $query->where('user_id', $team->id))
            ->firstOrFail();
        $report = $this->createReport(
            $event,
            User::where('email', 'admin.event@indosakti.com')->firstOrFail(),
            400,
            ReportStatus::DRAFT,
        );

        $this->actingAs($team)
            ->post(route('reports.submit', $report))
            ->assertForbidden();

        $this->assertDatabaseHas('event_reports', [
            'id' => $report->id,
            'status' => ReportStatus::DRAFT->value,
        ]);
    }

    public function test_report_owner_can_submit_a_draft_report(): void
    {
        $this->seedWorkspaceData();
        $team = User::where('email', 'team@indosakti.com')->firstOrFail();
        $event = Event::whereHas('teamMembers', fn ($query) => $query->where('user_id', $team->id))
            ->firstOrFail();
        $report = $this->createReport($event, $team, 400, ReportStatus::DRAFT);

        $this->actingAs($team)
            ->post(route('reports.submit', $report))
            ->assertRedirect();

        $this->assertDatabaseHas('event_reports', [
            'id' => $report->id,
            'status' => ReportStatus::SUBMITTED->value,
            'submitted_by' => $team->id,
        ]);
    }

    public function test_admin_event_cannot_request_revision_for_a_draft_report(): void
    {
        $this->seedWorkspaceData();
        $adminEvent = User::where('email', 'admin.event@indosakti.com')->firstOrFail();
        $event = Event::firstOrFail();
        $report = $this->createReport($event, $adminEvent, 400, ReportStatus::DRAFT);

        $this->actingAs($adminEvent)
            ->post(route('reports.revision', $report), [
                'revision_note' => 'Mohon lengkapi bukti pendukung.',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('event_reports', [
            'id' => $report->id,
            'status' => ReportStatus::DRAFT->value,
        ]);
    }

    public function test_report_owner_can_resubmit_a_report_in_revision_status(): void
    {
        $this->seedWorkspaceData();
        $team = User::where('email', 'team@indosakti.com')->firstOrFail();
        $event = Event::whereHas('teamMembers', fn ($query) => $query->where('user_id', $team->id))
            ->firstOrFail();
        $report = $this->createReport($event, $team, 400, ReportStatus::REVISION);
        $report->update(['revision_note' => 'Mohon lengkapi realisasi sampling.']);

        $this->actingAs($team)
            ->post(route('reports.submit', $report))
            ->assertRedirect();

        $fresh = $report->fresh();
        $this->assertSame(ReportStatus::SUBMITTED->value, $fresh->status->value);
        $this->assertSame($team->id, $fresh->submitted_by);
        // The revision note stays visible as history after resubmission.
        $this->assertSame('Mohon lengkapi realisasi sampling.', $fresh->revision_note);
    }

    public function test_revision_request_requires_a_note_of_at_least_five_characters(): void
    {
        $this->seedWorkspaceData();
        $adminEvent = User::where('email', 'admin.event@indosakti.com')->firstOrFail();
        $event = Event::firstOrFail();
        $report = $this->createReport($event, User::where('email', 'team@indosakti.com')->firstOrFail(), 400);

        $this->actingAs($adminEvent)
            ->from(route('reports.show', $report))
            ->post(route('reports.revision', $report), [
                'revision_note' => 'abc',
            ])
            ->assertSessionHasErrors('revision_note');

        $this->assertDatabaseHas('event_reports', [
            'id' => $report->id,
            'status' => ReportStatus::SUBMITTED->value,
        ]);
    }

    public function test_revision_request_moves_a_submitted_report_to_revision(): void
    {
        $this->seedWorkspaceData();
        $adminEvent = User::where('email', 'admin.event@indosakti.com')->firstOrFail();
        $event = Event::firstOrFail();
        $report = $this->createReport($event, User::where('email', 'team@indosakti.com')->firstOrFail(), 400);

        $this->actingAs($adminEvent)
            ->from(route('reports.show', $report))
            ->post(route('reports.revision', $report), [
                'revision_note' => 'Mohon lampirkan foto bukti sampling.',
            ])
            ->assertSessionHasNoErrors();

        $fresh = $report->fresh();
        $this->assertSame(ReportStatus::REVISION->value, $fresh->status->value);
        $this->assertSame('Mohon lampirkan foto bukti sampling.', $fresh->revision_note);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'REVISION_REPORT',
            'record_id' => $report->id,
        ]);
    }

    public function test_cannot_verify_a_report_that_is_not_submitted(): void
    {
        $this->seedWorkspaceData();
        $adminEvent = User::where('email', 'admin.event@indosakti.com')->firstOrFail();
        $event = Event::firstOrFail();
        $report = $this->createReport($event, $adminEvent, 400, ReportStatus::DRAFT);

        $this->actingAs($adminEvent)
            ->post(route('reports.verify', $report))
            ->assertForbidden();

        $this->assertDatabaseHas('event_reports', [
            'id' => $report->id,
            'status' => ReportStatus::DRAFT->value,
        ]);
    }

    public function test_daily_report_groups_output_by_event_date(): void
    {
        $this->seedWorkspaceData();
        $adminEvent = User::where('email', 'admin.event@indosakti.com')->firstOrFail();
        $submitter = User::where('email', 'team@indosakti.com')->firstOrFail();

        $event = Event::create([
            'event_code' => 'EVT-DAILY-TEST',
            'event_name' => 'Event Rekap Harian',
            'event_date' => '2030-01-15',
            'sampling_target' => 500,
            'status' => EventStatus::COMPLETED,
        ]);

        $this->createReport($event, $submitter, 400);

        $response = $this->actingAs($adminEvent)->get('/reports/daily?date=2030-01-15');

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reports/Daily')
                ->where('summary.events', 1)
                ->where('summary.reported', 1)
                ->where('summary.missing', 0)
                ->where('summary.target', 500)
                ->where('summary.actual', 400)
                ->where('summary.achievement', 80)
                ->has('days', 1)
                ->where('days.0.date', '2030-01-15')
                ->where('days.0.events.0.event_code', 'EVT-DAILY-TEST')
                ->where('days.0.events.0.report_status', ReportStatus::SUBMITTED->value)
            );
    }

    public function test_daily_report_export_streams_a_csv(): void
    {
        $this->seedWorkspaceData();
        $adminEvent = User::where('email', 'admin.event@indosakti.com')->firstOrFail();
        $submitter = User::where('email', 'team@indosakti.com')->firstOrFail();

        $event = Event::create([
            'event_code' => 'EVT-DAILY-EXPORT',
            'event_name' => 'Event Export Harian',
            'event_date' => '2030-01-16',
            'sampling_target' => 300,
            'status' => EventStatus::COMPLETED,
        ]);

        $this->createReport($event, $submitter, 315);

        $response = $this->actingAs($adminEvent)->get('/reports/daily/export?date=2030-01-16');

        $response->assertOk();

        $csv = $response->streamedContent();
        $this->assertStringContainsString('Kode Event', $csv);
        $this->assertStringContainsString('EVT-DAILY-EXPORT', $csv);
        $this->assertStringContainsString('Event Export Harian', $csv);
    }

    private function seedWorkspaceData(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(DemoDataSeeder::class);
    }

    private function createEvent(User $pic): Event
    {
        return Event::create([
            'event_code' => 'EVT-UNASSIGNED-TEST',
            'event_name' => 'Event Tanpa Penugasan Tim',
            'status' => EventStatus::READY,
            'pic_id' => $pic->id,
        ]);
    }

    private function createReport(
        Event $event,
        User $submitter,
        int $actualSampling,
        ReportStatus $status = ReportStatus::SUBMITTED,
    ): EventReport {
        return EventReport::create([
            'event_id' => $event->id,
            'sampling_target' => 500,
            'actual_sampling' => $actualSampling,
            'status' => $status,
            'submitted_by' => $submitter->id,
            'submitted_at' => now(),
        ]);
    }
}
