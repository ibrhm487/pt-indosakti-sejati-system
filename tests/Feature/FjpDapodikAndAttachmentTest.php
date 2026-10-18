<?php

namespace Tests\Feature;

use App\Enums\PermitStatus;
use App\Models\Brand;
use App\Models\EventType;
use App\Models\Fjp;
use App\Models\FjpAttachment;
use App\Models\User;
use App\Models\Venue;
use App\Services\DapoApiService;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

class FjpDapodikAndAttachmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(DemoDataSeeder::class);

        Storage::fake('local');
        Storage::fake('public');
    }

    public function test_dapodik_sync_updates_only_dapodik_sourced_fields(): void
    {
        $permitter = $this->user('permitter@indosakti.com');
        $fjp = $this->createFjp([
            'jumlah_siswa_sekolah' => 320,
            'jumlah_siswa_dapodik' => 300,
        ]);

        $this->mock(DapoApiService::class, function ($mock) {
            $mock->shouldReceive('detail')
                ->once()
                ->with('20112345')
                ->andReturn([
                    'npsn' => '20112345',
                    'school_name' => 'SMPN Baru',
                    'address' => 'Jl. Baru No. 1',
                    'jumlah_siswa' => 305,
                    'maps_url' => 'https://maps.example.test/1',
                ]);
        });

        $this->actingAs($permitter)
            ->from(route('fjp.show', $fjp))
            ->post(route('fjp.dapodik', $fjp))
            ->assertSessionHasNoErrors();

        $fresh = $fjp->fresh();
        $this->assertSame(320, $fresh->jumlah_siswa_sekolah, 'Nilai manual sekolah tidak boleh ditimpa.');
        $this->assertSame(305, $fresh->jumlah_siswa_dapodik);
        $this->assertNotNull($fresh->dapodik_synced_at);
        $this->assertStringContainsString('Dapodik', $fresh->dapodik_source);
    }

    public function test_failed_dapodik_sync_leaves_existing_data_intact(): void
    {
        $permitter = $this->user('permitter@indosakti.com');
        $fjp = $this->createFjp([
            'jumlah_siswa_sekolah' => 320,
            'jumlah_siswa_dapodik' => 300,
        ]);

        $this->mock(DapoApiService::class, function ($mock) {
            $mock->shouldReceive('detail')
                ->once()
                ->andThrow(new RuntimeException('down'));
        });

        $this->actingAs($permitter)
            ->from(route('fjp.show', $fjp))
            ->post(route('fjp.dapodik', $fjp))
            ->assertSessionHas('error');

        $fresh = $fjp->fresh();
        $this->assertSame(320, $fresh->jumlah_siswa_sekolah);
        $this->assertSame(300, $fresh->jumlah_siswa_dapodik);
        $this->assertNull($fresh->dapodik_synced_at);
    }

    public function test_fjp_form_persists_scheduling_and_target_fields(): void
    {
        $permitter = $this->user('permitter@indosakti.com');
        $brand = Brand::firstOrFail();
        $eventType = EventType::firstOrFail();
        $venue = Venue::firstOrFail();

        $this->actingAs($permitter)
            ->post(route('fjp.store'), [
                'school_name' => 'SMPN Store',
                'npsn' => '20998877',
                'brand_id' => $brand->id,
                'event_type_id' => $eventType->id,
                'venue_id' => $venue->id,
                'event_date' => now()->addDays(14)->toDateString(),
                'start_time' => '08:00',
                'end_time' => '12:00',
                'sampling_target' => 750,
                'jumlah_siswa_sekolah' => 400,
                'jumlah_siswa_dapodik' => 390,
                'dapodik_source' => 'Manual',
            ])
            ->assertRedirect();

        $fjp = Fjp::where('npsn', '20998877')->firstOrFail();
        $this->assertSame($brand->id, $fjp->brand_id);
        $this->assertSame(750, $fjp->sampling_target);
        $this->assertSame('08:00', $fjp->start_time);
        $this->assertSame('12:00', $fjp->end_time);
        $this->assertNotNull($fjp->event_date);
    }

    public function test_create_page_exposes_master_data_options(): void
    {
        $permitter = $this->user('permitter@indosakti.com');

        $this->actingAs($permitter)
            ->get(route('fjp.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Fjp/Create')
                ->has('formOptions.brands')
                ->has('formOptions.eventTypes')
                ->has('formOptions.venues'));
    }

    public function test_fjp_attachment_access_follows_the_permission_matrix(): void
    {
        $permitter = $this->user('permitter@indosakti.com');
        $team = $this->user('team@indosakti.com');
        $fjp = $this->createFjp([]);

        Storage::disk('local')->put('fjp/test-file.pdf', 'dummy');
        $attachment = FjpAttachment::create([
            'fjp_id' => $fjp->id,
            'category' => 'permit',
            'original_name' => 'surat.pdf',
            'path' => 'fjp/test-file.pdf',
            'mime' => 'application/pdf',
            'size' => 5,
            'uploaded_by' => $permitter->id,
        ]);

        $this->get(route('fjp.attachments.download', $attachment))
            ->assertRedirect();

        $this->actingAs($permitter)
            ->post(route('fjp.attachments.store', $fjp), [
                'category' => 'permit',
                'file' => UploadedFile::fake()->create('surat.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($permitter)
            ->get(route('fjp.attachments.download', $attachment))
            ->assertOk();

        $this->actingAs($team)
            ->get(route('fjp.attachments.download', $attachment))
            ->assertForbidden();

        $this->actingAs($this->user('admin.event@indosakti.com'))
            ->get(route('fjp.attachments.download', $attachment))
            ->assertForbidden();
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    /** @param array<string, mixed> $overrides */
    private function createFjp(array $overrides): Fjp
    {
        return Fjp::create(array_merge([
            'fjp_number' => Fjp::generateNumber(),
            'event_name' => 'FJP Dapodik '.uniqid(),
            'school_name' => 'Sekolah Uji',
            'npsn' => '20112345',
            'permit_status' => PermitStatus::DRAFT,
            'status' => 'draft',
        ], $overrides));
    }
}
