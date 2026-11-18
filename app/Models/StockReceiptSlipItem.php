<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'stock_receipt_slip_id', 'product_id', 'sku', 'item_name', 'lot_code',
    'description', 'received_quantity', 'uom', 'expired_date', 'status', 'remark',
])]
class StockReceiptSlipItem extends Model
{
    protected function casts(): array
    {
        return [
            'received_quantity' => 'integer',
            'expired_date' => 'date',
        ];
    }

    public function slip(): BelongsTo
    {
        return $this->belongsTo(StockReceiptSlip::class, 'stock_receipt_slip_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
