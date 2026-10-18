<?php

namespace App\Enums;

enum PermitStatus: string
{
    case DRAFT = 'draft';
    case SUBMITTED = 'submitted';
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::SUBMITTED => 'Diajukan',
            self::PENDING => 'Menunggu',
            self::APPROVED => 'Disetujui',
            self::REJECTED => 'Ditolak',
            self::CANCELLED => 'Dibatalkan',
        };
    }

    /**
     * Statuses reachable from this one via an explicit workflow action.
     * `approved` and `cancelled` are terminal: an approved permit may only be
     * changed through a formal process, and a cancelled one cannot be revived.
     * A rejected permit can be resubmitted (`submitted`) or sent back for
     * revision (`pending`) — never silently revived.
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::DRAFT => [self::SUBMITTED, self::CANCELLED],
            self::SUBMITTED => [self::PENDING, self::APPROVED, self::REJECTED, self::CANCELLED],
            self::PENDING => [self::SUBMITTED, self::APPROVED, self::REJECTED, self::CANCELLED],
            self::REJECTED => [self::SUBMITTED, self::PENDING, self::CANCELLED],
            self::APPROVED, self::CANCELLED => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::APPROVED, self::CANCELLED], true);
    }
}
