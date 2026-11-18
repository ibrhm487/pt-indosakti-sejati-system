<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    public function test_system_admin_can_render_admin_system_pages(): void
    {
        $admin = $this->user('admin@indosakti.com');

        foreach (['/admin/roles', '/admin/system', '/admin/logs'] as $uri) {
            $this->actingAs($admin)->get($uri)->assertOk();
        }
    }

    public function test_operational_roles_cannot_render_admin_system_pages(): void
    {
        $emails = [
            'permitter@indosakti.com',
            'pic@indosakti.com',
            'admin.event@indosakti.com',
        ];

        foreach ($emails as $email) {
            $user = $this->user($email);

            foreach (['/admin/roles', '/admin/system', '/admin/logs'] as $uri) {
                $this->actingAs($user)->get($uri)->assertForbidden();
            }
        }
    }

    public function test_system_roles_can_update_fjp_status_and_view_system(): void
    {
        foreach (['admin@indosakti.com', 'developer@indosakti.com'] as $email) {
            $user = $this->user($email);

            $this->assertTrue($user->hasPermission('fjp.status.update'), $email);
            $this->assertTrue($user->hasPermission('admin.system.view'), $email);
            $this->assertTrue($user->hasPermission('admin.logs.view'), $email);
        }
    }

    public function test_custom_role_can_be_created_updated_and_deleted(): void
    {
        $admin = $this->user('admin@indosakti.com');

        $this->actingAs($admin)->post('/admin/roles', [
            'name' => 'Auditor Internal',
            'slug' => 'auditor-internal',
            'description' => 'Hanya membaca laporan',
            'permissions' => ['dashboard.view', 'reports.view'],
        ])->assertRedirect(route('admin.roles.index'));

        $role = Role::where('slug', 'auditor-internal')->firstOrFail();
        $this->assertFalse($role->is_system);
        $this->assertEqualsCanonicalizing(
            ['dashboard.view', 'reports.view'],
            $role->permissions()->pluck('slug')->all(),
        );

        $this->actingAs($admin)->put("/admin/roles/{$role->id}", [
            'name' => 'Auditor',
            'slug' => 'auditor',
            'description' => null,
            'permissions' => ['dashboard.view'],
        ])->assertRedirect(route('admin.roles.index'));

        $role->refresh();
        $this->assertSame('auditor', $role->slug);
        $this->assertSame(['dashboard.view'], $role->permissions()->pluck('slug')->all());

        $this->actingAs($admin)->delete("/admin/roles/{$role->id}")->assertRedirect();
        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    public function test_system_roles_cannot_be_modified_or_deleted(): void
    {
        $admin = $this->user('admin@indosakti.com');
        $systemRole = Role::where('slug', 'system_admin')->firstOrFail();

        $this->actingAs($admin)->put("/admin/roles/{$systemRole->id}", [
            'name' => 'Diubah',
            'slug' => 'system_admin',
            'permissions' => ['dashboard.view'],
        ])->assertForbidden();

        $this->actingAs($admin)->delete("/admin/roles/{$systemRole->id}")->assertForbidden();
    }
}
