<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Enums\PermitStatus;
use App\Enums\VenueStatus;
use App\Models\Attendance;
use App\Models\Brand;
use App\Models\Event;
use App\Models\EventTeamMember;
use App\Models\EventType;
use App\Models\Fjp;
use App\Models\Personnel;
use App\Models\Role;
use App\Models\User;
use App\Models\Venue;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FjpEventCreationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(DemoDataSeeder::class);
    }

    public function test_creating_an_fjp_provisions_a_school_venue_without_creating_an_event(): void
    {
        $permitter = $this->user('permitter@indosakti.com');
        [$brand, $type] = $this->brandAndSchoolType('milo');

        $this->actingAs($permitter)
            ->post(route('fjp.store'), [
                'brand_id' => $brand->id,
                'event_type_id' => $type->id,
                'school_name' => 'SDN Baru Uji',
                'npsn' => '20999001',
                'address' => 'Jl. Pendidikan No. 1',
                'event_date' => now()->addDays(7)->toDateString(),
                'start_time' => '08:00',
                'end_time' => '12:00',
                'sampling_target' => 250,
            ])
            ->assertSessionHasNoErrors();

        $fjp = Fjp::where('npsn', '20999001')->firstOrFail();
        $this->assertSame(Fjp::STATUS_PLAN, $fjp->status);
        $this->assertNotNull($fjp->venue_id);

        $venue = Venue::findOrFail($fjp->venue_id);
        $this->assertSame('SDN Baru Uji', $venue->name);
        $this->assertSame('20999001', $venue->npsn);
        $this->assertTrue($venue->is_school);
        $this->assertSame('Jl. Pendidikan No. 1', $venue->address);
        $this->assertSame(VenueStatus::PENDING, $venue->status);

        $this->assertDatabaseMissing('events', ['fjp_id' => $fjp->id]);
    }

    public function test_creating_an_fjp_reuses_an_existing_school_venue_by_npsn(): void
    {
        $permitter = $this->user('permitter@indosakti.com');
        [$brand, $type] = $this->brandAndSchoolType('milo');
        $existing = Venue::create([
            'name' => 'SDN Lama',
            'npsn' => '20999002',
            'is_school' => true,
            'address' => 'Jl. Lama',
        ]);
        $this->actingAs($permitter)
            ->post(route('fjp.store'), [
                'brand_id' => $brand->id,
                'event_type_id' => $type->id,
                'school_name' => 'Nama Sekolah Berbeda',
                'npsn' => '20999002',
            ])
            ->assertSessionHasNoErrors();

        $fjp = Fjp::where('npsn', '20999002')->firstOrFail();
        $this->assertSame($existing->id, $fjp->venue_id);
        $this->assertSame(1, Venue::where('npsn', '20999002')->count());
        $this->assertDatabaseMissing('events', ['fjp_id' => $fjp->id]);
    }

    public function test_updating_an_fjp_fills_missing_venue_location_without_overwriting_existing_values(): void
    {
        $permitter = $this->user('permitter@indosakti.com');
        $venue = Venue::create([
            'name' => 'SDN Venue Terkait',
            'npsn' => '20999012',
            'is_school' => true,
            'address' => 'Alamat disetujui PIC',
            'city' => 'Kota disetujui PIC',
        ]);
        $fjp = $this->createFjp([
            'venue_id' => $venue->id,
            'address' => 'Alamat dari FJP',
            'city' => 'Kota dari FJP',
            'province' => 'Provinsi dari FJP',
        ]);

        $this->actingAs($permitter)
            ->put(route('fjp.update', $fjp), [
                'address' => 'Alamat FJP terbaru',
                'city' => 'Kota FJP terbaru',
                'province' => 'Provinsi FJP terbaru',
            ])
            ->assertRedirect(route('fjp.show', $fjp));

        $venue->refresh();
        $this->assertSame('Alamat disetujui PIC', $venue->address);
        $this->assertSame('Kota disetujui PIC', $venue->city);
        $this->assertSame('Provinsi FJP terbaru', $venue->province);
    }

    public function test_pic_assigning_a_team_group_creates_events_and_assigns_both_team_roles(): void
    {
        $pic = $this->user('pic@indosakti.com');
        $teamUsers = $this->createTeamUsers(2);
        $venue = Venue::create([
            'name' => 'SDN Venue Pending Tetap',
            'npsn' => '20999197',
            'is_school' => true,
            'status' => VenueStatus::PENDING,
        ]);
        $fjp = $this->createFjp([
            'status' => Fjp::STATUS_CONFIRM,
            'venue_id' => $venue->id,
        ]);
        $secondFjp = $this->createFjp([
            'event_date' => $fjp->event_date?->toDateString(),
            'npsn' => '20101998',
            'status' => Fjp::STATUS_CONFIRM,
        ]);
        $this->permitterAssignsTeamGroup([$fjp, $secondFjp]);

        $this->actingAs($pic)
            ->post(route('fjp.team-group'), [
                'fjp_ids' => [$fjp->id, $secondFjp->id],
                'team_leader_id' => $teamUsers[0]->id,
                'spg_user_id' => $teamUsers[1]->id,
            ])
            ->assertSessionHasNoErrors();

        $fjp->refresh();
        $this->assertSame(Fjp::STATUS_CONFIRM, $fjp->status);
        $event = Event::where('fjp_id', $fjp->id)->firstOrFail();
        $this->assertSame(VenueStatus::PENDING, $event->venue->status);
        $this->assertSame(VenueStatus::PENDING, $venue->fresh()->status);
        $this->assertSame(2, $event->teamMembers()->count());
        $this->assertSame('Team Leader', $event->teamMembers()->where('user_id', $teamUsers[0]->id)->value('position'));
        $this->assertSame('SPG', $event->teamMembers()->where('user_id', $teamUsers[1]->id)->value('position'));
    }

    public function test_pic_team_assignment_uses_fjp_schedule_and_school_target_for_event_and_team(): void
    {
        $pic = $this->user('pic@indosakti.com');
        $teamUsers = $this->createTeamUsers(2);
        [$brand, $type] = $this->brandAndSchoolType('milo');
        $fjp = $this->createFjp([
            'brand_id' => $brand->id,
            'event_type_id' => $type->id,
            'status' => Fjp::STATUS_CONFIRM,
            'school_name' => 'SDN Event Baru',
            'npsn' => '20999003',
            'event_date' => now()->addDays(9)->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '15:00:00',
            'jumlah_siswa_sekolah' => 420,
            'sampling_target' => 300,
        ]);
        $secondFjp = $this->createFjp([
            'brand_id' => $brand->id,
            'event_type_id' => $type->id,
            'event_date' => $fjp->event_date?->toDateString(),
            'npsn' => '20999004',
            'status' => Fjp::STATUS_CONFIRM,
        ]);
        $this->permitterAssignsTeamGroup([$fjp, $secondFjp]);

        $this->actingAs($pic)
            ->post(route('fjp.team-group'), [
                'fjp_ids' => [$fjp->id, $secondFjp->id],
                'team_leader_id' => $teamUsers[0]->id,
                'spg_user_id' => $teamUsers[1]->id,
            ])
            ->assertSessionHasNoErrors();

        $fjp->refresh();
        $event = Event::where('fjp_id', $fjp->id)->firstOrFail();

        $this->assertSame(EventStatus::TEAM_ASSIGNMENT, $event->status);
        $this->assertSame($fjp->venue_id, $event->venue_id);
        $this->assertSame($brand->id, $event->brand_id);
        $this->assertSame($type->id, $event->event_type_id);
        $this->assertSame($fjp->event_date?->toDateString(), $event->event_date?->toDateString());
        $this->assertSame('09:00', substr($event->start_time, 0, 5));
        $this->assertSame('15:00', substr($event->end_time, 0, 5));
        $this->assertSame(420, $event->sampling_target);
        $this->assertSame($pic->id, $event->pic_id);
        $this->assertSame($pic->id, $event->created_by);
        $this->assertStringContainsString('SDN Event Baru', $event->event_name);
        $members = $event->teamMembers()->orderBy('position')->get();
        $this->assertCount(2, $members);
        $this->assertSame($event->event_date?->toDateString(), $members->first()->schedule_date?->toDateString());
        $this->assertSame('09:00', $members->first()->start_time);
        $this->assertSame('15:00', $members->first()->end_time);
    }

    public function test_pic_can_assign_multiple_same_day_schools_to_one_team_group(): void
    {
        $pic = $this->user('pic@indosakti.com');
        $teamUsers = $this->createTeamUsers(2);
        $eventDate = now()->addDays(12)->toDateString();
        $firstFjp = $this->createFjp([
            'permit_status' => PermitStatus::SUBMITTED,
            'status' => Fjp::STATUS_CONFIRM,
            'event_date' => $eventDate,
            'school_name' => 'SDN Kelompok 1',
            'jumlah_siswa_sekolah' => 600,
        ]);
        $secondFjp = $this->createFjp([
            'permit_status' => PermitStatus::SUBMITTED,
            'status' => Fjp::STATUS_CONFIRM,
            'event_date' => $eventDate,
            'school_name' => 'SDN Kelompok 2',
            'jumlah_siswa_sekolah' => 450,
        ]);
        $this->permitterAssignsTeamGroup([$firstFjp, $secondFjp]);

        $this->actingAs($pic)
            ->from(route('fjp.index'))
            ->post(route('fjp.team-group'), [
                'fjp_ids' => [$firstFjp->id, $secondFjp->id],
                'team_leader_id' => $teamUsers[0]->id,
                'spg_user_id' => $teamUsers[1]->id,
            ])
            ->assertRedirect(route('fjp.index'))
            ->assertSessionHasNoErrors();

        $events = Event::query()->whereIn('fjp_id', [$firstFjp->id, $secondFjp->id])->get();

        $this->assertCount(2, $events);
        $this->assertCount(1, $events->pluck('team_group_id')->unique());
        $this->assertNotNull($events->first()->team_group_id);
        $this->assertSame([450, 600], $events->pluck('sampling_target')->sort()->values()->all());
        $this->assertSame(2, $events->first()->teamMembers()->count());
        $this->assertSame(PermitStatus::SUBMITTED, $firstFjp->fresh()->permit_status);
        $this->assertSame(PermitStatus::SUBMITTED, $secondFjp->fresh()->permit_status);

        $this->actingAs($teamUsers[0])
            ->get(route('team.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Team/Index')
                ->where('upcomingGroups.0.event_types.0.dates.0.count', 2)
                ->where('upcomingGroups.0.event_types.0.dates.0.events.0.team_group_count', 2)
                ->where('upcomingGroups.0.event_types.0.dates.0.events.0.team_group_sampling_target', 1050)
            );
    }

    public function test_pic_cannot_assign_a_team_to_an_unconfirmed_fjp(): void
    {
        $pic = $this->user('pic@indosakti.com');
        $teamUsers = $this->createTeamUsers(2);
        $fjp = $this->createFjp(['status' => Fjp::STATUS_PLAN]);
        $plannedTeamGroupId = (string) Str::uuid();
        $fjp->update([
            'team_group_id' => $plannedTeamGroupId,
            'team_group_label' => 'Tim 1',
        ]);
        $confirmedFjp = $this->createFjp([
            'event_date' => $fjp->event_date?->toDateString(),
            'npsn' => '20999198',
            'status' => Fjp::STATUS_CONFIRM,
            'team_group_id' => $plannedTeamGroupId,
            'team_group_label' => 'Tim 1',
        ]);

        $this->actingAs($pic)
            ->from(route('fjp.index'))
            ->post(route('fjp.team-group'), [
                'fjp_ids' => [$fjp->id, $confirmedFjp->id],
                'team_leader_id' => $teamUsers[0]->id,
                'spg_user_id' => $teamUsers[1]->id,
            ])
            ->assertRedirect(route('fjp.index'))
            ->assertSessionHasErrors([
                'fjp_id' => 'Tim hanya dapat dibagikan setelah status FJP Confirm.',
            ]);

        $this->assertDatabaseMissing('events', ['fjp_id' => $fjp->id]);
        $this->assertDatabaseMissing('events', ['fjp_id' => $confirmedFjp->id]);
        $this->assertSame(Fjp::STATUS_PLAN, $fjp->fresh()->status);
    }

    public function test_group_assignment_rejects_schools_with_different_event_dates(): void
    {
        $pic = $this->user('pic@indosakti.com');
        $firstFjp = $this->createFjp([
            'permit_status' => PermitStatus::SUBMITTED,
            'status' => Fjp::STATUS_CONFIRM,
            'event_date' => now()->addDays(12)->toDateString(),
            'team_group_id' => 'team-group-different-date',
            'team_group_label' => 'Tim 1',
        ]);
        $secondFjp = $this->createFjp([
            'permit_status' => PermitStatus::SUBMITTED,
            'status' => Fjp::STATUS_CONFIRM,
            'event_date' => now()->addDays(13)->toDateString(),
            'team_group_id' => 'team-group-different-date',
            'team_group_label' => 'Tim 1',
        ]);

        $this->actingAs($pic)
            ->from(route('fjp.index'))
            ->post(route('fjp.team-group'), [
                'fjp_ids' => [$firstFjp->id, $secondFjp->id],
                'team_leader_id' => $this->user('tl@indosakti.com')->id,
                'spg_user_id' => $this->user('spg@indosakti.com')->id,
            ])
            ->assertRedirect(route('fjp.index'))
            ->assertSessionHasErrors('fjp_ids');

        $this->assertSame(0, Event::query()->whereIn('fjp_id', [$firstFjp->id, $secondFjp->id])->count());
        $this->assertSame(PermitStatus::SUBMITTED, $firstFjp->fresh()->permit_status);
        $this->assertSame(PermitStatus::SUBMITTED, $secondFjp->fresh()->permit_status);
    }

    public function test_pic_can_reschedule_seven_jabodetabek_schools_across_three_team_groups(): void
    {
        $this->freezeTime();
        $pic = $this->user('pic@indosakti.com');
        $teamUsers = $this->createTeamUsers(6);
        $fjps = collect(range(0, 6))->map(fn (int $index): Fjp => $this->createFjp([
            'school_name' => 'SDN Jakarta Jadwal '.$index,
            'npsn' => '2010100'.(10 + $index),
            'city' => $index === 6 ? 'Kab. Bogor' : 'Jakarta Pusat',
            'province' => $index === 6 ? 'Jawa Barat' : 'DKI Jakarta',
            'event_date' => today()->addDays($index < 3 ? 2 : ($index < 5 ? 3 : 4))->toDateString(),
            'permit_status' => $index < 5 ? PermitStatus::APPROVED : PermitStatus::SUBMITTED,
            'status' => Fjp::STATUS_CONFIRM,
            'jumlah_siswa_sekolah' => 300 + $index,
        ]))->all();
        $targetDate = today()->addDay()->toDateString();
        $groups = [
            [
                'fjp_ids' => collect(array_slice($fjps, 0, 3))->pluck('id')->all(),
                'team_leader_id' => $teamUsers[0]->id,
                'spg_user_id' => $teamUsers[1]->id,
            ],
            [
                'fjp_ids' => collect(array_slice($fjps, 3, 2))->pluck('id')->all(),
                'team_leader_id' => $teamUsers[2]->id,
                'spg_user_id' => $teamUsers[3]->id,
            ],
            [
                'fjp_ids' => collect(array_slice($fjps, 5, 2))->pluck('id')->all(),
                'team_leader_id' => $teamUsers[4]->id,
                'spg_user_id' => $teamUsers[5]->id,
            ],
        ];
        foreach ($groups as $index => $group) {
            $this->permitterAssignsTeamGroup(
                Fjp::query()->whereIn('id', $group['fjp_ids'])->get(),
                $index + 1,
            );
        }

        $this->actingAs($pic)
            ->get(route('fjp.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('teamScheduleDate', $targetDate)
                ->has('teamScheduleCandidates', 7)
                ->where('teamScheduleCandidates.0.id', $fjps[0]->id));

        $this->actingAs($pic)
            ->from(route('fjp.index'))
            ->post(route('fjp.team-schedule'), [
                'event_date' => $targetDate,
                'groups' => $groups,
            ])
            ->assertRedirect(route('fjp.index'))
            ->assertSessionHasNoErrors();

        foreach ($groups as $group) {
            $groupEvents = Event::query()->whereIn('fjp_id', $group['fjp_ids'])->get();

            $this->assertSame(
                count($group['fjp_ids']),
                Fjp::query()
                    ->whereIn('id', $group['fjp_ids'])
                    ->whereDate('event_date', $targetDate)
                    ->count(),
            );
            $this->assertCount(count($group['fjp_ids']), $groupEvents);
            $this->assertCount(1, $groupEvents->pluck('team_group_id')->unique());

            foreach ($groupEvents as $event) {
                $this->assertSame($targetDate, $event->event_date?->toDateString());
                $this->assertSame(300 + array_search($event->fjp_id, array_column($fjps, 'id'), true), $event->sampling_target);
                $this->assertDatabaseHas('event_team_members', [
                    'event_id' => $event->id,
                    'user_id' => $group['team_leader_id'],
                    'position' => 'Team Leader',
                ]);
                $this->assertDatabaseHas('event_team_members', [
                    'event_id' => $event->id,
                    'user_id' => $group['spg_user_id'],
                    'position' => 'SPG',
                ]);
            }
        }

        $this->assertSame(7, Event::query()->whereIn('fjp_id', collect($fjps)->pluck('id'))->count());
        $this->assertSame(PermitStatus::SUBMITTED, $fjps[5]->fresh()->permit_status);
        $this->assertSame(14, EventTeamMember::query()->whereIn(
            'event_id',
            Event::query()->whereIn('fjp_id', collect($fjps)->pluck('id'))->select('id'),
        )->count());
    }

    public function test_pic_can_replace_saved_team_members_for_all_schools_in_the_group(): void
    {
        $this->freezeTime();
        $pic = $this->user('pic@indosakti.com');
        $teamUsers = $this->createTeamUsers(4);
        $fjps = collect(range(1, 2))->map(fn (int $number): Fjp => $this->createFjp([
            'school_name' => "SDN Ganti Tim {$number}",
            'npsn' => '2010102'.(10 + $number),
            'city' => 'Jakarta Selatan',
            'province' => 'DKI Jakarta',
            'event_date' => today()->addDays(2)->toDateString(),
            'status' => Fjp::STATUS_CONFIRM,
        ]));
        $this->permitterAssignsTeamGroup($fjps);

        $this->actingAs($pic)
            ->from(route('fjp.index'))
            ->post(route('fjp.team-schedule'), [
                'event_date' => today()->addDay()->toDateString(),
                'groups' => [[
                    'fjp_ids' => $fjps->pluck('id')->all(),
                    'team_leader_id' => $teamUsers[0]->id,
                    'spg_user_id' => $teamUsers[1]->id,
                ]],
            ])
            ->assertRedirect(route('fjp.index'))
            ->assertSessionHasNoErrors();

        $events = Event::query()->whereIn('fjp_id', $fjps->pluck('id'))->orderBy('id')->get();
        $teamGroupId = Event::query()->whereIn('fjp_id', $fjps->pluck('id'))->value('team_group_id');

        $this->actingAs($teamUsers[0])
            ->patch(route('fjp.team-group.update', $teamGroupId), [
                'team_leader_id' => $teamUsers[2]->id,
                'spg_user_id' => $teamUsers[3]->id,
            ])
            ->assertForbidden();

        $this->actingAs($pic)
            ->from(route('fjp.index'))
            ->patch(route('fjp.team-group.update', $teamGroupId), [
                'team_leader_id' => $teamUsers[2]->id,
                'spg_user_id' => $teamUsers[3]->id,
            ])
            ->assertRedirect(route('fjp.index'))
            ->assertSessionHasNoErrors();

        foreach ($events as $event) {
            $this->assertDatabaseHas('event_team_members', [
                'event_id' => $event->id,
                'user_id' => $teamUsers[2]->id,
                'position' => 'Team Leader',
            ]);
            $this->assertDatabaseHas('event_team_members', [
                'event_id' => $event->id,
                'user_id' => $teamUsers[3]->id,
                'position' => 'SPG',
            ]);
            $this->assertDatabaseMissing('event_team_members', [
                'event_id' => $event->id,
                'user_id' => $teamUsers[0]->id,
            ]);
            $this->assertDatabaseMissing('event_team_members', [
                'event_id' => $event->id,
                'user_id' => $teamUsers[1]->id,
            ]);
            $this->assertDatabaseHas('attendances', [
                'event_id' => $event->id,
                'user_id' => $teamUsers[2]->id,
            ]);
            $this->assertDatabaseHas('attendances', [
                'event_id' => $event->id,
                'user_id' => $teamUsers[3]->id,
            ]);
            $this->assertDatabaseMissing('attendances', [
                'event_id' => $event->id,
                'user_id' => $teamUsers[0]->id,
            ]);
            $this->assertDatabaseMissing('attendances', [
                'event_id' => $event->id,
                'user_id' => $teamUsers[1]->id,
            ]);
        }
    }

    public function test_pic_can_replace_team_members_while_a_confirmed_event_is_still_in_planning(): void
    {
        $this->freezeTime();
        $pic = $this->user('pic@indosakti.com');
        $teamUsers = $this->createTeamUsers(4);
        $eventDate = today()->addDays(2)->toDateString();
        $fjp = $this->createFjp([
            'school_name' => 'SDN Tim Perencanaan',
            'npsn' => '201010211',
            'city' => 'Jakarta Selatan',
            'province' => 'DKI Jakarta',
            'event_date' => $eventDate,
            'status' => Fjp::STATUS_CONFIRM,
        ]);
        $event = $this->createSavedTeamGroup($pic, $teamUsers, $fjp, $eventDate);
        $event->update(['status' => EventStatus::PLANNING]);

        $this->actingAs($pic)
            ->get(route('fjp.index', ['search' => 'SDN Tim Perencanaan']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('canManageTeamSchedules', true)
                ->where('fjps.data.0.id', $fjp->id)
                ->where('fjps.data.0.team.editable', true));

        $this->actingAs($pic)
            ->from(route('fjp.index'))
            ->patch(route('fjp.team-group.update', $event->team_group_id), [
                'team_leader_id' => $teamUsers[2]->id,
                'spg_user_id' => $teamUsers[3]->id,
            ])
            ->assertRedirect(route('fjp.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('event_team_members', [
            'event_id' => $event->id,
            'user_id' => $teamUsers[2]->id,
            'position' => 'Team Leader',
        ]);
        $this->assertDatabaseHas('event_team_members', [
            'event_id' => $event->id,
            'user_id' => $teamUsers[3]->id,
            'position' => 'SPG',
        ]);
    }

    public function test_pic_can_add_a_confirmed_school_to_an_existing_team_and_keep_pic_attribution(): void
    {
        $this->freezeTime();
        $pic = $this->user('pic@indosakti.com');
        $teamUsers = $this->createTeamUsers(2);
        $eventDate = today()->addDays(2)->toDateString();
        $scheduledFjp = $this->createFjp([
            'school_name' => 'SDN Tim Awal',
            'npsn' => '201010301',
            'city' => 'Jakarta Selatan',
            'province' => 'DKI Jakarta',
            'event_date' => $eventDate,
            'status' => Fjp::STATUS_CONFIRM,
        ]);
        $firstEvent = $this->createSavedTeamGroup($pic, $teamUsers, $scheduledFjp, $eventDate);
        $newSchool = $this->createFjp([
            'school_name' => 'SDN Tim Tambahan',
            'npsn' => '201010302',
            'city' => 'Jakarta Selatan',
            'province' => 'DKI Jakarta',
            'event_date' => $eventDate,
            'status' => Fjp::STATUS_CONFIRM,
        ]);
        $this->permitterAssignsTeamGroup([$newSchool]);

        $this->actingAs($pic)
            ->from(route('fjp.index'))
            ->post(route('fjp.team-group.schools.store', $firstEvent->team_group_id), [
                'fjp_id' => $newSchool->id,
            ])
            ->assertRedirect(route('fjp.index'))
            ->assertSessionHasNoErrors();

        $addedEvent = Event::query()->where('fjp_id', $newSchool->id)->firstOrFail();
        $this->assertSame($eventDate, $newSchool->fresh()->event_date?->toDateString());
        $this->assertSame($firstEvent->team_group_id, $addedEvent->team_group_id);
        $this->assertSame(EventStatus::TEAM_ASSIGNMENT, $addedEvent->status);
        $this->assertSame($pic->id, $addedEvent->pic_id);
        $this->assertSame($pic->id, $addedEvent->created_by);
        $this->assertCount(2, Event::query()->where('team_group_id', $firstEvent->team_group_id)->get());
        $this->assertDatabaseHas('event_team_members', [
            'event_id' => $addedEvent->id,
            'user_id' => $teamUsers[0]->id,
            'position' => 'Team Leader',
        ]);
        $this->assertDatabaseHas('event_team_members', [
            'event_id' => $addedEvent->id,
            'user_id' => $teamUsers[1]->id,
            'position' => 'SPG',
        ]);
        $this->assertDatabaseHas('attendances', [
            'event_id' => $addedEvent->id,
            'user_id' => $teamUsers[0]->id,
        ]);
        $this->assertDatabaseHas('attendances', [
            'event_id' => $addedEvent->id,
            'user_id' => $teamUsers[1]->id,
        ]);
    }

    public function test_permitter_cannot_add_a_school_to_a_saved_team(): void
    {
        $permitter = $this->user('permitter@indosakti.com');
        $fjp = $this->createFjp(['status' => Fjp::STATUS_CONFIRM]);

        $this->actingAs($permitter)
            ->post(route('fjp.team-group.schools.store', 'nonexistent-team-group'), [
                'fjp_id' => $fjp->id,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('events', ['fjp_id' => $fjp->id]);
    }

    public function test_pic_sees_saved_team_management_actions(): void
    {
        $pic = $this->user('pic@indosakti.com');
        $teamUsers = $this->createTeamUsers(2);
        $fjp = $this->createFjp([
            'city' => 'Jakarta Selatan',
            'province' => 'DKI Jakarta',
            'status' => Fjp::STATUS_CONFIRM,
        ]);
        $event = $this->createSavedTeamGroup($pic, $teamUsers, $fjp, today()->addDays(2)->toDateString());

        $this->actingAs($pic)
            ->get(route('fjp.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('canAssignTeamMembers', true)
                ->where('canAssignTeam', false)
                ->where('canManageTeamSchedules', true)
                ->where('fjps.data.0.team.team_group_id', $event->team_group_id)
                ->where('fjps.data.0.team.editable', true)
                ->where('fjps.data.0.team.members.0.user_id', $teamUsers[0]->id));
    }

    public function test_permitter_does_not_see_saved_team_management_actions(): void
    {
        $permitter = $this->user('permitter@indosakti.com');

        $this->actingAs($permitter)
            ->get(route('fjp.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('canAssignTeam', true)
                ->where('canAssignTeamMembers', false)
                ->where('canManageTeamSchedules', false));
    }

    public function test_permitter_cannot_edit_saved_team_members(): void
    {
        $permitter = $this->user('permitter@indosakti.com');

        $this->actingAs($permitter)
            ->patch(route('fjp.team-group.update', 'nonexistent-team-group'), [])
            ->assertForbidden();
    }

    public function test_pic_cannot_add_a_school_to_a_team_after_attendance_starts(): void
    {
        $this->freezeTime();
        $pic = $this->user('pic@indosakti.com');
        $teamUsers = $this->createTeamUsers(2);
        $eventDate = today()->addDays(2)->toDateString();
        $scheduledFjp = $this->createFjp([
            'city' => 'Jakarta Selatan',
            'province' => 'DKI Jakarta',
            'event_date' => $eventDate,
            'status' => Fjp::STATUS_CONFIRM,
        ]);
        $firstEvent = $this->createSavedTeamGroup($pic, $teamUsers, $scheduledFjp, $eventDate);
        $newSchool = $this->createFjp([
            'city' => 'Jakarta Selatan',
            'province' => 'DKI Jakarta',
            'event_date' => today()->addDays(5)->toDateString(),
            'status' => Fjp::STATUS_CONFIRM,
        ]);
        $this->permitterAssignsTeamGroup([$newSchool]);
        $firstEvent->attendances()->where('user_id', $teamUsers[0]->id)->update([
            'check_in_at' => now(),
            'status' => 'checked_in',
        ]);

        $this->actingAs($pic)
            ->from(route('fjp.index'))
            ->post(route('fjp.team-group.schools.store', $firstEvent->team_group_id), [
                'fjp_id' => $newSchool->id,
            ])
            ->assertRedirect(route('fjp.index'))
            ->assertSessionHasErrors([
                'team_group_id' => 'Tim tidak dapat diubah setelah aktivitas Event dimulai.',
            ]);

        $this->assertSame(
            today()->addDays(5)->toDateString(),
            $newSchool->fresh()->event_date?->toDateString(),
        );
        $this->assertDatabaseMissing('events', ['fjp_id' => $newSchool->id]);
    }

    public function test_pic_cannot_add_an_unconfirmed_school_to_a_saved_team(): void
    {
        $this->freezeTime();
        $pic = $this->user('pic@indosakti.com');
        $teamUsers = $this->createTeamUsers(2);
        $eventDate = today()->addDays(2)->toDateString();
        $scheduledFjp = $this->createFjp([
            'city' => 'Jakarta Selatan',
            'province' => 'DKI Jakarta',
            'event_date' => $eventDate,
            'status' => Fjp::STATUS_CONFIRM,
        ]);
        $firstEvent = $this->createSavedTeamGroup($pic, $teamUsers, $scheduledFjp, $eventDate);
        $newSchool = $this->createFjp([
            'city' => 'Jakarta Selatan',
            'province' => 'DKI Jakarta',
            'event_date' => today()->addDays(5)->toDateString(),
            'status' => Fjp::STATUS_PLAN,
        ]);

        $this->actingAs($pic)
            ->from(route('fjp.index'))
            ->post(route('fjp.team-group.schools.store', $firstEvent->team_group_id), [
                'fjp_id' => $newSchool->id,
            ])
            ->assertRedirect(route('fjp.index'))
            ->assertSessionHasErrors([
                'fjp_id' => 'Sekolah hanya dapat ditambahkan jika FJP berstatus Confirm.',
            ]);

        $this->assertSame(
            today()->addDays(5)->toDateString(),
            $newSchool->fresh()->event_date?->toDateString(),
        );
        $this->assertDatabaseMissing('events', ['fjp_id' => $newSchool->id]);
    }

    public function test_pic_cannot_add_a_school_outside_jabodetabek_to_a_saved_team(): void
    {
        $this->freezeTime();
        $pic = $this->user('pic@indosakti.com');
        $teamUsers = $this->createTeamUsers(2);
        $eventDate = today()->addDays(2)->toDateString();
        $scheduledFjp = $this->createFjp([
            'city' => 'Jakarta Selatan',
            'province' => 'DKI Jakarta',
            'event_date' => $eventDate,
            'status' => Fjp::STATUS_CONFIRM,
        ]);
        $firstEvent = $this->createSavedTeamGroup($pic, $teamUsers, $scheduledFjp, $eventDate);
        $newSchool = $this->createFjp([
            'city' => 'Kota Bandung',
            'province' => 'Jawa Barat',
            'event_date' => $eventDate,
            'status' => Fjp::STATUS_CONFIRM,
        ]);
        $this->permitterAssignsTeamGroup([$newSchool]);

        $this->actingAs($pic)
            ->from(route('fjp.index'))
            ->post(route('fjp.team-group.schools.store', $firstEvent->team_group_id), [
                'fjp_id' => $newSchool->id,
            ])
            ->assertRedirect(route('fjp.index'))
            ->assertSessionHasErrors([
                'fjp_id' => 'Sekolah yang ditambahkan harus berlokasi di Jabodetabek.',
            ]);

        $this->assertSame(
            $eventDate,
            $newSchool->fresh()->event_date?->toDateString(),
        );
        $this->assertDatabaseMissing('events', ['fjp_id' => $newSchool->id]);
    }

    public function test_saved_team_members_cannot_be_changed_after_attendance_starts(): void
    {
        $this->freezeTime();
        $pic = $this->user('pic@indosakti.com');
        $teamUsers = $this->createTeamUsers(4);
        $fjp = $this->createFjp([
            'school_name' => 'SDN Tim Sedang Berjalan',
            'npsn' => '201010299',
            'city' => 'Jakarta Selatan',
            'province' => 'DKI Jakarta',
            'event_date' => today()->addDays(2)->toDateString(),
            'status' => Fjp::STATUS_CONFIRM,
        ]);
        $this->permitterAssignsTeamGroup([$fjp]);

        $this->actingAs($pic)
            ->from(route('fjp.index'))
            ->post(route('fjp.team-schedule'), [
                'event_date' => today()->addDay()->toDateString(),
                'groups' => [[
                    'fjp_ids' => [$fjp->id],
                    'team_leader_id' => $teamUsers[0]->id,
                    'spg_user_id' => $teamUsers[1]->id,
                ]],
            ])
            ->assertRedirect(route('fjp.index'))
            ->assertSessionHasNoErrors();

        $event = Event::query()->where('fjp_id', $fjp->id)->firstOrFail();
        $event->attendances()->where('user_id', $teamUsers[0]->id)->update([
            'check_in_at' => now(),
            'status' => 'checked_in',
        ]);

        $this->actingAs($pic)
            ->from(route('fjp.index'))
            ->patch(route('fjp.team-group.update', $event->team_group_id), [
                'team_leader_id' => $teamUsers[2]->id,
                'spg_user_id' => $teamUsers[3]->id,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('team_group_id');

        $this->assertDatabaseHas('event_team_members', [
            'event_id' => $event->id,
            'user_id' => $teamUsers[0]->id,
            'position' => 'Team Leader',
        ]);
        $this->assertDatabaseMissing('event_team_members', [
            'event_id' => $event->id,
            'user_id' => $teamUsers[2]->id,
            'position' => 'Team Leader',
        ]);
    }

    public function test_confirmed_fjp_uses_its_planned_team_group_when_scheduled(): void
    {
        $pic = $this->user('pic@indosakti.com');
        $teamUsers = $this->createTeamUsers(2);
        $plannedTeamGroupId = (string) Str::uuid();
        $fjps = collect([
            $this->createFjp([
                'school_name' => 'SDN Tim Rencana Satu',
                'npsn' => '20999201',
                'city' => 'Jakarta Selatan',
                'event_date' => null,
                'status' => Fjp::STATUS_CONFIRM,
                'team_group_id' => $plannedTeamGroupId,
                'team_group_label' => 'Tim 1',
            ]),
            $this->createFjp([
                'school_name' => 'SDN Tim Rencana Dua',
                'npsn' => '20999202',
                'city' => 'Jakarta Timur',
                'event_date' => null,
                'status' => Fjp::STATUS_CONFIRM,
                'team_group_id' => $plannedTeamGroupId,
                'team_group_label' => 'Tim 1',
            ]),
        ]);
        $targetDate = today()->addDays(3)->toDateString();

        $candidatePage = $this->actingAs($pic)
            ->get(route('fjp.index'))
            ->assertOk();

        $candidates = collect($candidatePage->inertiaProps('teamScheduleCandidates'));
        $this->assertTrue($candidates->contains('id', $fjps[0]->id));
        $this->assertTrue($candidates->contains('id', $fjps[1]->id));

        $this->actingAs($pic)
            ->post(route('fjp.team-schedule'), [
                'event_date' => $targetDate,
                'groups' => [[
                    'fjp_ids' => $fjps->pluck('id')->all(),
                    'team_leader_id' => $teamUsers[0]->id,
                    'spg_user_id' => $teamUsers[1]->id,
                ]],
            ])
            ->assertRedirect(route('fjp.index'))
            ->assertSessionHasNoErrors();

        $events = Event::query()->whereIn('fjp_id', $fjps->pluck('id'))->get();
        $this->assertCount(2, $events);
        $this->assertSame([$plannedTeamGroupId], $events->pluck('team_group_id')->unique()->all());
        $this->assertSame(
            [$plannedTeamGroupId],
            $fjps->map(fn (Fjp $fjp): ?string => $fjp->fresh()->team_group_id)->unique()->all(),
        );
        $this->assertSame(
            ['Tim 1'],
            $fjps->map(fn (Fjp $fjp): ?string => $fjp->fresh()->team_group_label)->unique()->all(),
        );
    }

    public function test_pic_can_assign_members_directly_from_the_permitter_planned_team_group(): void
    {
        $pic = $this->user('pic@indosakti.com');
        $teamUsers = $this->createTeamUsers(2);
        $plannedTeamGroupId = (string) Str::uuid();
        $eventDate = today()->addDays(4)->toDateString();
        $fjps = collect([
            $this->createFjp([
                'school_name' => 'SDN Rencana Inline Satu',
                'npsn' => '20999301',
                'city' => 'Jakarta Selatan',
                'event_date' => $eventDate,
                'status' => Fjp::STATUS_CONFIRM,
                'team_group_id' => $plannedTeamGroupId,
                'team_group_label' => 'Tim 4',
            ]),
            $this->createFjp([
                'school_name' => 'SDN Rencana Inline Dua',
                'npsn' => '20999302',
                'city' => 'Jakarta Timur',
                'event_date' => $eventDate,
                'status' => Fjp::STATUS_CONFIRM,
                'team_group_id' => $plannedTeamGroupId,
                'team_group_label' => 'Tim 4',
            ]),
        ]);
        $ungrouped = $this->createFjp([
            'school_name' => 'SDN Rencana Inline Tiga',
            'npsn' => '20999303',
            'city' => 'Jakarta Barat',
            'event_date' => $eventDate,
            'status' => Fjp::STATUS_CONFIRM,
        ]);

        $page = $this->actingAs($pic)
            ->get(route('fjp.index', ['search' => 'SDN Rencana Inline']))
            ->assertOk();

        $rows = collect($page->inertiaProps('fjps.data'))->keyBy('id');
        $plannedIds = collect($rows[$fjps[0]->id]['planned_team_group_fjp_ids'])
            ->sort()
            ->values()
            ->all();

        $this->assertSame($fjps->pluck('id')->sort()->values()->all(), $plannedIds);
        $this->assertNotContains($ungrouped->id, $rows[$fjps[0]->id]['planned_team_group_fjp_ids']);

        $this->actingAs($pic)
            ->post(route('fjp.team-group'), [
                'fjp_ids' => $plannedIds,
                'team_leader_id' => $teamUsers[0]->id,
                'spg_user_id' => $teamUsers[1]->id,
            ])
            ->assertRedirect(route('fjp.index', ['search' => 'SDN Rencana Inline']))
            ->assertSessionHasNoErrors();

        $events = Event::query()->whereIn('fjp_id', $fjps->pluck('id'))->get();
        $this->assertCount(2, $events);
        $this->assertSame([$plannedTeamGroupId], $events->pluck('team_group_id')->unique()->all());
        $this->assertSame(
            [$teamUsers[0]->id, $teamUsers[1]->id],
            $events->first()->teamMembers->pluck('user_id')->sort()->values()->all(),
        );
        $this->assertDatabaseMissing('events', ['fjp_id' => $ungrouped->id]);
    }

    public function test_pic_can_schedule_one_confirmed_school_for_a_team_with_a_large_student_target(): void
    {
        $this->freezeTime();
        $pic = $this->user('pic@indosakti.com');
        $teamUsers = $this->createTeamUsers(2);
        $fjp = $this->createFjp([
            'school_name' => 'SDN Target Besar',
            'npsn' => '201010099',
            'city' => 'Jakarta Selatan',
            'province' => 'DKI Jakarta',
            'event_date' => today()->addDays(2)->toDateString(),
            'status' => Fjp::STATUS_CONFIRM,
            'jumlah_siswa_sekolah' => 1000,
        ]);
        $this->permitterAssignsTeamGroup([$fjp]);
        $targetDate = today()->addDay()->toDateString();

        $this->actingAs($pic)
            ->from(route('fjp.index'))
            ->post(route('fjp.team-schedule'), [
                'event_date' => $targetDate,
                'groups' => [[
                    'fjp_ids' => [$fjp->id],
                    'team_leader_id' => $teamUsers[0]->id,
                    'spg_user_id' => $teamUsers[1]->id,
                ]],
            ])
            ->assertRedirect(route('fjp.index'))
            ->assertSessionHasNoErrors();

        $event = Event::query()->where('fjp_id', $fjp->id)->firstOrFail();
        $this->assertSame($targetDate, $event->event_date?->toDateString());
        $this->assertSame(1000, $event->sampling_target);
    }

    public function test_pic_can_schedule_five_confirmed_schools_to_one_team_without_school_count_caps(): void
    {
        $this->freezeTime();
        $pic = $this->user('pic@indosakti.com');
        $teamUsers = $this->createTeamUsers(2);
        $fjps = collect(range(0, 4))->map(fn (int $index): Fjp => $this->createFjp([
            'school_name' => 'SDN Lima Sekolah '.$index,
            'npsn' => '2010101'.(10 + $index),
            'city' => 'Jakarta Barat',
            'province' => 'DKI Jakarta',
            'event_date' => today()->addDays(2)->toDateString(),
            'status' => Fjp::STATUS_CONFIRM,
            'jumlah_siswa_sekolah' => 800 + ($index * 50),
        ]))->all();
        $this->permitterAssignsTeamGroup($fjps);
        $targetDate = today()->addDay()->toDateString();
        $fjpIds = collect($fjps)->pluck('id')->all();

        $this->actingAs($pic)
            ->from(route('fjp.index'))
            ->post(route('fjp.team-schedule'), [
                'event_date' => $targetDate,
                'groups' => [[
                    'fjp_ids' => $fjpIds,
                    'team_leader_id' => $teamUsers[0]->id,
                    'spg_user_id' => $teamUsers[1]->id,
                ]],
            ])
            ->assertRedirect(route('fjp.index'))
            ->assertSessionHasNoErrors();

        $events = Event::query()->whereIn('fjp_id', $fjpIds)->get();
        $this->assertCount(5, $events);
        $this->assertSame(
            [800, 850, 900, 950, 1000],
            $events->sortBy('sampling_target')->pluck('sampling_target')->values()->all(),
        );
        $this->assertCount(1, $events->pluck('team_group_id')->unique());
    }

    public function test_team_schedule_uses_school_reported_count_instead_of_dapodik_or_sampling_target(): void
    {
        $this->freezeTime();
        $pic = $this->user('pic@indosakti.com');
        $teamUsers = $this->createTeamUsers(2);
        $fjp = $this->createFjp([
            'city' => 'Jakarta Selatan',
            'province' => 'DKI Jakarta',
            'school_name' => 'SDN Uji Tanpa Jumlah Siswa',
            'event_date' => today()->addDays(2)->toDateString(),
            'status' => Fjp::STATUS_CONFIRM,
            'jumlah_siswa_sekolah' => null,
            'jumlah_siswa_dapodik' => 450,
            'sampling_target' => 600,
        ]);
        $this->permitterAssignsTeamGroup([$fjp]);
        $candidatePage = $this->actingAs($pic)
            ->get(route('fjp.index'))
            ->assertOk();
        $candidate = collect($candidatePage->inertiaProps('teamScheduleCandidates'))
            ->firstWhere('id', $fjp->id);

        $this->assertSame(0, $candidate['student_count']);

        $this->actingAs($pic)
            ->from(route('fjp.index'))
            ->post(route('fjp.team-schedule'), [
                'event_date' => today()->addDay()->toDateString(),
                'groups' => [[
                    'fjp_ids' => [$fjp->id],
                    'team_leader_id' => $teamUsers[0]->id,
                    'spg_user_id' => $teamUsers[1]->id,
                ]],
            ])
            ->assertRedirect(route('fjp.index'))
            ->assertSessionHasNoErrors();

        $event = Event::query()->where('fjp_id', $fjp->id)->firstOrFail();
        $this->assertSame(0, $event->sampling_target);
    }

    public function test_pic_cannot_schedule_a_school_until_its_fjp_is_confirmed(): void
    {
        $this->freezeTime();
        $pic = $this->user('pic@indosakti.com');
        $teamUsers = $this->createTeamUsers(2);
        $fjp = $this->createFjp([
            'city' => 'Jakarta Pusat',
            'province' => 'DKI Jakarta',
            'event_date' => today()->addDays(2)->toDateString(),
            'status' => Fjp::STATUS_PLAN,
        ]);
        $originalDate = $fjp->event_date?->toDateString();

        $this->actingAs($pic)
            ->from(route('fjp.index'))
            ->post(route('fjp.team-schedule'), [
                'event_date' => today()->addDay()->toDateString(),
                'groups' => [[
                    'fjp_ids' => [$fjp->id],
                    'team_leader_id' => $teamUsers[0]->id,
                    'spg_user_id' => $teamUsers[1]->id,
                ]],
            ])
            ->assertRedirect(route('fjp.index'))
            ->assertSessionHasErrors('groups');

        $this->assertDatabaseMissing('events', ['fjp_id' => $fjp->id]);
        $this->assertSame($originalDate, $fjp->fresh()->event_date?->toDateString());
    }

    public function test_pic_can_schedule_eleven_confirmed_schools_across_four_team_groups(): void
    {
        $this->freezeTime();
        $pic = $this->user('pic@indosakti.com');
        $teamUsers = $this->createTeamUsers(8);
        $fjps = collect(range(0, 10))->map(fn (int $index): Fjp => $this->createFjp([
            'school_name' => 'SDN Jabodetabek Sampling '.$index,
            'npsn' => '2010300'.(10 + $index),
            'city' => $index % 2 === 0 ? 'Jakarta Selatan' : 'Bekasi',
            'province' => $index % 2 === 0 ? 'DKI Jakarta' : 'Jawa Barat',
            'event_date' => today()->addDays(intdiv($index, 3) + 2)->toDateString(),
            'status' => Fjp::STATUS_CONFIRM,
            'jumlah_siswa_sekolah' => 300 + $index,
        ]))->all();
        $unconfirmedFjp = $this->createFjp([
            'school_name' => 'SDN Belum Confirm',
            'npsn' => '201030099',
            'city' => 'Jakarta Selatan',
            'province' => 'DKI Jakarta',
            'event_date' => today()->addDays(2)->toDateString(),
            'status' => Fjp::STATUS_PLAN,
        ]);
        $targetDate = today()->addDay()->toDateString();
        $groups = [
            [
                'fjp_ids' => collect(array_slice($fjps, 0, 3))->pluck('id')->all(),
                'team_leader_id' => $teamUsers[0]->id,
                'spg_user_id' => $teamUsers[1]->id,
            ],
            [
                'fjp_ids' => collect(array_slice($fjps, 3, 3))->pluck('id')->all(),
                'team_leader_id' => $teamUsers[2]->id,
                'spg_user_id' => $teamUsers[3]->id,
            ],
            [
                'fjp_ids' => collect(array_slice($fjps, 6, 3))->pluck('id')->all(),
                'team_leader_id' => $teamUsers[4]->id,
                'spg_user_id' => $teamUsers[5]->id,
            ],
            [
                'fjp_ids' => collect(array_slice($fjps, 9, 2))->pluck('id')->all(),
                'team_leader_id' => $teamUsers[6]->id,
                'spg_user_id' => $teamUsers[7]->id,
            ],
        ];
        foreach ($groups as $index => $group) {
            $this->permitterAssignsTeamGroup(
                Fjp::query()->whereIn('id', $group['fjp_ids'])->get(),
                $index + 1,
            );
        }

        $candidatePage = $this->actingAs($pic)
            ->get(route('fjp.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('teamScheduleDate', $targetDate)
                ->has('teamScheduleCandidates', 11)
                ->where('teamScheduleCandidates.0.id', $fjps[0]->id));
        $this->assertFalse(
            collect($candidatePage->inertiaProps('teamScheduleCandidates'))
                ->pluck('id')
                ->contains($unconfirmedFjp->id),
        );

        $this->actingAs($pic)
            ->post(route('fjp.team-schedule'), [
                'event_date' => $targetDate,
                'groups' => $groups,
            ])
            ->assertRedirect(route('fjp.index'))
            ->assertSessionHasNoErrors();

        $events = Event::query()->whereIn('fjp_id', collect($fjps)->pluck('id'))->get();

        $this->assertCount(11, $events);
        $this->assertSame(22, EventTeamMember::query()
            ->whereIn('event_id', $events->pluck('id'))
            ->count());
        $this->assertSame(22, Attendance::query()->whereIn('event_id', $events->pluck('id'))->count());

        foreach ($groups as $group) {
            $groupEvents = $events->whereIn('fjp_id', $group['fjp_ids']);
            $this->assertCount(count($group['fjp_ids']), $groupEvents);
            $this->assertCount(1, $groupEvents->pluck('team_group_id')->unique());

            foreach ($groupEvents as $event) {
                $this->assertSame($targetDate, $event->event_date?->toDateString());
                $this->assertDatabaseHas('event_team_members', [
                    'event_id' => $event->id,
                    'user_id' => $group['team_leader_id'],
                    'position' => 'Team Leader',
                ]);
                $this->assertDatabaseHas('event_team_members', [
                    'event_id' => $event->id,
                    'user_id' => $group['spg_user_id'],
                    'position' => 'SPG',
                ]);
            }
        }
    }

    public function test_team_schedule_rolls_back_when_any_fjp_is_outside_jakarta(): void
    {
        $this->freezeTime();
        $pic = $this->user('pic@indosakti.com');
        $teamUsers = $this->createTeamUsers(6);
        $fjps = collect(range(0, 6))->map(fn (int $index): Fjp => $this->createFjp([
            'school_name' => 'Sekolah Jadwal Validasi '.$index,
            'npsn' => '2010200'.(10 + $index),
            'city' => $index === 6 ? 'Bandung' : 'Jakarta Pusat',
            'province' => $index === 6 ? 'Jawa Barat' : 'DKI Jakarta',
            'event_date' => today()->addDays($index + 2)->toDateString(),
            'permit_status' => PermitStatus::APPROVED,
            'status' => Fjp::STATUS_CONFIRM,
        ]))->all();
        $originalDates = collect($fjps)->mapWithKeys(fn (Fjp $fjp): array => [
            $fjp->id => $fjp->event_date?->toDateString(),
        ]);
        $groups = [
            [
                'fjp_ids' => collect(array_slice($fjps, 0, 3))->pluck('id')->all(),
                'team_leader_id' => $teamUsers[0]->id,
                'spg_user_id' => $teamUsers[1]->id,
            ],
            [
                'fjp_ids' => collect(array_slice($fjps, 3, 2))->pluck('id')->all(),
                'team_leader_id' => $teamUsers[2]->id,
                'spg_user_id' => $teamUsers[3]->id,
            ],
            [
                'fjp_ids' => collect(array_slice($fjps, 5, 2))->pluck('id')->all(),
                'team_leader_id' => $teamUsers[4]->id,
                'spg_user_id' => $teamUsers[5]->id,
            ],
        ];

        $this->actingAs($pic)
            ->from(route('fjp.index'))
            ->post(route('fjp.team-schedule'), [
                'event_date' => today()->addDay()->toDateString(),
                'groups' => $groups,
            ])
            ->assertRedirect(route('fjp.index'))
            ->assertSessionHasErrors('groups');

        foreach ($originalDates as $fjpId => $originalDate) {
            $this->assertSame($originalDate, Fjp::findOrFail($fjpId)->event_date?->toDateString());
        }

        $this->assertSame(0, Event::query()->whereIn('fjp_id', collect($fjps)->pluck('id'))->count());
    }

    public function test_permitter_cannot_assign_confirmed_schools_to_a_team_group(): void
    {
        $permitter = $this->user('permitter@indosakti.com');
        $teamUsers = $this->createTeamUsers(2);
        $firstFjp = $this->createFjp([
            'status' => Fjp::STATUS_CONFIRM,
            'permit_status' => PermitStatus::APPROVED,
        ]);
        $secondFjp = $this->createFjp([
            'event_date' => $firstFjp->event_date?->toDateString(),
            'npsn' => '20101998',
            'status' => Fjp::STATUS_CONFIRM,
            'permit_status' => PermitStatus::APPROVED,
        ]);

        $this->actingAs($permitter)
            ->post(route('fjp.team-group'), [
                'fjp_ids' => [$firstFjp->id, $secondFjp->id],
                'team_leader_id' => $teamUsers[0]->id,
                'spg_user_id' => $teamUsers[1]->id,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('events', ['fjp_id' => $firstFjp->id]);
        $this->assertDatabaseMissing('events', ['fjp_id' => $secondFjp->id]);
    }

    public function test_team_assignment_rejects_duplicate_team_members_without_changing_fjp_status(): void
    {
        $pic = $this->user('pic@indosakti.com');
        $teamLeader = $this->user('tl@indosakti.com');
        $fjp = $this->createFjp();
        $secondFjp = $this->createFjp([
            'event_date' => $fjp->event_date?->toDateString(),
            'npsn' => '20101998',
        ]);

        $this->actingAs($pic)
            ->post(route('fjp.team-group'), [
                'fjp_ids' => [$fjp->id, $secondFjp->id],
                'team_leader_id' => $teamLeader->id,
                'spg_user_id' => $teamLeader->id,
            ])
            ->assertSessionHasErrors('spg_user_id');

        $this->assertSame(Fjp::STATUS_PLAN, $fjp->fresh()->status);
        $this->assertDatabaseMissing('events', ['fjp_id' => $fjp->id]);
    }

    public function test_team_assignment_rejects_users_without_the_active_team_role(): void
    {
        $pic = $this->user('pic@indosakti.com');
        $picPersonnel = Personnel::create([
            'name' => 'Akun PIC bukan anggota',
            'role' => 'Team Leader',
            'is_active' => true,
            'user_id' => $pic->id,
        ]);
        $spgPersonnel = Personnel::query()
            ->where('user_id', $this->user('spg@indosakti.com')->id)
            ->firstOrFail();
        $plannedTeamGroupId = (string) Str::uuid();
        $fjp = $this->createFjp([
            'status' => Fjp::STATUS_CONFIRM,
            'team_group_id' => $plannedTeamGroupId,
            'team_group_label' => 'Tim 1',
        ]);
        $secondFjp = $this->createFjp([
            'event_date' => $fjp->event_date?->toDateString(),
            'npsn' => '20101998',
            'status' => Fjp::STATUS_CONFIRM,
            'team_group_id' => $plannedTeamGroupId,
            'team_group_label' => 'Tim 1',
        ]);

        $this->actingAs($pic)
            ->post(route('fjp.team-group'), [
                'fjp_ids' => [$fjp->id, $secondFjp->id],
                'team_leader_personnel_id' => $picPersonnel->id,
                'spg_personnel_ids' => [$spgPersonnel->id],
            ])
            ->assertSessionHasErrors('team_leader_personnel_id');

        $this->assertSame(Fjp::STATUS_CONFIRM, $fjp->fresh()->status);
        $this->assertDatabaseMissing('events', ['fjp_id' => $fjp->id]);
    }

    public function test_event_sampling_target_defaults_to_the_school_student_count(): void
    {
        $pic = $this->user('pic@indosakti.com');
        $venue = Venue::where('is_school', true)->firstOrFail();
        [$brand, $type] = $this->brandAndSchoolType('milo');
        $fjp = $this->createFjp([
            'venue_id' => $venue->id,
            'brand_id' => $brand->id,
            'event_type_id' => $type->id,
            'school_name' => $venue->name,
            'jumlah_siswa_sekolah' => 480,
            'sampling_target' => 300,
            'permit_status' => PermitStatus::APPROVED,
            'status' => Fjp::STATUS_CONFIRM,
        ]);

        $page = $this->actingAs($pic)->get(route('events.create'))->assertOk();
        $option = collect($page->viewData('page')['props']['confirmedFjps'])->firstWhere('id', $fjp->id);

        $this->assertSame(480, $option['jumlah_siswa_sekolah']);

        $this->actingAs($pic)
            ->post(route('events.store'), ['event_name' => '', 'fjp_id' => $fjp->id])
            ->assertSessionHasNoErrors();

        $event = Event::where('fjp_id', $fjp->id)->firstOrFail();
        $this->assertSame(480, $event->sampling_target);
    }

    public function test_event_list_exposes_fjp_schedule_for_venue_autofill(): void
    {
        $pic = $this->user('pic@indosakti.com');
        $venue = Venue::where('is_school', true)->firstOrFail();
        $fjp = $this->createFjp([
            'venue_id' => $venue->id,
            'event_date' => '2026-12-10',
            'start_time' => '08:30',
            'end_time' => '12:30',
            'jumlah_siswa_sekolah' => 480,
            'sampling_target' => 300,
            'permit_status' => PermitStatus::APPROVED,
            'status' => Fjp::STATUS_CONFIRM,
        ]);

        $response = $this->actingAs($pic)->get(route('events.index'))->assertOk();
        $option = collect($response->viewData('page')['props']['confirmedFjps'])
            ->firstWhere('id', $fjp->id);

        $this->assertSame($venue->id, $option['venue_id']);
        $this->assertSame('2026-12-10', $option['event_date']);
        $this->assertSame('08:30', $option['start_time']);
        $this->assertSame('12:30', $option['end_time']);
        $this->assertSame(480, $option['jumlah_siswa_sekolah']);
    }

    public function test_pic_can_override_the_default_sampling_target(): void
    {
        $pic = $this->user('pic@indosakti.com');
        $venue = Venue::where('is_school', true)->firstOrFail();
        $fjp = $this->createFjp([
            'venue_id' => $venue->id,
            'school_name' => $venue->name,
            'jumlah_siswa_sekolah' => 480,
            'sampling_target' => 300,
            'permit_status' => PermitStatus::APPROVED,
            'status' => Fjp::STATUS_CONFIRM,
        ]);

        $this->actingAs($pic)
            ->post(route('events.store'), [
                'event_name' => '',
                'fjp_id' => $fjp->id,
                'sampling_target' => 320,
            ])
            ->assertSessionHasNoErrors();

        $event = Event::where('fjp_id', $fjp->id)->firstOrFail();
        $this->assertSame(320, $event->sampling_target);
    }

    public function test_creating_an_fjp_copies_city_and_province_to_the_provisioned_venue(): void
    {
        $permitter = $this->user('permitter@indosakti.com');

        $this->actingAs($permitter)
            ->post(route('fjp.store'), [
                'school_name' => 'SDN Wilayah Uji',
                'npsn' => '20999111',
                'address' => 'Jl. Wilayah No. 1',
                'city' => 'Kota Bandung',
                'province' => 'Jawa Barat',
            ])
            ->assertSessionHasNoErrors();

        $fjp = Fjp::where('npsn', '20999111')->firstOrFail();
        $venue = Venue::findOrFail($fjp->fresh()->venue_id);

        $this->assertSame('Kota Bandung', $venue->city);
        $this->assertSame('Jawa Barat', $venue->province);
    }

    public function test_creating_a_second_event_for_the_same_fjp_is_blocked(): void
    {
        $pic = $this->user('pic@indosakti.com');
        $fjp = $this->approvedFjp();

        $this->actingAs($pic)
            ->post(route('events.store'), ['event_name' => '', 'fjp_id' => $fjp->id])
            ->assertSessionHasNoErrors();

        $this->actingAs($pic)
            ->from(route('events.create'))
            ->post(route('events.store'), ['event_name' => '', 'fjp_id' => $fjp->id])
            ->assertRedirect(route('events.create'))
            ->assertSessionHasErrors('fjp_id');

        $this->assertSame(1, Event::where('fjp_id', $fjp->id)->count());
    }

    public function test_cancelled_event_does_not_block_a_new_event_for_the_same_fjp(): void
    {
        $pic = $this->user('pic@indosakti.com');
        $fjp = $this->approvedFjp();

        Event::create([
            'event_code' => Event::generateCode(),
            'event_name' => 'Event Lama Batal',
            'fjp_id' => $fjp->id,
            'venue_id' => $fjp->venue_id,
            'event_date' => $fjp->event_date,
            'status' => EventStatus::CANCELLED,
            'created_by' => $pic->id,
        ]);

        $this->actingAs($pic)
            ->post(route('events.store'), ['event_name' => '', 'fjp_id' => $fjp->id])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Event::where('fjp_id', $fjp->id)->count());
    }

    public function test_confirmed_fjp_options_exclude_fjps_that_already_have_an_event(): void
    {
        $pic = $this->user('pic@indosakti.com');
        $fjp = $this->approvedFjp();

        $before = $this->actingAs($pic)->get(route('events.create'))->assertOk();
        $beforeIds = collect($before->viewData('page')['props']['confirmedFjps'])->pluck('id');
        $this->assertTrue($beforeIds->contains($fjp->id));

        $this->actingAs($pic)
            ->post(route('events.store'), ['event_name' => '', 'fjp_id' => $fjp->id])
            ->assertSessionHasNoErrors();

        $after = $this->actingAs($pic)->get(route('events.create'))->assertOk();
        $afterIds = collect($after->viewData('page')['props']['confirmedFjps'])->pluck('id');
        $this->assertFalse($afterIds->contains($fjp->id));
    }

    public function test_event_edit_options_still_include_its_linked_fjp(): void
    {
        $pic = $this->user('pic@indosakti.com');
        $fjp = $this->approvedFjp();

        $this->actingAs($pic)
            ->post(route('events.store'), ['event_name' => '', 'fjp_id' => $fjp->id])
            ->assertSessionHasNoErrors();

        $event = Event::where('fjp_id', $fjp->id)->firstOrFail();

        $response = $this->actingAs($pic)->get(route('events.edit', $event))->assertOk();
        $ids = collect($response->viewData('page')['props']['confirmedFjps'])->pluck('id');

        $this->assertTrue($ids->contains($fjp->id));
    }

    public function test_fjp_detail_no_longer_exposes_event_creation_modal_data(): void
    {
        $admin = $this->user('admin@indosakti.com');
        $fjp = $this->createFjp();

        $this->actingAs($admin)
            ->get(route('fjp.show', $fjp))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Fjp/Show')
                ->missing('pics')
                ->missing('venues')
                ->missing('can.create_event'));
    }

    public function test_pic_sees_general_status_but_no_fjp_approval_actions_on_fjp_detail(): void
    {
        $pic = $this->user('pic@indosakti.com');
        $fjp = $this->createFjp(['permit_status' => PermitStatus::SUBMITTED]);

        $this->actingAs($pic)
            ->get(route('fjp.show', $fjp))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('fjp.status', Fjp::STATUS_PLAN)
                ->where('can.update', true)
                ->missing('can.approve')
                ->missing('can.reject'));
    }

    /** @param Collection<int, User> $teamUsers */
    private function createSavedTeamGroup(User $pic, Collection $teamUsers, Fjp $fjp, string $eventDate): Event
    {
        $this->permitterAssignsTeamGroup([$fjp]);

        $this->actingAs($pic)
            ->from(route('fjp.index'))
            ->post(route('fjp.team-schedule'), [
                'event_date' => $eventDate,
                'groups' => [[
                    'fjp_ids' => [$fjp->id],
                    'team_leader_id' => $teamUsers[0]->id,
                    'spg_user_id' => $teamUsers[1]->id,
                ]],
            ])
            ->assertRedirect(route('fjp.index'))
            ->assertSessionHasNoErrors();

        return Event::query()->where('fjp_id', $fjp->id)->firstOrFail();
    }

    /**
     * @param  iterable<Fjp>  $fjps
     */
    private function permitterAssignsTeamGroup(iterable $fjps, int $teamNumber = 1): void
    {
        $permitter = $this->user('permitter@indosakti.com');

        foreach ($fjps as $fjp) {
            $this->actingAs($permitter)
                ->patch(route('fjp.inline', $fjp), ['team_number' => $teamNumber])
                ->assertSessionHasNoErrors();
        }
    }

    /**
     * @return Collection<int, User>
     */
    private function createTeamUsers(int $count): Collection
    {
        $teamRole = Role::where('slug', 'team')->firstOrFail();
        $teamUsers = new Collection;

        for ($number = 1; $number <= $count; $number++) {
            $user = User::factory()->create([
                'name' => "Schedule Team {$number}",
                'email' => "schedule-team-{$number}@example.test",
                'active_role_id' => $teamRole->id,
            ]);
            $user->roles()->attach($teamRole->id);
            Personnel::create([
                'name' => $user->name,
                'role' => $number % 2 === 1 ? 'Team Leader' : 'SPG',
                'is_active' => true,
                'user_id' => $user->id,
            ]);
            $teamUsers->push($user);
        }

        return $teamUsers;
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    /** @return array{0: Brand, 1: EventType} */
    private function brandAndSchoolType(string $slug): array
    {
        $brand = Brand::where('slug', $slug)->firstOrFail();
        $type = EventType::where('brand_id', $brand->id)->where('requires_school', true)->firstOrFail();

        return [$brand, $type];
    }

    private function approvedFjp(): Fjp
    {
        $venue = Venue::where('is_school', true)->firstOrFail();

        return $this->createFjp([
            'venue_id' => $venue->id,
            'school_name' => $venue->name,
            'permit_status' => PermitStatus::APPROVED,
            'status' => Fjp::STATUS_CONFIRM,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function createFjp(array $overrides = []): Fjp
    {
        return Fjp::create(array_merge([
            'fjp_number' => Fjp::generateNumber(),
            'event_name' => 'FJP Uji '.uniqid(),
            'school_name' => 'Sekolah Uji',
            'npsn' => '20101999',
            'event_date' => now()->addDays(10)->toDateString(),
            'start_time' => '08:00',
            'end_time' => '12:00',
            'sampling_target' => 500,
            'jug_required' => false,
            'permit_status' => PermitStatus::DRAFT,
            'status' => Fjp::STATUS_PLAN,
        ], $overrides));
    }
}
