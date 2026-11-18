<?php

namespace App\Enums;

enum KitchenStatus: string
{
    case NOT_REQUIRED = 'not_required';
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case PREPARING = 'preparing';
    case READY = 'ready';
    case DISPATCHED = 'dispatched';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::NOT_REQUIRED => 'Tidak Diperlukan',
            self::PENDING => 'Menunggu',
            self::CONFIRMED => 'Dikonfirmasi',
            self::PREPARING => 'Disiapkan',
            self::READY => 'Siap',
            self::DISPATCHED => 'Dikirim',
            self::CANCELLED => 'Dibatalkan',
        };
    }

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::PENDING => [self::CONFIRMED, self::PREPARING, self::CANCELLED],
            self::CONFIRMED => [self::PREPARING, self::READY, self::CANCELLED],
            self::PREPARING => [self::READY, self::CANCELLED],
            self::READY => [self::DISPATCHED, self::CANCELLED],
            self::NOT_REQUIRED, self::DISPATCHED, self::CANCELLED => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
