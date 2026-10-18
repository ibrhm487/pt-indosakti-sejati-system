<?php

namespace App\Enums;

enum EventStatus: string
{
    case DRAFT = 'draft';
    case PLANNING = 'planning';
    case PERMISSION = 'permission';
    case TEAM_ASSIGNMENT = 'team_assignment';
    case WAREHOUSE_PREPARATION = 'warehouse_preparation';
    case KITCHEN_PREPARATION = 'kitchen_preparation';
    case READY = 'ready';
    case ONGOING = 'ongoing';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::PLANNING => 'Perencanaan',
            self::PERMISSION => 'Perizinan',
            self::TEAM_ASSIGNMENT => 'Penugasan Tim',
            self::WAREHOUSE_PREPARATION => 'Persiapan Gudang',
            self::KITCHEN_PREPARATION => 'Persiapan Kitchen',
            self::READY => 'Siap',
            self::ONGOING => 'Berjalan',
            self::COMPLETED => 'Selesai',
            self::CANCELLED => 'Dibatalkan',
        };
    }

    /**
     * Statuses reachable from this one. Terminal states map to an empty list.
     * `completed` is only reachable from `ongoing` and should normally be set
     * through the guarded close action rather than a free status change.
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::DRAFT => [self::PLANNING, self::CANCELLED],
            self::PLANNING => [self::PERMISSION, self::TEAM_ASSIGNMENT, self::CANCELLED],
            self::PERMISSION => [self::TEAM_ASSIGNMENT, self::WAREHOUSE_PREPARATION, self::CANCELLED],
            self::TEAM_ASSIGNMENT => [self::WAREHOUSE_PREPARATION, self::KITCHEN_PREPARATION, self::READY, self::CANCELLED],
            self::WAREHOUSE_PREPARATION => [self::KITCHEN_PREPARATION, self::READY, self::CANCELLED],
            self::KITCHEN_PREPARATION => [self::READY, self::CANCELLED],
            self::READY => [self::ONGOING, self::CANCELLED],
            self::ONGOING => [self::COMPLETED, self::CANCELLED],
            self::COMPLETED, self::CANCELLED => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::COMPLETED, self::CANCELLED], true);
    }
}
