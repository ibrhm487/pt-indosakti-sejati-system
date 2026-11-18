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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
                    'kabupaten' => 'Kota Bogor',
                    'provinsi' => 'Jawa Barat',
                    'kecamatan' => 'Bogor Tengah',
                    'kelurahan' => 'Pabaton',
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
        $this->assertSame('Kota Bogor', $fresh->city);
        $this->assertSame('Jawa Barat', $fresh->province);
        $this->assertSame('Bogor Tengah', $fresh->kecamatan);
        $this->assertSame('Pabaton', $fresh->kelurahan);
        $this->assertNotNull($fresh->dapodik_synced_at);
        $this->assertStringContainsString('Dapodik', $fresh->dapodik_source);
    }

    public function test_changed_npsn_autofills_all_school_identity_fields_from_dapodik(): void
    {
        $permitter = $this->user('permitter@indosakti.com');
        $fjp = $this->createFjp([
            'npsn' => '20111111',
            'school_name' => 'Nama Lama',
            'city' => 'Kota Lama',
            'province' => 'Provinsi Lama',
            'kecamatan' => 'Kecamatan Lama',
            'kelurahan' => 'Kelurahan Lama',
            'address' => 'Alamat Lama',
        ]);

        $this->mock(DapoApiService::class, function ($mock): void {
            $mock->shouldReceive('detail')
                ->once()
                ->with('20122222')
                ->andReturn([
                    'npsn' => '20122222',
                    'school_name' => 'SDN Dapodik Baru',
                    'address' => 'Jl. Dapodik Baru',
                    'kabupaten' => 'Jakarta Timur',
                    'provinsi' => 'DKI Jakarta',
                    'kecamatan' => 'Cakung',
                    'kelurahan' => 'Penggilingan',
                    'jumlah_siswa' => 450,
                    'maps_url' => 'https://maps.example.test/dapodik',
                ]);
        });

        $this->actingAs($permitter)
            ->post(route('fjp.dapodik', $fjp), ['npsn' => '20122222'])
            ->assertSessionHasNoErrors();

        $fresh = $fjp->fresh();
        $this->assertSame('20122222', $fresh->npsn);
        $this->assertSame('SDN Dapodik Baru', $fresh->school_name);
        $this->assertSame('Jl. Dapodik Baru', $fresh->address);
        $this->assertSame('Jakarta Timur', $fresh->city);
        $this->assertSame('DKI Jakarta', $fresh->province);
        $this->assertSame('Cakung', $fresh->kecamatan);
        $this->assertSame('Penggilingan', $fresh->kelurahan);
        $this->assertSame(450, $fresh->jumlah_siswa_dapodik);
        $this->assertSame('https://maps.example.test/dapodik', $fresh->maps_url);
    }

    public function test_dapodik_sync_does_not_overwrite_manual_city_and_province(): void
    {
        $permitter = $this->user('permitter@indosakti.com');
        $fjp = $this->createFjp([
            'city' => 'Kota Manual',
            'province' => 'Provinsi Manual',
            'kecamatan' => 'Kecamatan Manual',
            'kelurahan' => 'Kelurahan Manual',
        ]);

        $this->mock(DapoApiService::class, function ($mock) {
            $mock->shouldReceive('detail')
                ->once()
                ->with('20112345')
                ->andReturn([
                    'npsn' => '20112345',
                    'school_name' => 'SMPN Baru',
                    'address' => 'Jl. Baru No. 1',
                    'kabupaten' => 'Kota Dapodik',
                    'provinsi' => 'Provinsi Dapodik',
                    'kecamatan' => 'Kecamatan Dapodik',
                    'kelurahan' => 'Kelurahan Dapodik',
                    'jumlah_siswa' => 305,
                ]);
        });

        $this->actingAs($permitter)
            ->post(route('fjp.dapodik', $fjp))
            ->assertSessionHasNoErrors();

        $fresh = $fjp->fresh();
        $this->assertSame('Kota Manual', $fresh->city);
        $this->assertSame('Provinsi Manual', $fresh->province);
        $this->assertSame('Kecamatan Manual', $fresh->kecamatan);
        $this->assertSame('Kelurahan Manual', $fresh->kelurahan);
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
                'city' => 'Kota Store',
                'province' => 'Provinsi Store',
                'kecamatan' => 'Kecamatan Store',
                'kelurahan' => 'Kelurahan Store',
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
        $this->assertSame('Kota Store', $fjp->city);
        $this->assertSame('Provinsi Store', $fjp->province);
        $this->assertSame('Kecamatan Store', $fjp->kecamatan);
        $this->assertSame('Kelurahan Store', $fjp->kelurahan);
        $this->assertSame(750, $fjp->sampling_target);
        $this->assertSame('08:00', $fjp->start_time);
        $this->assertSame('12:00', $fjp->end_time);
        $this->assertNotNull($fjp->event_date);
    }

    public function test_pic_can_create_a_jakarta_fjp_draft_from_dapodik(): void
    {
        $pic = $this->user('pic@indosakti.com');

        $this->mock(DapoApiService::class, function ($mock): void {
            $mock->shouldReceive('detail')
                ->once()
                ->with('20998879')
                ->andReturn([
                    'npsn' => '20998879',
                    'school_name' => 'SMPN Jakarta Dapodik',
                    'address' => 'Jl. Jakarta No. 1',
                    'kabupaten' => 'Kota Jakarta Selatan',
                    'provinsi' => 'DKI Jakarta',
                    'kecamatan' => 'Kebayoran Baru',
                    'kelurahan' => 'Senayan',
                    'jumlah_siswa' => 525,
                    'maps_url' => 'https://maps.example.test/jakarta',
                ]);
        });

        $this->actingAs($pic)
            ->from(route('fjp.index'))
            ->post(route('fjp.dapodik-schools.store'), ['npsn' => '20998879'])
            ->assertRedirect(route('fjp.index'))
            ->assertSessionHasNoErrors();

        $fjp = Fjp::where('npsn', '20998879')->firstOrFail();
        $this->assertSame('SMPN Jakarta Dapodik', $fjp->school_name);
        $this->assertSame('SMPN Jakarta Dapodik', $fjp->event_name);
        $this->assertSame('Kota Jakarta Selatan', $fjp->city);
        $this->assertSame('DKI Jakarta', $fjp->province);
        $this->assertSame('Kebayoran Baru', $fjp->kecamatan);
        $this->assertSame('Senayan', $fjp->kelurahan);
        $this->assertSame(525, $fjp->jumlah_siswa_dapodik);
        $this->assertSame(Fjp::STATUS_PLAN, $fjp->status);
        $this->assertNull($fjp->brand_id);
        $this->assertNull($fjp->event_date);
        $this->assertSame($pic->id, $fjp->created_by);
        $this->assertSame('Dapodik (dapo.kemendikdasmen.go.id)', $fjp->dapodik_source);
        $this->assertNotNull($fjp->venue_id);
    }

    public function test_user_can_create_multiple_fjp_drafts_from_dapodik_rows(): void
    {
        $pic = $this->user('pic@indosakti.com');
        $brand = Brand::where('is_active', true)->orderBy('name')->firstOrFail();
        $eventType = EventType::where('brand_id', $brand->id)
            ->where('requires_school', true)
            ->where('is_active', true)
            ->first();

        $this->mock(DapoApiService::class, function ($mock): void {
            $mock->shouldReceive('detail')
                ->once()
                ->with('20998881')
                ->andReturn([
                    'npsn' => '20998881',
                    'school_name' => 'SDN Batch Satu',
                    'address' => 'Jl. Batch No. 1',
                    'kabupaten' => 'Kota Jakarta Selatan',
                    'provinsi' => 'DKI Jakarta',
                    'kecamatan' => 'Kebayoran Baru',
                    'kelurahan' => 'Senayan',
                    'jumlah_siswa' => 420,
                ]);
            $mock->shouldReceive('detail')
                ->once()
                ->with('20998882')
                ->andReturn([
                    'npsn' => '20998882',
                    'school_name' => 'SDN Batch Dua',
                    'kabupaten' => 'Kota Bogor',
                    'provinsi' => 'DKI Jakarta',
                    'kecamatan' => 'Cakung',
                    'kelurahan' => 'Penggilingan',
                    'jumlah_siswa' => 315,
                ]);
        });

        $this->actingAs($pic)
            ->from(route('fjp.index'))
            ->post(route('fjp.dapodik-schools.batch'), [
                'schools' => [
                    ['npsn' => '20998881', 'jumlah_siswa_sekolah' => 410, 'event_date' => '2026-11-20', 'start_time' => '08:30'],
                    ['npsn' => '20998882', 'jumlah_siswa_sekolah' => null],
                ],
                'brand_id' => $brand->id,
                'event_type_id' => $eventType?->id,
            ])
            ->assertRedirect(route('fjp.index'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', '2 FJP draft berhasil ditambahkan dari Dapodik.');

        $firstFjp = Fjp::where('npsn', '20998881')->firstOrFail();
        $secondFjp = Fjp::where('npsn', '20998882')->firstOrFail();
        $this->assertSame('SDN Batch Satu', $firstFjp->school_name);
        $this->assertSame($brand->id, $firstFjp->brand_id);
        $this->assertSame($eventType?->id, $firstFjp->event_type_id);
        $this->assertSame('Kebayoran Baru', $firstFjp->kecamatan);
        $this->assertSame('Senayan', $firstFjp->kelurahan);
        $this->assertSame(420, $firstFjp->jumlah_siswa_dapodik);
        $this->assertSame(410, $firstFjp->jumlah_siswa_sekolah);
        $this->assertSame(410, $firstFjp->sampling_target);
        $this->assertSame('2026-11-20', $firstFjp->event_date?->toDateString());
        $this->assertSame('08:30', $firstFjp->start_time);
        $this->assertSame(Fjp::STATUS_PLAN, $firstFjp->status);
        $this->assertNotNull($firstFjp->venue_id);
        $this->assertSame('SDN Batch Dua', $secondFjp->school_name);
        $this->assertSame('Kota Bogor', $secondFjp->city);
        $this->assertSame(315, $secondFjp->jumlah_siswa_dapodik);
        $this->assertNull($secondFjp->jumlah_siswa_sekolah);
        $this->assertNull($secondFjp->sampling_target);
        $this->assertNotNull($secondFjp->venue_id);
    }

    public function test_dapodik_batch_uses_school_sampling_event_type_for_the_selected_brand(): void
    {
        $pic = $this->user('pic@indosakti.com');
        $milo = Brand::where('slug', 'milo')->firstOrFail();
        $schoolSampling = EventType::where('slug', 'milo-school-sampling')->firstOrFail();
        $inStore = EventType::where('slug', 'milo-instore')->firstOrFail();

        $this->mock(DapoApiService::class, function ($mock): void {
            $mock->shouldReceive('detail')
                ->once()
                ->with('20998878')
                ->andReturn([
                    'npsn' => '20998878',
                    'school_name' => 'SDN Milo Sheet',
                    'kabupaten' => 'Kota Jakarta Selatan',
                    'provinsi' => 'DKI Jakarta',
                ]);
        });

        $this->actingAs($pic)
            ->from(route('fjp.index'))
            ->post(route('fjp.dapodik-schools.batch'), [
                'schools' => [['npsn' => '20998878']],
                'brand_id' => $milo->id,
                'event_type_id' => $inStore->id,
            ])
            ->assertRedirect(route('fjp.index'))
            ->assertSessionHasNoErrors();

        $fjp = Fjp::where('npsn', '20998878')->firstOrFail();
        $this->assertSame($milo->id, $fjp->brand_id);
        $this->assertSame($schoolSampling->id, $fjp->event_type_id);
    }

    public function test_dapodik_batch_persists_school_groups_without_creating_events(): void
    {
        $permitter = $this->user('permitter@indosakti.com');

        $this->mock(DapoApiService::class, function ($mock): void {
            foreach ([
                '20998891' => ['SDN Kelompok Satu', 'Kota Jakarta Selatan'],
                '20998892' => ['SDN Kelompok Dua', 'Kota Jakarta Timur'],
                '20998893' => ['SDN Kelompok Tiga', 'Kota Jakarta Utara'],
            ] as $npsn => [$schoolName, $city]) {
                $mock->shouldReceive('detail')
                    ->once()
                    ->with($npsn)
                    ->andReturn([
                        'npsn' => $npsn,
                        'school_name' => $schoolName,
                        'kabupaten' => $city,
                    ]);
            }
        });

        $this->actingAs($permitter)
            ->from(route('fjp.index'))
            ->post(route('fjp.dapodik-schools.batch'), [
                'schools' => [
                    ['npsn' => '20998891', 'team_number' => 1],
                    ['npsn' => '20998892', 'team_number' => 1],
                    ['npsn' => '20998893', 'team_number' => 2],
                ],
            ])
            ->assertRedirect(route('fjp.index'))
            ->assertSessionHasNoErrors();

        $firstFjp = Fjp::where('npsn', '20998891')->firstOrFail();
        $secondFjp = Fjp::where('npsn', '20998892')->firstOrFail();
        $thirdFjp = Fjp::where('npsn', '20998893')->firstOrFail();

        $this->assertNotNull($firstFjp->team_group_id);
        $this->assertSame($firstFjp->team_group_id, $secondFjp->team_group_id);
        $this->assertSame('Tim 1', $firstFjp->team_group_label);
        $this->assertNotSame($firstFjp->team_group_id, $thirdFjp->team_group_id);
        $this->assertSame('Tim 2', $thirdFjp->team_group_label);
        $this->assertDatabaseMissing('events', ['fjp_id' => $firstFjp->id]);
        $this->assertDatabaseMissing('events', ['fjp_id' => $secondFjp->id]);
        $this->assertDatabaseMissing('events', ['fjp_id' => $thirdFjp->id]);
    }

    public function test_user_without_team_assignment_permission_cannot_submit_team_groups(): void
    {
        $pic = $this->user('pic@indosakti.com');
        $picRole = $pic->roles()->where('slug', 'pic')->firstOrFail();
        $permissionId = DB::table('permissions')
            ->where('slug', 'fjp.assign_team')
            ->value('id');

        DB::table('permission_role')
            ->where('role_id', $picRole->id)
            ->where('permission_id', $permissionId)
            ->delete();

        $this->actingAs($pic)
            ->post(route('fjp.dapodik-schools.batch'), [
                'schools' => [
                    ['npsn' => '20998894', 'team_number' => 1],
                ],
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('fjp', ['npsn' => '20998894']);
    }

    public function test_dapodik_batch_rejects_an_invalid_school_without_creating_partial_fjp_rows(): void
    {
        $pic = $this->user('pic@indosakti.com');

        $this->mock(DapoApiService::class, function ($mock): void {
            $mock->shouldReceive('detail')
                ->once()
                ->with('20998883')
                ->andReturn([
                    'npsn' => '20998883',
                    'school_name' => 'SDN Jakarta Valid',
                    'kabupaten' => 'Kota Jakarta Utara',
                ]);
            $mock->shouldReceive('detail')
                ->once()
                ->with('20998884')
                ->andReturn([
                    'npsn' => '20998884',
                    'school_name' => 'SDN Bandung',
                    'kabupaten' => 'Kota Bandung',
                ]);
        });

        $this->actingAs($pic)
            ->from(route('fjp.index'))
            ->post(route('fjp.dapodik-schools.batch'), [
                'schools' => [
                    ['npsn' => '20998883'],
                    ['npsn' => '20998884'],
                ],
            ])
            ->assertRedirect(route('fjp.index'))
            ->assertSessionHasErrors([
                'schools.1.npsn' => 'Pilih sekolah Jabodetabek yang terdaftar di Dapodik.',
            ]);

        $this->assertDatabaseMissing('fjp', ['npsn' => '20998883']);
        $this->assertDatabaseMissing('fjp', ['npsn' => '20998884']);
    }

    public function test_dapodik_batch_creation_requires_fjp_create_permission(): void
    {
        $teamMember = $this->user('team@indosakti.com');

        $this->actingAs($teamMember)
            ->post(route('fjp.dapodik-schools.batch'), [
                'schools' => [
                    ['npsn' => '20998885'],
                ],
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('fjp', ['npsn' => '20998885']);
    }

    public function test_dapodik_school_draft_rejects_non_jakarta_schools(): void
    {
        $permitter = $this->user('permitter@indosakti.com');

        $this->mock(DapoApiService::class, function ($mock): void {
            $mock->shouldReceive('detail')
                ->once()
                ->with('20998880')
                ->andReturn([
                    'npsn' => '20998880',
                    'school_name' => 'SMPN Bandung Dapodik',
                    'kabupaten' => 'Kota Bandung',
                    'provinsi' => 'Jawa Barat',
                ]);
        });

        $this->actingAs($permitter)
            ->from(route('fjp.index'))
            ->post(route('fjp.dapodik-schools.store'), ['npsn' => '20998880'])
            ->assertSessionHasErrors([
                'npsn' => 'Pilih sekolah Jabodetabek yang terdaftar di Dapodik.',
            ]);

        $this->assertDatabaseMissing('fjp', ['npsn' => '20998880']);
    }

    public function test_pic_can_save_fjp_validation_data_and_location_hierarchy(): void
    {
        $pic = $this->user('pic@indosakti.com');
        $fjp = $this->createFjp([]);

        $this->actingAs($pic)
            ->put(route('fjp.update', $fjp), [
                'kecamatan' => 'Kecamatan Contoh',
                'kelurahan' => 'Kelurahan Contoh',
                'permitter_total_outlet_canteen_sell_drink' => 5,
                'permitter_quantity_with_milo' => 2,
                'permitter_status' => 'MILO SACHET',
                'permitter_quantity_without_milo' => 3,
                'team_sampling_quantity_with_milo' => 1,
                'team_sampling_quantity_without_milo' => 1,
            ])
            ->assertRedirect(route('fjp.show', $fjp))
            ->assertSessionHasNoErrors();

        $fresh = $fjp->fresh();
        $this->assertSame('Kecamatan Contoh', $fresh->kecamatan);
        $this->assertSame('Kelurahan Contoh', $fresh->kelurahan);
        $this->assertSame(5, $fresh->permitter_total_outlet_canteen_sell_drink);
        $this->assertSame(2, $fresh->permitter_quantity_with_milo);
        $this->assertSame('MILO SACHET', $fresh->permitter_status);
        $this->assertSame(3, $fresh->permitter_quantity_without_milo);
        $this->assertSame(1, $fresh->team_sampling_quantity_with_milo);
        $this->assertSame(1, $fresh->team_sampling_quantity_without_milo);
    }

    public function test_fjp_rejects_permitter_quantities_above_total_outlets(): void
    {
        $permitter = $this->user('permitter@indosakti.com');
        $fjp = $this->createFjp([]);

        $this->actingAs($permitter)
            ->from(route('fjp.edit', $fjp))
            ->put(route('fjp.update', $fjp), [
                'permitter_total_outlet_canteen_sell_drink' => 2,
                'permitter_quantity_with_milo' => 2,
                'permitter_quantity_without_milo' => 1,
            ])
            ->assertSessionHasErrors([
                'permitter_total_outlet_canteen_sell_drink' => 'Jumlah kuantitas with dan without MILO tidak boleh melebihi total outlet.',
            ]);

        $this->assertNull($fjp->fresh()->permitter_total_outlet_canteen_sell_drink);
    }

    public function test_fjp_defaults_sampling_target_to_the_school_reported_student_count(): void
    {
        $permitter = $this->user('permitter@indosakti.com');

        $this->actingAs($permitter)
            ->post(route('fjp.store'), [
                'school_name' => 'SMPN Target Otomatis',
                'npsn' => '20998878',
                'jumlah_siswa_sekolah' => 425,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(
            425,
            Fjp::where('npsn', '20998878')->firstOrFail()->sampling_target,
        );
    }

    public function test_fjp_attachment_access_follows_the_permission_matrix(): void
    {
        $permitter = $this->user('permitter@indosakti.com');
        $team = $this->user('team@indosakti.com');
        $fjp = $this->createFjp([]);

        Storage::disk('local')->put('fjp/test-file.pdf', 'dummy');
        $attachment = FjpAttachment::create([
            'fjp_id' => $fjp->id,
            'category' => 'supporting',
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
                'category' => 'supporting',
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
            ->assertOk();
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
