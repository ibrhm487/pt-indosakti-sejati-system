<?php

namespace App\Services;

use App\Enums\MrvStatus;
use App\Enums\PermitStatus;
use App\Models\Event;

/**
 * Event readiness engine. Not-required modules are excluded from the
 * denominator so optional flows never artificially block event readiness.
 */
class EventReadinessService
{
    public const READY = 'READY';

    public const PENDING = 'PENDING';

    public const NOT_REQUIRED = 'NOT_REQUIRED';

    public const BLOCKED = 'BLOCKED';

    /** @return array{modules: array<string,string>, percentage: int} */
    public function evaluate(Event $event): array
    {
        $event->loadMissing(['permit', 'teamMembers', 'stockRequests', 'kitchenRequest']);

        $modules = [
            'permission' => $this->permissionState($event),
            'team' => $this->teamState($event),
            'warehouse' => $this->warehouseState($event),
            'kitchen' => $this->kitchenState($event),
        ];

        $applicable = array_filter($modules, fn ($state) => $state !== self::NOT_REQUIRED);
        $ready = count(array_filter($applicable, fn ($state) => $state === self::READY));

        $percentage = count($applicable) === 0
            ? 100
            : (int) round(($ready / count($applicable)) * 100);

        return [
            'modules' => $modules,
            'percentage' => $percentage,
        ];
    }

    private function permissionState(Event $event): string
    {
        $permit = $event->permit;

        if (! $permit) {
            return self::PENDING;
        }

        if ($permit->status === PermitStatus::APPROVED) {
            return self::READY;
        }

        if ($permit->status === PermitStatus::REJECTED || $permit->status === PermitStatus::CANCELLED) {
            return self::BLOCKED;
        }

        return self::PENDING;
    }

    private function teamState(Event $event): string
    {
        return $event->teamMembers->isNotEmpty() ? self::READY : self::PENDING;
    }

    private function warehouseState(Event $event): string
    {
        $requests = $event->stockRequests;

        if ($requests->isEmpty()) {
            return self::PENDING;
        }

        $issued = $requests->contains(fn ($r) => in_array($r->status, [MrvStatus::ISSUED, MrvStatus::RETURNED], true));

        return $issued || $requests->every(fn ($r) => $r->status === MrvStatus::APPROVED)
            ? self::READY
            : self::PENDING;
    }

    private function kitchenState(Event $event): string
    {
        if (! $event->jug_required) {
            return self::NOT_REQUIRED;
        }

        $kitchen = $event->kitchenRequest;

        if (! $kitchen) {
            return self::PENDING;
        }

        return in_array($kitchen->status->value, ['ready', 'dispatched'], true)
            ? self::READY
            : self::PENDING;
    }
}
