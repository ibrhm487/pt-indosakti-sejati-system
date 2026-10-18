<?php

namespace App\Models;

use App\Enums\KitchenStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'event_id', 'jug_quantity', 'preparation_date', 'preparation_time',
    'notes', 'status', 'assigned_to',
])]
class KitchenRequest extends Model
{
    protected function casts(): array
    {
        return [
            'preparation_date' => 'date',
            'status' => KitchenStatus::class,
            'jug_quantity' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(KitchenReport::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
