<?php

namespace App\Enums;

enum StockMovementType: string
{
    case STOCK_IN = 'stock_in';
    case STOCK_OUT = 'stock_out';
    case RETURN = 'return';
    case DAMAGE = 'damage';
    case ADJUSTMENT = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::STOCK_IN => 'Barang Masuk',
            self::STOCK_OUT => 'Barang Keluar',
            self::RETURN => 'Pengembalian',
            self::DAMAGE => 'Rusak',
            self::ADJUSTMENT => 'Penyesuaian',
        };
    }
}
