<?php

namespace App\Enums;

enum MrvStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case ISSUED = 'issued';
    case RETURNED = 'returned';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Menunggu',
            self::APPROVED => 'Disetujui',
            self::REJECTED => 'Ditolak',
            self::ISSUED => 'Dikeluarkan',
            self::RETURNED => 'Dikembalikan',
            self::CANCELLED => 'Dibatalkan',
        };
    }
}
