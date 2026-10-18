<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'name', 'sku', 'brand_id', 'product_category_id', 'unit', 'minimum_stock', 'is_active',
])]
class Product extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'minimum_stock' => 'integer',
        ];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function inventory(): HasOne
    {
        return $this->hasOne(Inventory::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(StockReceipt::class);
    }

    public function requestItems(): HasMany
    {
        return $this->hasMany(StockRequestItem::class);
    }

    public function returnItems(): HasMany
    {
        return $this->hasMany(StockReturnItem::class);
    }

    /**
     * A product with any stock history must never be hard-deleted: the
     * movement ledger and request/return lines would cascade away with it.
     */
    public function hasStockHistory(): bool
    {
        return $this->movements()->exists()
            || $this->receipts()->exists()
            || $this->requestItems()->exists()
            || $this->returnItems()->exists();
    }

    public function availableStock(): int
    {
        $inventory = $this->inventory;

        if (! $inventory) {
            return 0;
        }

        return $inventory->qty_on_hand - $inventory->qty_reserved;
    }

    public function stockStatus(): string
    {
        $available = $this->availableStock();

        if ($available <= 0) {
            return 'out_of_stock';
        }

        if ($available <= $this->minimum_stock) {
            return 'low_stock';
        }

        return 'available';
    }
}
