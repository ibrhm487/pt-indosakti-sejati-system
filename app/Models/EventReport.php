<?php

namespace App\Models;

use App\Enums\ReportStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable([
    'event_id', 'sampling_target', 'actual_sampling', 'achievement_percentage',
    'issues', 'notes', 'status', 'revision_note', 'submitted_by', 'submitted_at',
    'verified_by', 'verified_at',
])]
class EventReport extends Model
{
    protected function casts(): array
    {
        return [
            'status' => ReportStatus::class,
            'submitted_at' => 'datetime',
            'verified_at' => 'datetime',
            'achievement_percentage' => 'decimal:2',
            'sampling_target' => 'integer',
            'actual_sampling' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    /**
     * target == 0 must never trigger a division error.
     */
    public function calculateAchievement(): float
    {
        if ($this->sampling_target <= 0) {
            return 0.0;
        }

        return round(($this->actual_sampling / $this->sampling_target) * 100, 2);
    }
}
