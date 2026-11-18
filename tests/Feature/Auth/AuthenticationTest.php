<?php

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $role = $this->makeRole('pic', 'PIC Event');
        $user = User::factory()->create();
        $user->roles()->attach($role);
        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticated();
        $this->assertSame($role->id, $user->fresh()->active_role_id);
    }

    public function test_users_can_authenticate_with_surrounding_email_spaces(): void
    {
        $role = $this->makeRole('pic', 'PIC Event');
        $user = User::factory()->create();
        $user->roles()->attach($role);

        $response = $this->post('/login', [
            'email' => '  '.$user->email.'  ',
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_with_multiple_roles_must_select_one_before_login(): void
    {
        $user = User::factory()->create();
        $roles = collect([
            $this->makeRole('pic', 'PIC Event'),
            $this->makeRole('team', 'Tim Lapangan'),
        ]);
        $user->roles()->attach($roles);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertRedirect(route('login'));
        $this->assertSame($user->id, session('auth.pending_user_id'));
        $this->assertNull($user->fresh()->active_role_id);

        $this->get('/login')->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Login')
            ->where('showRoleSelection', true)
            ->where('pendingEmail', $user->email)
            ->has('roles', 2));
    }

    public function test_multi_role_user_can_complete_login_by_selecting_a_role(): void
    {
        $user = User::factory()->create();
        $roles = collect([
            $this->makeRole('pic', 'PIC Event'),
            $this->makeRole('team', 'Tim Lapangan'),
        ]);
        $user->roles()->attach($roles);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $selected = $roles->firstWhere('slug', 'team');

        $response = $this->post('/login/role', [
            'role_id' => $selected->id,
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertSame($selected->id, $user->fresh()->active_role_id);
        $this->assertNull(session('auth.pending_user_id'));
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'select_role',
            'role' => 'team',
        ]);
    }

    public function test_user_cannot_select_a_role_they_do_not_have(): void
    {
        $user = User::factory()->create();
        $user->roles()->attach($this->makeRole('pic', 'PIC Event'));
        $user->roles()->attach($this->makeRole('team', 'Tim Lapangan'));
        $foreignRole = $this->makeRole('warehouse', 'Gudang');

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response = $this->post('/login/role', [
            'role_id' => $foreignRole->id,
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('role_id');
        $this->assertNull($user->fresh()->active_role_id);
    }

    public function test_role_selection_requires_a_pending_login(): void
    {
        $role = $this->makeRole('pic', 'PIC Event');

        $response = $this->post('/login/role', [
            'role_id' => $role->id,
        ]);

        $this->assertGuest();
        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('role_id');
    }

    public function test_users_without_roles_cannot_login(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }

    private function makeRole(string $slug, string $name): Role
    {
        return Role::create([
            'slug' => $slug,
            'name' => $name,
        ]);
    }
}
