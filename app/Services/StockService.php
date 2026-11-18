<?php

namespace App\Services;

use App\Enums\MrvStatus;
use App\Enums\StockMovementType;
use App\Models\Inventory;
use App\Models\OutMaterialForm;
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
     * Issue stock for a legacy MRV that was approved in a separate step.
     * New requests use approveAndIssue() to combine approval and deduction.
     */
    public function issue(StockRequest $request, ?string $photoPath = null, ?string $recipient = null): void
    {
        DB::transaction(function () use ($request, $photoPath, $recipient) {
            $locked = StockRequest::query()
                ->whereKey($request->getKey())
                ->lockForUpdate()
                ->first();

            if (! $locked || $locked->status !== MrvStatus::APPROVED) {
                throw new DomainException('MRV harus disetujui sebelum dikeluarkan; permintaan ganda ditolak.');
            }

            $this->issueLocked($locked, $photoPath, $recipient);
        });
    }

    /**
     * Approve a pending MRV and issue its materials as one atomic operation.
     */
    public function approveAndIssue(StockRequest $request, int $approvedBy): void
    {
        DB::transaction(function () use ($request, $approvedBy) {
            $locked = StockRequest::query()
                ->whereKey($request->getKey())
                ->lockForUpdate()
                ->first();

            if (! $locked || $locked->status !== MrvStatus::PENDING) {
                throw new DomainException('Hanya MRV berstatus menunggu yang bisa disetujui.');
            }

            $locked->status = MrvStatus::APPROVED;
            $locked->approved_by = $approvedBy;
            $locked->approved_at = now();
            $locked->save();

            $this->issueLocked($locked, null, $locked->material_received_by);
        });
    }

    /**
     * Process a return. Good units go back to available stock; damaged units
     * are recorded but never added to usable inventory. The return row is
     * locked and re-validated inside the transaction so concurrent processing
     * cannot add stock twice (REVISI P1 #5).
     */
    public function processReturn(StockReturn $return): void
    {
        DB::transaction(function () use ($return) {
            $locked = StockReturn::query()
                ->whereKey($return->getKey())
                ->lockForUpdate()
                ->first();

            if (! $locked || $locked->status === 'processed') {
                throw new DomainException('Pengembalian ini sudah diproses; stok tidak boleh ditambah dua kali.');
            }

            $locked->load(['items.product', 'stockRequest.items']);

            foreach ($locked->items as $item) {
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
                        [
                            'reference' => $locked,
                            'condition' => 'good',
                            'lot_code' => $this->returnLotCode($locked, $product->id),
                        ],
                    );
                }

                if ($item->quantity_damaged > 0) {
                    $this->recordMovement(
                        $product,
                        StockMovementType::DAMAGE,
                        $item->quantity_damaged,
                        $inventory->qty_on_hand,
                        [
                            'reference' => $locked,
                            'condition' => 'damaged',
                            'lot_code' => $this->returnLotCode($locked, $product->id),
                        ],
                    );
                }
            }

            $locked->status = 'processed';
            $locked->save();

            if ($locked->stock_request_id) {
                $request = StockRequest::query()
                    ->whereKey($locked->stock_request_id)
                    ->lockForUpdate()
                    ->first();

                if ($request && $request->status === MrvStatus::ISSUED) {
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

    public function reconcileLot(
        Product $product,
        string $lotCode,
        int $actualQuantity,
        string $reason,
        LotStockService $lotStock,
    ): ?StockMovement {
        return DB::transaction(function () use ($product, $lotCode, $actualQuantity, $reason, $lotStock): ?StockMovement {
            if ($actualQuantity < 0) {
                throw new DomainException('Saldo fisik lot tidak boleh kurang dari 0.');
            }

            $inventory = $this->lockInventory($product);

            if (! StockMovement::query()
                ->where('product_id', $product->id)
                ->where('lot_code', $lotCode)
                ->exists()) {
                throw new DomainException('Lot tidak ditemukan pada riwayat stok produk ini.');
            }

            $currentQuantity = $lotStock->availableForLot($product, $lotCode);
            $delta = $actualQuantity - $currentQuantity;

            if ($delta === 0) {
                return null;
            }

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
                ['reason' => $reason, 'lot_code' => $lotCode],
            );
        });
    }

    /**
     * Deduct stock for an Out Material Form, validating every line against the
     * per-lot ledger so a lot can never be over-issued.
     */
    public function issueOutMaterialForm(OutMaterialForm $form, ?LotStockService $lotStock = null): void
    {
        $lotStock ??= app(LotStockService::class);

        DB::transaction(function () use ($form, $lotStock) {
            $locked = OutMaterialForm::query()
                ->whereKey($form->getKey())
                ->lockForUpdate()
                ->first();

            if (! $locked) {
                throw new DomainException('Formulir barang keluar tidak ditemukan.');
            }

            $locked->load('items.product');
            $planned = [];

            foreach ($locked->items as $item) {
                $product = $item->product;

                if (! $product) {
                    throw new DomainException('Produk pada formulir tidak ditemukan.');
                }

                $key = $item->product_id.'|'.$item->lot_code;
                $planned[$key] = ($planned[$key] ?? 0) + $item->quantity;

                $available = $lotStock->availableForLot($product, $item->lot_code);

                if ($item->quantity > $available || $planned[$key] > $available) {
                    throw new DomainException(
                        "Stok lot {$item->lot_code} untuk {$product->name} tidak cukup. Tersedia {$available}."
                    );
                }

                $inventory = $this->lockInventory($product);

                if ($inventory->qty_on_hand < $item->quantity) {
                    throw new DomainException(
                        "Stok tidak cukup untuk {$product->name}. Tersedia {$inventory->qty_on_hand}."
                    );
                }

                $inventory->qty_on_hand -= $item->quantity;
                $inventory->save();

                $this->recordMovement(
                    $product,
                    StockMovementType::STOCK_OUT,
                    $item->quantity,
                    $inventory->qty_on_hand,
                    [
                        'reference' => $item,
                        'lot_code' => $item->lot_code,
                        'expired_date' => $item->expired_date?->toDateString(),
                    ],
                );
            }
        });
    }

    private function lockInventory(Product $product): Inventory
    {
        return Inventory::query()
            ->where('product_id', $product->id)
            ->lockForUpdate()
            ->firstOrCreate(['product_id' => $product->id], ['qty_on_hand' => 0, 'qty_reserved' => 0]);
    }

    private function issueLocked(
        StockRequest $locked,
        ?string $photoPath,
        ?string $recipient,
    ): void {
        $locked->load('items.product');
        $lotStock = app(LotStockService::class);
        $plannedLots = [];

        foreach ($locked->items as $item) {
            $product = $item->product;
            $inventory = $this->lockInventory($product);

            if ($inventory->qty_on_hand < $item->quantity_requested) {
                throw new DomainException("Stok tidak cukup untuk {$product->name}. Tersedia {$inventory->qty_on_hand}.");
            }

            if (filled($item->lot_no)) {
                $key = $item->product_id.'|'.$item->lot_no;
                $plannedLots[$key] = ($plannedLots[$key] ?? 0) + $item->quantity_requested;
                $available = $lotStock->availableForLot($product, $item->lot_no);

                if ($plannedLots[$key] > $available) {
                    throw new DomainException(
                        "Stok lot {$item->lot_no} untuk {$product->name} tidak cukup. Tersedia {$available}."
                    );
                }
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
                ['reference' => $locked, 'lot_code' => $item->lot_no],
            );
        }

        $locked->status = MrvStatus::ISSUED;
        $locked->issued_at = now();

        if ($photoPath !== null) {
            $locked->photo_path = $photoPath;
        }
        if ($recipient !== null) {
            $locked->recipient = $recipient;
        }

        $locked->save();
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
            'lot_code' => $context['lot_code']
                ?? $reference?->getAttribute('lot_code')
                ?? $reference?->getAttribute('lot_no'),
            'expired_date' => $context['expired_date']
                ?? $reference?->getAttribute('expired_date'),
            'created_by' => auth()->id(),
        ]);
    }

    private function returnLotCode(StockReturn $return, int $productId): ?string
    {
        $requestItems = $return->stockRequest?->items
            ->where('product_id', $productId);

        return $requestItems?->count() === 1
            ? $requestItems->first()->lot_no
            : null;
    }
}
