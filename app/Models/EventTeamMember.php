<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'event_id',
    'user_id',
    'personnel_id',
    'position',
    'status',
    'assigned_by',
    'schedule_date',
    'report_time',
    'start_time',
    'end_time',
    'task',
    'schedule_notes',
])]
class EventTeamMember extends Model
{
    protected function casts(): array
    {
        return [
            'schedule_date' => 'date',
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

    public function personnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class);
    }

    public function memberName(): string
    {
        return $this->personnel?->name ?? $this->user?->name ?? '-';
    }
}
