<?php

namespace App\Models;

use App\Enums\MrvStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'mrv_number', 'event_id', 'type', 'status', 'notes', 'rejection_reason',
    'photo_path', 'recipient',
    'requested_by', 'approved_by', 'approved_at', 'issued_at',
])]
class StockRequest extends Model
{
    protected function casts(): array
    {
        return [
            'status' => MrvStatus::class,
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

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public static function generateNumber(): string
    {
        $prefix = 'MRV-'.now()->format('Ymd');
        $sequence = static::where('mrv_number', 'like', $prefix.'%')->count() + 1;

        return $prefix.'-'.Str::padLeft((string) $sequence, 3, '0');
    }
}
