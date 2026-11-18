<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Str;

class DocumentNumber
{
    /**
     * Build the next document number in the JKT/DDMM/BRAND/NN format.
     */
    public static function suggest(
        ?string $brandCode,
        ?CarbonInterface $date,
        callable $numberExists,
    ): string {
        $date ??= now();
        $code = Str::upper(trim((string) $brandCode)) ?: 'GEN';
        $stem = sprintf('JKT/%s/%s/', $date->format('dm'), $code);

        for ($sequence = 1; ; $sequence++) {
            $number = $stem.str_pad((string) $sequence, 2, '0', STR_PAD_LEFT);

            if (! $numberExists($number)) {
                return $number;
            }
        }
    }

    public static function normalize(?string $number): ?string
    {
        if (! is_string($number)) {
            return null;
        }

        $number = Str::upper(trim($number));

        return $number === '' ? null : $number;
    }
}
