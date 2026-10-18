<?php

namespace App\Models;

use App\Enums\PermitStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'permit_number', 'event_id', 'fjp_id', 'venue_id', 'deadline', 'assigned_to',
    'status', 'notes', 'reason', 'created_by', 'decided_by', 'decided_at',
])]
class PermitRequest extends Model
{
    protected function casts(): array
    {
        return [
            'deadline' => 'date',
            'decided_at' => 'datetime',
            'status' => PermitStatus::class,
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function fjp(): BelongsTo
    {
        return $this->belongsTo(Fjp::class);
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(PermitDocument::class);
    }

    public static function generateNumber(): string
    {
        $prefix = 'PRM-'.now()->format('Ymd');
        $sequence = static::where('permit_number', 'like', $prefix.'%')->count() + 1;

        return $prefix.'-'.Str::padLeft((string) $sequence, 3, '0');
    }
}
