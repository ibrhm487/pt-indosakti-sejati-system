<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Support\Collection;

class LotStockService
{
    /**
     * Derive per-lot balances from the immutable movement ledger.
     *
     * @return Collection<int, array{lot_code: string, quantity: int, expired_date: ?string}>
     */
    public function lots(Product|int $product): Collection
    {
        $productId = $product instanceof Product ? $product->id : $product;

        return $this->lotsForProducts([$productId])->get($productId, collect());
    }

    /**
     * @param  iterable<int, int>  $productIds
     * @return Collection<int, Collection<int, array{lot_code: string, quantity: int, expired_date: ?string}>>
     */
    public function lotsForProducts(iterable $productIds): Collection
    {
        $balances = [];
        $lastBalanceByProduct = [];
        $ids = collect($productIds)->unique()->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        $movements = StockMovement::query()
            ->whereIn('product_id', $ids)
            ->orderBy('id')
            ->select(['id', 'product_id', 'type', 'quantity', 'balance_after', 'lot_code', 'expired_date'])
            ->lazyById(500);

        foreach ($movements as $movement) {
            $productId = $movement->product_id;
            $lotCode = $movement->lot_code;
            $previousBalance = $lastBalanceByProduct[$productId] ?? null;

            $delta = match ($movement->type) {
                StockMovementType::STOCK_IN, StockMovementType::RETURN => $movement->quantity,
                StockMovementType::STOCK_OUT => -$movement->quantity,
                StockMovementType::ADJUSTMENT => $previousBalance === null
                    ? 0
                    : (int) $movement->balance_after - $previousBalance,
                default => 0,
            };

            if ($lotCode !== null) {
                $balances[$productId][$lotCode] ??= [
                    'lot_code' => $lotCode,
                    'quantity' => 0,
                    'expired_date' => $movement->expired_date?->toDateString(),
                ];
                $balances[$productId][$lotCode]['quantity'] += $delta;

                if ($balances[$productId][$lotCode]['expired_date'] === null && $movement->expired_date !== null) {
                    $balances[$productId][$lotCode]['expired_date'] = $movement->expired_date->toDateString();
                }
            }

            if ($movement->balance_after !== null) {
                $lastBalanceByProduct[$productId] = (int) $movement->balance_after;
            }
        }

        return collect($balances)->map(fn (array $lots): Collection => collect($lots)
            ->filter(fn (array $lot): bool => $lot['quantity'] > 0)
            ->sortBy('expired_date')
            ->values());
    }

    public function availableForLot(Product|int $product, ?string $lotCode): int
    {
        if (blank($lotCode)) {
            return 0;
        }

        $lot = $this->lots($product)->first(
            fn (array $lot) => $lot['lot_code'] === $lotCode,
        );

        return $lot['quantity'] ?? 0;
    }
}
