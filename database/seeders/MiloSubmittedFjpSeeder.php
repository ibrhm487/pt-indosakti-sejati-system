<?php

namespace Database\Seeders;

use App\Enums\PermitStatus;
use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\EventType;
use App\Models\Fjp;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MiloSubmittedFjpSeeder extends Seeder
{
    public function run(): void
    {
        $permitter = User::query()->where('email', 'permitter@indosakti.com')->firstOrFail();
        $brand = Brand::query()->where('slug', 'milo')->firstOrFail();
        $eventType = EventType::query()->where('slug', 'milo-school-sampling')->firstOrFail();

        DB::transaction(function () use ($permitter, $brand, $eventType): void {
            $start = now()->startOfDay();

            for ($number = 1; $number <= 20; $number++) {
                $npsn = DapodikSchoolVenueSeeder::SCHOOL_NPSNS[$number - 1];
                $venue = Venue::query()
                    ->where('npsn', $npsn)
                    ->where('is_school', true)
                    ->first();

                if (! $venue) {
                    $placeholderNpsn = sprintf('9999%04d', $number);
                    $venue = Venue::query()
                        ->where('npsn', $placeholderNpsn)
                        ->where('is_school', true)
                        ->firstOrFail();
                }

                $eventName = 'MILO School Sampling - '.$venue->name;
                $fjp = Fjp::query()->firstOrNew(['event_name' => $eventName]);

                if ($fjp->exists) {
                    continue;
                }

                $fjp->fill([
                    'fjp_number' => Fjp::generateNumber(),
                    'event_name' => $eventName,
                    'brand_id' => $brand->id,
                    'event_type_id' => $eventType->id,
                    'venue_id' => $venue->id,
                    'school_name' => $venue->name,
                    'npsn' => $venue->npsn,
                    'address' => $venue->address,
                    'city' => $venue->city,
                    'province' => $venue->province,
                    'event_date' => $start->copy()->addWeekdays($number + 2)->toDateString(),
                    'start_time' => '08:00',
                    'end_time' => '12:00',
                    'sampling_target' => 300,
                    'notes' => 'Diajukan oleh Permitter dan menunggu verifikasi PIC.',
                    'jug_required' => true,
                    'permit_status' => PermitStatus::SUBMITTED,
                    'status' => 'submitted',
                    'created_by' => $permitter->id,
                ])->save();

                AuditLog::firstOrCreate(
                    [
                        'user_id' => $permitter->id,
                        'role' => 'permitter',
                        'action' => 'FJP_SUBMIT',
                        'module' => 'fjp',
                        'record_type' => $fjp->getMorphClass(),
                        'record_id' => $fjp->id,
                    ],
                    [
                        'old_values' => ['permit_status' => PermitStatus::DRAFT->value],
                        'new_values' => [
                            'permit_status' => PermitStatus::SUBMITTED->value,
                            'status' => 'submitted',
                        ],
                    ],
                );
            }
        });
    }
}
