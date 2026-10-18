<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\AuditLogger;
use App\Services\StockService;
use DomainException;
use Illuminate\Http\Request;
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

        $products = $query->orderBy('name')->get()->map(fn (Product $product) => [
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'brand' => $product->brand?->name,
            'category' => $product->category?->name,
            'unit' => $product->unit,
            'on_hand' => $product->inventory?->qty_on_hand ?? 0,
            'reserved' => $product->inventory?->qty_reserved ?? 0,
            'available' => $product->availableStock(),
            'minimum_stock' => $product->minimum_stock,
            'status' => $product->stockStatus(),
        ]);

        return Inertia::render('Warehouse/Inventory/Index', [
            'products' => $products,
            'filters' => $request->only('search'),
            'can' => ['manage' => $request->user()->hasPermission('warehouse.manage')],
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
        ]);
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
