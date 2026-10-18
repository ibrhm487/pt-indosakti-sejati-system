<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable([
    'receipt_number', 'product_id', 'quantity', 'supplier', 'received_date',
    'notes', 'photo_path', 'recorded_by',
])]
class StockReceipt extends Model
{
    protected function casts(): array
    {
        return [
            'received_date' => 'date',
            'quantity' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public static function generateNumber(): string
    {
        $prefix = 'RCV-'.now()->format('Ymd');
        $sequence = static::where('receipt_number', 'like', $prefix.'%')->count() + 1;

        return $prefix.'-'.Str::padLeft((string) $sequence, 3, '0');
    }
}
