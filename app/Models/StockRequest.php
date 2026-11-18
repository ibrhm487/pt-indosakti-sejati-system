<?php

namespace App\Models;

use App\Enums\MrvStatus;
use App\Support\DocumentNumber;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'mrv_number', 'event_id', 'activity_type', 'activity_date', 'jug_quantity',
    'week_label', 'shift', 'brand_name', 'venue_name', 'venue_names', 'school_names', 'team_member_id',
    'team_member_name', 'jug_details',
    'material_categories',
    'sampling_target_wet', 'sampling_target_dry',
    'team_no', 'type', 'status', 'material_category',
    'notes', 'pickup_time', 'supporting_items', 'rejection_reason',
    'photo_path', 'recipient', 'material_prepared_by', 'material_received_by',
    'prepared_by_name', 'approved_by_name', 'wet_received_by', 'dry_received_by',
    'requested_by', 'approved_by', 'approved_at', 'issued_at',
])]
class StockRequest extends Model
{
    protected function casts(): array
    {
        return [
            'status' => MrvStatus::class,
            'supporting_items' => 'array',
            'activity_date' => 'date',
            'jug_quantity' => 'integer',
            'jug_details' => 'array',
            'material_categories' => 'array',
            'school_names' => 'array',
            'venue_names' => 'array',
            'sampling_target_wet' => 'integer',
            'sampling_target_dry' => 'integer',
            'approved_at' => 'datetime',
            'issued_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockRequestItem::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(StockReturn::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function teamMember(): BelongsTo
    {
        return $this->belongsTo(User::class, 'team_member_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public static function generateNumber(): string
    {
        return static::suggestNumber();
    }

    public static function suggestNumber(?string $brandCode = null, ?CarbonInterface $date = null): string
    {
        return DocumentNumber::suggest(
            $brandCode,
            $date,
            fn (string $number) => static::where('mrv_number', $number)->exists(),
        );
    }
}
