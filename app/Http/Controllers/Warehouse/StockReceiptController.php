<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockReceipt;
use App\Services\AuditLogger;
use App\Services\StockService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
                'photo_url' => $r->photo_path ? route('warehouse.receipts.photo', $r->id) : null,
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
            'photo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:4096'],
        ]);

        $product = Product::findOrFail($data['product_id']);

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('warehouse/receipts');
        }

        try {
            $receipt = DB::transaction(function () use ($data, $request, $product, $stock, $photoPath) {
                $receipt = StockReceipt::create([
                    'receipt_number' => StockReceipt::generateNumber(),
                    'product_id' => $product->id,
                    'quantity' => $data['quantity'],
                    'supplier' => $data['supplier'] ?? null,
                    'received_date' => $data['received_date'] ?? now()->toDateString(),
                    'notes' => $data['notes'] ?? null,
                    'photo_path' => $photoPath,
                    'recorded_by' => $request->user()->id,
                ]);

                $stock->receive($product, (int) $data['quantity'], ['reference' => $receipt]);

                return $receipt;
            });
        } catch (DomainException $e) {
            if ($photoPath) {
                Storage::disk('local')->delete($photoPath);
            }

            return back()->withErrors(['quantity' => $e->getMessage()]);
        }

        AuditLogger::log('RECEIVE_STOCK', 'warehouse', $receipt, null, collect($data)->except('photo')->all());

        return redirect()->route('warehouse.receipts.index')->with('success', 'Barang masuk dicatat & stok bertambah.');
    }

    public function downloadPhoto(StockReceipt $receipt)
    {
        abort_unless(request()->user()->hasPermission('warehouse.view'), 403);

        abort_unless($receipt->photo_path, 404);

        if (Storage::disk('local')->exists($receipt->photo_path)) {
            return Storage::disk('local')->response($receipt->photo_path);
        }

        if (Storage::disk('public')->exists($receipt->photo_path)) {
            return Storage::disk('public')->response($receipt->photo_path);
        }

        abort(404);
    }
}
