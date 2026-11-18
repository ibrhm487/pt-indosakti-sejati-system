<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'event_id', 'user_id', 'title', 'body', 'photo_path',
    'sampling_target', 'sampling_actual', 'selling_target', 'selling_actual',
    'booth_package_1', 'booth_package_2', 'booth_package_3',
    'store_package_1', 'store_package_2', 'store_package_3', 'learning',
])]
class QuickReport extends Model
{
    protected function casts(): array
    {
        return [
            'sampling_target' => 'integer',
            'sampling_actual' => 'integer',
            'selling_target' => 'decimal:2',
            'selling_actual' => 'decimal:2',
            'booth_package_1' => 'integer',
            'booth_package_2' => 'integer',
            'booth_package_3' => 'integer',
            'store_package_1' => 'integer',
            'store_package_2' => 'integer',
            'store_package_3' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(QuickReportMedia::class);
    }
}
