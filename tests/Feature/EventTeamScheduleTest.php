<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\EventTeamMember;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventTeamScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(DemoDataSeeder::class);
    }

    public function test_pic_can_assign_a_team_member_with_a_schedule(): void
    {
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();
        $teamLeader = User::where('email', 'tl@indosakti.com')->firstOrFail();
        $event = Event::create([
            'event_code' => 'EVT-SCHEDULE-'.uniqid(),
            'event_name' => 'Event Jadwal Tim',
            'status' => EventStatus::READY,
            'pic_id' => $pic->id,
            'event_date' => now()->addDay()->toDateString(),
        ]);

        $this->actingAs($pic)
            ->post(route('events.team.assign', $event), [
                'user_id' => $teamLeader->id,
                'position' => 'Team Leader',
                'schedule_date' => now()->addDay()->toDateString(),
                'report_time' => '07:30',
                'start_time' => '08:00',
                'end_time' => '12:00',
                'task' => 'Koordinasi sampling',
                'schedule_notes' => 'Bawa perlengkapan booth.',
            ])
            ->assertSessionHasNoErrors();

        $member = EventTeamMember::where('event_id', $event->id)
            ->where('user_id', $teamLeader->id)
            ->firstOrFail();

        $this->assertSame('07:30', $member->report_time);
        $this->assertSame('08:00', $member->start_time);
        $this->assertSame('12:00', $member->end_time);
        $this->assertSame('Koordinasi sampling', $member->task);
    }

    public function test_pic_can_update_a_team_member_schedule(): void
    {
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();
        $spg = User::where('email', 'spg@indosakti.com')->firstOrFail();
        $member = EventTeamMember::where('user_id', $spg->id)->firstOrFail();
        $event = $member->event;

        $this->actingAs($pic)
            ->patch(route('events.team.update', [$event, $member]), [
                'position' => 'SPG Senior',
                'schedule_date' => now()->addDays(2)->toDateString(),
                'report_time' => '09:00',
                'start_time' => '09:30',
                'end_time' => '15:00',
                'task' => 'Sampling lapangan',
                'schedule_notes' => 'Cek stok terlebih dahulu.',
            ])
            ->assertSessionHasNoErrors();

        $member->refresh();

        $this->assertSame('SPG Senior', $member->position);
        $this->assertSame('09:30', $member->start_time);
        $this->assertSame('15:00', $member->end_time);
        $this->assertSame('Sampling lapangan', $member->task);
    }

    public function test_team_member_schedule_must_have_a_valid_time_range(): void
    {
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();
        $spg = User::where('email', 'spg@indosakti.com')->firstOrFail();
        $member = EventTeamMember::where('user_id', $spg->id)->firstOrFail();

        $this->actingAs($pic)
            ->from(route('events.show', $member->event_id))
            ->patch(route('events.team.update', [$member->event, $member]), [
                'start_time' => '15:00',
                'end_time' => '09:00',
            ])
            ->assertRedirect(route('events.show', $member->event_id))
            ->assertSessionHasErrors('end_time');
    }

    public function test_team_dashboard_exposes_the_member_schedule(): void
    {
        $spg = User::where('email', 'spg@indosakti.com')->firstOrFail();
        $member = EventTeamMember::where('user_id', $spg->id)->firstOrFail();

        $response = $this->actingAs($spg)->get(route('team.index'));

        $response->assertOk();
        $groups = collect($response->inertiaProps('upcomingGroups'))
            ->merge($response->inertiaProps('pastGroups'));
        $events = $groups->flatMap(fn (array $brand): array => collect($brand['event_types'])
            ->flatMap(fn (array $eventType): array => collect($eventType['dates'])
                ->flatMap(fn (array $date): array => $date['events'])
                ->all())
            ->all());
        $row = $events->firstWhere('id', $member->event_id);

        $this->assertNotNull($row);
        $this->assertSame($member->position, $row['my_schedule']['position']);
        $this->assertSame($member->start_time, $row['my_schedule']['start_time']);
        $this->assertSame($member->task, $row['my_schedule']['task']);
    }
}
