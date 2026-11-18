<?php

namespace App\Http\Controllers\Warehouse;

use App\Enums\StockMovementType;
use App\Http\Controllers\Controller;
use App\Models\OutMaterialFormItem;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\StockReceipt;
use App\Models\StockReceiptSlipItem;
use App\Models\StockRequest;
use App\Models\StockReturn;
use App\Support\StockCardType;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockCardController extends Controller
{
    public function show(Request $request, Product $product): Response
    {
        $filters = $request->validate([
            'lot' => ['nullable', 'string', 'max:100'],
            'direction' => ['nullable', 'in:incoming,outgoing'],
        ]);

        $direction = $filters['direction'] ?? 'incoming';

        $lotOptions = StockMovement::query()
            ->where('product_id', $product->id)
            ->whereNotNull('lot_code')
            ->orderBy('id')
            ->get(['lot_code', 'expired_date'])
            ->groupBy('lot_code')
            ->map(fn ($group, string $lotCode): array => [
                'lot_code' => $lotCode,
                'expired_date' => $group->firstWhere('expired_date', '!=', null)?->expired_date?->toDateString(),
            ])
            ->sortBy('lot_code')
            ->values();

        $firstLot = $lotOptions->first();
        $selectedLot = $filters['lot'] ?? $firstLot['lot_code'] ?? null;

        if ($selectedLot !== null && ! $lotOptions->contains(fn (array $lot) => $lot['lot_code'] === $selectedLot)) {
            $selectedLot = $firstLot['lot_code'] ?? null;
        }

        $selectedLotOption = $lotOptions->firstWhere('lot_code', $selectedLot);
        $lotExpiredDate = $selectedLotOption['expired_date'] ?? null;

        $movements = $selectedLot === null
            ? collect()
            : StockMovement::query()
                ->where('product_id', $product->id)
                ->where('lot_code', $selectedLot)
                ->whereIn('type', [
                    StockMovementType::STOCK_IN,
                    StockMovementType::RETURN,
                    StockMovementType::STOCK_OUT,
                    StockMovementType::DAMAGE,
                ])
                ->orderBy('id')
                ->get();

        $rows = [];
        $outgoingDetails = [];
        $running = 0;
        $totalIncoming = 0;
        $totalOutgoing = 0;
        $totalDamage = 0;

        foreach ($movements as $movement) {
            $quantity = (int) $movement->quantity;
            $isIncoming = in_array($movement->type, [StockMovementType::STOCK_IN, StockMovementType::RETURN], true);
            $isDamage = $movement->type === StockMovementType::DAMAGE;
            $beginning = $running;
            [$documentNumber, $documentKind] = $this->documentFor($movement);
            $expiredDate = $movement->expired_date?->format('M-y')
                ?? ($lotExpiredDate ? date('M-y', strtotime($lotExpiredDate)) : null);

            if ($isIncoming) {
                $running += $quantity;
                $totalIncoming += $quantity;
            } else {
                $running -= $quantity;
                $isDamage ? $totalDamage += $quantity : $totalOutgoing += $quantity;
            }

            $rows[] = [
                'no' => count($rows) + 1,
                'date' => $movement->created_at?->format('d-m-y'),
                'beginning' => $beginning,
                'incoming_document' => $isIncoming ? $documentNumber : null,
                'incoming_document_kind' => $isIncoming ? $documentKind : null,
                'incoming_quantity' => $isIncoming ? $quantity : null,
                'outgoing_document' => $isIncoming ? null : $documentNumber,
                'outgoing_document_kind' => $isIncoming ? null : $documentKind,
                'uom' => $product->unit,
                'expired_date' => $expiredDate,
                'is_damage' => $isDamage,
            ];

            if (! $isIncoming) {
                $outgoingDetails[] = [
                    'no' => count($outgoingDetails) + 1,
                    'document_number' => $documentNumber,
                    'document_kind' => $documentKind,
                    'quantity' => $quantity,
                    'uom' => $product->unit,
                    'balance' => $running,
                    'is_damage' => $isDamage,
                ];
            }
        }

        $receiptType = $this->receiptTypeFor($product, $selectedLot);
        $period = $this->period();

        return Inertia::render('Warehouse/Inventory/StockCard', [
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'description' => $product->description,
                'unit' => $product->unit,
                'brand' => $product->brand?->name,
                'brand_code' => $product->brand?->code,
            ],
            'lots' => $lotOptions,
            'selected_lot' => $selectedLot,
            'lot_expired_date' => $lotExpiredDate,
            'direction' => $direction,
            'type_options' => StockCardType::all(),
            'type_key' => StockCardType::normalize($receiptType),
            'type_raw' => $receiptType,
            'rows' => $rows,
            'outgoing_details' => $outgoingDetails,
            'totals' => [
                'incoming' => $totalIncoming,
                'outgoing' => $totalOutgoing,
                'damage' => $totalDamage,
            ],
            'summary' => [
                'beginning' => $rows[0]['beginning'] ?? 0,
                'incoming' => $totalIncoming,
                'outgoing' => $totalOutgoing,
                'damage' => $totalDamage,
                'final' => $running,
            ],
            'period' => $period,
        ]);
    }

    /**
     * Resolve the source document number for a movement's reference.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function documentFor(StockMovement $movement): array
    {
        $reference = $movement->reference;

        return match (true) {
            $reference instanceof StockReceiptSlipItem => [$reference->slip?->mrs_number, 'MRS'],
            $reference instanceof OutMaterialFormItem => [$reference->form?->omf_number, 'OMF'],
            $reference instanceof StockRequest => [$reference->mrv_number, 'MRV'],
            $reference instanceof StockReturn => [$reference->return_number, 'Return'],
            $reference instanceof StockReceipt => [$reference->receipt_number, 'Receipt'],
            default => [null, null],
        };
    }

    private function receiptTypeFor(Product $product, ?string $lotCode): ?string
    {
        $item = $product->receiptSlipItems()
            ->with('slip:id,receipt_type')
            ->when($lotCode !== null, fn ($query) => $query->where('lot_code', $lotCode))
            ->latest('id')
            ->first()
            ?? $product->receiptSlipItems()
                ->with('slip:id,receipt_type')
                ->latest('id')
                ->first();

        return $item?->slip?->receipt_type;
    }

    /**
     * @return array{week: string, month: string, year: string}
     */
    private function period(): array
    {
        $now = now();

        return [
            'week' => (string) $now->isoWeek,
            'month' => $this->monthName($now),
            'year' => $now->format('Y'),
        ];
    }

    private function monthName(CarbonInterface $date): string
    {
        $months = [
            1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
        ];

        return $months[(int) $date->format('n')] ?? $date->format('F');
    }
}
