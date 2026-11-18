<?php

namespace App\Http\Controllers\Warehouse;

use App\Enums\MrvStatus;
use App\Enums\StockMovementType;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\StockRequest;
use App\Services\AuditLogger;
use App\Services\LotStockService;
use App\Services\StockService;
use App\Support\BrandContext;
use Carbon\Carbon;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends Controller
{
    public function mios(Request $request): Response|RedirectResponse
    {
        $filters = $request->validate([
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $brands = Brand::query()
            ->whereIn('id', Product::query()->select('brand_id')->whereNotNull('brand_id')->distinct())
            ->orderBy('name')
            ->get(['id', 'name']);
        $brandId = isset($filters['brand_id']) ? (int) $filters['brand_id'] : null;
        $startDate = $filters['start_date'] ?? today()->startOfMonth()->toDateString();
        $endDate = $filters['end_date'] ?? today()->toDateString();

        if (Carbon::parse($endDate)->lt(Carbon::parse($startDate))) {
            return back()
                ->withErrors(['end_date' => 'Tanggal akhir harus sama atau setelah tanggal awal.'])
                ->withInput();
        }

        $products = Product::query()
            ->with('inventory')
            ->when($brandId !== null, fn ($query) => $query->where('brand_id', $brandId))
            ->orderBy('name')
            ->get();
        $productsById = $products->keyBy('id');
        $periodStart = Carbon::parse($startDate)->startOfDay();
        $periodEnd = Carbon::parse($endDate)->endOfDay();
        $mrvReferenceType = (new StockRequest)->getMorphClass();
        $rows = [];
        $lastBalanceByProduct = [];
        $productsWithMovements = [];

        $getRow = function (Product $product, ?string $lotCode, ?string $expiredDate) use (&$rows): array {
            $key = implode('|', [$product->id, $lotCode ?? '']);

            if (! isset($rows[$key])) {
                $rows[$key] = [
                    'product_id' => $product->id,
                    'description' => $product->name,
                    'item_number' => $product->sku,
                    'sku' => $product->sku,
                    'uom' => $product->unit,
                    'units_per_case' => $product->units_per_case,
                    'lot_code' => $lotCode,
                    'expired_date' => $expiredDate,
                    'beginning_stock' => 0,
                    'incoming_new' => 0,
                    'incoming_return' => 0,
                    'outgoing_mrv' => 0,
                    'outgoing_omf' => 0,
                    'outgoing_damage' => 0,
                    'adjustments' => 0,
                    'balance' => 0,
                ];
            } elseif ($rows[$key]['expired_date'] === null && $expiredDate !== null) {
                $rows[$key]['expired_date'] = $expiredDate;
            }

            return $rows[$key];
        };

        if ($products->isNotEmpty()) {
            $movements = StockMovement::query()
                ->whereIn('product_id', $products->modelKeys())
                ->where('created_at', '<=', $periodEnd)
                ->select([
                    'id', 'product_id', 'type', 'quantity', 'balance_after',
                    'reference_type', 'lot_code', 'expired_date', 'created_at',
                ])
                ->orderBy('created_at')
                ->orderBy('id')
                ->cursor();

            foreach ($movements as $movement) {
                $product = $productsById->get($movement->product_id);

                if (! $product) {
                    continue;
                }

                $productsWithMovements[$product->id] = true;
                $expiredDate = $movement->expired_date?->toDateString();
                $lotCode = $movement->lot_code;
                $rowKey = implode('|', [$product->id, $lotCode ?? '']);
                $getRow($product, $lotCode, $expiredDate);
                $type = $movement->type;
                $quantity = (int) $movement->quantity;
                $previousBalance = $lastBalanceByProduct[$product->id] ?? null;

                $delta = match ($type) {
                    StockMovementType::STOCK_IN, StockMovementType::RETURN => $quantity,
                    StockMovementType::STOCK_OUT => -$quantity,
                    StockMovementType::DAMAGE, StockMovementType::ADJUSTMENT => 0,
                };

                if ($type === StockMovementType::ADJUSTMENT && $previousBalance !== null) {
                    $delta = (int) $movement->balance_after - $previousBalance;
                }

                if ($previousBalance === null) {
                    $openingBalance = (int) $movement->balance_after - $delta;

                    if ($openingBalance !== 0) {
                        $getRow($product, null, null);
                        $rows[implode('|', [$product->id, ''])]['beginning_stock'] += $openingBalance;
                    }
                }

                $lastBalanceByProduct[$product->id] = (int) $movement->balance_after;
                $movementDate = $movement->created_at;

                if ($movementDate->lt($periodStart)) {
                    $rows[$rowKey]['beginning_stock'] += $delta;

                    continue;
                }

                if ($type === StockMovementType::STOCK_IN) {
                    $rows[$rowKey]['incoming_new'] += $quantity;
                } elseif ($type === StockMovementType::RETURN) {
                    $rows[$rowKey]['incoming_return'] += $quantity;
                } elseif ($type === StockMovementType::STOCK_OUT) {
                    if ($movement->reference_type === $mrvReferenceType) {
                        $rows[$rowKey]['outgoing_mrv'] += $quantity;
                    } else {
                        $rows[$rowKey]['outgoing_omf'] += $quantity;
                    }
                } elseif ($type === StockMovementType::DAMAGE) {
                    $rows[$rowKey]['outgoing_damage'] += $quantity;
                } elseif ($type === StockMovementType::ADJUSTMENT) {
                    $rows[$rowKey]['adjustments'] += $delta;
                }
            }
        }

        foreach ($products as $product) {
            if (isset($productsWithMovements[$product->id])) {
                continue;
            }

            $currentStock = (int) ($product->inventory?->qty_on_hand ?? 0);

            if ($currentStock > 0 && $endDate >= today()->toDateString()) {
                $getRow($product, null, null);
                $key = implode('|', [$product->id, '']);
                $rows[$key]['beginning_stock'] = $currentStock;
            }
        }

        foreach ($rows as &$row) {
            $row['balance'] = $row['beginning_stock']
                + $row['incoming_new']
                + $row['incoming_return']
                - $row['outgoing_mrv']
                - $row['outgoing_omf']
                + $row['adjustments'];
        }
        unset($row);

        usort($rows, fn (array $first, array $second): int => [
            $first['description'],
            $first['lot_code'] ?? '',
            $first['expired_date'] ?? '',
        ] <=> [
            $second['description'],
            $second['lot_code'] ?? '',
            $second['expired_date'] ?? '',
        ]);

        $productRows = collect(array_values($rows))
            ->groupBy('product_id')
            ->map(function (Collection $productLots): array {
                $firstLot = $productLots->first();
                $balance = (int) $productLots->sum('balance');
                $unitsPerCase = $firstLot['units_per_case'];

                return [
                    'product_id' => $firstLot['product_id'],
                    'description' => $firstLot['description'],
                    'item_number' => $firstLot['item_number'],
                    'sku' => $firstLot['sku'],
                    'uom' => $firstLot['uom'],
                    'units_per_case' => $unitsPerCase,
                    'balance_pack_units' => $unitsPerCase
                        ? $balance % $unitsPerCase
                        : $balance,
                    'balance_cases' => $unitsPerCase
                        ? intdiv($balance, $unitsPerCase)
                        : null,
                    'lots' => $productLots->map(fn (array $lot): array => [
                        'lot_code' => $lot['lot_code'],
                        'expired_date' => $lot['expired_date'],
                        'beginning_stock' => $lot['beginning_stock'],
                        'incoming_new' => $lot['incoming_new'],
                        'incoming_return' => $lot['incoming_return'],
                        'outgoing_mrv' => $lot['outgoing_mrv'],
                        'outgoing_omf' => $lot['outgoing_omf'],
                        'outgoing_damage' => $lot['outgoing_damage'],
                        'adjustments' => $lot['adjustments'],
                        'balance' => $lot['balance'],
                    ])->values()->all(),
                    'beginning_stock' => (int) $productLots->sum('beginning_stock'),
                    'incoming_new' => (int) $productLots->sum('incoming_new'),
                    'incoming_return' => (int) $productLots->sum('incoming_return'),
                    'outgoing_mrv' => (int) $productLots->sum('outgoing_mrv'),
                    'outgoing_omf' => (int) $productLots->sum('outgoing_omf'),
                    'outgoing_damage' => (int) $productLots->sum('outgoing_damage'),
                    'adjustments' => (int) $productLots->sum('adjustments'),
                    'balance' => $balance,
                ];
            })
            ->values();

        return Inertia::render('Warehouse/Inventory/Mios', [
            'brands' => $brands,
            'selectedBrandId' => $brandId,
            'selectedBrandName' => $brands->firstWhere('id', $brandId)?->name,
            'filters' => [
                'brand_id' => $brandId,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'rows' => $productRows,
        ]);
    }

    public function index(Request $request, LotStockService $lotStock): Response
    {
        $query = Product::query()->with(['brand:id,name', 'category:id,name', 'inventory']);

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%");
            });
        }

        $brandId = BrandContext::resolve($request);

        if ($brandId) {
            $query->where('brand_id', $brandId);
        }

        $movementTotals = DB::table('stock_movements')
            ->select('product_id', 'type', DB::raw('SUM(quantity) as total'))
            ->groupBy('product_id', 'type')
            ->get()
            ->groupBy('product_id');

        $allProducts = Product::query()->with('inventory')->get(['id', 'minimum_stock']);
        $lowStock = $allProducts->count(fn (Product $p) => $p->stockStatus() === 'low_stock');
        $outOfStock = $allProducts->count(fn (Product $p) => $p->stockStatus() === 'out_of_stock');

        $canManage = $request->user()->hasPermission('warehouse.manage');
        $productRecords = $query->orderBy('name')->get();
        $lotsByProduct = $canManage
            ? $lotStock->lotsForProducts($productRecords->modelKeys())
            : collect();
        $products = $productRecords->map(function (Product $product) use ($movementTotals, $lotsByProduct) {
            $totals = $movementTotals->get($product->id) ?? collect();

            return [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'brand' => $product->brand?->name,
                'category' => $product->category?->name,
                'unit' => $product->unit,
                'stock_in' => (int) ($totals->get(StockMovementType::STOCK_IN->value)?->total ?? 0),
                'stock_out' => (int) ($totals->get(StockMovementType::STOCK_OUT->value)?->total ?? 0),
                'on_hand' => $product->inventory?->qty_on_hand ?? 0,
                'reserved' => $product->inventory?->qty_reserved ?? 0,
                'available' => $product->availableStock(),
                'minimum_stock' => $product->minimum_stock,
                'status' => $product->stockStatus(),
                'lots' => $lotsByProduct->get($product->id, collect())->all(),
            ];
        });

        $canApprove = $request->user()->hasPermission('warehouse.approve');

        return Inertia::render('Warehouse/Inventory/Index', [
            'products' => $products,
            'filters' => [
                ...$request->only('search'),
                'brand_id' => $brandId,
            ],
            'brands' => Brand::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'can' => ['manage' => $canManage],
            'summary' => [
                'total_products' => $allProducts->count(),
                'low_stock' => $lowStock,
                'out_of_stock' => $outOfStock,
                'mrv_pending' => $canApprove
                    ? StockRequest::query()->where('status', MrvStatus::PENDING)->count()
                    : null,
            ],
        ]);
    }

    public function movements(Request $request): Response
    {
        $query = StockMovement::query()->with(['product:id,name,sku', 'creator:id,name']);

        if ($productId = $request->integer('product_id')) {
            $query->where('product_id', $productId);
        }

        if ($type = $request->string('type')->toString()) {
            $query->where('type', $type);
        }

        $movements = $query->latest()->paginate(20)->withQueryString()->through(fn (StockMovement $m) => [
            'id' => $m->id,
            'product' => $m->product?->name,
            'sku' => $m->product?->sku,
            'type' => $m->type->value,
            'type_label' => $m->type->label(),
            'quantity' => $m->quantity,
            'balance_after' => $m->balance_after,
            'lot_code' => $m->lot_code,
            'expired_date' => $m->expired_date?->toDateString(),
            'notes' => $m->notes,
            'by' => $m->creator?->name,
            'at' => $m->created_at?->toIso8601String(),
        ]);

        return Inertia::render('Warehouse/Inventory/Movements', [
            'movements' => $movements,
            'products' => Product::orderBy('name')->get(['id', 'name', 'sku']),
            'filters' => $request->only('product_id', 'type'),
            'summary' => $productId ? $this->productSummary($productId) : null,
        ]);
    }

    /**
     * Per-product mutation recap (REVISI §4.4.D): opening stock, goods in,
     * goods out, usable returns, damage, and current balance. Opening stock is
     * derived from the first movement's recorded balance; null when it cannot
     * be derived (first movement is an adjustment).
     */
    private function productSummary(int $productId): ?array
    {
        $product = Product::with('inventory')->find($productId);

        if (! $product) {
            return null;
        }

        $movements = StockMovement::query()
            ->where('product_id', $productId)
            ->orderBy('id')
            ->get(['id', 'type', 'quantity', 'balance_after']);

        $opening = null;

        if ($first = $movements->first()) {
            $opening = match ($first->type) {
                StockMovementType::STOCK_IN, StockMovementType::RETURN => $first->balance_after - $first->quantity,
                StockMovementType::STOCK_OUT => $first->balance_after + $first->quantity,
                StockMovementType::DAMAGE => $first->balance_after,
                StockMovementType::ADJUSTMENT => null,
            };
        }

        $totals = $movements->groupBy(fn (StockMovement $m) => $m->type->value)
            ->map(fn ($group) => $group->sum('quantity'));

        return [
            'product' => $product->name,
            'sku' => $product->sku,
            'unit' => $product->unit,
            'opening' => $opening,
            'stock_in' => $totals->get(StockMovementType::STOCK_IN->value, 0),
            'stock_out' => $totals->get(StockMovementType::STOCK_OUT->value, 0),
            'returned' => $totals->get(StockMovementType::RETURN->value, 0),
            'damaged' => $totals->get(StockMovementType::DAMAGE->value, 0),
            'adjustments' => $totals->get(StockMovementType::ADJUSTMENT->value, 0),
            'on_hand' => $product->inventory?->qty_on_hand ?? 0,
            'available' => $product->availableStock(),
        ];
    }

    public function adjust(Request $request, StockService $stock, LotStockService $lotStock)
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'lot_code' => ['required', 'string', 'max:100'],
            'actual_quantity' => ['required', 'integer', 'min:0'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $product = Product::findOrFail($data['product_id']);

        if (! StockMovement::query()
            ->where('product_id', $product->id)
            ->where('lot_code', $data['lot_code'])
            ->exists()) {
            return back()
                ->withErrors(['lot_code' => 'Lot tidak ditemukan pada riwayat stok produk ini.'])
                ->withInput();
        }

        try {
            $movement = $stock->reconcileLot(
                $product,
                $data['lot_code'],
                (int) $data['actual_quantity'],
                $data['reason'],
                $lotStock,
            );
        } catch (DomainException $e) {
            return back()->withErrors(['actual_quantity' => $e->getMessage()])->withInput();
        }

        if ($movement === null) {
            return back()->with('info', 'Saldo fisik sudah sesuai; tidak ada penyesuaian yang diperlukan.');
        }

        AuditLogger::log('ADJUST_STOCK', 'warehouse', $product, null, $data);

        return back()->with('success', 'Stok disesuaikan.');
    }
}
