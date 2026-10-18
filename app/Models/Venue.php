<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name', 'npsn', 'is_school', 'address', 'city', 'province',
    'contact_name', 'contact_phone', 'latitude', 'longitude',
])]
class Venue extends Model
{
    protected function casts(): array
    {
        return [
            'is_school' => 'boolean',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }
}
