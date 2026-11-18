<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\EventTeamMember;
use App\Models\QuickReport;
use App\Models\User;
use App\Models\Venue;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_dashboard_shows_location_shift_and_instructions(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $team = User::where('email', 'team@indosakti.com')->firstOrFail();
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();
        $venue = Venue::create(['name' => 'GOR Senayan']);

        $event = $this->createEvent($pic, 'EVT-TEAM-DASH-DETAIL', [
            'venue_id' => $venue->id,
            'event_date' => now()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '12:00',
            'notes' => 'Bawa ID card dan seragam.',
        ]);

        EventTeamMember::create(['event_id' => $event->id, 'user_id' => $team->id]);

        $response = $this->actingAs($team)->get(route('team.index'));

        $response->assertOk();
        $row = collect($this->flattenGroups($response->inertiaProps('upcomingGroups')))
            ->firstWhere('id', $event->id);

        $this->assertNotNull($row);
        $this->assertSame('GOR Senayan', $row['venue']);
        $this->assertSame($event->start_time, $row['start_time']);
        $this->assertSame($event->end_time, $row['end_time']);
        $this->assertSame('Bawa ID card dan seragam.', $row['notes']);
    }

    public function test_team_events_are_separated_into_upcoming_and_past_brand_groups(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $team = User::where('email', 'team@indosakti.com')->firstOrFail();
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();
        $past = $this->createEvent($pic, 'EVT-TEAM-GROUP-PAST', [
            'event_date' => now()->subDay()->toDateString(),
        ]);
        $upcoming = $this->createEvent($pic, 'EVT-TEAM-GROUP-UPCOMING', [
            'event_date' => now()->addDay()->toDateString(),
        ]);

        EventTeamMember::create(['event_id' => $past->id, 'user_id' => $team->id]);
        EventTeamMember::create(['event_id' => $upcoming->id, 'user_id' => $team->id]);

        $response = $this->actingAs($team)->get(route('team.index'));

        $response->assertOk();
        $upcomingRows = collect($this->flattenGroups($response->inertiaProps('upcomingGroups')));
        $pastRows = collect($this->flattenGroups($response->inertiaProps('pastGroups')));

        $this->assertTrue($upcomingRows->contains('id', $upcoming->id));
        $this->assertFalse($upcomingRows->contains('id', $past->id));
        $this->assertTrue($pastRows->contains('id', $past->id));
        $this->assertFalse($pastRows->contains('id', $upcoming->id));
    }

    public function test_team_quick_report_page_lists_pending_and_submitted_tasks_with_report_history(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $team = User::where('email', 'team@indosakti.com')->firstOrFail();
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();

        $pendingEvent = $this->createEvent($pic, 'EVT-TEAM-REPORT-PENDING', [
            'event_date' => now()->toDateString(),
        ]);
        $submittedEvent = $this->createEvent($pic, 'EVT-TEAM-REPORT-SUBMITTED', [
            'event_date' => now()->addDay()->toDateString(),
        ]);

        foreach ([$pendingEvent, $submittedEvent] as $event) {
            EventTeamMember::create(['event_id' => $event->id, 'user_id' => $team->id]);
        }

        QuickReport::create([
            'event_id' => $submittedEvent->id,
            'user_id' => $team->id,
            'title' => 'Laporan terkirim',
        ]);

        $response = $this->actingAs($team)->get(route('quick-reports.index'));

        $response->assertOk();
        $tasks = collect($response->inertiaProps('tasks'))->keyBy('id');

        $this->assertSame('pending', $tasks[$pendingEvent->id]['status']);
        $this->assertSame('Belum dikirim', $tasks[$pendingEvent->id]['status_label']);
        $this->assertSame('submitted', $tasks[$submittedEvent->id]['status']);
        $this->assertSame('Sudah dikirim', $tasks[$submittedEvent->id]['status_label']);
        $this->assertCount(1, $response->inertiaProps('events'));
        $this->assertSame($pendingEvent->id, $response->inertiaProps('events.0.id'));

        $history = $this->actingAs($team)->get(route('quick-reports.history'));
        $history->assertOk();
        $this->assertCount(1, $history->inertiaProps('reports.data'));
    }

    public function test_team_quick_report_tasks_include_only_assigned_reportable_events(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $team = User::where('email', 'team@indosakti.com')->firstOrFail();
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();

        $missing = $this->createEvent($pic, 'EVT-TEAM-REPORT-MISSING', [
            'event_date' => now()->toDateString(),
            'status' => EventStatus::ONGOING,
        ]);
        $reported = $this->createEvent($pic, 'EVT-TEAM-REPORT-READY', [
            'event_date' => now()->addDay()->toDateString(),
            'status' => EventStatus::READY,
        ]);
        $planning = $this->createEvent($pic, 'EVT-TEAM-REPORT-PLANNING', [
            'event_date' => now()->addDays(2)->toDateString(),
            'status' => EventStatus::PLANNING,
        ]);
        $unassigned = $this->createEvent($pic, 'EVT-TEAM-REPORT-UNASSIGNED', [
            'status' => EventStatus::ONGOING,
        ]);

        foreach ([$missing, $reported, $planning] as $event) {
            EventTeamMember::create(['event_id' => $event->id, 'user_id' => $team->id]);
        }

        QuickReport::create([
            'event_id' => $reported->id,
            'user_id' => $team->id,
            'title' => 'Laporan siap',
        ]);

        $response = $this->actingAs($team)->get(route('quick-reports.index'));

        $response->assertOk();
        $tasks = collect($response->inertiaProps('tasks'));

        $this->assertCount(2, $tasks);
        $this->assertSame([$missing->id, $reported->id], $tasks->pluck('id')->all());
        $this->assertNotContains($planning->id, $tasks->pluck('id'));
        $this->assertNotContains($unassigned->id, $tasks->pluck('id'));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createEvent(User $pic, string $code, array $attributes = []): Event
    {
        return Event::create([
            'event_code' => $code,
            'event_name' => 'Team dashboard event',
            'status' => EventStatus::ONGOING,
            'pic_id' => $pic->id,
            ...$attributes,
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $groups
     * @return array<int, array<string, mixed>>
     */
    private function flattenGroups(array $groups): array
    {
        return collect($groups)
            ->flatMap(fn (array $brand) => collect($brand['event_types'])
                ->flatMap(fn (array $type) => collect($type['dates'])
                    ->flatMap(fn (array $date) => $date['events'])))
            ->all();
    }
}
