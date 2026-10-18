<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockReceipt;
use App\Services\AuditLogger;
use App\Services\StockService;
use DomainException;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockReceiptController extends Controller
{
    public function index(Request $request): Response
    {
        $receipts = StockReceipt::query()->with(['product:id,name,sku', 'recorder:id,name'])
            ->latest()->paginate(15)->withQueryString()->through(fn (StockReceipt $r) => [
                'id' => $r->id,
                'receipt_number' => $r->receipt_number,
                'product' => $r->product?->name,
                'sku' => $r->product?->sku,
                'quantity' => $r->quantity,
                'supplier' => $r->supplier,
                'received_date' => $r->received_date?->toDateString(),
                'recorded_by' => $r->recorder?->name,
            ]);

        return Inertia::render('Warehouse/Receipts/Index', [
            'receipts' => $receipts,
            'products' => Product::where('is_active', true)->orderBy('name')->get(['id', 'name', 'sku', 'unit']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Warehouse/Receipts/Form', [
            'products' => Product::where('is_active', true)->orderBy('name')->get(['id', 'name', 'sku', 'unit']),
        ]);
    }

    public function store(Request $request, StockService $stock)
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'received_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $product = Product::findOrFail($data['product_id']);

        $receipt = StockReceipt::create([
            'receipt_number' => StockReceipt::generateNumber(),
            'product_id' => $product->id,
            'quantity' => $data['quantity'],
            'supplier' => $data['supplier'] ?? null,
            'received_date' => $data['received_date'] ?? now()->toDateString(),
            'notes' => $data['notes'] ?? null,
            'recorded_by' => $request->user()->id,
        ]);

        try {
            $stock->receive($product, (int) $data['quantity'], ['reference' => $receipt]);
        } catch (DomainException $e) {
            $receipt->delete();

            return back()->withErrors(['quantity' => $e->getMessage()]);
        }

        AuditLogger::log('RECEIVE_STOCK', 'warehouse', $receipt, null, $data);

        return redirect()->route('warehouse.receipts.index')->with('success', 'Barang masuk dicatat & stok bertambah.');
    }
}
