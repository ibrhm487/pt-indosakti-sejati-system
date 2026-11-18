<?php

namespace Database\Seeders;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\EventTeamMember;
use App\Models\Fjp;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class TeamSimulationSeeder extends Seeder
{
    public function run(): void
    {
        $pic = User::where('email', 'pic@indosakti.com')->first();
        $teamLeaders = User::whereHas('roles', fn ($q) => $q->where('slug', 'team'))
            ->where('name', 'like', '% (TL)')
            ->orderBy('id')
            ->get();
        $spgs = User::whereHas('roles', fn ($q) => $q->where('slug', 'team'))
            ->where('name', 'like', '% (SPG)')
            ->orderBy('id')
            ->get();

        if (! $pic || $teamLeaders->count() < 10 || $spgs->count() < 10) {
            $this->command?->warn('Butuh PIC dan minimal 10 akun TL serta 10 akun SPG. Jalankan TeamAccountSeeder terlebih dahulu.');

            return;
        }

        $fjps = $this->schoolFjps();

        if ($fjps->count() < 10) {
            $this->command?->warn('Butuh minimal 10 FJP sekolah yang sudah confirm. Jalankan RealSchoolFjpSeeder terlebih dahulu.');

            return;
        }

        foreach ($fjps as $index => $fjp) {
            $teamLeader = $teamLeaders[$index];
            $spg = $spgs[$index];
            $startTime = substr((string) $fjp->start_time, 0, 5);
            $endTime = substr((string) $fjp->end_time, 0, 5);

            $event = Event::firstOrNew(['fjp_id' => $fjp->id]);

            if (! $event->exists) {
                $event->event_code = Event::generateCode();
            }

            $event->fill([
                'event_name' => $fjp->event_name,
                'brand_id' => $fjp->brand_id,
                'event_type_id' => $fjp->event_type_id,
                'venue_id' => $fjp->venue_id,
                'event_date' => $fjp->event_date?->toDateString(),
                'start_time' => $fjp->start_time,
                'end_time' => $fjp->end_time,
                'sampling_target' => $fjp->sampling_target ?? 300,
                'notes' => 'Simulasi pembagian tim PIC untuk '.$fjp->school_name.'.',
                'jug_required' => $fjp->jug_required,
                'status' => EventStatus::TEAM_ASSIGNMENT,
                'pic_id' => $pic->id,
                'created_by' => $pic->id,
            ])->save();

            EventTeamMember::updateOrCreate(
                ['event_id' => $event->id, 'user_id' => $teamLeader->id],
                [
                    'position' => 'Team Leader',
                    'status' => 'assigned',
                    'assigned_by' => $pic->id,
                    'schedule_date' => $fjp->event_date?->toDateString(),
                    'report_time' => '07:30',
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'task' => 'Koordinasi tim, briefing, dan pengawasan sampling sekolah.',
                    'schedule_notes' => 'Briefing pagi bersama PIC.',
                ],
            );

            EventTeamMember::updateOrCreate(
                ['event_id' => $event->id, 'user_id' => $spg->id],
                [
                    'position' => 'SPG',
                    'status' => 'assigned',
                    'assigned_by' => $pic->id,
                    'schedule_date' => $fjp->event_date?->toDateString(),
                    'report_time' => '07:30',
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'task' => 'Distribusi sampel, pencatatan konsumen, dan laporan cepat.',
                    'schedule_notes' => 'Briefing pagi bersama PIC.',
                ],
            );
        }
    }

    /**
     * @return Collection<int, Fjp>
     */
    private function schoolFjps(): Collection
    {
        $candidates = Fjp::query()
            ->with(['venue:id,name,city', 'brand:id,name', 'eventType:id,name'])
            ->where('status', Fjp::STATUS_CONFIRM)
            ->whereNotNull('venue_id')
            ->whereNotNull('npsn')
            ->orderBy('event_date')
            ->get()
            ->unique('venue_id')
            ->values();

        $byCity = $candidates
            ->groupBy(fn (Fjp $fjp) => $fjp->venue?->city ?? 'Lainnya')
            ->sortKeys()
            ->map(fn (Collection $group) => $group->values());

        $selected = collect();

        for ($round = 0; $round < 2; $round++) {
            foreach ($byCity as $group) {
                if ($group->has($round)) {
                    $selected->push($group->get($round));
                }

                if ($selected->count() >= 10) {
                    break 2;
                }
            }
        }

        return $selected->take(10)->values();
    }
}
