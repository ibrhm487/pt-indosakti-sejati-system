<?php

namespace App\Models;

use App\Enums\EventStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable([
    'event_code', 'event_name', 'fjp_id', 'brand_id', 'event_type_id', 'venue_id',
    'event_date', 'start_time', 'end_time', 'sampling_target', 'notes',
    'jug_required', 'status', 'pic_id', 'created_by', 'team_group_id',
])]
class Event extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'jug_required' => 'boolean',
            'sampling_target' => 'integer',
            'status' => EventStatus::class,
        ];
    }

    public function fjp(): BelongsTo
    {
        return $this->belongsTo(Fjp::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function eventType(): BelongsTo
    {
        return $this->belongsTo(EventType::class);
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function pic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_id');
    }

    public function teamMembers(): HasMany
    {
        return $this->hasMany(EventTeamMember::class);
    }

    public function permit(): HasOne
    {
        return $this->hasOne(PermitRequest::class);
    }

    public function kitchenRequest(): HasOne
    {
        return $this->hasOne(KitchenRequest::class);
    }

    public function stockRequests(): HasMany
    {
        return $this->hasMany(StockRequest::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(EventReport::class);
    }

    public function quickReports(): HasMany
    {
        return $this->hasMany(QuickReport::class);
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function latestReport(): HasOne
    {
        return $this->hasOne(EventReport::class)->latestOfMany();
    }

    public function scopeForPic(Builder $query, User $user): Builder
    {
        return $query->where('pic_id', $user->id);
    }

    public static function composeName(?string $brand, ?string $eventType, ?string $venue): string
    {
        $brand = trim((string) $brand);
        $type = trim((string) $eventType);
        $venue = trim((string) $venue);

        if ($brand !== '' && ($type === '' || ! Str::contains(Str::upper($type), Str::upper($brand)))) {
            $type = trim($brand.' '.$type);
        }

        $base = $type !== '' ? $type : ($brand !== '' ? $brand : 'Event');

        return $venue !== '' ? $base.' - '.$venue : $base;
    }

    public static function generateCode(): string
    {
        $prefix = 'EVT-'.now()->format('Ymd');

        $sequence = static::withTrashed()
            ->where('event_code', 'like', $prefix.'%')
            ->count() + 1;

        return $prefix.'-'.Str::padLeft((string) $sequence, 3, '0');
    }
}
