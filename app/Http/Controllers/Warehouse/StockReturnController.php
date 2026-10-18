<?php

namespace App\Http\Controllers\Warehouse;

use App\Enums\MrvStatus;
use App\Http\Controllers\Controller;
use App\Models\StockRequest;
use App\Models\StockReturn;
use App\Services\AuditLogger;
use App\Services\StockService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class StockReturnController extends Controller
{
    public function index(Request $request): Response
    {
        $returns = StockReturn::query()->with(['stockRequest:id,mrv_number', 'event:id,event_name', 'recorder:id,name'])
            ->latest()->paginate(15)->withQueryString()->through(fn (StockReturn $r) => [
                'id' => $r->id,
                'return_number' => $r->return_number,
                'mrv_number' => $r->stockRequest?->mrv_number,
                'event' => $r->event?->event_name,
                'status' => $r->status,
                'recorded_by' => $r->recorder?->name,
            ]);

        return Inertia::render('Warehouse/Returns/Index', ['returns' => $returns]);
    }

    public function create(Request $request): Response
    {
        $mrvId = $request->integer('stock_request_id');

        $requests = StockRequest::query()
            ->where('status', MrvStatus::ISSUED)
            ->whereDoesntHave('returns')
            ->with('items.product:id,name,sku,unit')
            ->latest()
            ->get()
            ->map(fn (StockRequest $r) => [
                'id' => $r->id,
                'mrv_number' => $r->mrv_number,
                'event' => $r->event?->event_name,
                'items' => $r->items->map(fn ($i) => [
                    'product_id' => $i->product_id,
                    'product' => $i->product?->name,
                    'unit' => $i->product?->unit,
                    'quantity_issued' => $i->quantity_issued,
                ]),
            ]);

        $selected = $mrvId ? $requests->firstWhere('id', $mrvId) : null;

        return Inertia::render('Warehouse/Returns/Form', [
            'requests' => $requests,
            'selectedRequest' => $selected,
        ]);
    }

    public function store(Request $request, StockService $stock)
    {
        $data = $request->validate([
            'stock_request_id' => ['required', 'exists:stock_requests,id'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity_good' => ['required', 'integer', 'min:0'],
            'items.*.quantity_damaged' => ['required', 'integer', 'min:0'],
        ]);

        $stockRequest = StockRequest::with('items.product')->findOrFail($data['stock_request_id']);

        if ($stockRequest->status !== MrvStatus::ISSUED) {
            return back()->withErrors(['stock_request_id' => 'Pengembalian hanya dapat dicatat untuk MRV yang sudah dikeluarkan.']);
        }

        if ($stockRequest->returns()->exists()) {
            return back()->withErrors(['stock_request_id' => 'MRV ini sudah memiliki catatan pengembalian.']);
        }

        if ($error = $this->validateReturnItems($data['items'], $stockRequest)) {
            return back()->withErrors(['items' => $error]);
        }

        try {
            $return = DB::transaction(function () use ($data, $stockRequest, $request, $stock) {
                $return = StockReturn::create([
                    'return_number' => StockReturn::generateNumber(),
                    'stock_request_id' => $stockRequest->id,
                    'event_id' => $stockRequest->event_id,
                    'status' => 'pending',
                    'notes' => $data['notes'] ?? null,
                    'recorded_by' => $request->user()->id,
                ]);

                foreach ($data['items'] as $item) {
                    $good = (int) $item['quantity_good'];
                    $damaged = (int) $item['quantity_damaged'];

                    $return->items()->create([
                        'product_id' => $item['product_id'],
                        'quantity_good' => $good,
                        'quantity_damaged' => $damaged,
                    ]);

                    $stockRequest->items()
                        ->where('product_id', $item['product_id'])
                        ->update([
                            'quantity_returned' => DB::raw('quantity_returned + '.$good),
                            'quantity_damaged' => DB::raw('quantity_damaged + '.$damaged),
                        ]);
                }

                $stock->processReturn($return);

                return $return;
            });
        } catch (DomainException $e) {
            return back()->withErrors(['items' => $e->getMessage()]);
        }

        AuditLogger::log('RECORD_RETURN', 'warehouse', $return, null, $data);

        return redirect()->route('warehouse.returns.index')->with('success', 'Pengembalian diproses. Barang rusak tidak menambah stok.');
    }

    /**
     * @param  array<int, array{product_id: int|string, quantity_good: int|string, quantity_damaged: int|string}>  $items
     */
    private function validateReturnItems(array $items, StockRequest $stockRequest): ?string
    {
        $issued = $stockRequest->items->keyBy('product_id');
        $seen = [];

        foreach ($items as $item) {
            $productId = (int) $item['product_id'];

            if (isset($seen[$productId])) {
                return 'Produk tidak boleh dicatat dua kali dalam satu pengembalian.';
            }
            $seen[$productId] = true;

            $line = $issued->get($productId);

            if (! $line) {
                return 'Produk yang dicatat tidak termasuk dalam MRV ini.';
            }

            $good = (int) $item['quantity_good'];
            $damaged = (int) $item['quantity_damaged'];

            if ($good + $damaged <= 0) {
                return 'Setiap baris harus memiliki jumlah (baik atau rusak) lebih dari 0.';
            }

            $remaining = (int) $line->quantity_issued - (int) $line->quantity_returned - (int) $line->quantity_damaged;

            if ($good + $damaged > $remaining) {
                $name = $line->product?->name ?? 'produk';

                return "Jumlah pengembalian {$name} melebihi jumlah yang dikeluarkan ({$line->quantity_issued}).";
            }
        }

        return null;
    }
}
