<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'kitchen_report_id', 'type', 'path', 'mime', 'size', 'original_name',
])]
class KitchenReportMedia extends Model
{
    public const TYPE_WASHING = 'washing';

    public const TYPE_COOKING = 'cooking';

    public const TYPE_READY = 'ready';

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    public function kitchenReport(): BelongsTo
    {
        return $this->belongsTo(KitchenReport::class);
    }
}
