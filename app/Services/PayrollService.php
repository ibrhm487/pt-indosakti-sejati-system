<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\ShiftRate;
use App\Models\StockRequest;
use Illuminate\Support\Collection;

/**
 * Payroll summary: eligible shifts x applicable shift rate.
 * Incomplete attendance never silently counts as a full paid shift.
 */
class PayrollService
{
    /**
     * @param  Collection<int, Attendance>  $attendances
     * @param  Collection<int, StockRequest>  $issuedRequests
     * @return array{rate: int, eligible_shifts: int, total: int, incomplete: int, by_user: Collection<int, array{user_id: int|string, user: string, eligible_shifts: int, total: int}>, by_event: Collection<int, array{event_id: int|string, event: string, event_date: string|null, shifts: int, total: int}>}
     */
    public function summarize(
        Collection $attendances,
        Collection $issuedRequests,
        ?int $eventId = null,
    ): array {
        $rate = ShiftRate::current();
        $incomplete = 0;
        $byUser = [];
        $byEvent = [];

        foreach ($attendances as $attendance) {
            if (! $attendance->isComplete() || $attendance->check_out_at?->equalTo($attendance->check_in_at)) {
                $incomplete++;
            }
        }

        $creditedShifts = $this->eligibleShiftRecords($issuedRequests)
            ->filter(fn (array $shift) => $eventId === null || $shift['request']->event_id === $eventId)
            ->values();

        foreach ($creditedShifts as $shift) {
            /** @var StockRequest $request */
            $request = $shift['request'];
            $userKey = $shift['user_id'];

            if (! isset($byUser[$userKey])) {
                $byUser[$userKey] = [
                    'user_id' => $userKey,
                    'user' => $shift['user'],
                    'eligible_shifts' => 0,
                    'total' => 0,
                ];
            }

            $eventKey = $request->event_id ?? 0;
            if (! isset($byEvent[$eventKey])) {
                $byEvent[$eventKey] = [
                    'event_id' => $eventKey,
                    'event' => $request->event?->event_name
                        ?? $request->activity_type
                        ?? 'Kegiatan sampling',
                    'event_date' => $request->activity_date?->toDateString()
                        ?? $request->event?->event_date?->toDateString()
                        ?? $request->issued_at?->toDateString(),
                    'shifts' => 0,
                    'total' => 0,
                ];
            }

            $byUser[$userKey]['eligible_shifts']++;
            $byUser[$userKey]['total'] += $rate;
            $byEvent[$eventKey]['shifts']++;
            $byEvent[$eventKey]['total'] += $rate;
        }

        $eligible = $creditedShifts->count();

        return [
            'rate' => $rate,
            'eligible_shifts' => $eligible,
            'total' => $eligible * $rate,
            'incomplete' => $incomplete,
            'by_user' => collect($byUser)->sortBy('user')->values(),
            'by_event' => collect($byEvent)->sortBy('event_date')->values(),
        ];
    }

    /**
     * @param  Collection<int, StockRequest>  $issuedRequests
     * @return Collection<int, array{request: StockRequest, user_id: int|string, user: string}>
     */
    public function eligibleShiftRecords(Collection $issuedRequests): Collection
    {
        $seenShiftKeys = [];
        $records = collect();

        foreach ($issuedRequests as $request) {
            if (! $request->issued_at) {
                continue;
            }

            $activityDate = $request->activity_date
                ?? $request->event?->event_date
                ?? $request->issued_at;

            foreach ($this->shiftParticipants($request) as $participant) {
                $shiftKey = $participant['user_id'].'|'.$activityDate->toDateString();

                if (isset($seenShiftKeys[$shiftKey])) {
                    continue;
                }

                $seenShiftKeys[$shiftKey] = true;
                $records->push([
                    'request' => $request,
                    'user_id' => $participant['user_id'],
                    'user' => $participant['user'],
                ]);
            }
        }

        return $records;
    }

    /**
     * @return Collection<int, array{user_id: int|string, user: string}>
     */
    public function shiftParticipants(StockRequest $request): Collection
    {
        $assignedMembers = $request->event?->teamMembers
            ->filter(fn ($member) => $member->user_id !== null && $member->user !== null)
            ->map(fn ($member) => [
                'user_id' => $member->user_id,
                'user' => $member->user->name,
            ])
            ->values() ?? collect();

        if ($assignedMembers->isNotEmpty()) {
            return $assignedMembers;
        }

        if (! $request->team_member_id || ! $request->teamMember) {
            return collect();
        }

        return collect([[
            'user_id' => $request->team_member_id,
            'user' => $request->teamMember->name,
        ]]);
    }
}
