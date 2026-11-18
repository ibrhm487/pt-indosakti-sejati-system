<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'attendance_id', 'type', 'stage', 'path', 'mime', 'size', 'original_name',
])]
class AttendanceMedia extends Model
{
    public const TYPE_PHOTO = 'photo';

    public const STAGE_PRE_DEPARTURE = 'pre_departure';

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }
}
