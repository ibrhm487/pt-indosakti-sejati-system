<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'out_material_form_id', 'product_id', 'sku', 'item_name', 'lot_code',
    'expired_date', 'quantity', 'uom', 'remark',
])]
class OutMaterialFormItem extends Model
{
    protected function casts(): array
    {
        return [
            'expired_date' => 'date',
            'quantity' => 'integer',
        ];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(OutMaterialForm::class, 'out_material_form_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
