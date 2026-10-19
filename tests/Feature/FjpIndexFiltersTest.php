<?php

namespace Tests\Feature;

use App\Enums\PermitStatus;
use App\Models\Brand;
use App\Models\Fjp;
use App\Models\User;
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
            ->where('filters.brand_id', (string) $brandA->id)
            ->where('filters.date_from', now()->subDays(7)->toDateString())
            ->where('filters.date_to', now()->toDateString())
            ->where('totalCount', 1));

        $ids = collect($response->viewData('page')['props']['fjps']['data'])->pluck('id');
        $this->assertTrue($ids->contains($inside->id));
        $this->assertFalse($ids->contains($otherBrand->id));
        $this->assertFalse($ids->contains($outsideRange->id));
    }

    public function test_status_counts_respect_the_brand_filter(): void
    {
        $admin = $this->admin();
        $brandA = $this->brand('Brand Count A');
        $brandB = $this->brand('Brand Count B');

        $this->createFjp(['brand_id' => $brandA->id, 'permit_status' => PermitStatus::DRAFT]);
        $this->createFjp(['brand_id' => $brandA->id, 'permit_status' => PermitStatus::SUBMITTED]);
        $this->createFjp(['brand_id' => $brandB->id, 'permit_status' => PermitStatus::DRAFT]);

        $this->actingAs($admin)
            ->get(route('fjp.index', ['brand_id' => $brandA->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('totalCount', 2)
                ->where('statusCounts.draft', 1)
                ->where('statusCounts.submitted', 1));
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
            'npsn' => '20112345',
            'permit_status' => PermitStatus::DRAFT,
            'status' => 'draft',
        ], $overrides));
    }
}
