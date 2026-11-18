<?php

namespace Tests\Feature;

use App\Enums\RoleSlug;
use App\Models\Event;
use App\Models\EventReport;
use App\Models\Role;
use App\Models\User;
use App\Support\EventAccess;
use App\Support\Permissions;
use App\Support\ReportAccess;
use App\Support\RoleDefinitions;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(DemoDataSeeder::class);
    }

    public function test_role_definitions_match_seeded_permissions(): void
    {
        foreach (RoleDefinitions::all() as $slug => $definition) {
            $role = Role::where('slug', $slug)->firstOrFail();
            $expected = $definition['permissions'] === '*'
                ? Permissions::slugs()
                : $definition['permissions'];

            $this->assertEqualsCanonicalizing(
                $expected,
                $role->permissions()->pluck('slug')->all(),
                "Permission matrix mismatch for role {$slug}.",
            );
        }
    }

    public function test_developer_role_receives_every_permission(): void
    {
        $role = Role::where('slug', RoleSlug::DEVELOPER->value)->firstOrFail();

        $this->assertTrue($role->is_system);
        $this->assertEqualsCanonicalizing(
            Permissions::slugs(),
            $role->permissions()->pluck('slug')->all(),
        );
    }

    public function test_seeded_developer_can_login(): void
    {
        $developer = $this->user('developer@indosakti.com');

        $this->post('/login', [
            'email' => $developer->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($developer);
        $this->assertSame(RoleSlug::DEVELOPER->value, $developer->fresh()->activeRoleSlug());
    }

    public function test_developer_can_access_operational_and_administrative_areas(): void
    {
        $uris = [
            '/dashboard',
            '/admin/users',
            '/admin/audit-logs',
            '/events',
            '/team',
            '/fjp',
            '/permits',
            '/warehouse/inventory',
            '/kitchen',
            '/reports/by-event',
            '/quick-reports/history',
            '/attendance/payroll',
        ];

        foreach ($uris as $uri) {
            $this->actingAs($this->user('developer@indosakti.com'))
                ->get($uri)
                ->assertOk();
        }
    }

    public function test_operational_roles_cannot_access_user_management(): void
    {
        $emails = [
            'permitter@indosakti.com',
            'pic@indosakti.com',
            'team@indosakti.com',
            'warehouse@indosakti.com',
            'kitchen@indosakti.com',
            'admin.event@indosakti.com',
        ];

        foreach ($emails as $email) {
            $this->actingAs($this->user($email))
                ->get('/admin/users')
                ->assertForbidden();

            $this->actingAs($this->user($email))
                ->get('/admin/audit-logs')
                ->assertForbidden();
        }
    }

    public function test_developer_bypasses_event_and_report_scoping(): void
    {
        $developer = $this->user('developer@indosakti.com');
        $event = Event::firstOrFail();
        $report = EventReport::firstOrFail();

        $this->assertTrue(EventAccess::allows($developer, $event));
        $this->assertTrue(ReportAccess::allows($developer, $report));

        $this->assertSame(
            Event::count(),
            $this->actingAs($developer)->get('/events')->inertiaProps('eventCount'),
        );
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }
}
