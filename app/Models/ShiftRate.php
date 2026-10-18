<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['label', 'rate', 'is_active', 'effective_from'])]
class ShiftRate extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'effective_from' => 'date',
            'rate' => 'integer',
        ];
    }

    public static function current(): int
    {
        return (int) (static::query()->where('is_active', true)->value('rate') ?? 125000);
    }
}
