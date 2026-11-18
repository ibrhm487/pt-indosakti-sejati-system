<?php

namespace Database\Seeders;

use App\Enums\ReportStatus;
use App\Models\Event;
use App\Models\EventReport;
use App\Models\User;
use Illuminate\Database\Seeder;

class DailyOutputSimulationSeeder extends Seeder
{
    public function run(): void
    {
        $adminEvent = User::where('email', 'admin.event@indosakti.com')->first();

        $events = Event::query()
            ->with(['teamMembers.user'])
            ->where('notes', 'like', 'Simulasi pembagian tim PIC%')
            ->orderBy('event_date')
            ->get();

        $factors = [0.95, 1.02, 0.98, 1.05, 0.92, 1.00, 0.88, 1.04, 0.96, 1.01];

        $statuses = [
            ReportStatus::VERIFIED,
            ReportStatus::VERIFIED,
            ReportStatus::SUBMITTED,
            ReportStatus::VERIFIED,
            ReportStatus::SUBMITTED,
            ReportStatus::VERIFIED,
            ReportStatus::REVISION,
            ReportStatus::VERIFIED,
            ReportStatus::SUBMITTED,
            ReportStatus::VERIFIED,
        ];

        foreach ($events->values() as $index => $event) {
            if (! $event->event_date) {
                continue;
            }

            $target = (int) ($event->sampling_target ?: 300);
            $actual = (int) round($target * $factors[$index % count($factors)]);
            $status = $statuses[$index % count($statuses)];

            $spg = $event->teamMembers
                ->first(fn ($member) => strtoupper((string) $member->position) === 'SPG')
                ?->user;

            EventReport::updateOrCreate(
                ['event_id' => $event->id],
                [
                    'sampling_target' => $target,
                    'actual_sampling' => $actual,
                    'achievement_percentage' => $target > 0
                        ? round(($actual / $target) * 100, 2)
                        : 0,
                    'issues' => $index % 4 === 0
                        ? 'Sebagian siswa pulang lebih awal sehingga sesi sampling terpotong.'
                        : null,
                    'notes' => 'Rekap simulasi output harian.',
                    'status' => $status,
                    'submitted_by' => $spg?->id ?? $event->pic_id,
                    'submitted_at' => $event->event_date->copy()->setTime(15, 30),
                    'verified_by' => $status === ReportStatus::VERIFIED
                        ? $adminEvent?->id
                        : null,
                    'verified_at' => $status === ReportStatus::VERIFIED
                        ? $event->event_date->copy()->setTime(17, 0)
                        : null,
                ],
            );
        }
    }
}
