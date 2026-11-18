<?php

namespace App\Models;

use App\Enums\VenueStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name', 'npsn', 'is_school', 'address', 'city', 'province',
    'contact_name', 'contact_phone', 'latitude', 'longitude',
    'status', 'approved_by', 'approved_at', 'rejection_reason',
])]
class Venue extends Model
{
    protected function casts(): array
    {
        return [
            'is_school' => 'boolean',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'status' => VenueStatus::class,
            'approved_at' => 'datetime',
        ];
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function fjps(): HasMany
    {
        return $this->hasMany(Fjp::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Venues that may be picked on event/permit forms: explicitly approved by
     * a PIC, or tied to an FJP that has already been confirmed.
     */
    public function scopeUsable(Builder $query): Builder
    {
        return $query->where(function (Builder $query) {
            $query->where('status', VenueStatus::APPROVED->value)
                ->orWhereHas('fjps', fn (Builder $q) => $q->where('status', Fjp::STATUS_CONFIRM));
        });
    }
}
