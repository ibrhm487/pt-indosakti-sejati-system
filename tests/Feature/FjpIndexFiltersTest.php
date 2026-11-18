<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Models\Brand;
use App\Models\Event;
use App\Models\EventTeamMember;
use App\Models\Fjp;
use App\Models\User;
use App\Models\Venue;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FjpIndexFiltersTest extends TestCase
{
    use RefreshDatabase;

    public function test_fjp_index_filters_by_brand_and_event_date_range(): void
    {
        $admin = $this->admin();
        $brandA = $this->brand('Brand Filter A');
        $brandB = $this->brand('Brand Filter B');

        $inside = $this->createFjp([
            'brand_id' => $brandA->id,
            'event_date' => now()->subDays(3),
        ]);
        $otherBrand = $this->createFjp([
            'brand_id' => $brandB->id,
            'event_date' => now()->subDays(3),
        ]);
        $outsideRange = $this->createFjp([
            'brand_id' => $brandA->id,
            'event_date' => now()->subDays(40),
        ]);

        $response = $this->actingAs($admin)->get(route('fjp.index', [
            'brand_id' => $brandA->id,
            'date_from' => now()->subDays(7)->toDateString(),
            'date_to' => now()->toDateString(),
        ]));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Fjp/Index')
            ->where('filters.brand_id', $brandA->id)
            ->where('filters.date_from', now()->subDays(7)->toDateString())
            ->where('filters.date_to', now()->toDateString())
            ->where('totalCount', 1));

        $ids = collect($response->viewData('page')['props']['fjps']['data'])->pluck('id');
        $this->assertTrue($ids->contains($inside->id));
        $this->assertFalse($ids->contains($otherBrand->id));
        $this->assertFalse($ids->contains($outsideRange->id));
    }

    public function test_fjp_index_displays_the_jabodetabek_sheet(): void
    {
        $admin = $this->admin();
        $jakarta = $this->createFjp([
            'school_name' => 'SDN Jakarta Filter',
            'city' => 'Jakarta Selatan',
        ]);
        $jakartaVenue = Venue::create([
            'name' => 'Venue Jakarta Filter',
            'city' => 'Jakarta Barat',
        ]);
        $jakartaVenueFjp = $this->createFjp([
            'school_name' => 'SDN Jakarta Barat Filter',
            'venue_id' => $jakartaVenue->id,
        ]);
        $bogor = $this->createFjp([
            'school_name' => 'SDN Bogor Filter',
            'city' => 'Kabupaten Bogor',
        ]);
        $outside = $this->createFjp([
            'school_name' => 'SDN Bandung Filter',
            'city' => 'Kota Bandung',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('fjp.index'));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.city', 'Jabodetabek')
                ->where('totalCount', 3));

        $ids = collect($response->inertiaProps('fjps.data'))->pluck('id');
        $this->assertTrue($ids->contains($jakarta->id));
        $this->assertTrue($ids->contains($jakartaVenueFjp->id));
        $this->assertTrue($ids->contains($bogor->id));
        $this->assertFalse($ids->contains($outside->id));
    }

    public function test_status_counts_respect_the_brand_filter(): void
    {
        $admin = $this->admin();
        $brandA = $this->brand('Brand Count A');
        $brandB = $this->brand('Brand Count B');

        $this->createFjp(['brand_id' => $brandA->id, 'status' => Fjp::STATUS_PLAN]);
        $this->createFjp(['brand_id' => $brandA->id, 'status' => Fjp::STATUS_CONFIRM]);
        $this->createFjp(['brand_id' => $brandB->id, 'status' => Fjp::STATUS_PLAN]);

        $this->actingAs($admin)
            ->get(route('fjp.index', ['brand_id' => $brandA->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('totalCount', 2)
                ->where('statusCounts.plan', 1)
                ->where('statusCounts.confirm', 1));
    }

    public function test_fjp_index_shows_the_merged_team_and_school_assignment(): void
    {
        $admin = $this->admin();
        $teamGroupId = 'team-group-'.uniqid();
        $firstSchool = $this->createFjp([
            'school_name' => 'SDN Sekolah Satu',
            'npsn' => '20110001',
            'start_time' => '10:00',
        ]);
        $secondSchool = $this->createFjp([
            'school_name' => 'SDN Sekolah Dua',
            'npsn' => '20110002',
            'start_time' => '08:00',
        ]);

        $firstEvent = Event::create([
            'event_code' => 'EVT-TEAM-'.uniqid(),
            'event_name' => 'Event Sekolah Satu',
            'fjp_id' => $firstSchool->id,
            'status' => EventStatus::PLANNING,
            'pic_id' => $admin->id,
            'team_group_id' => $teamGroupId,
            'start_time' => '10:30',
        ]);
        Event::create([
            'event_code' => 'EVT-TEAM-'.uniqid(),
            'event_name' => 'Event Sekolah Dua',
            'fjp_id' => $secondSchool->id,
            'status' => EventStatus::PLANNING,
            'pic_id' => $admin->id,
            'team_group_id' => $teamGroupId,
            'start_time' => '08:45',
        ]);
        EventTeamMember::create([
            'event_id' => $firstEvent->id,
            'user_id' => $admin->id,
            'position' => 'Team Leader',
        ]);

        $response = $this->actingAs($admin)->get(route('fjp.index'));
        $response->assertOk();
        $row = collect($response->inertiaProps('fjps.data'))->firstWhere('id', $firstSchool->id);

        $this->assertNotNull($row);
        $this->assertSame([
            [
                'personnel_id' => null,
                'user_id' => $admin->id,
                'position' => 'Team Leader',
                'name' => $admin->name,
                'phone' => null,
            ],
        ], $row['team']['members']);
        $this->assertContains($firstSchool->school_name, $row['team']['schools']);
        $this->assertContains($secondSchool->school_name, $row['team']['schools']);
        $this->assertSame([
            [
                'fjp_id' => $secondSchool->id,
                'school_name' => $secondSchool->school_name,
                'start_time' => '08:45',
            ],
            [
                'fjp_id' => $firstSchool->id,
                'school_name' => $firstSchool->school_name,
                'start_time' => '10:30',
            ],
        ], $row['team']['school_schedule']);

        $teams = collect($response->inertiaProps('teamSchedules'));
        $this->assertCount(1, $teams);
        $this->assertContains($firstSchool->school_name, $teams->first()['schools']);
    }

    public function test_team_schedules_include_all_teams_for_the_displayed_day(): void
    {
        $admin = $this->admin();
        $eventDate = now()->addDays(2)->toDateString();

        for ($index = 0; $index < 16; $index++) {
            $this->createFjp([
                'school_name' => sprintf('SDN Jakarta %02d', $index + 1),
                'city' => 'Jakarta Timur',
                'event_date' => $eventDate,
                'start_time' => sprintf('%02d:00', $index + 7),
            ]);
        }

        $lastSchool = $this->createFjp([
            'school_name' => 'ZZZ Jakarta Terakhir',
            'city' => 'Jakarta Timur',
            'event_date' => $eventDate,
            'start_time' => '23:00',
        ]);
        $event = Event::create([
            'event_code' => 'EVT-LATE-TEAM-'.uniqid(),
            'event_name' => $lastSchool->school_name,
            'fjp_id' => $lastSchool->id,
            'event_date' => $eventDate,
            'start_time' => '23:00',
            'status' => EventStatus::PLANNING,
            'pic_id' => $admin->id,
            'team_group_id' => 'late-team-'.uniqid(),
        ]);
        EventTeamMember::create([
            'event_id' => $event->id,
            'user_id' => $admin->id,
            'position' => 'Team Leader',
        ]);

        $response = $this->actingAs($admin)->get(route('fjp.index'));
        $response->assertOk();
        $teams = collect($response->inertiaProps('teamSchedules'));

        $this->assertCount(1, $teams);
        $this->assertContains($lastSchool->school_name, $teams->first()['schools']);
    }

    public function test_team_schedule_candidates_list_school_names_together_by_date(): void
    {
        $admin = $this->admin();
        $eventDate = now()->addDays(2)->toDateString();
        $highSchool = $this->createFjp([
            'school_name' => 'SMAN Contoh Jakarta',
            'npsn' => '20110004',
            'city' => 'Jakarta Selatan',
            'event_date' => $eventDate,
            'status' => Fjp::STATUS_CONFIRM,
        ]);
        $elementarySchool = $this->createFjp([
            'school_name' => 'SDN Contoh Jakarta',
            'npsn' => '20110003',
            'city' => 'Jakarta Selatan',
            'event_date' => $eventDate,
            'status' => Fjp::STATUS_CONFIRM,
        ]);
        $bogorSchool = $this->createFjp([
            'school_name' => 'SDN Cibinong',
            'npsn' => '20210001',
            'city' => 'Kab. Bogor',
            'province' => 'Jawa Barat',
            'event_date' => $eventDate,
            'status' => Fjp::STATUS_CONFIRM,
        ]);

        foreach ([$elementarySchool, $bogorSchool] as $fjp) {
            $this->actingAs($admin)
                ->patch(route('fjp.inline', $fjp), ['team_number' => 1])
                ->assertSessionHasNoErrors();
        }

        $response = $this->actingAs($admin)->get(route('fjp.index'));

        $response->assertOk();
        $candidates = collect($response->inertiaProps('teamScheduleCandidates'));
        $this->assertSame(
            [$bogorSchool->id, $elementarySchool->id],
            $candidates->pluck('id')->all(),
        );
    }

    public function test_saved_team_schedule_preview_merges_schools_for_the_same_team_and_date(): void
    {
        $admin = $this->admin();
        $date = '2026-12-25';
        $firstSchool = $this->createFjp([
            'school_name' => 'SDN Jadwal PNG Satu',
            'npsn' => '20119001',
            'event_date' => $date,
            'jumlah_siswa_sekolah' => 415,
            'status' => Fjp::STATUS_CONFIRM,
        ]);
        $secondSchool = $this->createFjp([
            'school_name' => 'SDN Jadwal PNG Dua',
            'npsn' => '20119002',
            'event_date' => $date,
            'jumlah_siswa_sekolah' => 416,
            'status' => Fjp::STATUS_CONFIRM,
        ]);
        $teamMember = User::factory()->create(['name' => 'SPG Jadwal']);
        $teamGroupId = 'team-group-preview-'.uniqid();

        foreach ([$firstSchool, $secondSchool] as $fjp) {
            $event = Event::create([
                'event_code' => 'EVT-PREVIEW-'.uniqid(),
                'event_name' => $fjp->school_name,
                'fjp_id' => $fjp->id,
                'event_date' => $date,
                'status' => EventStatus::PLANNING,
                'pic_id' => $admin->id,
                'team_group_id' => $teamGroupId,
            ]);

            EventTeamMember::create([
                'event_id' => $event->id,
                'user_id' => $admin->id,
                'position' => 'Team Leader',
            ]);
            EventTeamMember::create([
                'event_id' => $event->id,
                'user_id' => $teamMember->id,
                'position' => 'SPG',
            ]);
        }
        $unconfirmedFjp = $this->createFjp([
            'school_name' => 'SDN Belum Confirm',
            'npsn' => '20119003',
            'event_date' => $date,
            'status' => Fjp::STATUS_PLAN,
        ]);
        $unconfirmedEvent = Event::create([
            'event_code' => 'EVT-PREVIEW-'.uniqid(),
            'event_name' => $unconfirmedFjp->school_name,
            'fjp_id' => $unconfirmedFjp->id,
            'event_date' => $date,
            'status' => EventStatus::TEAM_ASSIGNMENT,
            'pic_id' => $admin->id,
            'team_group_id' => 'unconfirmed-group-'.uniqid(),
        ]);
        EventTeamMember::create([
            'event_id' => $unconfirmedEvent->id,
            'user_id' => $admin->id,
            'position' => 'Team Leader',
        ]);
        EventTeamMember::create([
            'event_id' => $unconfirmedEvent->id,
            'user_id' => $teamMember->id,
            'position' => 'SPG',
        ]);

        $response = $this->actingAs($admin)
            ->getJson(route('fjp.team-schedule-preview', ['event_date' => $date]));

        $response->assertOk()
            ->assertJsonPath('event_date', $date)
            ->assertJsonCount(1, 'teams')
            ->assertJsonCount(2, 'teams.0.schools')
            ->assertJsonPath('teams.0.total_students', 831)
            ->assertJsonPath('teams.0.id', $teamGroupId)
            ->assertJsonCount(2, 'teams.0.event_ids')
            ->assertJsonPath('teams.0.editable', true)
            ->assertJsonPath('teams.0.members.0.user_id', $admin->id)
            ->assertJsonPath('teams.0.members.1.user_id', $teamMember->id)
            ->assertJsonPath('teams.0.schools.0.name', 'SDN Jadwal PNG Satu')
            ->assertJsonPath('teams.0.schools.1.name', 'SDN Jadwal PNG Dua');
    }

    public function test_saved_team_schedule_preview_requires_a_valid_date(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->getJson(route('fjp.team-schedule-preview', ['event_date' => 'not-a-date']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('event_date');
    }

    public function test_saved_team_schedule_preview_requires_team_assignment_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson(route('fjp.team-schedule-preview', ['event_date' => '2026-12-25']))
            ->assertForbidden();
    }

    public function test_fjp_index_orders_rows_and_date_groups_chronologically(): void
    {
        $admin = $this->admin();

        $latest = $this->createFjp([
            'school_name' => 'SDN Terakhir',
            'event_date' => '2026-12-20',
        ]);
        $undated = $this->createFjp([
            'school_name' => 'SDN Tanpa Tanggal',
            'event_date' => null,
        ]);
        $earliest = $this->createFjp([
            'school_name' => 'SDN Pertama',
            'event_date' => '2026-12-01',
        ]);
        $middle = $this->createFjp([
            'school_name' => 'SDN Tengah',
            'event_date' => '2026-12-10',
        ]);

        $response = $this->actingAs($admin)->get(route('fjp.index'));

        $response->assertOk();

        $this->assertSame(
            [$earliest->id, $middle->id, $latest->id, $undated->id],
            collect($response->inertiaProps('fjps.data'))->pluck('id')->all(),
        );
    }

    private function admin(): User
    {
        $this->seed(RolePermissionSeeder::class);

        return User::where('email', 'admin@indosakti.com')->firstOrFail();
    }

    private function brand(string $name): Brand
    {
        return Brand::create([
            'name' => $name.' '.uniqid(),
            'slug' => 'brand-filter-'.uniqid(),
            'is_active' => true,
        ]);
    }

    private function createFjp(array $overrides = []): Fjp
    {
        return Fjp::create(array_merge([
            'fjp_number' => Fjp::generateNumber(),
            'event_name' => 'FJP Filter '.uniqid(),
            'school_name' => 'Sekolah Uji',
            'city' => 'Jakarta Selatan',
            'npsn' => '20112345',
            'status' => Fjp::STATUS_PLAN,
        ], $overrides));
    }
}
