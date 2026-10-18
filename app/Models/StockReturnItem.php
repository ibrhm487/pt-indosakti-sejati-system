<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['stock_return_id', 'product_id', 'quantity_good', 'quantity_damaged'])]
class StockReturnItem extends Model
{
    protected function casts(): array
    {
        return [
            'quantity_good' => 'integer',
            'quantity_damaged' => 'integer',
        ];
    }

    public function stockReturn(): BelongsTo
    {
        return $this->belongsTo(StockReturn::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
