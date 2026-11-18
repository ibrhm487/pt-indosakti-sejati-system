<?php

namespace Tests\Feature;

use App\Enums\PermitStatus;
use App\Enums\VenueStatus;
use App\Models\Brand;
use App\Models\Event;
use App\Models\EventType;
use App\Models\Fjp;
use App\Models\User;
use App\Models\Venue;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class VenueApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(DemoDataSeeder::class);
    }

    public function test_pending_venue_is_hidden_until_a_pic_approves_it(): void
    {
        $pic = $this->user('pic@indosakti.com');
        $venue = Venue::create([
            'name' => 'SDN Menunggu',
            'npsn' => '20999010',
            'is_school' => true,
        ]);

        $this->assertSame(VenueStatus::PENDING, $venue->refresh()->status);

        $before = collect(
            $this->actingAs($pic)->get(route('events.create'))->viewData('page')['props']['venues'],
        );
        $this->assertFalse($before->contains('id', $venue->id));

        $this->actingAs($pic)
            ->from(route('venues.index'))
            ->post(route('venues.approve', $venue))
            ->assertRedirect(route('venues.index'))
            ->assertSessionHasNoErrors();

        $venue->refresh();
        $this->assertSame(VenueStatus::APPROVED, $venue->status);
        $this->assertSame($pic->id, $venue->approved_by);
        $this->assertNotNull($venue->approved_at);

        $after = collect(
            $this->actingAs($pic)->get(route('events.create'))->viewData('page')['props']['venues'],
        );
        $this->assertTrue($after->contains('id', $venue->id));
    }

    public function test_approved_venue_is_usable_without_a_confirmed_fjp(): void
    {
        $admin = $this->user('admin@indosakti.com');
        $venue = Venue::create([
            'name' => 'Lokasi Disetujui',
            'is_school' => false,
            'status' => VenueStatus::APPROVED,
        ]);

        $this->assertSame(0, $venue->fjps()->count());

        foreach ([route('events.create'), route('permits.create')] as $uri) {
            $venues = collect(
                $this->actingAs($admin)->get($uri)->viewData('page')['props']['venues'],
            );

            $this->assertTrue($venues->contains('id', $venue->id), "Venue missing from {$uri}.");
        }
    }

    public function test_pic_can_schedule_an_event_directly_on_an_approved_venue(): void
    {
        $pic = $this->user('pic@indosakti.com');
        $brand = Brand::where('slug', 'milo')->firstOrFail();
        $type = EventType::where('name', 'MILO InStore')->firstOrFail();
        $venue = Venue::create([
            'name' => 'Mall Jadwal Langsung',
            'is_school' => false,
            'status' => VenueStatus::APPROVED,
        ]);

        $this->actingAs($pic)
            ->post(route('events.store'), [
                'venue_id' => $venue->id,
                'brand_id' => $brand->id,
                'event_type_id' => $type->id,
                'event_date' => now()->addDays(10)->toDateString(),
                'start_time' => '09:00',
                'end_time' => '15:00',
                'sampling_target' => 120,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $event = Event::where('venue_id', $venue->id)->firstOrFail();

        $this->assertSame($pic->id, $event->pic_id);
        $this->assertNull($event->fjp_id);
    }

    public function test_pic_can_create_an_approved_non_school_venue(): void
    {
        $pic = $this->user('pic@indosakti.com');

        $this->actingAs($pic)
            ->post(route('venues.store'), [
                'name' => 'Supermarket Baru',
                'address' => 'Jalan Merdeka 10',
                'city' => 'Bandung',
                'province' => 'Jawa Barat',
                'contact_name' => 'Manajer Toko',
                'contact_phone' => '08123456789',
                'latitude' => -6.914744,
                'longitude' => 107.60981,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $venue = Venue::where('name', 'Supermarket Baru')->firstOrFail();

        $this->assertSame(VenueStatus::APPROVED, $venue->status);
        $this->assertFalse($venue->is_school);
        $this->assertSame($pic->id, $venue->approved_by);
        $this->assertNotNull($venue->approved_at);
        $this->assertSame('Bandung', $venue->city);
    }

    public function test_only_roles_with_the_approve_permission_can_approve_a_venue(): void
    {
        $venue = Venue::create(['name' => 'SDN Tanpa Izin']);
        $venue->refresh();

        $this->actingAs($this->user('permitter@indosakti.com'))
            ->post(route('venues.approve', $venue))
            ->assertForbidden();

        $this->assertSame(VenueStatus::PENDING, $venue->fresh()->status);

        $this->actingAs($this->user('pic@indosakti.com'))
            ->post(route('venues.approve', $venue))
            ->assertRedirect();

        $this->assertSame(VenueStatus::APPROVED, $venue->fresh()->status);
    }

    public function test_approving_a_venue_does_not_change_related_fjp_status_or_create_an_event(): void
    {
        $pic = $this->user('pic@indosakti.com');
        $venue = Venue::create([
            'name' => 'Venue FJP Menunggu',
            'status' => VenueStatus::PENDING,
        ]);
        $fjp = Fjp::create([
            'fjp_number' => Fjp::generateNumber(),
            'event_name' => 'Sampling Sekolah',
            'school_name' => $venue->name,
            'venue_id' => $venue->id,
            'event_date' => now()->addDays(8)->toDateString(),
            'start_time' => '08:30',
            'end_time' => '12:30',
            'sampling_target' => 180,
            'permit_status' => PermitStatus::SUBMITTED,
            'status' => Fjp::STATUS_PLAN,
        ]);

        $this->actingAs($pic)
            ->post(route('venues.approve', $venue))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(VenueStatus::APPROVED, $venue->fresh()->status);
        $this->assertSame(Fjp::STATUS_PLAN, $fjp->fresh()->status);
        $this->assertSame(PermitStatus::SUBMITTED, $fjp->fresh()->permit_status);
        $this->assertDatabaseMissing('events', ['fjp_id' => $fjp->id]);
    }

    public function test_venue_index_filters_by_schedule_date_instead_of_geographic_groups(): void
    {
        $pic = $this->user('pic@indosakti.com');
        $date = '2037-01-15';
        $venue = Venue::create(['name' => 'Venue Perlu FJP']);
        $otherVenue = Venue::create(['name' => 'Venue Hari Lain']);
        foreach ([[$venue, $date], [$otherVenue, '2037-01-16']] as [$scheduledVenue, $scheduledDate]) {
            Fjp::create([
                'fjp_number' => Fjp::generateNumber(),
                'event_name' => 'FJP Venue '.$scheduledDate,
                'school_name' => $scheduledVenue->name,
                'venue_id' => $scheduledVenue->id,
                'event_date' => $scheduledDate,
                'status' => Fjp::STATUS_PLAN,
            ]);
        }

        $this->actingAs($pic)
            ->get(route('venues.index', ['date' => $date]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Venues/Index')
                ->where('selectedDate', $date)
                ->has('venues', 1)
                ->where('venues.0.name', $venue->name));
    }

    public function test_pic_can_reject_a_pending_venue_with_a_reason(): void
    {
        $pic = $this->user('pic@indosakti.com');
        $venue = Venue::create(['name' => 'Venue Ditolak']);

        $this->actingAs($pic)
            ->post(route('venues.reject', $venue), [
                'reason' => 'Dokumen lokasi belum lengkap.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(VenueStatus::REJECTED, $venue->fresh()->status);
        $this->assertSame('Dokumen lokasi belum lengkap.', $venue->fresh()->rejection_reason);
    }

    public function test_venue_index_exposes_pending_count_and_approval_flags(): void
    {
        $pic = $this->user('pic@indosakti.com');
        $date = '2037-01-15';

        foreach (['SDN Satu', 'SDN Dua'] as $name) {
            $venue = Venue::create(['name' => $name]);
            Fjp::create([
                'fjp_number' => Fjp::generateNumber(),
                'event_name' => 'FJP '.$name,
                'school_name' => $name,
                'venue_id' => $venue->id,
                'event_date' => $date,
                'status' => Fjp::STATUS_PLAN,
            ]);
        }

        $this->actingAs($pic)
            ->get(route('venues.index', ['date' => $date]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Venues/Index')
                ->where('pendingCount', 2)
                ->where('canApprove', true)
                ->where('selectedDate', $date)
                ->has('venues', 2));
    }

    public function test_venues_created_from_master_data_are_approved_immediately(): void
    {
        $admin = $this->user('admin@indosakti.com');

        $this->actingAs($admin)
            ->post(route('admin.master.store', 'venues'), [
                'name' => 'Lokasi Master Baru',
                'is_school' => false,
            ])
            ->assertRedirect();

        $venue = Venue::where('name', 'Lokasi Master Baru')->firstOrFail();

        $this->assertSame(VenueStatus::APPROVED, $venue->status);
        $this->assertSame($admin->id, $venue->approved_by);
        $this->assertNotNull($venue->approved_at);
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }
}
