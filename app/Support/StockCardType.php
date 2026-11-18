<?php

namespace App\Support;

class StockCardType
{
    /**
     * Product-type categories printed on the Stock Card (incoming/receipt).
     *
     * @var list<string>
     */
    public const FIXED = [
        'Free Goods/FGR',
        'Cups/Spoon',
        'Premium/Gimmick',
        'POP/POSM',
    ];

    public const OTHER = 'Other';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [...self::FIXED, self::OTHER];
    }

    /**
     * Map a stored receipt type onto one of the Stock Card categories. Any
     * unrecognised free-text value is treated as "Other".
     */
    public static function normalize(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        foreach (self::FIXED as $option) {
            if (strcasecmp($option, $value) === 0) {
                return $option;
            }
        }

        return self::OTHER;
    }
}
