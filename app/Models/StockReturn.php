<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'return_number', 'stock_request_id', 'event_id', 'status', 'notes',
    'photo_path', 'recorded_by',
])]
class StockReturn extends Model
{
    public function stockRequest(): BelongsTo
    {
        return $this->belongsTo(StockRequest::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockReturnItem::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public static function generateNumber(): string
    {
        $prefix = 'RTN-'.now()->format('Ymd');
        $sequence = static::where('return_number', 'like', $prefix.'%')->count() + 1;

        return $prefix.'-'.Str::padLeft((string) $sequence, 3, '0');
    }
}
