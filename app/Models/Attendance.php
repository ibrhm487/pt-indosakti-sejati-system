<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'event_id', 'user_id', 'planned_start', 'planned_end', 'check_in_at',
    'check_out_at', 'status', 'correction_note',
])]
class Attendance extends Model
{
    protected function casts(): array
    {
        return [
            'planned_start' => 'datetime',
            'planned_end' => 'datetime',
            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
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

    public function isComplete(): bool
    {
        return $this->check_in_at !== null && $this->check_out_at !== null;
    }

    public function durationMinutes(): ?int
    {
        if (! $this->isComplete()) {
            return null;
        }

        return (int) round($this->check_in_at->diffInMinutes($this->check_out_at));
    }

    public function qualifiesForPay(): bool
    {
        if (! $this->isComplete() || $this->status === 'invalid') {
            return false;
        }

        return $this->check_out_at->greaterThan($this->check_in_at);
    }
}
