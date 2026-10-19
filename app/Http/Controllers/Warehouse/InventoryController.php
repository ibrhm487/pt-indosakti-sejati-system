<?php

namespace App\Http\Controllers\Warehouse;

use App\Enums\MrvStatus;
use App\Enums\StockMovementType;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\StockRequest;
use App\Services\AuditLogger;
use App\Services\StockService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Product::query()->with(['brand:id,name', 'category:id,name', 'inventory']);

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%");
            });
        }

        $movementTotals = DB::table('stock_movements')
            ->select('product_id', 'type', DB::raw('SUM(quantity) as total'))
            ->groupBy('product_id', 'type')
            ->get()
            ->groupBy('product_id');

        $allProducts = Product::query()->with('inventory')->get(['id', 'minimum_stock']);
        $lowStock = $allProducts->count(fn (Product $p) => $p->stockStatus() === 'low_stock');
        $outOfStock = $allProducts->count(fn (Product $p) => $p->stockStatus() === 'out_of_stock');

        $products = $query->orderBy('name')->get()->map(function (Product $product) use ($movementTotals) {
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
            ];
        });

        $canApprove = $request->user()->hasPermission('warehouse.approve');

        return Inertia::render('Warehouse/Inventory/Index', [
            'products' => $products,
            'filters' => $request->only('search'),
            'can' => ['manage' => $request->user()->hasPermission('warehouse.manage')],
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

    public function adjust(Request $request, StockService $stock)
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'delta' => ['required', 'integer', 'not_in:0'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $product = Product::findOrFail($data['product_id']);

        try {
            $stock->adjust($product, (int) $data['delta'], $data['reason']);
        } catch (DomainException $e) {
            return back()->withErrors(['delta' => $e->getMessage()]);
        }

        AuditLogger::log('ADJUST_STOCK', 'warehouse', $product, null, $data);

        return back()->with('success', 'Stok disesuaikan.');
    }
}
