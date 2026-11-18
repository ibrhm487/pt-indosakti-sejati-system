<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'stock_request_id', 'product_id', 'lot_no', 'quantity_requested', 'cold', 'lbs',
    'packing', 'on_the_spot', 'd1', 'd2', 'cups', 'spoon', 'notes',
    'quantity_issued', 'quantity_returned', 'quantity_damaged',
])]
class StockRequestItem extends Model
{
    protected function casts(): array
    {
        return [
            'quantity_requested' => 'integer',
            'cold' => 'decimal:2',
            'lbs' => 'decimal:2',
            'on_the_spot' => 'decimal:2',
            'd1' => 'decimal:2',
            'd2' => 'decimal:2',
            'cups' => 'decimal:2',
            'spoon' => 'decimal:2',
            'quantity_issued' => 'integer',
            'quantity_returned' => 'integer',
            'quantity_damaged' => 'integer',
        ];
    }

    public function stockRequest(): BelongsTo
    {
        return $this->belongsTo(StockRequest::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
