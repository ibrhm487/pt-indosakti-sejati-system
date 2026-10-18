<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_id', 'qty_on_hand', 'qty_reserved'])]
class Inventory extends Model
{
    protected $table = 'inventory';

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
