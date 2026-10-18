<?php

namespace App\Http\Controllers\Warehouse;

use App\Enums\EventStatus;
use App\Enums\MrvStatus;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Product;
use App\Models\StockRequest;
use App\Services\AuditLogger;
use App\Services\StockService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class MrvController extends Controller
{
    public function index(Request $request): Response
    {
        $query = StockRequest::query()->with(['event:id,event_name', 'requester:id,name']);

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        $requests = $query->latest()->paginate(15)->withQueryString()->through(fn (StockRequest $r) => [
            'id' => $r->id,
            'mrv_number' => $r->mrv_number,
            'event' => $r->event?->event_name,
            'requester' => $r->requester?->name,
            'status' => $r->status->value,
            'status_label' => $r->status->label(),
            'created_at' => $r->created_at?->toIso8601String(),
        ]);

        return Inertia::render('Warehouse/Mrv/Index', [
            'requests' => $requests,
            'filters' => $request->only('status'),
            'statuses' => collect(MrvStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Warehouse/Mrv/Form', [
            'events' => Event::whereDoesntHave('stockRequests', fn ($q) => $q->whereIn('status', ['pending', 'approved']))
                ->orderBy('event_name')->get(['id', 'event_name']),
            'products' => Product::where('is_active', true)->orderBy('name')->get(['id', 'name', 'sku', 'unit'])
                ->map(fn (Product $p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'sku' => $p->sku,
                    'unit' => $p->unit,
                    'available' => $p->availableStock(),
                ]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'event_id' => ['required', 'exists:events,id'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'distinct', 'exists:products,id'],
            'items.*.quantity_requested' => ['required', 'integer', 'min:1'],
        ]);

        $event = Event::findOrFail($data['event_id']);

        if ($event->status === EventStatus::CANCELLED) {
            return back()->withErrors(['event_id' => 'Tidak dapat membuat MRV untuk event yang dibatalkan.']);
        }

        $duplicate = StockRequest::where('event_id', $event->id)
            ->whereIn('status', [MrvStatus::PENDING->value, MrvStatus::APPROVED->value])
            ->exists();

        if ($duplicate) {
            return back()->withErrors(['event_id' => 'Event ini sudah memiliki MRV yang menunggu diproses atau disetujui.']);
        }

        $mrv = DB::transaction(function () use ($data, $request) {
            $mrv = StockRequest::create([
                'mrv_number' => StockRequest::generateNumber(),
                'event_id' => $data['event_id'],
                'type' => 'issue',
                'status' => MrvStatus::PENDING,
                'notes' => $data['notes'] ?? null,
                'requested_by' => $request->user()->id,
            ]);

            foreach ($data['items'] as $item) {
                $mrv->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity_requested' => $item['quantity_requested'],
                ]);
            }

            return $mrv;
        });

        AuditLogger::log('CREATE_MRV', 'warehouse', $mrv, null, $data);

        return redirect()->route('warehouse.mrv.show', $mrv)->with('success', 'Permintaan stok (MRV) dibuat.');
    }

    public function show(Request $request, StockRequest $mrv): Response
    {
        $mrv->load(['event:id,event_name', 'requester:id,name', 'approver:id,name', 'items.product:id,name,sku,unit', 'returns']);

        return Inertia::render('Warehouse/Mrv/Show', [
            'mrv' => [
                'id' => $mrv->id,
                'mrv_number' => $mrv->mrv_number,
                'event' => $mrv->event?->event_name,
                'event_id' => $mrv->event_id,
                'requester' => $mrv->requester?->name,
                'approver' => $mrv->approver?->name,
                'status' => $mrv->status->value,
                'status_label' => $mrv->status->label(),
                'notes' => $mrv->notes,
                'rejection_reason' => $mrv->rejection_reason,
                'approved_at' => $mrv->approved_at?->toIso8601String(),
                'issued_at' => $mrv->issued_at?->toIso8601String(),
            ],
            'items' => $mrv->items->map(fn ($item) => [
                'id' => $item->id,
                'product' => $item->product?->name,
                'sku' => $item->product?->sku,
                'unit' => $item->product?->unit,
                'quantity_requested' => $item->quantity_requested,
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
            ],
        ]);
    }

    public function approve(Request $request, StockRequest $mrv)
    {
        abort_unless($request->user()->hasPermission('warehouse.approve'), 403);

        if ($mrv->status !== MrvStatus::PENDING) {
            return back()->withErrors(['status' => 'Hanya MRV berstatus menunggu yang bisa disetujui.']);
        }

        $old = ['status' => $mrv->status->value];
        $mrv->update([
            'status' => MrvStatus::APPROVED,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        AuditLogger::log('APPROVE_MRV', 'warehouse', $mrv, $old, ['status' => $mrv->status->value]);

        return back()->with('success', 'MRV disetujui. Stok belum dikurangi sampai dikeluarkan.');
    }

    public function reject(Request $request, StockRequest $mrv)
    {
        abort_unless($request->user()->hasPermission('warehouse.approve'), 403);

        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']]);

        $old = ['status' => $mrv->status->value];
        $mrv->update([
            'status' => MrvStatus::REJECTED,
            'rejection_reason' => $data['reason'],
        ]);

        AuditLogger::log('REJECT_MRV', 'warehouse', $mrv, $old, ['status' => 'rejected', 'reason' => $data['reason']]);

        return back()->with('success', 'MRV ditolak.');
    }

    public function issue(Request $request, StockRequest $mrv, StockService $stock)
    {
        abort_unless($request->user()->hasPermission('warehouse.issue'), 403);

        if ($mrv->status !== MrvStatus::APPROVED) {
            return back()->withErrors(['status' => 'MRV harus disetujui sebelum dikeluarkan.']);
        }

        try {
            $stock->issue($mrv);
        } catch (DomainException $e) {
            return back()->withErrors(['stock' => $e->getMessage()]);
        }

        AuditLogger::log('ISSUE_MRV', 'warehouse', $mrv, null, ['status' => 'issued']);

        return back()->with('success', 'Stok dikeluarkan. Pengurangan stok terjadi tepat satu kali.');
    }
}
