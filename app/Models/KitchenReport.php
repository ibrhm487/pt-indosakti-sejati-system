<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'kitchen_request_id', 'prepared_quantity', 'condition', 'notes',
    'photo_path', 'washing_photo_path', 'prepared_by', 'prepared_at',
])]
class KitchenReport extends Model
{
    protected function casts(): array
    {
        return [
            'prepared_at' => 'datetime',
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
}
