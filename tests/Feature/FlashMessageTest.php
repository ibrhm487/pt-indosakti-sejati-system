<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlashMessageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_profile_update_flashes_success(): void
    {
        $user = User::where('email', 'team@indosakti.com')->firstOrFail();

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Tim Lapangan',
                'email' => $user->email,
            ])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('success');
    }

    public function test_password_update_flashes_success(): void
    {
        $user = User::where('email', 'team@indosakti.com')->firstOrFail();

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->put(route('password.update'), [
                'current_password' => 'password',
                'password' => 'New-Password-123!',
                'password_confirmation' => 'New-Password-123!',
            ])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('success');
    }

    public function test_logout_flashes_success(): void
    {
        $user = User::where('email', 'team@indosakti.com')->firstOrFail();

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect('/')
            ->assertSessionHas('success');
    }

    public function test_event_status_noop_flashes_info(): void
    {
        $admin = User::where('email', 'admin@indosakti.com')->firstOrFail();
        $pic = User::where('email', 'pic@indosakti.com')->firstOrFail();

        $event = Event::create([
            'event_code' => 'EVT-FLASH-'.uniqid(),
            'event_name' => 'Event Flash '.uniqid(),
            'status' => EventStatus::PLANNING,
            'pic_id' => $pic->id,
            'event_date' => now()->addDay()->toDateString(),
        ]);

        $this->actingAs($admin)
            ->from(route('events.show', $event))
            ->patch(route('events.status', $event), [
                'status' => EventStatus::PLANNING->value,
            ])
            ->assertRedirect(route('events.show', $event))
            ->assertSessionHas('info');
    }
}
