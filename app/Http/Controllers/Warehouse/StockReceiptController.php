<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\StockReceipt;
use App\Models\StockReceiptSlip;
use App\Models\StockReceiptSlipItem;
use App\Services\AuditLogger;
use App\Services\LotStockService;
use App\Services\StockService;
use App\Support\DocumentNumber;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class StockReceiptController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'brand_id' => ['nullable', 'integer', Rule::exists('brands', 'id')],
        ]);
        $brandId = isset($filters['brand_id']) ? (int) $filters['brand_id'] : null;

        $receiptsQuery = StockReceipt::query()->with([
            'product:id,name,sku,brand_id',
            'product.brand:id,name,code',
            'recorder:id,name',
        ]);
        $slipsQuery = StockReceiptSlip::query()
            ->withCount('items')
            ->withSum('items', 'received_quantity')
            ->with('items.product.brand:id,name,code')
            ->with('recorder:id,name');

        if ($brandId !== null) {
            $receiptsQuery->whereHas('product', fn ($query) => $query->where('brand_id', $brandId));
            $slipsQuery->whereHas(
                'items.product',
                fn ($query) => $query->where('brand_id', $brandId),
            );
        }

        $receipts = $receiptsQuery
            ->latest()->paginate(15, ['*'], 'legacy_page')->withQueryString()->through(fn (StockReceipt $r) => [
                'id' => $r->id,
                'receipt_number' => $r->receipt_number,
                'product' => $r->product?->name,
                'sku' => $r->product?->sku,
                'brand' => $r->product?->brand?->name,
                'quantity' => $r->quantity,
                'supplier' => $r->supplier,
                'received_date' => $r->received_date?->toDateString(),
                'recorded_by' => $r->recorder?->name,
                'photo_url' => $r->photo_path ? route('warehouse.receipts.photo', $r->id) : null,
            ]);

        $slips = $slipsQuery->latest()
            ->paginate(15, ['*'], 'page')
            ->withQueryString()
            ->through(fn (StockReceiptSlip $slip) => [
                'id' => $slip->id,
                'mrs_number' => $slip->mrs_number,
                'brand' => $slip->items->first()?->product?->brand?->name,
                'receipt_type' => $slip->receipt_type,
                'do_number' => $slip->do_number,
                'shipper' => $slip->shipper,
                'supplier' => $slip->supplier,
                'received_date' => $slip->received_date?->toDateString(),
                'items_count' => $slip->items_count,
                'total_received' => $slip->items_sum_received_quantity ?? 0,
                'recorded_by' => $slip->recorder?->name,
            ]);

        return Inertia::render('Warehouse/Receipts/Index', [
            'slips' => $slips,
            'receipts' => $receipts,
            'filters' => ['brand_id' => $brandId],
            'brands' => Brand::query()
                ->whereIn(
                    'id',
                    Product::query()->select('brand_id')->whereNotNull('brand_id')->distinct(),
                )
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function create(LotStockService $lotStock): Response
    {
        return Inertia::render('Warehouse/Receipts/Form', [
            'products' => Product::where('is_active', true)
                ->with('brand:id,code')
                ->orderBy('name')
                ->get(['id', 'name', 'sku', 'unit', 'brand_id'])
                ->map(fn (Product $product) => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'unit' => $product->unit,
                    'brand_id' => $product->brand_id,
                    'brand_code' => $product->brand?->code,
                    'lots' => $lotStock->lots($product),
                ]),
            'mrsNumber' => StockReceiptSlip::generateNumber(),
            'today' => today()->toDateString(),
            'recentMovements' => StockMovement::query()
                ->with('product:id,name,sku')
                ->latest()
                ->limit(8)
                ->get()
                ->map(fn (StockMovement $m) => [
                    'id' => $m->id,
                    'product' => $m->product?->name,
                    'type' => $m->type->value,
                    'type_label' => $m->type->label(),
                    'quantity' => $m->quantity,
                    'balance_after' => $m->balance_after,
                    'at' => $m->created_at?->format('d/m/Y H:i'),
                ]),
        ]);
    }

    public function show(Request $request, StockReceiptSlip $slip): Response
    {
        $slip->load(['items', 'recorder:id,name']);

        return Inertia::render('Warehouse/Receipts/Show', [
            'slip' => [
                'id' => $slip->id,
                'mrs_number' => $slip->mrs_number,
                'receipt_type' => $slip->receipt_type,
                'do_number' => $slip->do_number,
                'shipper' => $slip->shipper,
                'supplier' => $slip->supplier,
                'document_status' => $slip->document_status,
                'received_date' => $slip->received_date?->toDateString(),
                'release_date' => $slip->release_date?->toDateString(),
                'release_time' => $slip->release_time ? substr($slip->release_time, 0, 5) : null,
                'authorized_by' => $slip->authorized_by,
                'quantity_released' => $slip->quantity_released,
                'quantity_assurance' => $slip->quantity_assurance,
                'received_by_name' => $slip->received_by_name,
                'received_by_role' => $slip->received_by_role,
                'approved_by_name' => $slip->approved_by_name,
                'approved_by_role' => $slip->approved_by_role,
                'operational_manager_by_name' => $slip->operational_manager_by_name,
                'operational_manager_by_role' => $slip->operational_manager_by_role,
                'recorded_at' => $slip->created_at?->format('d/m/Y H:i'),
                'quality_assurance_by_name' => $slip->quality_assurance_by_name,
                'quality_assurance_by_role' => $slip->quality_assurance_by_role,
                'notes' => $slip->notes,
                'recorded_by' => $slip->recorder?->name,
                'items' => $slip->items->map(fn (StockReceiptSlipItem $item) => [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'sku' => $item->sku,
                    'item_name' => $item->item_name,
                    'lot_code' => $item->lot_code,
                    'description' => $item->description,
                    'received_quantity' => $item->received_quantity,
                    'uom' => $item->uom,
                    'expired_date' => $item->expired_date?->toDateString(),
                    'status' => $item->status,
                    'remark' => $item->remark,
                ]),
            ],
            'can' => [
                'manage' => $request->user()->hasPermission('warehouse.manage'),
            ],
        ]);
    }

    public function updateNumber(Request $request, StockReceiptSlip $slip): RedirectResponse
    {
        $data = $request->validate([
            'mrs_number' => [
                'required',
                'string',
                'max:60',
                Rule::unique('stock_receipt_slips', 'mrs_number')->ignore($slip->id),
            ],
        ]);

        $old = $slip->mrs_number;
        $slip->update(['mrs_number' => DocumentNumber::normalize($data['mrs_number'])]);

        AuditLogger::log('UPDATE_MRS_NUMBER', 'warehouse', $slip, ['mrs_number' => $old], [
            'mrs_number' => $slip->mrs_number,
        ]);

        return back()->with('success', 'Nomor MRS diperbarui.');
    }

    public function store(Request $request, StockService $stock): RedirectResponse
    {
        if ($request->has('items')) {
            return $this->storeSlip($request, $stock);
        }

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

    private function storeSlip(Request $request, StockService $stock): RedirectResponse
    {
        $manualNumber = $request->exists('mrs_number_manual')
            ? $request->boolean('mrs_number_manual')
            : filled($request->input('mrs_number'));

        if ($manualNumber && is_string($request->input('mrs_number'))) {
            $request->merge([
                'mrs_number' => DocumentNumber::normalize($request->input('mrs_number')),
            ]);
        }

        $data = $request->validate([
            'mrs_number' => [
                $manualNumber ? 'required' : 'nullable',
                'string',
                'max:60',
                ...($manualNumber ? [Rule::unique('stock_receipt_slips', 'mrs_number')] : []),
            ],
            'mrs_number_manual' => ['sometimes', 'boolean'],
            'receipt_type' => ['required', 'string', 'max:100'],
            'do_number' => ['required', 'string', 'max:100'],
            'shipper' => ['nullable', 'string', 'max:255'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'document_status' => ['nullable', 'string', 'max:50'],
            'received_date' => ['required', 'date'],
            'release_date' => ['nullable', 'date_format:Y-m-d', 'required_with:release_time'],
            'release_time' => ['nullable', 'date_format:H:i', 'required_with:release_date'],
            'authorized_by' => ['nullable', 'string', 'max:255'],
            'quantity_released' => ['nullable', 'integer', 'min:0'],
            'quantity_assurance' => ['nullable', 'integer', 'min:0'],
            'received_by_name' => ['nullable', 'string', 'max:255'],
            'received_by_role' => ['nullable', 'string', 'max:100'],
            'approved_by_name' => ['nullable', 'string', 'max:255'],
            'approved_by_role' => ['nullable', 'string', 'max:100'],
            'operational_manager_by_name' => ['nullable', 'string', 'max:255'],
            'operational_manager_by_role' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id,is_active,1'],
            'items.*.sku' => ['required', 'string', 'max:100'],
            'items.*.item_name' => ['required', 'string', 'max:255'],
            'items.*.lot_code' => ['nullable', 'string', 'max:100'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.received_quantity' => ['required', 'integer', 'min:1'],
            'items.*.uom' => ['required', 'string', 'max:30'],
            'items.*.expired_date' => ['nullable', 'date'],
            'items.*.status' => ['nullable', 'string', 'max:50'],
            'items.*.remark' => ['nullable', 'string'],
        ]);

        $products = Product::query()
            ->whereIn('id', collect($data['items'])->pluck('product_id')->unique())
            ->with('brand:id,code')
            ->get()
            ->keyBy('id');

        if ($products->count() !== collect($data['items'])->pluck('product_id')->unique()->count()) {
            throw ValidationException::withMessages([
                'items' => 'Semua produk harus berasal dari master barang aktif.',
            ]);
        }

        if ($products->pluck('brand_id')->unique()->count() > 1) {
            throw ValidationException::withMessages([
                'items' => 'Satu MRS hanya dapat berisi produk dari satu brand.',
            ]);
        }

        $firstProduct = $products->get((int) $data['items'][0]['product_id']);
        $mrsNumber = $manualNumber
            ? DocumentNumber::normalize($data['mrs_number'])
            : StockReceiptSlip::suggestNumber(
                $firstProduct?->brand?->code,
                Carbon::parse($data['received_date']),
            );

        try {
            $slip = DB::transaction(function () use ($data, $request, $stock, $products, $mrsNumber): StockReceiptSlip {
                $slip = StockReceiptSlip::create([
                    ...collect($data)->except(['items', 'mrs_number_manual'])->all(),
                    'mrs_number' => $mrsNumber,
                    'recorded_by' => $request->user()->id,
                ]);

                foreach ($data['items'] as $itemData) {
                    $item = $slip->items()->create($itemData);
                    $product = $products->get($item->product_id);

                    if (! $product) {
                        throw new DomainException('Produk tidak ditemukan atau sudah tidak aktif.');
                    }

                    $stock->receive(
                        $product,
                        (int) $item->received_quantity,
                        ['reference' => $item],
                    );
                }

                return $slip;
            });
        } catch (DomainException $e) {
            return back()->withErrors(['items' => $e->getMessage()]);
        }

        AuditLogger::log('RECEIVE_STOCK_MRS', 'warehouse', $slip, null, $data);

        return redirect()->route('warehouse.receipts.show', $slip)
            ->with('success', 'MRS berhasil disimpan dan stok bertambah.');
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
