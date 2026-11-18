<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Enums\PermitStatus;
use App\Models\Brand;
use App\Models\Event;
use App\Models\EventTeamMember;
use App\Models\EventType;
use App\Models\Fjp;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SystemUserSimulationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(DemoDataSeeder::class);
    }

    public function test_permitter_sets_team_before_pic_assigns_team_members(): void
    {
        $permitter = $this->user('permitter@indosakti.com');
        $pic = $this->user('pic@indosakti.com');
        $teamLeader = $this->user('tl@indosakti.com');
        $spg = $this->user('spg@indosakti.com');
        $brand = Brand::where('slug', 'milo')->firstOrFail();
        $type = EventType::where('slug', 'milo-school-sampling')->firstOrFail();
        $eventDate = now()->addDays(14)->toDateString();

        $this->actingAs($permitter)
            ->post(route('fjp.store'), [
                'brand_id' => $brand->id,
                'event_type_id' => $type->id,
                'school_name' => 'SDN Simulasi 01',
                'npsn' => '20199999',
                'event_date' => $eventDate,
                'start_time' => '08:00',
                'end_time' => '12:00',
                'sampling_target' => 120,
                'jug_required' => true,
            ])
            ->assertSessionHasNoErrors();

        $fjp = Fjp::where('npsn', '20199999')->firstOrFail();
        $secondFjp = $this->newFjpWithoutVenue();
        $secondFjp->update([
            'school_name' => 'SDN Simulasi 02',
            'npsn' => '20199998',
            'event_date' => $eventDate,
        ]);

        $fjp->update(['status' => Fjp::STATUS_CONFIRM]);
        $secondFjp->update(['status' => Fjp::STATUS_CONFIRM]);

        foreach ([$fjp, $secondFjp] as $scheduledFjp) {
            $this->actingAs($permitter)
                ->patch(route('fjp.inline', $scheduledFjp), ['team_number' => 1])
                ->assertSessionHasNoErrors();
        }

        $this->actingAs($pic)
            ->post(route('fjp.team-group'), [
                'fjp_ids' => [$fjp->id, $secondFjp->id],
                'team_leader_id' => $teamLeader->id,
                'spg_user_id' => $spg->id,
            ])
            ->assertSessionHasNoErrors();

        $fjp->refresh();
        $this->assertSame(PermitStatus::DRAFT, $fjp->permit_status);
        $this->assertSame(Fjp::STATUS_CONFIRM, $fjp->status);

        $event = Event::where('fjp_id', $fjp->id)->firstOrFail();
        $this->assertSame(EventStatus::TEAM_ASSIGNMENT, $event->status);
        $this->assertSame($pic->id, $event->pic_id);
        $this->assertNotNull($event->venue_id);
        $this->assertTrue($event->venue->is_school);
        $this->assertSame($fjp->brand_id, $event->brand_id);
        $this->assertSame($fjp->event_type_id, $event->event_type_id);
        $this->assertSame($fjp->event_date->toDateString(), $event->event_date->toDateString());
        $this->assertSame(2, $event->teamMembers()->count());

        $this->actingAs($pic)->get(route('events.show', $event))->assertOk();

        $teamPage = $this->actingAs($teamLeader)->get(route('team.index'))->assertOk();
        $teamEvent = $this->groupedEvents($teamPage->viewData('page')['props']['upcomingGroups'])
            ->firstWhere('id', $event->id);
        $this->assertNotNull($teamEvent);
        $this->assertSame('Team Leader', $teamEvent['my_schedule']['position']);
        $this->assertSame($fjp->event_date->toDateString(), $teamEvent['my_schedule']['schedule_date']);
        $this->assertSame($fjp->start_time, $teamEvent['my_schedule']['start_time']);
    }

    public function test_pic_assigns_team_leader_and_spg_with_schedules_that_the_team_can_see(): void
    {
        $pic = $this->user('pic@indosakti.com');
        $teamLeader = $this->user('tl@indosakti.com');
        $spg = $this->user('spg@indosakti.com');
        $event = $this->scheduledEvent($pic);

        foreach ([
            [$teamLeader, 'Team Leader', '07:30', '08:00', '12:00', 'Koordinasi booth'],
            [$spg, 'SPG', '07:30', '08:00', '12:00', 'Sampling dan edukasi produk'],
        ] as [$member, $position, $report, $start, $end, $task]) {
            $this->actingAs($pic)
                ->post(route('events.team.assign', $event), [
                    'user_id' => $member->id,
                    'position' => $position,
                    'schedule_date' => $event->event_date->toDateString(),
                    'report_time' => $report,
                    'start_time' => $start,
                    'end_time' => $end,
                    'task' => $task,
                ])
                ->assertSessionHasNoErrors();
        }

        $member = EventTeamMember::where('event_id', $event->id)
            ->where('user_id', $spg->id)
            ->firstOrFail();

        $this->actingAs($pic)
            ->patch(route('events.team.update', [$event, $member]), [
                'start_time' => '09:00',
                'end_time' => '15:00',
                'task' => 'Sampling lapangan (revisi PIC)',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('09:00', $member->fresh()->start_time);

        $teamPage = $this->actingAs($spg)->get(route('team.index'))->assertOk();
        $row = $this->groupedEvents($teamPage->viewData('page')['props']['upcomingGroups'])
            ->firstWhere('id', $event->id);

        $this->assertNotNull($row);
        $this->assertSame('SPG', $row['my_schedule']['position']);
        $this->assertSame('09:00', $row['my_schedule']['start_time']);
        $this->assertSame('Sampling lapangan (revisi PIC)', $row['my_schedule']['task']);
    }

    public function test_team_leader_uploads_photos_and_a_video_then_previews_them_inline(): void
    {
        Storage::fake('local');

        $pic = $this->user('pic@indosakti.com');
        $teamLeader = $this->user('tl@indosakti.com');
        $event = $this->scheduledEvent($pic, $teamLeader);

        $this->actingAs($teamLeader)
            ->post(route('documents.store'), [
                'documentable_type' => 'event',
                'documentable_id' => $event->id,
                'category' => 'video',
                'files' => [
                    UploadedFile::fake()->image('kegiatan-1.jpg'),
                    UploadedFile::fake()->image('kegiatan-2.jpg'),
                    UploadedFile::fake()->create('kegiatan.mp4', 2048, 'video/mp4'),
                ],
            ])
            ->assertSessionHasNoErrors();

        $documents = $event->documents()->latest('id')->take(3)->get();

        $this->assertCount(3, $documents);
        Storage::disk('local')->assertExists($documents->first()->path);

        $video = $documents->firstWhere(fn ($document) => str_ends_with($document->original_name, '.mp4'));

        $this->assertNotNull($video);

        $response = $this->actingAs($teamLeader)
            ->get(route('documents.preview', $video))
            ->assertOk();

        $this->assertStringContainsString('inline', (string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString('mp4', (string) $response->headers->get('content-type'));
    }

    public function test_team_leader_quick_report_options_automatically_expose_the_event_variant(): void
    {
        $teamLeader = $this->user('tl@indosakti.com');

        $events = $this->actingAs($teamLeader)
            ->get(route('quick-reports.create'))
            ->assertOk()
            ->inertiaProps('events');

        $variants = collect($events)->pluck('variant')->unique()->values();

        $this->assertNotEmpty($events);
        $this->assertTrue($variants->every(fn (string $variant) => in_array($variant, ['school', 'instore', 'general'], true)));
        $this->assertTrue(collect($events)->every(
            fn (array $event) => $event['event_type_slug'] !== null && $event['event_type_name'] !== null,
        ));
        $this->assertTrue($variants->contains('school') || $variants->contains('instore'));
    }

    public function test_each_persona_can_open_their_own_workspace(): void
    {
        $workspaces = [
            'permitter@indosakti.com' => 'workspace.permitter',
            'pic@indosakti.com' => 'workspace.pic',
            'admin@indosakti.com' => 'workspace.admin',
            'admin.event@indosakti.com' => 'workspace.admin-event',
            'tl@indosakti.com' => 'workspace.tim',
            'spg@indosakti.com' => 'workspace.tim',
            'warehouse@indosakti.com' => 'workspace.gudang',
            'kitchen@indosakti.com' => 'workspace.dapur',
        ];

        foreach ($workspaces as $email => $route) {
            $this->actingAs($this->user($email))
                ->followingRedirects()
                ->get(route($route))
                ->assertOk();
        }
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    /**
     * @param  array<int, array<string, mixed>>  $groups
     * @return Collection<int, array<string, mixed>>
     */
    private function groupedEvents(array $groups): Collection
    {
        return collect($groups)
            ->flatMap(fn (array $brandGroup) => collect($brandGroup['event_types'])
                ->flatMap(fn (array $typeGroup) => collect($typeGroup['dates'])
                    ->flatMap(fn (array $dateGroup) => $dateGroup['events'])))
            ->values();
    }

    private function newFjpWithoutVenue(): Fjp
    {
        $brand = Brand::where('slug', 'milo')->firstOrFail();
        $type = EventType::where('slug', 'milo-school-sampling')->firstOrFail();

        return Fjp::create([
            'fjp_number' => Fjp::generateNumber(),
            'event_name' => 'Simulasi FJP '.uniqid(),
            'school_name' => 'SDN Simulasi 01',
            'npsn' => '20199999',
            'brand_id' => $brand->id,
            'event_type_id' => $type->id,
            'event_date' => now()->addDays(14)->toDateString(),
            'start_time' => '08:00',
            'end_time' => '12:00',
            'sampling_target' => 120,
            'jug_required' => true,
            'permit_status' => PermitStatus::DRAFT,
            'status' => 'draft',
        ]);
    }

    private function scheduledEvent(User $pic, ?User $teamLeader = null): Event
    {
        $event = Event::create([
            'event_code' => 'EVT-SIM-'.uniqid(),
            'event_name' => 'Simulasi Event Pengguna',
            'status' => EventStatus::READY,
            'pic_id' => $pic->id,
            'event_date' => now()->addDays(2)->toDateString(),
            'start_time' => '08:00',
            'end_time' => '15:00',
        ]);

        if ($teamLeader) {
            $event->teamMembers()->create([
                'user_id' => $teamLeader->id,
                'position' => 'Team Leader',
                'schedule_date' => $event->event_date->toDateString(),
                'report_time' => '07:30',
                'start_time' => '08:00',
                'end_time' => '12:00',
                'task' => 'Koordinasi booth',
                'assigned_by' => $pic->id,
            ]);
        }

        return $event;
    }
}
