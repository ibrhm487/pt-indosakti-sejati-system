<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'kitchen_request_id', 'prepared_quantity', 'condition', 'notes',
    'photo_path', 'washing_photo_path', 'ready_photo_path',
    'prepared_by', 'prepared_at', 'washing_at', 'cooking_at', 'ready_at',
])]
class KitchenReport extends Model
{
    protected function casts(): array
    {
        return [
            'prepared_at' => 'datetime',
            'washing_at' => 'datetime',
            'cooking_at' => 'datetime',
            'ready_at' => 'datetime',
            'prepared_quantity' => 'integer',
        ];
    }

    public function kitchenRequest(): BelongsTo
    {
        return $this->belongsTo(KitchenRequest::class);
    }

    public function preparer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function media(): HasMany
    {
        return $this->hasMany(KitchenReportMedia::class);
    }

    public function stage(): string
    {
        return match (true) {
            $this->ready_at !== null => 'done',
            $this->cooking_at !== null => 'ready',
            $this->washing_at !== null => 'cooking',
            default => 'washing',
        };
    }

    public function isComplete(): bool
    {
        return $this->ready_at !== null || $this->ready_photo_path !== null;
    }
}
