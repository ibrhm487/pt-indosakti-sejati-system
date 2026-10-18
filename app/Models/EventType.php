<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'slug', 'requires_school', 'kitchen_default', 'is_active'])]
class EventType extends Model
{
    protected function casts(): array
    {
        return [
            'requires_school' => 'boolean',
            'kitchen_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
