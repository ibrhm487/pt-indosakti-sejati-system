<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\ShiftRate;
use Illuminate\Support\Collection;

/**
 * Payroll summary: eligible shifts x applicable shift rate.
 * Incomplete attendance never silently counts as a full paid shift.
 */
class PayrollService
{
    /**
     * @param  Collection<int, Attendance>  $attendances
     * @return array{rate: int, eligible_shifts: int, total: int, incomplete: int}
     */
    public function summarize(Collection $attendances): array
    {
        $rate = ShiftRate::current();
        $eligible = 0;
        $incomplete = 0;

        foreach ($attendances as $attendance) {
            if ($attendance->qualifiesForPay()) {
                $eligible++;
            } elseif (! $attendance->isComplete()) {
                $incomplete++;
            }
        }

        return [
            'rate' => $rate,
            'eligible_shifts' => $eligible,
            'total' => $eligible * $rate,
            'incomplete' => $incomplete,
        ];
    }
}
