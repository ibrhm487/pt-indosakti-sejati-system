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
     * @return array{rate: int, eligible_shifts: int, total: int, incomplete: int, by_user: Collection<int, array{user_id: int|string, user: string, eligible_shifts: int, total: int}>}
     */
    public function summarize(Collection $attendances): array
    {
        $rate = ShiftRate::current();
        $eligible = 0;
        $incomplete = 0;
        $byUser = [];

        foreach ($attendances as $attendance) {
            if ($attendance->qualifiesForPay()) {
                $eligible++;
            } elseif (! $attendance->isComplete() || $attendance->check_out_at?->equalTo($attendance->check_in_at)) {
                $incomplete++;
            }

            $key = $attendance->user_id ?? 0;
            if (! isset($byUser[$key])) {
                $byUser[$key] = [
                    'user_id' => $key,
                    'user' => $attendance->user?->name ?? '-',
                    'eligible_shifts' => 0,
                    'total' => 0,
                ];
            }

            if ($attendance->qualifiesForPay()) {
                $byUser[$key]['eligible_shifts']++;
                $byUser[$key]['total'] += $rate;
            }
        }

        return [
            'rate' => $rate,
            'eligible_shifts' => $eligible,
            'total' => $eligible * $rate,
            'incomplete' => $incomplete,
            'by_user' => collect($byUser)->sortBy('user')->values(),
        ];
    }
}
