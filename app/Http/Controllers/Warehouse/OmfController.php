<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\OutMaterialForm;
use App\Models\OutMaterialFormItem;
use App\Models\Product;
use App\Services\AuditLogger;
use App\Services\LotStockService;
use App\Services\StockService;
use App\Support\DocumentNumber;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OmfController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'brand_id' => ['nullable', 'integer', Rule::exists('brands', 'id')],
        ]);
        $query = OutMaterialForm::query()
            ->with(['brand:id,name,code', 'recorder:id,name'])
            ->withCount('items')
            ->withSum('items', 'quantity');

        if ($search = $filters['search'] ?? '') {
            $query->where(function (Builder $scoped) use ($search) {
                $scoped->where('omf_number', 'like', "%{$search}%")
                    ->orWhere('recipient', 'like', "%{$search}%")
                    ->orWhere('department', 'like', "%{$search}%");
            });
        }

        $brandId = isset($filters['brand_id']) ? (int) $filters['brand_id'] : null;

        if ($brandId !== null) {
            $query->where('brand_id', $brandId);
        }

        $forms = $query->latest()->paginate(15)->withQueryString()->through(
            fn (OutMaterialForm $form) => [
                'id' => $form->id,
                'omf_number' => $form->omf_number,
                'issued_date' => $form->issued_date?->toDateString(),
                'brand' => $form->brand?->name,
                'department' => $form->department,
                'recipient' => $form->recipient,
                'items_count' => $form->items_count,
                'total_quantity' => (int) ($form->items_sum_quantity ?? 0),
                'recorded_by' => $form->recorder?->name,
            ],
        );

        return Inertia::render('Warehouse/Omf/Index', [
            'forms' => $forms,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'brand_id' => $brandId,
            ],
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
        return Inertia::render('Warehouse/Omf/Form', [
            'omfNumber' => OutMaterialForm::suggestNumber(),
            'today' => today()->toDateString(),
            'products' => $this->productOptions($lotStock),
            'brands' => $this->brandOptions(),
        ]);
    }

    public function store(Request $request, StockService $stock): RedirectResponse
    {
        $manualNumber = $request->exists('omf_number_manual')
            ? $request->boolean('omf_number_manual')
            : filled($request->input('omf_number'));

        if ($manualNumber && is_string($request->input('omf_number'))) {
            $request->merge([
                'omf_number' => DocumentNumber::normalize($request->input('omf_number')),
            ]);
        }

        $data = $request->validate([
            'omf_number' => [
                $manualNumber ? 'required' : 'nullable',
                'string',
                'max:100',
                ...($manualNumber ? [Rule::unique('out_material_forms', 'omf_number')] : []),
            ],
            'omf_number_manual' => ['sometimes', 'boolean'],
            'issued_date' => ['required', 'date'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'department' => ['nullable', 'string', 'max:150'],
            'recipient' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string'],
            'received_by_name' => ['nullable', 'string', 'max:255'],
            'received_by_role' => ['nullable', 'string', 'max:100'],
            'approved_by_name' => ['nullable', 'string', 'max:255'],
            'approved_by_role' => ['nullable', 'string', 'max:100'],
            'operational_manager_by_name' => ['nullable', 'string', 'max:255'],
            'operational_manager_by_role' => ['nullable', 'string', 'max:100'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('is_active', true)],
            'items.*.lot_code' => ['required', 'string', 'max:100'],
            'items.*.expired_date' => ['nullable', 'date'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.remark' => ['nullable', 'string'],
        ]);

        $products = Product::query()
            ->whereIn('id', collect($data['items'])->pluck('product_id')->unique())
            ->get()
            ->keyBy('id');

        $data['omf_number'] = $manualNumber
            ? DocumentNumber::normalize($data['omf_number'])
            : OutMaterialForm::suggestNumber(
                $data['brand_id'] ? Brand::whereKey($data['brand_id'])->value('code') : null,
                Carbon::parse($data['issued_date']),
            );

        try {
            $form = DB::transaction(function () use ($data, $request, $products, $stock): OutMaterialForm {
                $form = OutMaterialForm::create([
                    'omf_number' => $data['omf_number'],
                    'event_id' => null,
                    'brand_id' => $data['brand_id'] ?? null,
                    'issued_date' => $data['issued_date'],
                    'department' => $data['department'] ?? null,
                    'recipient' => $data['recipient'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'received_by_name' => $data['received_by_name'] ?? null,
                    'received_by_role' => $data['received_by_role'] ?? null,
                    'approved_by_name' => $data['approved_by_name'] ?? null,
                    'approved_by_role' => $data['approved_by_role'] ?? null,
                    'operational_manager_by_name' => $data['operational_manager_by_name'] ?? null,
                    'operational_manager_by_role' => $data['operational_manager_by_role'] ?? null,
                    'recorded_by' => $request->user()->id,
                ]);

                foreach ($data['items'] as $row) {
                    $product = $products->get($row['product_id']);

                    if (! $product) {
                        throw new DomainException('Produk tidak ditemukan atau sudah tidak aktif.');
                    }

                    $form->items()->create([
                        'product_id' => $product->id,
                        'sku' => $product->sku,
                        'item_name' => $product->name,
                        'lot_code' => trim($row['lot_code']),
                        'expired_date' => $row['expired_date'] ?? null,
                        'quantity' => $row['quantity'],
                        'uom' => $product->unit,
                        'remark' => $row['remark'] ?? null,
                    ]);
                }

                $form->load('items.product');
                $stock->issueOutMaterialForm($form);

                return $form;
            });
        } catch (DomainException $e) {
            return back()->withErrors(['items' => $e->getMessage()])->withInput();
        }

        AuditLogger::log('CREATE_OMF', 'warehouse', $form, null, $data);

        return redirect()->route('warehouse.omf.show', $form)
            ->with('success', 'Barang keluar (OMF) dicatat dan stok dipotong per lot.');
    }

    public function show(Request $request, OutMaterialForm $omf): Response
    {
        $omf->load([
            'brand:id,name,code',
            'recorder:id,name',
            'items' => fn ($query) => $query->orderBy('id'),
        ]);

        return Inertia::render('Warehouse/Omf/Show', [
            'omf' => [
                'id' => $omf->id,
                'omf_number' => $omf->omf_number,
                'issued_date' => $omf->issued_date?->toDateString(),
                'brand' => $omf->brand?->name,
                'brand_code' => $omf->brand?->code,
                'department' => $omf->department,
                'recipient' => $omf->recipient,
                'notes' => $omf->notes,
                'received_by_name' => $omf->received_by_name,
                'received_by_role' => $omf->received_by_role,
                'approved_by_name' => $omf->approved_by_name,
                'approved_by_role' => $omf->approved_by_role,
                'operational_manager_by_name' => $omf->operational_manager_by_name,
                'operational_manager_by_role' => $omf->operational_manager_by_role,
                'recorded_by' => $omf->recorder?->name,
                'recorded_at' => $omf->created_at?->format('d/m/Y H:i'),
                'items' => $omf->items->map(fn (OutMaterialFormItem $item) => [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'sku' => $item->sku,
                    'item_name' => $item->item_name,
                    'lot_code' => $item->lot_code,
                    'expired_date' => $item->expired_date?->toDateString(),
                    'quantity' => $item->quantity,
                    'uom' => $item->uom,
                    'remark' => $item->remark,
                ]),
            ],
            'can' => [
                'manage' => $request->user()->hasPermission('warehouse.manage'),
            ],
        ]);
    }

    public function updateNumber(Request $request, OutMaterialForm $omf): RedirectResponse
    {
        $data = $request->validate([
            'omf_number' => [
                'required',
                'string',
                'max:100',
                Rule::unique('out_material_forms', 'omf_number')->ignore($omf->id),
            ],
        ]);

        $old = $omf->omf_number;
        $omf->update(['omf_number' => DocumentNumber::normalize($data['omf_number'])]);

        AuditLogger::log('UPDATE_OMF_NUMBER', 'warehouse', $omf, ['omf_number' => $old], [
            'omf_number' => $omf->omf_number,
        ]);

        return back()->with('success', 'Nomor OMF diperbarui.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function productOptions(LotStockService $lotStock): array
    {
        return Product::query()
            ->where('is_active', true)
            ->with(['brand:id,name,code', 'inventory:id,product_id,qty_on_hand,qty_reserved'])
            ->orderBy('name')
            ->get()
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'unit' => $product->unit,
                'brand' => $product->brand?->name,
                'brand_code' => $product->brand?->code,
                'available' => $product->availableStock(),
                'lots' => $lotStock->lots($product),
            ])
            ->filter(fn (array $product) => $product['lots']->isNotEmpty())
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, array{id: int, name: string, code: ?string}>
     */
    private function brandOptions()
    {
        return Brand::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);
    }
}
