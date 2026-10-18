<?php

namespace App\Services;

use App\Enums\MrvStatus;
use App\Enums\StockMovementType;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\StockRequest;
use App\Models\StockReturn;
use DomainException;
use Illuminate\Support\Facades\DB;

class StockService
{
    /**
     * Record a goods receipt and increase on-hand stock atomically.
     */
    public function receive(Product $product, int $quantity, array $context = []): StockMovement
    {
        if ($quantity <= 0) {
            throw new DomainException('Jumlah penerimaan harus lebih dari 0.');
        }

        return DB::transaction(function () use ($product, $quantity, $context) {
            $inventory = $this->lockInventory($product);
            $inventory->qty_on_hand += $quantity;
            $inventory->save();

            return $this->recordMovement(
                $product,
                StockMovementType::STOCK_IN,
                $quantity,
                $inventory->qty_on_hand,
                $context,
            );
        });
    }

    /**
     * Issue stock for an approved MRV. Deduction happens exactly once at the
     * issuance stage; approving a request never moves stock.
     */
    public function issue(StockRequest $request): void
    {
        if ($request->status === MrvStatus::ISSUED) {
            throw new DomainException('MRV ini sudah dikeluarkan; stok tidak boleh dikurangi dua kali.');
        }

        DB::transaction(function () use ($request) {
            $request->loadMissing('items.product');

            foreach ($request->items as $item) {
                $product = $item->product;
                $inventory = $this->lockInventory($product);

                if ($inventory->qty_on_hand < $item->quantity_requested) {
                    throw new DomainException("Stok tidak cukup untuk {$product->name}. Tersedia {$inventory->qty_on_hand}.");
                }

                $inventory->qty_on_hand -= $item->quantity_requested;
                $inventory->save();

                $item->quantity_issued = $item->quantity_requested;
                $item->save();

                $this->recordMovement(
                    $product,
                    StockMovementType::STOCK_OUT,
                    $item->quantity_requested,
                    $inventory->qty_on_hand,
                    ['reference' => $request],
                );
            }

            $request->status = MrvStatus::ISSUED;
            $request->issued_at = now();
            $request->save();
        });
    }

    /**
     * Process a return. Good units go back to available stock; damaged units
     * are recorded but never added to usable inventory.
     */
    public function processReturn(StockReturn $return): void
    {
        if ($return->status === 'processed') {
            throw new DomainException('Pengembalian ini sudah diproses; stok tidak boleh ditambah dua kali.');
        }

        DB::transaction(function () use ($return) {
            $return->loadMissing('items.product');

            foreach ($return->items as $item) {
                $product = $item->product;
                $inventory = $this->lockInventory($product);

                if ($item->quantity_good > 0) {
                    $inventory->qty_on_hand += $item->quantity_good;
                    $inventory->save();

                    $this->recordMovement(
                        $product,
                        StockMovementType::RETURN,
                        $item->quantity_good,
                        $inventory->qty_on_hand,
                        ['reference' => $return, 'condition' => 'good'],
                    );
                }

                if ($item->quantity_damaged > 0) {
                    $this->recordMovement(
                        $product,
                        StockMovementType::DAMAGE,
                        $item->quantity_damaged,
                        $inventory->qty_on_hand,
                        ['reference' => $return, 'condition' => 'damaged'],
                    );
                }
            }

            $return->status = 'processed';
            $return->save();

            if ($return->stock_request_id) {
                $request = $return->stockRequest;
                if ($request) {
                    $request->status = MrvStatus::RETURNED;
                    $request->save();
                }
            }
        });
    }

    /**
     * Manual adjustment (auditable, may be positive or negative). Negative
     * stock is never allowed.
     */
    public function adjust(Product $product, int $delta, string $reason): StockMovement
    {
        return DB::transaction(function () use ($product, $delta, $reason) {
            $inventory = $this->lockInventory($product);

            if ($inventory->qty_on_hand + $delta < 0) {
                throw new DomainException('Penyesuaian akan menghasilkan stok negatif.');
            }

            $inventory->qty_on_hand += $delta;
            $inventory->save();

            return $this->recordMovement(
                $product,
                StockMovementType::ADJUSTMENT,
                $delta,
                $inventory->qty_on_hand,
                ['reason' => $reason],
            );
        });
    }

    private function lockInventory(Product $product): Inventory
    {
        return Inventory::query()
            ->where('product_id', $product->id)
            ->lockForUpdate()
            ->firstOrCreate(['product_id' => $product->id], ['qty_on_hand' => 0, 'qty_reserved' => 0]);
    }

    private function recordMovement(
        Product $product,
        StockMovementType $type,
        int $quantity,
        int $balanceAfter,
        array $context = [],
    ): StockMovement {
        $reference = $context['reference'] ?? null;

        return StockMovement::create([
            'product_id' => $product->id,
            'type' => $type,
            'quantity' => abs($quantity),
            'balance_after' => $balanceAfter,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference?->getKey(),
            'notes' => $context['reason'] ?? $context['condition'] ?? null,
            'created_by' => auth()->id(),
        ]);
    }
}
