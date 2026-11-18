<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['brand_id', 'name', 'slug', 'requires_school', 'kitchen_default', 'is_active'])]
class EventType extends Model
{
    protected function casts(): array
    {
        return [
            'brand_id' => 'integer',
            'requires_school' => 'boolean',
            'kitchen_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }
}
