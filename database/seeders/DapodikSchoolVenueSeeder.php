<?php

namespace Database\Seeders;

use App\Enums\PermitStatus;
use App\Enums\VenueStatus;
use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\EventType;
use App\Models\Fjp;
use App\Models\User;
use App\Models\Venue;
use App\Services\DapoApiService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DapodikSchoolVenueSeeder extends Seeder
{
    /** @var array<int, string> */
    public const SCHOOL_NPSNS = [
        '20521207', '20524912', '20512637', '20402390', '20236252',
        '20210101', '20533997', '10605295', '20236230', '20205588',
        '20104105', '20315011', '20524731', '20105933', '20105932',
        '20519434', '20528463', '20502779', '20205459', '20554204',
        '20554344', '20524726', '20502797', '20236240', '20236247',
        '20519395', '20512642', '20531696', '20519397', '20512660',
    ];

    public function run(DapoApiService $dapo): void
    {
        $schools = collect(self::SCHOOL_NPSNS)
            ->map(function (string $npsn) use ($dapo): array {
                $school = $dapo->detail($npsn);

                if ($school === null || $school['npsn'] !== $npsn || blank($school['school_name'] ?? null)) {
                    throw new RuntimeException("Data Dapodik untuk NPSN {$npsn} tidak dapat diverifikasi.");
                }

                return $school;
            })
            ->all();

        $permitter = User::query()->where('email', 'permitter@indosakti.com')->firstOrFail();
        $milo = Brand::query()->where('slug', 'milo')->firstOrFail();
        $miloSchoolSampling = EventType::query()->where('slug', 'milo-school-sampling')->firstOrFail();
        $syncedAt = now();

        DB::transaction(function () use ($schools, $permitter, $milo, $miloSchoolSampling, $syncedAt): void {
            foreach ($schools as $index => $school) {
                $placeholderNpsn = sprintf('9999%04d', $index + 1);
                $placeholder = Venue::query()->where('npsn', $placeholderNpsn)->first();
                $official = Venue::query()->where('npsn', $school['npsn'])->first();

                if ($placeholder && $official && $placeholder->id !== $official->id) {
                    throw new RuntimeException("NPSN {$school['npsn']} sudah dipakai venue lain.");
                }

                $venue = $official ?? $placeholder ?? new Venue;
                $venue->fill([
                    'name' => $school['school_name'],
                    'npsn' => $school['npsn'],
                    'is_school' => true,
                    'address' => $school['address'],
                    'city' => $school['kabupaten'],
                    'province' => $school['provinsi'],
                    'latitude' => $school['latitude'],
                    'longitude' => $school['longitude'],
                    'status' => VenueStatus::APPROVED,
                    'approved_at' => $venue->approved_at ?? $syncedAt,
                ])->save();

                $fjp = Fjp::query()
                    ->where('venue_id', $venue->id)
                    ->where('brand_id', $milo->id)
                    ->where('event_type_id', $miloSchoolSampling->id)
                    ->where('created_by', $permitter->id)
                    ->where('permit_status', PermitStatus::SUBMITTED)
                    ->where('status', 'submitted')
                    ->first();

                if (! $fjp) {
                    continue;
                }

                $trackedFields = [
                    'event_name', 'school_name', 'npsn', 'address', 'city', 'province',
                    'maps_url', 'jumlah_siswa_dapodik', 'dapodik_source', 'dapodik_synced_at',
                ];
                $oldValues = $fjp->only($trackedFields);
                $newValues = [
                    'event_name' => 'MILO School Sampling - '.$school['school_name'],
                    'school_name' => $school['school_name'],
                    'npsn' => $school['npsn'],
                    'address' => $school['address'],
                    'city' => $school['kabupaten'],
                    'province' => $school['provinsi'],
                    'maps_url' => $school['maps_url'],
                    'jumlah_siswa_dapodik' => $school['jumlah_siswa'] === null
                        ? null
                        : (int) $school['jumlah_siswa'],
                    'dapodik_source' => 'Dapodik (dapo.kemendikdasmen.go.id)',
                    'dapodik_synced_at' => $syncedAt,
                ];

                $fjp->update($newValues);
                $currentValues = $fjp->only($trackedFields);

                if ($oldValues !== $currentValues) {
                    AuditLog::firstOrCreate(
                        [
                            'user_id' => null,
                            'role' => 'system',
                            'action' => 'SYNC_DAPODIK',
                            'module' => 'fjp',
                            'record_type' => $fjp->getMorphClass(),
                            'record_id' => $fjp->id,
                        ],
                        [
                            'old_values' => $oldValues,
                            'new_values' => $currentValues,
                        ],
                    );
                }
            }
        });
    }
}
