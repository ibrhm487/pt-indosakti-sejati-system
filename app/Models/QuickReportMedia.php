<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'quick_report_id', 'type', 'path', 'mime', 'size', 'original_name',
])]
class QuickReportMedia extends Model
{
    public const TYPE_PHOTO = 'photo';

    public const TYPE_VIDEO = 'video';

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    public function quickReport(): BelongsTo
    {
        return $this->belongsTo(QuickReport::class);
    }
}
