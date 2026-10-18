<?php

namespace Tests\Feature;

use App\Models\Fjp;
use App\Models\User;
use App\Services\DapoApiService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class DapodikControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_search_schools_from_the_dapodik_service(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::where('email', 'admin@indosakti.com')->firstOrFail();
        $schools = [['npsn' => '12345678', 'nama' => 'SD Contoh']];

        $this->mock(DapoApiService::class, function (MockInterface $mock) use ($schools): void {
            $mock->shouldReceive('search')->once()->with('SD Contoh', 200, false, null, [])->andReturn($schools);
        });

        $this->actingAs($admin)
            ->getJson(route('dapodik.search', ['q' => 'SD Contoh']))
            ->assertOk()
            ->assertJsonPath('data.0.npsn', '12345678')
            ->assertJsonPath('source', 'Dapodik public portal; verify records before operational use.');
    }

    public function test_search_passes_filters_to_the_service(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::where('email', 'admin@indosakti.com')->firstOrFail();
        $schools = [['npsn' => '12345678', 'nama' => 'SD Contoh']];

        $this->mock(DapoApiService::class, function (MockInterface $mock) use ($schools): void {
            $mock->shouldReceive('search')->once()->with('SD Contoh', 200, true, 'SD', ['NEGERI', 'SWASTA'])->andReturn($schools);
        });

        $this->actingAs($admin)
            ->getJson(route('dapodik.search', [
                'q' => 'SD Contoh',
                'limit' => 200,
                'jabodetabek' => true,
                'jenjang' => 'sd',
                'status' => ['Negeri', 'Swasta'],
            ]))
            ->assertOk()
            ->assertJsonPath('data.0.npsn', '12345678');
    }

    public function test_search_rejects_queries_shorter_than_five_characters(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::where('email', 'admin@indosakti.com')->firstOrFail();

        $this->actingAs($admin)
            ->getJson(route('dapodik.search', ['q' => 'abcd']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('q')
            ->assertJsonPath('errors.q.0', 'The q field must be at least 5 characters.');
    }

    public function test_search_returns_service_unavailable_when_dapodik_is_unreachable(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::where('email', 'admin@indosakti.com')->firstOrFail();

        $this->mock(DapoApiService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('search')
                ->once()
                ->with('Sekolah', 200, false, null, [])
                ->andThrow(new RuntimeException('Upstream timeout'));
        });

        $this->actingAs($admin)
            ->getJson(route('dapodik.search', ['q' => 'Sekolah']))
            ->assertServiceUnavailable()
            ->assertJsonPath('message', 'Layanan Dapodik sedang tidak tersedia. Silakan coba lagi.');
    }

    public function test_school_detail_returns_not_found_when_npsn_is_unknown(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::where('email', 'admin@indosakti.com')->firstOrFail();

        $this->mock(DapoApiService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('detail')->once()->with('00000000')->andReturn(null);
        });

        $this->actingAs($admin)
            ->getJson(route('dapodik.show', '00000000'))
            ->assertNotFound()
            ->assertJsonPath('data', null);
    }

    public function test_fjp_records_manual_student_counts_without_a_dapodik_sync_timestamp(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $permitter = User::where('email', 'permitter@indosakti.com')->firstOrFail();

        $this->actingAs($permitter)->post(route('fjp.store'), [
            'event_name' => 'Manual school data',
            'school_name' => 'SD Contoh',
            'jumlah_siswa_dapodik' => 125,
            'dapodik_source' => 'Manual',
        ])->assertRedirect();

        $fjp = Fjp::query()->firstOrFail();
        $this->assertSame('Manual', $fjp->dapodik_source);
        $this->assertNull($fjp->dapodik_synced_at);
    }

    public function test_fjp_records_the_source_and_retrieval_time_for_portal_school_data(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->travelTo('2026-10-14 10:00:00');
        $permitter = User::where('email', 'permitter@indosakti.com')->firstOrFail();

        $this->actingAs($permitter)->post(route('fjp.store'), [
            'event_name' => 'Portal school data',
            'school_name' => 'SD Contoh',
            'npsn' => '12345678',
            'jumlah_siswa_dapodik' => 125,
            'dapodik_source' => 'Dapodik (dapo.kemendikdasmen.go.id)',
        ])->assertRedirect();

        $fjp = Fjp::query()->firstOrFail();
        $this->assertSame('Dapodik (dapo.kemendikdasmen.go.id)', $fjp->dapodik_source);
        $this->assertSame('2026-10-14 10:00:00', $fjp->dapodik_synced_at->toDateTimeString());
    }
}
