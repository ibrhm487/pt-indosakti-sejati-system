<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\Event;
use App\Models\ShiftRate;
use Illuminate\Database\Seeder;

class PayrollSimulationSeeder extends Seeder
{
    public function run(): void
    {
        $rate = ShiftRate::updateOrCreate(
            ['label' => 'Fee Shift Event'],
            [
                'rate' => 200000,
                'is_active' => true,
                'effective_from' => now()->startOfMonth()->toDateString(),
            ],
        );

        ShiftRate::where('id', '!=', $rate->id)->update(['is_active' => false]);

        $events = Event::query()
            ->with(['teamMembers' => fn ($q) => $q->whereNotNull('user_id')])
            ->where('notes', 'like', 'Simulasi pembagian tim PIC%')
            ->get();

        foreach ($events as $event) {
            if (! $event->event_date) {
                continue;
            }

            $checkIn = $event->event_date->copy()->setTime(7, 55);
            $checkOut = $event->event_date->copy()->setTime(12, 10);

            foreach ($event->teamMembers as $member) {
                Attendance::updateOrCreate(
                    ['event_id' => $event->id, 'user_id' => $member->user_id],
                    [
                        'planned_start' => $event->event_date->copy()->setTime(8, 0),
                        'planned_end' => $event->event_date->copy()->setTime(12, 0),
                        'check_in_at' => $checkIn,
                        'check_out_at' => $checkOut,
                        'status' => 'complete',
                    ],
                );
            }
        }
    }
}
