<?php

namespace App\Enums;

enum MaterialCategory: string
{
    case WET_SAMPLE = 'wet_sample';
    case DRY_SAMPLE = 'dry_sample';
    case OTHERS = 'others';

    public function label(): string
    {
        return match ($this) {
            self::WET_SAMPLE => 'Wet Sample',
            self::DRY_SAMPLE => 'Dry Sample',
            self::OTHERS => 'Others',
        };
    }
}
