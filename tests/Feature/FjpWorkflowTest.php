<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Enums\PermitStatus;
use App\Models\Brand;
use App\Models\Event;
use App\Models\EventTeamMember;
use App\Models\Fjp;
use App\Models\Personnel;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FjpWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(DemoDataSeeder::class);
    }

    public function test_pic_can_edit_core_fjp_fields_inline(): void
    {
        $pic = $this->user('pic@indosakti.com');
        $fjp = $this->createFjp([
            'school_name' => 'SDN Lama',
            'npsn' => '20999011',
            'status' => Fjp::STATUS_PLAN,
        ]);

        $this->actingAs($pic)
            ->from(route('fjp.index'))
            ->patch(route('fjp.inline', $fjp), [
                'school_name' => 'SDN Baru Langsung',
                'npsn' => '20999012',
                'event_date' => '2026-11-20',
                'start_time' => '08:15',
                'jumlah_siswa_sekolah' => 425,
                'city' => 'Jakarta Timur',
                'kecamatan' => 'Cakung',
                'kelurahan' => 'Penggilingan',
            ])
            ->assertRedirect(route('fjp.index'))
            ->assertSessionHasNoErrors();

        $fjp->refresh();
        $this->assertSame('SDN Baru Langsung', $fjp->school_name);
        $this->assertSame('20999012', $fjp->npsn);
        $this->assertSame('2026-11-20', $fjp->event_date?->toDateString());
        $this->assertSame('08:15', substr((string) $fjp->start_time, 0, 5));
        $this->assertSame(425, $fjp->jumlah_siswa_sekolah);
        $this->assertSame(425, $fjp->sampling_target);
        $this->assertSame('Jakarta Timur', $fjp->city);
        $this->assertSame('Cakung', $fjp->kecamatan);
        $this->assertSame('Penggilingan', $fjp->kelurahan);
    }

    public function test_pic_cannot_assign_or_change_the_permitter_team_group(): void
    {
        $pic = $this->user('pic@indosakti.com');
        $fjp = $this->createFjp([
            'status' => Fjp::STATUS_CONFIRM,
        ]);

        $this->actingAs($pic)
            ->patch(route('fjp.inline', $fjp), ['team_number' => 3])
            ->assertForbidden();

        $fjp->refresh();
        $this->assertNull($fjp->team_group_id);
    }

    public function test_permitter_can_input_team_group_but_cannot_assign_team_members(): void
    {
        $permitter = $this->user('permitter@indosakti.com');
        $pic = $this->user('pic@indosakti.com');
        $teamLeader = $this->user('tl@indosakti.com');
        $spg = $this->user('spg@indosakti.com');
        $teamLeaderPersonnel = Personnel::query()->where('user_id', $teamLeader->id)->firstOrFail();
        $spgPersonnel = Personnel::query()->where('user_id', $spg->id)->firstOrFail();
        $brand = Brand::query()->firstOrFail();
        $fjp = $this->createFjp([
            'brand_id' => $brand->id,
            'school_name' => 'SDN Alur FJP',
            'address' => 'Jl. Pendidikan 10',
            'city' => 'Jakarta Timur',
            'event_date' => today()->addDays(3)->toDateString(),
            'status' => Fjp::STATUS_CONFIRM,
        ]);

        $this->actingAs($pic)
            ->from(route('fjp.index'))
            ->post(route('fjp.team-group'), [
                'fjp_ids' => [$fjp->id],
                'team_leader_personnel_id' => $teamLeaderPersonnel->id,
                'spg_personnel_ids' => [$spgPersonnel->id],
            ])
            ->assertSessionHasErrors('fjp_ids');

        $this->assertDatabaseMissing('events', ['fjp_id' => $fjp->id]);

        $this->actingAs($permitter)
            ->patch(route('fjp.inline', $fjp), ['team_number' => 6])
            ->assertSessionHasNoErrors();

        $fjp->refresh();
        $this->assertSame('Tim 6', $fjp->team_group_label);

        $this->actingAs($pic)
            ->get(route('fjp.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('fjps.data.0.assignment_status', 'awaiting_members')
                ->where('fjps.data.0.assignment_status_label', 'Menunggu pembagian anggota oleh PIC'));

        $this->actingAs($permitter)
            ->post(route('fjp.team-group'), [
                'fjp_ids' => [$fjp->id],
                'team_leader_personnel_id' => $teamLeaderPersonnel->id,
                'spg_personnel_ids' => [$spgPersonnel->id],
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('events', ['fjp_id' => $fjp->id]);

        $this->actingAs($pic)
            ->post(route('fjp.team-group'), [
                'fjp_ids' => [$fjp->id],
                'team_leader_personnel_id' => $teamLeaderPersonnel->id,
                'spg_personnel_ids' => [$spgPersonnel->id],
            ])
            ->assertSessionHasNoErrors();

        $event = Event::query()->where('fjp_id', $fjp->id)->firstOrFail();
        $this->assertSame($fjp->team_group_id, $event->team_group_id);
        $this->assertSame(2, $event->teamMembers()->count());
        $this->assertDatabaseHas('event_team_members', [
            'event_id' => $event->id,
            'personnel_id' => $teamLeaderPersonnel->id,
            'position' => 'Team Leader',
        ]);
        $this->assertDatabaseHas('event_team_members', [
            'event_id' => $event->id,
            'personnel_id' => $spgPersonnel->id,
            'position' => 'SPG',
        ]);

        $this->actingAs($pic)
            ->get(route('fjp.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('fjps.data.0.assignment_status', 'ready')
                ->where('fjps.data.0.event_schedule.school_name', 'SDN Alur FJP')
                ->where('fjps.data.0.event_schedule.brand', $brand->name)
                ->where('fjps.data.0.event_schedule.pic', $pic->name)
                ->where('fjps.data.0.event_schedule.team', 'Tim 6')
                ->where('fjps.data.0.event_schedule.members.0.position', 'Team Leader')
                ->where('fjps.data.0.event_schedule.members.1.position', 'SPG'));

        $this->actingAs($permitter)
            ->get(route('fjp.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('fjps.data.0.event_schedule.members', [])
                ->where('teamSchedules', []));

        $this->actingAs($pic)
            ->from(route('fjp.index'))
            ->post(route('fjp.team-group'), [
                'fjp_ids' => [$fjp->id],
                'team_leader_personnel_id' => $teamLeaderPersonnel->id,
                'spg_personnel_ids' => [$spgPersonnel->id],
            ])
            ->assertRedirect(route('fjp.index'))
            ->assertSessionHasErrors([
                'fjp_id' => 'FJP ini sudah memiliki Event. Buka Event terkait untuk melanjutkan.',
            ]);

        $this->assertSame(1, Event::query()->where('fjp_id', $fjp->id)->count());
    }

    public function test_pic_assigns_one_team_leader_and_one_spg_and_can_edit_the_roster(): void
    {
        $pic = $this->user('pic@indosakti.com');
        $teamLeader = Personnel::query()->where('name', 'Team Leader 1')->firstOrFail();
        $firstSpg = Personnel::query()->where('name', 'SPG 1')->firstOrFail();
        $secondSpg = Personnel::query()->where('name', 'SPG 2')->firstOrFail();
        $teamAccount = $this->user('team@indosakti.com');
        $secondSpg->update(['user_id' => $teamAccount->id]);
        $fjp = $this->createFjp([
            'event_name' => 'FJP Roster Terintegrasi',
            'school_name' => 'SDN Roster Terintegrasi',
            'city' => 'Jakarta Selatan',
            'event_date' => today()->addDays(2)->toDateString(),
            'status' => Fjp::STATUS_CONFIRM,
        ]);

        $this->actingAs($this->user('permitter@indosakti.com'))
            ->patch(route('fjp.inline', $fjp), ['team_number' => 4])
            ->assertSessionHasNoErrors();
        $fjp->refresh();

        $payload = [
            'fjp_ids' => [$fjp->id],
            'team_leader_personnel_id' => $teamLeader->id,
            'spg_personnel_ids' => [$firstSpg->id],
        ];

        $this->actingAs($pic)
            ->post(route('fjp.team-group'), $payload)
            ->assertSessionHasNoErrors();

        $event = Event::query()->where('fjp_id', $fjp->id)->firstOrFail();
        $this->assertSame(2, $event->teamMembers()->count());
        $this->assertSame(1, $event->teamMembers()->where('position', 'Team Leader')->count());
        $this->assertSame(1, $event->teamMembers()->where('position', 'SPG')->count());

        $this->actingAs($pic)
            ->get(route('fjp.team-assignment', ['search' => 'SDN Roster Terintegrasi']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.data.0.team_assignment_status', 'Jadwal siap dilaksanakan')
                ->where('rows.data.0.team_members.0.position', 'Team Leader')
                ->where('rows.data.0.team_members.1.position', 'SPG'));

        $this->actingAs($pic)
            ->patch(route('fjp.team-group.update', $fjp->team_group_id), [
                'team_leader_personnel_id' => $teamLeader->id,
                'spg_personnel_ids' => [$secondSpg->id],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, $event->teamMembers()->count());
        $this->assertDatabaseMissing('event_team_members', [
            'event_id' => $event->id,
            'personnel_id' => $firstSpg->id,
        ]);

        $teamResponse = $this->actingAs($teamAccount)->get(route('team.index'));
        $this->assertSame(
            'SPG',
            collect($teamResponse->inertiaProps('upcomingGroups'))
                ->flatMap(fn (array $brand): array => collect($brand['event_types'])
                    ->flatMap(fn (array $eventType): array => collect($eventType['dates'])
                        ->flatMap(fn (array $date): array => $date['events'])
                        ->all())
                    ->all())
                ->firstWhere('id', $event->id)['my_schedule']['position'],
        );

        $this->actingAs($pic)
            ->patch(route('fjp.team-group.update', $fjp->team_group_id), [
                'team_leader_personnel_id' => $teamLeader->id,
                'spg_personnel_ids' => [$firstSpg->id, $secondSpg->id],
            ])
            ->assertSessionHasErrors('spg_personnel_ids');

        $this->actingAs($pic)
            ->delete(route('fjp.team-group.members.destroy', [
                'teamGroupId' => $fjp->team_group_id,
                'personnel' => $secondSpg->id,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $event->teamMembers()->count());
        $this->assertDatabaseMissing('event_team_members', [
            'event_id' => $event->id,
            'personnel_id' => $secondSpg->id,
        ]);

        $this->actingAs($pic)
            ->patch(route('fjp.team-group.update', $fjp->team_group_id), [
                'team_leader_personnel_id' => $teamLeader->id,
                'spg_personnel_ids' => [$firstSpg->id],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, $event->teamMembers()->count());
    }

    public function test_pic_cannot_schedule_a_member_during_another_event_on_the_same_date(): void
    {
        $pic = $this->user('pic@indosakti.com');
        $teamLeader = $this->user('tl@indosakti.com');
        $spg = $this->user('spg@indosakti.com');
        $eventDate = today()->addDays(3)->toDateString();
        $existingEvent = Event::create([
            'event_code' => 'EVT-CONFLICT-'.uniqid(),
            'event_name' => 'Event konflik jadwal',
            'event_date' => $eventDate,
            'start_time' => '08:00',
            'end_time' => '09:00',
            'status' => EventStatus::TEAM_ASSIGNMENT,
            'pic_id' => $pic->id,
        ]);
        EventTeamMember::create([
            'event_id' => $existingEvent->id,
            'user_id' => $teamLeader->id,
            'position' => 'Team Leader',
        ]);
        $fjp = $this->createFjp([
            'event_date' => $eventDate,
            'start_time' => '08:30',
            'end_time' => '10:00',
            'status' => Fjp::STATUS_CONFIRM,
        ]);

        $this->actingAs($this->user('permitter@indosakti.com'))
            ->patch(route('fjp.inline', $fjp), ['team_number' => 9])
            ->assertSessionHasNoErrors();

        $this->actingAs($pic)
            ->from(route('fjp.index'))
            ->post(route('fjp.team-group'), [
                'fjp_ids' => [$fjp->id],
                'team_leader_id' => $teamLeader->id,
                'spg_user_id' => $spg->id,
            ])
            ->assertRedirect(route('fjp.index'))
            ->assertSessionHasErrors([
                'team_leader_id' => 'Satu atau lebih anggota tim sudah memiliki jadwal yang bentrok pada tanggal dan waktu tersebut.',
            ]);

        $this->assertDatabaseMissing('events', ['fjp_id' => $fjp->id]);
    }

    public function test_pic_can_schedule_a_member_at_a_different_time_on_the_same_date(): void
    {
        $pic = $this->user('pic@indosakti.com');
        $teamLeader = $this->user('tl@indosakti.com');
        $spg = $this->user('spg@indosakti.com');
        $eventDate = today()->addDays(3)->toDateString();
        $existingEvent = Event::create([
            'event_code' => 'EVT-NON-CONFLICT-'.uniqid(),
            'event_name' => 'Event jadwal pagi',
            'event_date' => $eventDate,
            'start_time' => '08:00',
            'end_time' => '09:00',
            'status' => EventStatus::TEAM_ASSIGNMENT,
            'pic_id' => $pic->id,
        ]);
        EventTeamMember::create([
            'event_id' => $existingEvent->id,
            'user_id' => $teamLeader->id,
            'position' => 'Team Leader',
        ]);
        $fjp = $this->createFjp([
            'event_date' => $eventDate,
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => Fjp::STATUS_CONFIRM,
        ]);

        $this->actingAs($this->user('permitter@indosakti.com'))
            ->patch(route('fjp.inline', $fjp), ['team_number' => 10])
            ->assertSessionHasNoErrors();

        $this->actingAs($pic)
            ->from(route('fjp.index'))
            ->post(route('fjp.team-group'), [
                'fjp_ids' => [$fjp->id],
                'team_leader_id' => $teamLeader->id,
                'spg_user_id' => $spg->id,
            ])
            ->assertRedirect(route('fjp.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('events', ['fjp_id' => $fjp->id]);
    }

    public function test_permitter_can_update_the_fjp_status_without_changing_the_historical_permit_status(): void
    {
        $permitter = $this->user('permitter@indosakti.com');
        $fjp = $this->createFjp([
            'status' => Fjp::STATUS_PLAN,
            'permit_status' => PermitStatus::DRAFT,
            'school_name' => 'SDN Status Group Uji',
            'city' => 'Jakarta Selatan',
            'team_group_id' => (string) Str::uuid(),
            'team_group_label' => 'Tim 4',
        ]);

        $this->actingAs($permitter)
            ->patch(route('fjp.status', $fjp), ['status' => Fjp::STATUS_CONFIRM])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(Fjp::STATUS_CONFIRM, $fjp->fresh()->status);
        $this->assertSame(PermitStatus::DRAFT, $fjp->fresh()->permit_status);
        $this->assertSame('Tim 4', $fjp->fresh()->team_group_label);

        $this->actingAs($permitter)
            ->get(route('fjp.index', ['search' => 'SDN Status Group Uji']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('fjps.data.0.assignment_status', 'awaiting_members')
                ->where('fjps.data.0.assignment_status_label', 'Menunggu pembagian anggota oleh PIC'));

        $this->actingAs($this->user('pic@indosakti.com'))
            ->get(route('fjp.index', ['search' => 'SDN Status Group Uji']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('canAssignTeamMembers', true)
                ->where('fjps.data.0.id', $fjp->id)
                ->where('fjps.data.0.planned_team_group_id', $fjp->team_group_id)
                ->where('fjps.data.0.planned_team_group_label', 'Tim 4')
                ->where('fjps.data.0.planned_team_group_fjp_ids', [$fjp->id]));
    }

    public function test_pic_sees_permitter_team_number_and_school_order_by_start_time_before_member_assignment(): void
    {
        $teamGroupId = (string) Str::uuid();
        $eventDate = today()->addDays(3)->toDateString();
        $laterSchool = $this->createFjp([
            'school_name' => 'SDN Sekolah Kedua',
            'city' => 'Jakarta Selatan',
            'event_date' => $eventDate,
            'start_time' => '10:00',
            'team_group_id' => $teamGroupId,
            'team_group_label' => 'Tim 4',
            'status' => Fjp::STATUS_CONFIRM,
        ]);
        $earlierSchool = $this->createFjp([
            'school_name' => 'SDN Sekolah Pertama',
            'city' => 'Jakarta Selatan',
            'event_date' => $eventDate,
            'start_time' => '08:00',
            'team_group_id' => $teamGroupId,
            'team_group_label' => 'Tim 4',
            'status' => Fjp::STATUS_CONFIRM,
        ]);

        $this->actingAs($this->user('pic@indosakti.com'))
            ->get(route('fjp.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('canAssignTeamMembers', true)
                ->where(
                    'fjps.data.0.planned_team_group_schedule',
                    [
                        [
                            'fjp_id' => $earlierSchool->id,
                            'school_name' => 'SDN Sekolah Pertama',
                            'start_time' => '08:00',
                        ],
                        [
                            'fjp_id' => $laterSchool->id,
                            'school_name' => 'SDN Sekolah Kedua',
                            'start_time' => '10:00',
                        ],
                    ],
                )
                ->where('fjps.data.0.planned_team_group_label', 'Tim 4')
                ->where('fjps.data.1.planned_team_group_label', 'Tim 4')
                ->where('fjps.data.0.assignment_status', 'awaiting_members')
                ->where('fjps.data.1.assignment_status', 'awaiting_members'));
    }

    public function test_permitter_and_pic_use_the_dedicated_team_assignment_page(): void
    {
        $eventDate = today()->addDays(3)->toDateString();
        $teamGroupId = (string) Str::uuid();
        $firstSchool = $this->createFjp([
            'event_name' => 'FJP Pembagian Tim',
            'school_name' => 'SDN Pembagian Tim Pagi',
            'city' => 'Jakarta Selatan',
            'event_date' => $eventDate,
            'start_time' => '08:00',
            'team_group_id' => $teamGroupId,
            'team_group_label' => 'Tim 4',
            'notes' => 'Catatan untuk PIC',
            'status' => Fjp::STATUS_CONFIRM,
        ]);
        $secondSchool = $this->createFjp([
            'event_name' => 'FJP Pembagian Tim',
            'school_name' => 'SDN Pembagian Tim Siang',
            'city' => 'Jakarta Selatan',
            'event_date' => $eventDate,
            'start_time' => '09:00',
            'team_group_id' => $teamGroupId,
            'team_group_label' => 'Tim 4',
            'status' => Fjp::STATUS_CONFIRM,
        ]);
        $unassignedSchool = $this->createFjp([
            'event_name' => 'FJP Pembagian Tim',
            'school_name' => 'SDN Pembagian Tim Belum Ada Tim',
            'city' => 'Jakarta Selatan',
            'event_date' => $eventDate,
            'start_time' => '10:00',
            'status' => Fjp::STATUS_CONFIRM,
        ]);

        $this->actingAs($this->user('permitter@indosakti.com'))
            ->get(route('fjp.team-assignment', ['search' => 'FJP Pembagian Tim']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Fjp/TeamAssignment')
                ->where('canAssignTeam', true)
                ->where('canAssignTeamMembers', false)
                ->has('rows.data', 3)
                ->where('rows.data.0.id', $firstSchool->id)
                ->where('rows.data.0.school_order', 1)
                ->where('rows.data.0.team_group_label', 'Tim 4')
                ->where('rows.data.2.id', $unassignedSchool->id));

        $pic = $this->user('pic@indosakti.com');
        $this->actingAs($pic)
            ->get(route('fjp.team-assignment', ['search' => 'FJP Pembagian Tim']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Fjp/TeamAssignment')
                ->where('canAssignTeam', false)
                ->where('canAssignTeamMembers', true)
                ->has('rows.data', 2)
                ->where('rows.data.0.id', $firstSchool->id)
                ->where('rows.data.0.school_order', 1)
                ->where('rows.data.1.id', $secondSchool->id)
                ->where('rows.data.1.school_order', 2)
                ->where('rows.data.0.remark', 'Catatan untuk PIC')
                ->where('rows.data.0.team_assignment_status', 'Menunggu pembagian anggota oleh PIC'));

        $this->actingAs($pic)
            ->patch(route('fjp.inline', $firstSchool), ['notes' => 'Remark PIC diperbarui'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Remark PIC diperbarui', $firstSchool->fresh()->notes);
    }

    public function test_team_role_cannot_open_the_team_assignment_page(): void
    {
        $this->actingAs($this->user('team@indosakti.com'))
            ->get(route('fjp.team-assignment'))
            ->assertForbidden();
    }

    public function test_pic_and_permitter_receive_the_fjp_status_editor_permission(): void
    {
        foreach (['permitter@indosakti.com', 'pic@indosakti.com'] as $email) {
            $response = $this->actingAs($this->user($email))->get(route('fjp.index'));

            $response->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('canUpdateStatus', true));
        }
    }

    public function test_pic_can_update_fjp_status_including_done(): void
    {
        $pic = $this->user('pic@indosakti.com');
        $fjp = $this->createFjp(['status' => Fjp::STATUS_PLAN]);

        $this->actingAs($pic)
            ->patch(route('fjp.status', $fjp), ['status' => Fjp::STATUS_CONFIRM])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(Fjp::STATUS_CONFIRM, $fjp->fresh()->status);

        $this->actingAs($pic)
            ->from(route('fjp.index'))
            ->patch(route('fjp.status', $fjp), ['status' => Fjp::STATUS_DONE])
            ->assertRedirect(route('fjp.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame(Fjp::STATUS_DONE, $fjp->fresh()->status);
    }

    public function test_pic_fjp_status_changes_keep_linked_events_in_sync(): void
    {
        $pic = $this->user('pic@indosakti.com');
        $fjp = $this->createFjp(['status' => Fjp::STATUS_PLAN]);
        $event = Event::create([
            'event_code' => Event::generateCode(),
            'event_name' => 'Event status FJP',
            'fjp_id' => $fjp->id,
            'status' => EventStatus::PLANNING,
            'pic_id' => $pic->id,
        ]);

        $this->actingAs($pic)
            ->patch(route('fjp.status', $fjp), ['status' => Fjp::STATUS_CONFIRM])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(Fjp::STATUS_CONFIRM, $fjp->fresh()->status);
        $this->assertSame(EventStatus::TEAM_ASSIGNMENT, $event->fresh()->status);

        $this->actingAs($pic)
            ->patch(route('fjp.status', $fjp), ['status' => Fjp::STATUS_CANCEL])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(Fjp::STATUS_CANCEL, $fjp->fresh()->status);
        $this->assertSame(EventStatus::CANCELLED, $event->fresh()->status);
    }

    public function test_pic_event_detail_exposes_the_fjp_status_editor_for_linked_events(): void
    {
        $pic = $this->user('pic@indosakti.com');
        $fjp = $this->createFjp(['status' => Fjp::STATUS_CONFIRM]);
        $event = Event::create([
            'event_code' => Event::generateCode(),
            'event_name' => 'Event detail status FJP',
            'fjp_id' => $fjp->id,
            'status' => EventStatus::TEAM_ASSIGNMENT,
            'pic_id' => $pic->id,
        ]);

        $this->actingAs($pic)
            ->get(route('events.show', $event))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('fjp.status', Fjp::STATUS_CONFIRM)
                ->where('can.update_fjp_status', true)
                ->where('fjpStatusOptions.0.value', Fjp::STATUS_PLAN)
                ->where('fjpStatusOptions.1.value', Fjp::STATUS_CONFIRM));
    }

    public function test_linked_event_uses_fjp_status_and_only_reports_team_readiness(): void
    {
        $pic = $this->user('pic@indosakti.com');
        $fjp = $this->createFjp(['status' => Fjp::STATUS_CONFIRM]);
        $event = Event::create([
            'event_code' => Event::generateCode(),
            'event_name' => 'Event readiness FJP',
            'fjp_id' => $fjp->id,
            'status' => EventStatus::TEAM_ASSIGNMENT,
            'pic_id' => $pic->id,
        ]);

        $this->actingAs($pic)
            ->get(route('events.show', $event))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('event.status', EventStatus::TEAM_ASSIGNMENT->value)
                ->where('event.display_status', Fjp::STATUS_CONFIRM)
                ->where('event.display_status_label', 'Confirm')
                ->where('readiness.modules.team', 'PENDING')
                ->missing('readiness.modules.permission')
                ->missing('readiness.modules.warehouse')
                ->missing('readiness.modules.kitchen')
                ->missing('readiness.percentage'));

        EventTeamMember::create([
            'event_id' => $event->id,
            'user_id' => $this->user('team@indosakti.com')->id,
            'position' => 'Team Leader',
        ]);

        $this->actingAs($pic)
            ->get(route('events.show', $event))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('readiness.modules.team', 'READY'));
    }

    public function test_fjp_status_update_rejects_values_outside_the_general_status_list(): void
    {
        $permitter = $this->user('permitter@indosakti.com');
        $fjp = $this->createFjp([
            'status' => Fjp::STATUS_PLAN,
            'permit_status' => PermitStatus::SUBMITTED,
        ]);

        $this->actingAs($permitter)
            ->from(route('fjp.index'))
            ->patch(route('fjp.status', $fjp), ['status' => 'approved'])
            ->assertRedirect(route('fjp.index'))
            ->assertSessionHasErrors('status');

        $this->assertSame(Fjp::STATUS_PLAN, $fjp->fresh()->status);
        $this->assertSame(PermitStatus::SUBMITTED, $fjp->fresh()->permit_status);
    }

    public function test_fjp_detail_exposes_general_status_without_permit_workflow_actions(): void
    {
        $permitter = $this->user('permitter@indosakti.com');
        $fjp = $this->createFjp([
            'status' => Fjp::STATUS_PLAN,
            'permit_status' => PermitStatus::SUBMITTED,
        ]);

        $this->actingAs($permitter)
            ->get(route('fjp.show', $fjp))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Fjp/Show')
                ->where('fjp.status', Fjp::STATUS_PLAN)
                ->missing('fjp.permit_status')
                ->where('can.update', true)
                ->missing('can.approve')
                ->missing('can.reject')
                ->missing('can.cancel'));
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    /** @param array<string, mixed> $overrides */
    private function createFjp(array $overrides = []): Fjp
    {
        return Fjp::create(array_merge([
            'fjp_number' => Fjp::generateNumber(),
            'event_name' => 'FJP Status Uji '.uniqid(),
            'school_name' => 'Sekolah Status Uji',
            'permit_status' => PermitStatus::DRAFT,
            'status' => Fjp::STATUS_PLAN,
        ], $overrides));
    }
}
