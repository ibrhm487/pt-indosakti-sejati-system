<?php

namespace Tests\Feature;

use App\Enums\PermitStatus;
use App\Enums\VenueStatus;
use App\Models\Fjp;
use App\Models\User;
use App\Models\Venue;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class VerificationCenterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_pic_verifies_venues_for_a_selected_day_without_showing_fjp_approval(): void
    {
        $pic = $this->user('pic@indosakti.com');
        $date = '2026-11-13';
        $venue = Venue::create([
            'name' => 'Venue Jadwal Harian',
            'status' => VenueStatus::PENDING,
        ]);
        $fjp = $this->createFjp($venue, $date);

        $response = $this->actingAs($pic)
            ->get(route('verifications.index', [
                'type' => 'fjp',
                'status' => 'pending',
                'date' => $date,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Verification/Index')
                ->where('status', 'pending')
                ->where('selectedDate', $date)
                ->has('items.data', 1));

        $items = collect($response->viewData('page')['props']['items']['data']);
        $this->assertSame('venue', $items->sole()['type']);
        $this->assertSame($venue->name, $items->sole()['name']);
        $this->assertSame(Fjp::STATUS_PLAN, $fjp->fresh()->status);
        $this->assertSame(PermitStatus::SUBMITTED, $fjp->fresh()->permit_status);
    }

    public function test_approved_filter_lists_only_approved_venues_scheduled_for_the_selected_day(): void
    {
        $pic = $this->user('pic@indosakti.com');
        $date = '2026-11-13';
        $approvedVenue = Venue::create([
            'name' => 'Venue Disetujui',
            'status' => VenueStatus::APPROVED,
        ]);
        $pendingVenue = Venue::create([
            'name' => 'Venue Belum Disetujui',
            'status' => VenueStatus::PENDING,
        ]);
        $this->createFjp($approvedVenue, $date);
        $this->createFjp($pendingVenue, $date);

        $response = $this->actingAs($pic)
            ->get(route('verifications.index', ['status' => 'approved', 'date' => $date]))
            ->assertOk();
        $items = collect($response->viewData('page')['props']['items']['data']);

        $this->assertSame([$approvedVenue->id], $items->pluck('id')->all());
    }

    public function test_user_without_venue_verification_permission_cannot_open_the_page(): void
    {
        $this->actingAs($this->user('permitter@indosakti.com'))
            ->get(route('verifications.index'))
            ->assertForbidden();
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    private function createFjp(Venue $venue, string $date): Fjp
    {
        return Fjp::create([
            'fjp_number' => Fjp::generateNumber(),
            'event_name' => 'FJP Verifikasi '.uniqid(),
            'school_name' => $venue->name,
            'venue_id' => $venue->id,
            'event_date' => $date,
            'permit_status' => PermitStatus::SUBMITTED,
            'status' => Fjp::STATUS_PLAN,
        ]);
    }
}
