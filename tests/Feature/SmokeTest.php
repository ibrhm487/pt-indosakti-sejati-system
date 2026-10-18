<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Event;
use App\Models\EventReport;
use App\Models\Fjp;
use App\Models\KitchenRequest;
use App\Models\PermitRequest;
use App\Models\Product;
use App\Models\StockRequest;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(DemoDataSeeder::class);
    }

    private function admin(): User
    {
        return User::where('email', 'admin@indosakti.com')->firstOrFail();
    }

    public function test_guest_is_redirected_from_protected_pages(): void
    {
        foreach (['/dashboard', '/fjp', '/events', '/permits', '/kitchen', '/reports', '/attendance', '/admin/users'] as $uri) {
            $this->get($uri)->assertRedirect('/login');
        }
    }

    public function test_admin_can_render_all_index_and_show_pages(): void
    {
        $fjp = Fjp::first();
        $event = Event::first();
        $permit = PermitRequest::first();
        $stock = StockRequest::first();
        $kitchen = KitchenRequest::first();
        $report = EventReport::first();
        $product = Product::first();
        $attendance = Attendance::first();
        $user = $this->admin();

        $uris = [
            '/dashboard',
            '/team',
            '/fjp',
            '/fjp/create',
            "/fjp/{$fjp->id}",
            "/fjp/{$fjp->id}/edit",
            '/events',
            '/events/create',
            "/events/{$event->id}",
            '/permits',
            '/permits/create',
            "/permits/{$permit->id}",
            '/warehouse/products',
            '/warehouse/products/create',
            "/warehouse/products/{$product->id}/edit",
            '/warehouse/inventory',
            '/warehouse/inventory/movements',
            '/warehouse/mrv',
            '/warehouse/mrv/create',
            "/warehouse/mrv/{$stock->id}",
            '/warehouse/receipts',
            '/warehouse/receipts/create',
            '/warehouse/returns',
            '/warehouse/returns/create',
            '/kitchen',
            '/kitchen/create',
            "/kitchen/{$kitchen->id}",
            '/reports',
            '/reports/create',
            "/reports/{$report->id}",
            '/attendance',
            '/attendance/payroll',
            '/admin/users',
            '/admin/users/create',
            "/admin/users/{$user->id}/edit",
            '/admin/personnel',
            '/admin/master-data',
            '/admin/settings',
            '/admin/audit-logs',
        ];

        foreach ($uris as $uri) {
            $response = $this->actingAs($this->admin())->get($uri);
            $this->assertTrue(
                in_array($response->getStatusCode(), [200, 302], true),
                "Failed rendering {$uri}: HTTP {$response->getStatusCode()}"
            );
        }
    }

    public function test_each_role_can_render_its_landing_pages(): void
    {
        $roles = [
            'permitter@indosakti.com' => ['/dashboard', '/fjp', '/permits'],
            'pic@indosakti.com' => ['/dashboard', '/events'],
            'team@indosakti.com' => ['/dashboard', '/team'],
            'warehouse@indosakti.com' => ['/dashboard', '/warehouse/inventory', '/warehouse/mrv'],
            'kitchen@indosakti.com' => ['/dashboard', '/kitchen'],
        ];

        foreach ($roles as $email => $uris) {
            $user = User::where('email', $email)->firstOrFail();
            foreach ($uris as $uri) {
                $response = $this->actingAs($user)->get($uri);
                $this->assertTrue(
                    in_array($response->getStatusCode(), [200, 302], true),
                    "Role {$email} failed rendering {$uri}: HTTP {$response->getStatusCode()}"
                );
            }
        }
    }

    public function test_fjp_can_be_created_with_school_data_only(): void
    {
        $this->actingAs($this->admin())
            ->post('/fjp', [
                'school_name' => 'SMA Negeri 1 Contoh',
                'npsn' => '20109099',
                'address' => 'Jl. Contoh No. 1, Jakarta',
                'maps_url' => 'https://www.google.com/maps?q=-6.2,106.8',
                'jumlah_siswa_sekolah' => 1000,
                'jumlah_siswa_dapodik' => 980,
                'notes' => 'Catatan uji',
            ])
            ->assertRedirect();

        $fjp = Fjp::where('school_name', 'SMA Negeri 1 Contoh')->firstOrFail();

        $this->assertSame('SMA Negeri 1 Contoh', $fjp->event_name);
        $this->assertSame('20109099', $fjp->npsn);
        $this->assertSame(20, $fjp->student_count_difference);
    }

    public function test_fjp_can_be_created_without_school_name(): void
    {
        $this->actingAs($this->admin())
            ->post('/fjp', ['notes' => 'Hanya catatan'])
            ->assertRedirect();

        $this->assertDatabaseHas('fjp', ['notes' => 'Hanya catatan', 'event_name' => null]);
    }
}
