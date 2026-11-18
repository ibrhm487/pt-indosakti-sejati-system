<?php

namespace App\Http\Controllers\Warehouse;

use App\Enums\EventStatus;
use App\Enums\MaterialCategory;
use App\Enums\MrvStatus;
use App\Enums\RoleSlug;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Event;
use App\Models\Fjp;
use App\Models\Product;
use App\Models\Role;
use App\Models\StockRequest;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\CreateBulkMrvRequests;
use App\Services\LotStockService;
use App\Services\StockService;
use App\Support\DocumentNumber;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class MrvController extends Controller
{
    private const BULK_SAMPLING_PRODUCT_SKU = '12583797';

    private const BULK_SUPPORTING_PRODUCT_SKU = '12583798';

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(MrvStatus::class)],
            'brand_id' => ['nullable', 'integer', Rule::exists('brands', 'id')],
        ]);
        $query = StockRequest::query()->with([
            'event:id,event_name,event_date,brand_id',
            'event.brand:id,name',
            'requester:id,name',
            'items.product:id,brand_id',
            'items.product.brand:id,name',
        ]);
        $this->scopeRequests($query, $request->user());

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        $brandId = isset($filters['brand_id']) ? (int) $filters['brand_id'] : null;

        if ($brandId !== null) {
            $brandName = Brand::query()->whereKey($brandId)->value('name');
            $query->where(function (Builder $scoped) use ($brandId, $brandName) {
                $scoped->where('brand_name', $brandName)
                    ->orWhereHas('event', fn (Builder $event) => $event->where('brand_id', $brandId))
                    ->orWhereHas('items.product', fn (Builder $product) => $product->where('brand_id', $brandId));
            });
        }

        $requests = $query->latest()->paginate(15)->withQueryString()->through(
            fn (StockRequest $r) => [
                'id' => $r->id,
                'mrv_number' => $r->mrv_number,
                'brand' => $r->brand_name ?? $r->event?->brand?->name ?? $r->items->first()?->product?->brand?->name,
                'activity_type' => $r->activity_type ?? $r->event?->event_name,
                'activity_date' => ($r->activity_date ?? $r->event?->event_date)?->toDateString(),
                'period' => $r->week_label
                    ? $this->period($r->activity_date ?? $r->event?->event_date ?? $r->created_at)['month'].' · '.$r->week_label
                    : $this->period($r->activity_date ?? $r->event?->event_date ?? $r->created_at)['label'],
                'requester' => $r->requester?->name,
                'status' => $r->status->value,
                'status_label' => $r->status->label(),
                'created_at' => $r->created_at?->toIso8601String(),
            ],
        );

        return Inertia::render('Warehouse/Mrv/Index', [
            'requests' => $requests,
            'filters' => [
                'status' => $filters['status'] ?? '',
                'brand_id' => $brandId,
            ],
            'brands' => Brand::query()
                ->whereIn(
                    'id',
                    Product::query()->select('brand_id')->whereNotNull('brand_id')->distinct(),
                )
                ->orderBy('name')
                ->get(['id', 'name']),
            'statuses' => collect(MrvStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
        ]);
    }

    public function create(Request $request, LotStockService $lotStock): Response
    {
        $today = today();
        $events = $this->availableEvents($request->user())
            ->whereNotNull('fjp_id')
            ->where('status', '!=', EventStatus::CANCELLED->value)
            ->with([
                'brand:id,name,code',
                'venue:id,name,city',
                'teamMembers.user:id,name',
            ])
            ->orderBy('event_date')
            ->get(['id', 'event_name', 'event_date', 'brand_id', 'venue_id', 'fjp_id'])
            ->map(fn (Event $event) => [
                'id' => $event->id,
                'name' => $event->event_name,
                'date' => $event->event_date?->toDateString(),
                'brand' => $event->brand?->name,
                'brand_code' => $event->brand?->code,
                'venue' => $event->venue?->name,
                'city' => $event->venue?->city,
                'members' => $event->teamMembers
                    ->filter(fn ($member) => $member->user !== null)
                    ->map(fn ($member) => [
                        'id' => $member->user_id,
                        'name' => $member->user->name,
                    ])
                    ->values(),
            ]);
        $requestedEventId = $request->integer('event');

        return Inertia::render('Warehouse/Mrv/Form', [
            'activityDate' => $today->toDateString(),
            'events' => $events,
            'initialEventId' => $events->contains('id', $requestedEventId) ? $requestedEventId : null,
            'defaultMrvNumber' => StockRequest::generateNumber(),
            'defaultWeek' => 'Minggu '.$today->isoWeek,
            'products' => Product::where('is_active', true)
                ->with(['inventory:id,product_id,qty_on_hand,qty_reserved', 'brand:id,code'])
                ->orderBy('name')
                ->get(['id', 'name', 'sku', 'unit', 'brand_id'])
                ->map(fn (Product $p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'sku' => $p->sku,
                    'unit' => $p->unit,
                    'brand' => $p->brand?->name,
                    'brand_code' => $p->brand?->code,
                    'available' => $p->availableStock(),
                    'lots' => $this->mrvLots($p, $lotStock),
                ]),
            'materialCategories' => collect(MaterialCategory::cases())
                ->map(fn (MaterialCategory $category) => [
                    'value' => $category->value,
                    'label' => $category->label(),
                ]),
            'teamMembers' => User::query()
                ->where('is_active', true)
                ->whereHas('roles', fn (Builder $query) => $query->where('slug', RoleSlug::TEAM->value))
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function createBulk(Request $request, LotStockService $lotStock): Response
    {
        $products = Product::query()
            ->where('is_active', true)
            ->with('brand:id,code')
            ->whereIn('sku', [self::BULK_SAMPLING_PRODUCT_SKU, self::BULK_SUPPORTING_PRODUCT_SKU])
            ->get(['id', 'name', 'sku', 'unit', 'brand_id'])
            ->keyBy('sku');

        return Inertia::render('Warehouse/Mrv/BulkForm', [
            'activityDate' => today()->toDateString(),
            'teamGroups' => $this->availableMiloTeamGroups($request->user())
                ->values()
                ->map(fn (array $group, int $index) => [
                    'id' => $group['id'],
                    'sequence' => $index,
                    'date' => $group['date'],
                    'total_students' => $group['total_students'],
                    'ready' => $group['ready'],
                    'schools' => $group['schools']->values(),
                    'members' => $group['members']->values(),
                ])
                ->values(),
            'samplingProduct' => $this->bulkMrvProductData(
                $products->get(self::BULK_SAMPLING_PRODUCT_SKU),
                $lotStock,
            ),
            'supportingProduct' => $this->bulkMrvProductData(
                $products->get(self::BULK_SUPPORTING_PRODUCT_SKU),
                $lotStock,
            ),
        ]);
    }

    public function storeBulk(
        Request $request,
        LotStockService $lotStock,
        CreateBulkMrvRequests $createBulkMrvRequests,
    ): RedirectResponse {
        $data = $request->validate([
            'activity_date' => ['required', 'date'],
            'team_group_ids' => ['required', 'array', 'min:1'],
            'team_group_ids.*' => ['required', 'string', 'distinct'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $availableGroups = $this->availableMiloTeamGroups($request->user())->keyBy('id');
        $teamGroupIds = $data['team_group_ids'];
        $teamGroups = $availableGroups
            ->filter(fn (array $group): bool => in_array($group['id'], $teamGroupIds, true))
            ->values();

        if ($teamGroups->count() !== count($teamGroupIds)) {
            throw ValidationException::withMessages([
                'team_group_ids' => 'Satu atau lebih tim tidak lagi tersedia untuk MRV.',
            ]);
        }

        foreach ($teamGroups as $teamGroup) {
            if ($teamGroup['date'] !== $data['activity_date']) {
                throw ValidationException::withMessages([
                    'team_group_ids' => 'Semua tim yang dipilih harus memiliki tanggal FJP yang sama.',
                ]);
            }

            if (! $teamGroup['ready']) {
                throw ValidationException::withMessages([
                    'team_group_ids' => 'Lengkapi roster tim dan jumlah siswa FJP sebelum membuat MRV.',
                ]);
            }
        }

        $products = Product::query()
            ->where('is_active', true)
            ->with('brand:id,code')
            ->whereIn('sku', [self::BULK_SAMPLING_PRODUCT_SKU, self::BULK_SUPPORTING_PRODUCT_SKU])
            ->get(['id', 'name', 'sku', 'unit', 'brand_id'])
            ->keyBy('sku');

        $samplingProduct = $products->get(self::BULK_SAMPLING_PRODUCT_SKU);
        $supportingProduct = $products->get(self::BULK_SUPPORTING_PRODUCT_SKU);

        if (! $samplingProduct || ! $supportingProduct) {
            throw ValidationException::withMessages([
                'team_group_ids' => 'Produk MILO 3IN1 ACTIV-GO atau MILO CUP 4OZ tidak tersedia di master produk aktif.',
            ]);
        }

        $lotAllocations = [];
        foreach ([
            'sampling' => $samplingProduct,
            'supporting' => $supportingProduct,
        ] as $productType => $product) {
            $lots = $this->mrvLots($product, $lotStock);
            $availableQuantity = (int) $lots->sum('quantity');
            $requiredQuantity = (int) $teamGroups->sum('total_students');

            if ($availableQuantity < $requiredQuantity) {
                throw ValidationException::withMessages([
                    'team_group_ids' => "Stok lot {$product->name} tidak cukup. Dibutuhkan {$requiredQuantity} {$product->unit}, tersedia {$availableQuantity}.",
                ]);
            }

            foreach ($this->allocateMrvLotsByTeam($teamGroups, $lots, $product) as $teamGroupId => $allocations) {
                $lotAllocations[$teamGroupId][$productType] = $allocations;
            }
        }

        $createdRequests = $createBulkMrvRequests->handle(
            $request->user(),
            $data['activity_date'],
            $teamGroups->values(),
            $samplingProduct,
            $supportingProduct,
            $lotAllocations,
            $this->cleanString($data['notes'] ?? null),
        );

        foreach ($createdRequests as $createdRequest) {
            AuditLogger::log('CREATE_MRV_BULK', 'warehouse', $createdRequest, null, [
                'activity_date' => $data['activity_date'],
                'team_group_id' => $createdRequest->team_no,
                'school_names' => $createdRequest->school_names,
            ]);
        }

        return redirect()->route('warehouse.mrv.index')->with(
            'success',
            $createdRequests->count().' MRV tim berhasil diajukan untuk tanggal '.$data['activity_date'].'.',
        );
    }

    /**
     * @return array{id: int, name: string, sku: string, unit: string, lots: Collection<int, array{lot_code: string, quantity: int, expired_date: ?string, is_expired: bool}>, active_quantity: int, expired_quantity: int}|null
     */
    private function bulkMrvProductData(?Product $product, LotStockService $lotStock): ?array
    {
        if (! $product) {
            return null;
        }

        $lots = $this->mrvLots($product, $lotStock);

        return [
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'unit' => $product->unit,
            'lots' => $lots,
            'active_quantity' => (int) $lots->reject(fn (array $lot): bool => $lot['is_expired'])->sum('quantity'),
            'expired_quantity' => (int) $lots->filter(fn (array $lot): bool => $lot['is_expired'])->sum('quantity'),
        ];
    }

    /**
     * Semua lot dengan saldo > 0, termasuk lot kedaluwarsa (ditandai is_expired).
     * Lot aktif diurutkan lebih dulu berdasarkan ED terdekat, lalu lot kedaluwarsa.
     *
     * @return Collection<int, array{lot_code: string, quantity: int, expired_date: ?string, is_expired: bool}>
     */
    private function mrvLots(Product $product, LotStockService $lotStock): Collection
    {
        $today = today()->toDateString();

        return $lotStock->lots($product)
            ->map(function (array $lot) use ($today): array {
                $lot['is_expired'] = $lot['expired_date'] !== null && $lot['expired_date'] < $today;

                return $lot;
            })
            ->sortBy(fn (array $lot): string => sprintf(
                '%d|%s',
                $lot['is_expired'] ? 1 : 0,
                $lot['expired_date'] ?? '9999-12-31',
            ))
            ->values();
    }

    /**
     * @param  Collection<int, array{id: string, total_students: int}>  $teamGroups
     * @param  Collection<int, array{lot_code: string, quantity: int, expired_date: ?string, is_expired: bool}>  $lots
     * @return array<string, list<array{lot_no: string, quantity: int}>>
     */
    private function allocateMrvLotsByTeam(
        Collection $teamGroups,
        Collection $lots,
        Product $product,
    ): array {
        $remainingByLot = $lots
            ->mapWithKeys(fn (array $lot): array => [$lot['lot_code'] => $lot['quantity']])
            ->all();
        $allocationsByTeam = [];

        foreach ($teamGroups as $teamGroup) {
            $remainingQuantity = (int) $teamGroup['total_students'];
            $teamAllocations = [];

            foreach ($remainingByLot as $lotCode => $availableQuantity) {
                if ($remainingQuantity === 0) {
                    break;
                }

                if ($availableQuantity === 0) {
                    continue;
                }

                $quantity = min($remainingQuantity, $availableQuantity);
                $teamAllocations[] = [
                    'lot_no' => $lotCode,
                    'quantity' => $quantity,
                ];
                $remainingByLot[$lotCode] -= $quantity;
                $remainingQuantity -= $quantity;
            }

            if ($remainingQuantity > 0) {
                throw ValidationException::withMessages([
                    'team_group_ids' => "Stok lot aktif {$product->name} berubah saat pengajuan. Silakan muat ulang sebelum mencoba kembali.",
                ]);
            }

            $allocationsByTeam[$teamGroup['id']] = $teamAllocations;
        }

        return $allocationsByTeam;
    }

    public function store(Request $request): RedirectResponse
    {
        $manualNumber = $request->exists('mrv_number_manual')
            ? $request->boolean('mrv_number_manual')
            : filled($request->input('mrv_number'));

        if ($manualNumber && is_string($request->input('mrv_number'))) {
            $request->merge([
                'mrv_number' => DocumentNumber::normalize($request->input('mrv_number')),
            ]);
        }

        $data = $request->validate([
            'event_id' => ['nullable', 'integer', Rule::exists('events', 'id')],
            'mrv_number' => [
                $manualNumber ? 'required' : 'nullable',
                'string',
                'max:100',
                ...($manualNumber ? [Rule::unique('stock_requests', 'mrv_number')] : []),
            ],
            'mrv_number_manual' => ['sometimes', 'boolean'],
            'activity_type' => ['required', 'string', 'max:100'],
            'activity_date' => ['required', 'date'],
            'week_label' => ['nullable', 'string', 'max:50'],
            'shift' => ['nullable', 'string', 'max:50'],
            'jug_quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'brand_name' => ['nullable', 'string', 'max:100'],
            'venue_name' => ['nullable', 'string', 'max:255'],
            'venue_names' => ['nullable', 'array', 'max:10'],
            'venue_names.*' => ['required', 'string', 'max:255'],
            'team_member_id' => [
                'nullable',
                Rule::exists('users', 'id')->where('is_active', true),
                Rule::exists('role_user', 'user_id')->where(
                    'role_id',
                    Role::query()->where('slug', RoleSlug::TEAM->value)->value('id'),
                ),
            ],
            'team_member_name' => ['nullable', 'string', 'max:255'],
            'jug_details' => ['nullable', 'array', 'min:1'],
            'jug_details.*.jug_number' => ['required', 'string', 'max:50'],
            'jug_details.*.temperature' => ['nullable', 'numeric', 'between:-50,100'],
            'sampling_target_wet' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'sampling_target_dry' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'team_no' => ['nullable', 'string', 'max:100'],
            'material_category' => ['nullable', 'in:'.implode(',', array_map(fn (MaterialCategory $category) => $category->value, MaterialCategory::cases()))],
            'material_categories' => ['nullable', 'array', 'min:1'],
            'material_categories.*' => ['required', 'distinct', 'in:'.implode(',', array_map(fn (MaterialCategory $category) => $category->value, MaterialCategory::cases()))],
            'pickup_time' => ['nullable', 'date_format:H:i'],
            'notes' => ['nullable', 'string'],
            'material_prepared_by' => ['nullable', 'string', 'max:255'],
            'material_received_by' => ['nullable', 'string', 'max:255'],
            'prepared_by_name' => ['nullable', 'string', 'max:255'],
            'approved_by_name' => ['nullable', 'string', 'max:255'],
            'wet_received_by' => ['nullable', 'string', 'max:255'],
            'dry_received_by' => ['nullable', 'string', 'max:255'],
            'supporting_items' => ['nullable', 'array'],
            'supporting_items.*.item' => ['nullable', 'string', 'max:255'],
            'supporting_items.*.product_id' => [
                'nullable',
                'integer',
                Rule::exists('products', 'id')->where('is_active', true),
            ],
            'supporting_items.*.lot_no' => ['nullable', 'string', 'max:100'],
            'supporting_items.*.qty' => ['nullable', 'integer', 'min:1'],
            'supporting_items.*.uom' => ['nullable', 'string', 'max:50'],
            'supporting_items.*.remarks' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => [
                'required',
                'distinct',
                Rule::exists('products', 'id')->where('is_active', true),
            ],
            'items.*.sku' => [
                'nullable',
                'string',
                'max:100',
                Rule::exists('products', 'sku')->where('is_active', true),
            ],
            'items.*.quantity_requested' => ['required', 'integer', 'min:1'],
            'items.*.lot_no' => ['nullable', 'string', 'max:100'],
            'items.*.cold' => ['nullable', 'numeric', 'min:0'],
            'items.*.lbs' => ['nullable', 'numeric', 'min:0'],
            'items.*.packing' => ['nullable', 'string', 'max:100'],
            'items.*.on_the_spot' => ['nullable', 'numeric', 'min:0'],
            'items.*.d1' => ['nullable', 'numeric', 'min:0'],
            'items.*.d2' => ['nullable', 'numeric', 'min:0'],
            'items.*.cups' => ['nullable', 'numeric', 'min:0'],
            'items.*.spoon' => ['nullable', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ]);

        if (isset($data['jug_details'])) {
            $data['jug_quantity'] = count($data['jug_details']);
        }

        if (isset($data['material_categories'])) {
            $data['material_category'] = $data['material_categories'][0];
        }

        $event = null;

        if (filled($data['event_id'] ?? null)) {
            $event = $this->availableEvents($request->user())
                ->whereKey($data['event_id'])
                ->whereNotNull('fjp_id')
                ->with(['brand:id,name,code', 'venue:id,name', 'teamMembers.user:id,name'])
                ->first();

            if (! $event) {
                throw ValidationException::withMessages([
                    'event_id' => 'FJP sekolah tidak ditemukan atau tidak dapat diakses.',
                ]);
            }

            if (! filled($data['team_member_id'] ?? null)) {
                throw ValidationException::withMessages([
                    'team_member_id' => 'Pilih anggota tim dari FJP yang ditautkan.',
                ]);
            }

            if (! $event->teamMembers->contains('user_id', (int) $data['team_member_id'])) {
                throw ValidationException::withMessages([
                    'team_member_id' => 'Anggota tim harus berasal dari tim yang ditugaskan pada FJP ini.',
                ]);
            }

            $data['activity_type'] = 'Sampling';
            $data['activity_date'] = $event->event_date?->toDateString() ?? $data['activity_date'];
            $data['brand_name'] = $event->brand?->name ?? $data['brand_name'] ?? null;
            $data['venue_name'] = $event->venue?->name ?? $data['venue_name'] ?? null;
            $data['venue_names'] = array_values(array_filter([$data['venue_name']]));
            $data['school_names'] = array_values(array_filter([$data['venue_name']]));
            $data['team_member_name'] = $event->teamMembers
                ->firstWhere('user_id', (int) $data['team_member_id'])
                ?->user
                ?->name;
        }

        $resolvedProductIds = [];
        $resolvedProducts = [];
        foreach ($data['items'] as $index => &$item) {
            $product = filled($item['sku'] ?? null)
                ? Product::query()->with('brand:id,name,code')->where('sku', trim($item['sku']))->where('is_active', true)->first()
                : Product::query()->with('brand:id,name,code')->whereKey($item['product_id'])->where('is_active', true)->first();

            if (! $product) {
                throw ValidationException::withMessages([
                    "items.{$index}.sku" => 'SKU tidak ditemukan pada master barang aktif.',
                ]);
            }

            if (in_array($product->id, $resolvedProductIds, true)) {
                throw ValidationException::withMessages([
                    'items' => 'Barang yang sama tidak dapat dimasukkan lebih dari satu kali.',
                ]);
            }

            $item['product_id'] = $product->id;
            $item['sku'] = $product->sku;
            $resolvedProductIds[] = $product->id;
            $resolvedProducts[$product->id] = $product;
        }
        unset($item);

        $supportingRows = collect($data['supporting_items'] ?? []);
        foreach ($supportingRows as $index => $row) {
            $hasSupportingData = filled($row['product_id'] ?? null)
                || filled($row['item'] ?? null)
                || filled($row['qty'] ?? null)
                || filled($row['remarks'] ?? null);

            if ($hasSupportingData && ! filled($row['product_id'] ?? null)) {
                throw ValidationException::withMessages([
                    "supporting_items.{$index}.product_id" => 'Pilih produk aktif dari master barang agar stok dapat terintegrasi.',
                ]);
            }

            if (filled($row['product_id'] ?? null) && ! filled($row['qty'] ?? null)) {
                throw ValidationException::withMessages([
                    "supporting_items.{$index}.qty" => 'Jumlah barang pendukung wajib diisi.',
                ]);
            }
        }

        $supportingProductIds = $supportingRows
            ->pluck('product_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
        $allProductIds = array_merge($resolvedProductIds, $supportingProductIds);

        if (count($allProductIds) !== count(array_unique($allProductIds))) {
            throw ValidationException::withMessages([
                'supporting_items' => 'Produk yang sama tidak dapat dimasukkan sebagai material dan supporting sekaligus.',
            ]);
        }

        $supportingProducts = Product::query()
            ->whereIn('id', $supportingProductIds)
            ->with('brand:id,name,code')
            ->get()
            ->keyBy('id');
        $supportingItems = $this->supportingItems($supportingRows->all(), $supportingProducts);
        $firstProduct = reset($resolvedProducts) ?: null;
        $brandCode = $event?->brand?->code;

        if (! $brandCode && filled($data['brand_name'] ?? null)) {
            $brandCode = Brand::query()
                ->where('name', trim($data['brand_name']))
                ->value('code');
        }

        $brandCode ??= $firstProduct?->brand?->code;

        if (! filled($data['brand_name'] ?? null)) {
            $data['brand_name'] = $event?->brand?->name ?? $firstProduct?->brand?->name;
        }

        $data['mrv_number'] = $manualNumber
            ? DocumentNumber::normalize($data['mrv_number'])
            : StockRequest::suggestNumber(
                $brandCode,
                Carbon::parse($data['activity_date']),
            );
        $data['week_label'] = filled($data['week_label'] ?? null)
            ? trim($data['week_label'])
            : 'Minggu '.Carbon::parse($data['activity_date'])->isoWeek;

        $miloSachetProductIds = Product::query()
            ->whereIn('sku', ['MILO-ACTIV-GO', 'MILO-SACHET'])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        foreach ($data['items'] as &$item) {
            if (in_array((int) $item['product_id'], $miloSachetProductIds, true)) {
                $sachetQuantity = $data['jug_quantity'] * 250;
                $item['quantity_requested'] = $sachetQuantity;
                $item['cups'] = $sachetQuantity;
            }
        }
        unset($item);

        $venueNames = collect([$data['venue_name'] ?? null, ...($data['venue_names'] ?? [])])
            ->map(fn ($venue) => $this->cleanString($venue))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $schoolNames = $event
            ? $data['school_names']
            : ($venueNames ?: null);

        $mrv = DB::transaction(function () use ($data, $request, $supportingItems, $venueNames, $schoolNames, $event) {
            $mrv = StockRequest::create([
                'mrv_number' => $data['mrv_number'],
                'event_id' => $event?->id,
                'activity_type' => trim($data['activity_type']),
                'activity_date' => $data['activity_date'],
                'week_label' => $data['week_label'],
                'shift' => $this->cleanString($data['shift'] ?? null),
                'jug_quantity' => $data['jug_quantity'],
                'brand_name' => $this->cleanString($data['brand_name'] ?? null),
                'venue_name' => $this->cleanString($data['venue_name'] ?? null) ?? ($venueNames[0] ?? null),
                'venue_names' => $event ? $data['venue_names'] : ($venueNames ?: null),
                'school_names' => $schoolNames,
                'team_member_id' => $data['team_member_id'] ?? null,
                'team_member_name' => $this->cleanString($data['team_member_name'] ?? null),
                'jug_details' => $data['jug_details'] ?? null,
                'sampling_target_wet' => $data['sampling_target_wet'] ?? null,
                'sampling_target_dry' => $data['sampling_target_dry'] ?? null,
                'team_no' => $data['team_no'] ?? null,
                'type' => 'issue',
                'status' => MrvStatus::PENDING,
                'material_category' => $data['material_category'] ?? null,
                'material_categories' => $data['material_categories']
                    ?? (isset($data['material_category']) ? [$data['material_category']] : null),
                'notes' => $data['notes'] ?? null,
                'pickup_time' => $data['pickup_time'] ?? null,
                'supporting_items' => $supportingItems ?: null,
                'material_prepared_by' => $data['material_prepared_by'] ?? null,
                'material_received_by' => $data['material_received_by'] ?? null,
                'prepared_by_name' => $this->cleanString($data['prepared_by_name'] ?? null),
                'approved_by_name' => $this->cleanString($data['approved_by_name'] ?? null),
                'wet_received_by' => $this->cleanString($data['wet_received_by'] ?? null),
                'dry_received_by' => $this->cleanString($data['dry_received_by'] ?? null),
                'requested_by' => $request->user()->id,
            ]);

            foreach ($data['items'] as $item) {
                $mrv->items()->create([
                    'product_id' => $item['product_id'],
                    'lot_no' => $this->cleanString($item['lot_no'] ?? null),
                    'quantity_requested' => $item['quantity_requested'],
                    'cold' => $this->numberOrNull($item['cold'] ?? null),
                    'lbs' => $this->numberOrNull($item['lbs'] ?? null),
                    'packing' => $this->cleanString($item['packing'] ?? null),
                    'on_the_spot' => $this->numberOrNull($item['on_the_spot'] ?? null),
                    'd1' => $this->numberOrNull($item['d1'] ?? null),
                    'd2' => $this->numberOrNull($item['d2'] ?? null),
                    'cups' => $this->numberOrNull($item['cups'] ?? null),
                    'spoon' => $this->numberOrNull($item['spoon'] ?? null),
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            foreach ($supportingItems as $item) {
                $mrv->items()->create([
                    'product_id' => $item['product_id'],
                    'lot_no' => $item['lot_no'],
                    'quantity_requested' => $item['qty'],
                    'notes' => $item['remarks'],
                ]);
            }

            return $mrv;
        });

        AuditLogger::log('CREATE_MRV', 'warehouse', $mrv, null, $data);

        return redirect()->route('warehouse.mrv.show', $mrv)->with('success', 'Permintaan stok (MRV) dibuat.');
    }

    public function show(Request $request, StockRequest $mrv): Response
    {
        $mrv->load('event');

        abort_unless($this->canAccessRequest($request->user(), $mrv), 404);

        $mrv->load([
            'event:id,event_name,event_code,event_date,brand_id,venue_id,pic_id,fjp_id',
            'event.fjp:id,fjp_number',
            'event.venue:id,name',
            'event.brand:id,name',
            'event.pic:id,name',
            'requester:id,name',
            'teamMember:id,name',
            'approver:id,name',
            'items.product:id,name,sku,unit',
            'returns',
        ]);

        $activityDate = $mrv->activity_date ?? $mrv->event?->event_date;
        $period = $this->period($activityDate ?? $mrv->created_at);
        $materialCategories = $mrv->material_categories
            ?? array_values(array_filter([$mrv->material_category]));

        return Inertia::render('Warehouse/Mrv/Show', [
            'mrv' => [
                'id' => $mrv->id,
                'mrv_number' => $mrv->mrv_number,
                'event' => $mrv->event?->event_name,
                'event_id' => $mrv->event_id,
                'activity_type' => $mrv->activity_type ?? $mrv->event?->event_name,
                'activity_date' => $activityDate?->toDateString(),
                'jug_quantity' => $mrv->jug_quantity,
                'shift' => $mrv->shift,
                'event_code' => $mrv->event?->event_code,
                'event_date' => $activityDate?->toDateString(),
                'venue' => $mrv->venue_name ?? $mrv->event?->venue?->name,
                'venues' => $mrv->venue_names ?: ($mrv->venue_name ? [$mrv->venue_name] : []),
                'brand' => $mrv->brand_name ?? $mrv->event?->brand?->name,
                'pic' => $mrv->event?->pic?->name,
                'team_member' => $mrv->team_member_name ?: $mrv->teamMember?->name,
                'team_no' => $mrv->team_no ?? $mrv->event?->fjp?->fjp_number,
                'jug_details' => $mrv->jug_details ?? [],
                'sampling_target_wet' => $mrv->sampling_target_wet,
                'sampling_target_dry' => $mrv->sampling_target_dry,
                'material_category' => $mrv->material_category,
                'material_categories' => $materialCategories,
                'material_category_label' => collect($materialCategories)
                    ->map(fn (string $category) => MaterialCategory::tryFrom($category)?->label() ?? $category)
                    ->join(', ') ?: null,
                'pickup_time' => $mrv->pickup_time ? substr((string) $mrv->pickup_time, 0, 5) : null,
                'supporting_items' => $mrv->supporting_items ?? [],
                'material_prepared_by' => $mrv->material_prepared_by,
                'material_received_by' => $mrv->material_received_by,
                'prepared_by_name' => $mrv->prepared_by_name,
                'approved_by_name' => $mrv->approved_by_name,
                'wet_received_by' => $mrv->wet_received_by,
                'dry_received_by' => $mrv->dry_received_by,
                'period_month' => $period['month'],
                'period_week' => $period['week'],
                'period_week_label' => $mrv->week_label ?? 'Minggu '.$period['week'],
                'period_label' => $period['label'],
                'requester' => $mrv->requester?->name,
                'approver' => $mrv->approver?->name,
                'status' => $mrv->status->value,
                'status_label' => $mrv->status->label(),
                'notes' => $mrv->notes,
                'rejection_reason' => $mrv->rejection_reason,
                'recipient' => $mrv->recipient,
                'photo_url' => $mrv->photo_path ? route('warehouse.mrv.photo', $mrv->id) : null,
                'created_at' => $mrv->created_at?->toIso8601String(),
                'approved_at' => $mrv->approved_at?->toIso8601String(),
                'issued_at' => $mrv->issued_at?->toIso8601String(),
            ],
            'items' => $mrv->items
                ->reject(fn ($item) => collect($mrv->supporting_items ?? [])
                    ->contains(fn ($supporting) => is_array($supporting)
                        && (int) ($supporting['product_id'] ?? 0) === (int) $item->product_id))
                ->map(fn ($item) => [
                    'id' => $item->id,
                    'product' => $item->product?->name,
                    'sku' => $item->product?->sku,
                    'unit' => $item->product?->unit,
                    'lot_no' => $item->lot_no,
                    'quantity_requested' => $item->quantity_requested,
                    'cold' => $item->cold,
                    'lbs' => $item->lbs,
                    'packing' => $item->packing,
                    'on_the_spot' => $item->on_the_spot,
                    'd1' => $item->d1,
                    'd2' => $item->d2,
                    'cups' => $item->cups,
                    'spoon' => $item->spoon,
                    'notes' => $item->notes,
                    'quantity_issued' => $item->quantity_issued,
                    'quantity_returned' => $item->quantity_returned,
                    'quantity_damaged' => $item->quantity_damaged,
                ]),
            'returns' => $mrv->returns->map(fn ($r) => [
                'id' => $r->id,
                'return_number' => $r->return_number,
                'status' => $r->status,
            ]),
            'can' => [
                'approve' => $request->user()->hasPermission('warehouse.approve'),
                'issue' => $request->user()->hasPermission('warehouse.issue'),
                'return' => $request->user()->hasPermission('warehouse.return'),
                'manage' => $request->user()->hasPermission('warehouse.manage'),
            ],
        ]);
    }

    public function updateNumber(Request $request, StockRequest $mrv): RedirectResponse
    {
        $data = $request->validate([
            'mrv_number' => [
                'required',
                'string',
                'max:100',
                Rule::unique('stock_requests', 'mrv_number')->ignore($mrv->id),
            ],
        ]);

        $old = $mrv->mrv_number;
        $mrv->update(['mrv_number' => DocumentNumber::normalize($data['mrv_number'])]);

        AuditLogger::log('UPDATE_MRV_NUMBER', 'warehouse', $mrv, ['mrv_number' => $old], [
            'mrv_number' => $mrv->mrv_number,
        ]);

        return back()->with('success', 'Nomor MRV diperbarui.');
    }

    public function approve(Request $request, StockRequest $mrv, StockService $stock): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('warehouse.approve'), 403);

        $old = ['status' => $mrv->status->value];

        try {
            $stock->approveAndIssue($mrv, $request->user()->id);
        } catch (DomainException $e) {
            $key = str_starts_with($e->getMessage(), 'Stok tidak cukup') ? 'stock' : 'status';

            return back()->withErrors([$key => $e->getMessage()]);
        }

        AuditLogger::log('APPROVE_MRV', 'warehouse', $mrv, $old, [
            'status' => MrvStatus::ISSUED->value,
            'stock_deducted' => true,
        ]);

        return back()->with('success', 'MRV disetujui dan stok dikeluarkan otomatis.');
    }

    public function reject(Request $request, StockRequest $mrv): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('warehouse.approve'), 403);

        if ($mrv->status !== MrvStatus::PENDING) {
            return back()->withErrors(['status' => 'Hanya MRV berstatus menunggu yang bisa ditolak.']);
        }

        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']]);

        $old = ['status' => $mrv->status->value];

        $updated = DB::table('stock_requests')
            ->where('id', $mrv->id)
            ->where('status', MrvStatus::PENDING->value)
            ->update([
                'status' => MrvStatus::REJECTED->value,
                'rejection_reason' => $data['reason'],
                'updated_at' => now(),
            ]);

        if ($updated === 0) {
            return back()->withErrors(['status' => 'Hanya MRV berstatus menunggu yang bisa ditolak.']);
        }

        AuditLogger::log('REJECT_MRV', 'warehouse', $mrv, $old, ['status' => 'rejected', 'reason' => $data['reason']]);

        return back()->with('success', 'MRV ditolak.');
    }

    public function issue(Request $request, StockRequest $mrv, StockService $stock): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('warehouse.issue'), 403);

        $data = $request->validate([
            'recipient' => ['nullable', 'string', 'max:255'],
            'photo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:4096'],
        ]);

        if ($mrv->status !== MrvStatus::APPROVED) {
            return back()->withErrors(['status' => 'MRV harus disetujui sebelum dikeluarkan.']);
        }

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('warehouse/issue');
        }

        try {
            $stock->issue($mrv, $photoPath, $data['recipient'] ?? null);
        } catch (DomainException $e) {
            if ($photoPath) {
                Storage::disk('local')->delete($photoPath);
            }

            return back()->withErrors(['stock' => $e->getMessage()]);
        }

        AuditLogger::log('ISSUE_MRV', 'warehouse', $mrv, null, [
            'status' => 'issued',
            'recipient' => $data['recipient'] ?? null,
            'photo' => $photoPath,
        ]);

        return back()->with('success', 'Stok dikeluarkan. Pengurangan stok terjadi tepat satu kali.');
    }

    /**
     * @return Builder<Event>
     */
    private function availableEvents(User $user): Builder
    {
        $query = Event::query();

        return match ($user->activeRoleSlug()) {
            'pic' => $query->where('pic_id', $user->id),
            'team' => $query->whereHas(
                'teamMembers',
                fn (Builder $members) => $members->where('user_id', $user->id),
            ),
            default => $query,
        };
    }

    /**
     * @return Collection<string, array{
     *     id: string,
     *     date: string|null,
     *     total_students: int,
     *     ready: bool,
     *     schools: Collection<int, array{event_id: int, name: string, venue: string|null, student_count: int|null}>,
     *     events: Collection<int, Event>,
     *     members: Collection<int, array{user_id: int, name: string}>
     * }>
     */
    private function availableMiloTeamGroups(User $user): Collection
    {
        $events = $this->availableEvents($user)
            ->whereNotNull('fjp_id')
            ->where('status', '!=', EventStatus::CANCELLED->value)
            ->with([
                'fjp:id,status,school_name,jumlah_siswa_sekolah',
                'brand:id,name,slug,code',
                'venue:id,name,city',
                'teamMembers.user:id,name',
            ])
            ->orderBy('event_date')
            ->orderBy('id')
            ->get([
                'id',
                'event_name',
                'event_date',
                'brand_id',
                'venue_id',
                'fjp_id',
                'team_group_id',
            ])
            ->filter(fn (Event $event): bool => $event->fjp?->status === Fjp::STATUS_CONFIRM
                && $event->brand?->slug === 'milo');

        return $events
            ->groupBy(fn (Event $event): string => $event->team_group_id
                ? (string) $event->team_group_id
                : 'event-'.$event->id)
            ->map(function (Collection $groupEvents, string $teamGroupId): array {
                $groupEvents = $groupEvents->values();
                $firstEvent = $groupEvents->first();
                $schools = $groupEvents
                    ->map(fn (Event $event) => [
                        'event_id' => (int) $event->id,
                        'name' => $event->fjp?->school_name ?: $event->venue?->name ?: $event->event_name,
                        'venue' => $event->venue?->name,
                        'student_count' => $event->fjp?->jumlah_siswa_sekolah,
                    ])
                    ->values();
                $members = $firstEvent?->teamMembers
                    ->filter(fn ($member) => $member->user !== null)
                    ->map(fn ($member) => [
                        'user_id' => (int) $member->user_id,
                        'name' => $member->user->name,
                    ])
                    ->values() ?? collect();
                $memberIds = $members->pluck('user_id')->sort()->values()->all();
                $sameRoster = $groupEvents->every(fn (Event $event): bool => $event->teamMembers
                    ->filter(fn ($member) => $member->user !== null)
                    ->pluck('user_id')
                    ->map(fn ($id) => (int) $id)
                    ->sort()
                    ->values()
                    ->all() === $memberIds);
                $dates = $groupEvents
                    ->map(fn (Event $event): ?string => $event->event_date?->toDateString())
                    ->unique()
                    ->values();
                $hasStudentCounts = $schools->every(
                    fn (array $school): bool => $school['student_count'] !== null && $school['student_count'] > 0,
                );
                $date = $dates->count() === 1 ? $dates->first() : null;

                return [
                    'id' => $teamGroupId,
                    'date' => $date,
                    'total_students' => (int) $schools->sum('student_count'),
                    'ready' => $date !== null && $hasStudentCounts && $members->isNotEmpty() && $sameRoster,
                    'schools' => $schools,
                    'events' => $groupEvents,
                    'members' => $members,
                ];
            });
    }

    /**
     * @return array{month: string, week: int, label: string}
     */
    private function period(CarbonInterface $date): array
    {
        $months = [
            1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
        ];

        $month = $months[$date->month] ?? $date->format('F');
        $week = $date->isoWeek;

        return [
            'month' => $month,
            'week' => $week,
            'label' => "{$month} · Minggu {$week}",
        ];
    }

    private function cleanString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function numberOrNull(mixed $value): int|float|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? $value + 0 : null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function supportingItems(array $rows, Collection $products): array
    {
        return collect($rows)
            ->filter(fn (array $row) => filled($row['product_id'] ?? null))
            ->map(function (array $row, int $index) use ($products): array {
                $product = $products->get((int) $row['product_id']);

                if (! $product) {
                    throw ValidationException::withMessages([
                        "supporting_items.{$index}.product_id" => 'Produk supporting tidak ditemukan pada master barang aktif.',
                    ]);
                }

                return [
                    'product_id' => $product->id,
                    'item' => $product->name,
                    'lot_no' => $this->cleanString($row['lot_no'] ?? null),
                    'qty' => (int) $row['qty'],
                    'uom' => $product->unit,
                    'remarks' => $this->cleanString($row['remarks'] ?? null),
                ];
            })
            ->values()
            ->all();
    }

    public function downloadPhoto(Request $request, StockRequest $mrv)
    {
        $user = $request->user();
        $mrv->loadMissing('event');

        abort_unless($user->hasPermission('warehouse.request'), 403);
        abort_unless($this->canAccessRequest($user, $mrv), 404);

        abort_unless($mrv->photo_path, 404);

        if (Storage::disk('local')->exists($mrv->photo_path)) {
            return Storage::disk('local')->response($mrv->photo_path);
        }

        if (Storage::disk('public')->exists($mrv->photo_path)) {
            return Storage::disk('public')->response($mrv->photo_path);
        }

        abort(404);
    }

    /**
     * @param  Builder<StockRequest>  $query
     * @return Builder<StockRequest>
     */
    private function scopeRequests(Builder $query, User $user): Builder
    {
        if ($user->hasFullAccess() || $user->hasRole('warehouse')) {
            return $query;
        }

        return $query->where(function (Builder $requests) use ($user) {
            $requests->where('requested_by', $user->id);

            if ($user->hasRole('pic')) {
                $requests->orWhereHas('event', fn (Builder $events) => $events->where('pic_id', $user->id));
            }
        });
    }

    private function canAccessRequest(User $user, StockRequest $mrv): bool
    {
        if ($user->hasFullAccess() || $user->hasRole('warehouse') || $mrv->requested_by === $user->id) {
            return true;
        }

        return $user->hasRole('pic') && $mrv->event?->pic_id === $user->id;
    }
}
