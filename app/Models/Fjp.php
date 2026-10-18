<?php

namespace App\Models;

use App\Enums\PermitStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable([
    'fjp_number', 'event_name', 'brand_id', 'event_type_id', 'venue_id',
    'school_name', 'npsn', 'address', 'maps_url', 'jumlah_siswa_sekolah', 'jumlah_siswa_dapodik',
    'dapodik_source', 'dapodik_synced_at', 'event_date', 'start_time', 'end_time',
    'sampling_target', 'notes', 'jug_required', 'permit_status',
    'rejection_reason', 'cancellation_reason', 'status', 'created_by', 'permit_id',
])]
class Fjp extends Model
{
    use SoftDeletes;

    protected $table = 'fjp';

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'dapodik_synced_at' => 'datetime',
            'jug_required' => 'boolean',
            'jumlah_siswa_sekolah' => 'integer',
            'jumlah_siswa_dapodik' => 'integer',
            'sampling_target' => 'integer',
            'permit_status' => PermitStatus::class,
        ];
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(FjpAttachment::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function permit(): HasOne
    {
        return $this->hasOne(PermitRequest::class);
    }

    /**
     * Difference between the school-reported and Dapodik student counts.
     * Returns null when either value is missing so "no data" is never
     * confused with a real difference of zero.
     */
    public function getStudentCountDifferenceAttribute(): ?int
    {
        if ($this->jumlah_siswa_sekolah === null || $this->jumlah_siswa_dapodik === null) {
            return null;
        }

        return $this->jumlah_siswa_sekolah - $this->jumlah_siswa_dapodik;
    }

    public function hasDapodikData(): bool
    {
        return $this->jumlah_siswa_dapodik !== null;
    }

    public function scopeSchoolRelated(Builder $query): Builder
    {
        return $query->whereNotNull('npsn')->orWhereNotNull('school_name');
    }

    public static function generateNumber(): string
    {
        $prefix = 'FJP-'.now()->format('Ymd');

        $sequence = static::withTrashed()
            ->where('fjp_number', 'like', $prefix.'%')
            ->count() + 1;

        return $prefix.'-'.Str::padLeft((string) $sequence, 3, '0');
    }
}
