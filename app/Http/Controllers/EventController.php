<?php

namespace App\Http\Controllers;

use App\Enums\EventStatus;
use App\Enums\PermitStatus;
use App\Enums\ReportStatus;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Document;
use App\Models\Event;
use App\Models\EventReport;
use App\Models\EventTeamMember;
use App\Models\EventType;
use App\Models\Fjp;
use App\Models\KitchenReport;
use App\Models\KitchenRequest;
use App\Models\PermitRequest;
use App\Models\Personnel;
use App\Models\QuickReport;
use App\Models\StockRequest;
use App\Models\StockReturn;
use App\Models\User;
use App\Models\Venue;
use App\Services\AuditLogger;
use App\Services\EventReadinessService;
use App\Support\BrandContext;
use App\Support\EventAccess;
use App\Support\EventGrouping;
use App\Support\EventOperationalData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $query = Event::query()
            ->with([
                'brand:id,name,slug',
                'eventType:id,name,requires_school',
                'venue:id,name,city,is_school',
                'pic:id,name',
            ])
            ->withCount('teamMembers');

        $this->scopeForUser($query, $user);

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('event_name', 'like', "%{$search}%")
                    ->orWhere('event_code', 'like', "%{$search}%");
            });
        }

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        if ($venueId = $request->integer('venue_id')) {
            $query->where('venue_id', $venueId);
        }

        if ($eventTypeId = $request->integer('event_type_id')) {
            $query->where('event_type_id', $eventTypeId);
        }

        $brandId = BrandContext::resolve($request);
        $brandSummaries = $this->brandSummaries(clone $query);

        if ($brandId) {
            $query->where('brand_id', $brandId);
        }

        $base = clone $query;

        $eventRows = $query
            ->orderBy('event_date')
            ->get()
            ->map(fn (Event $event) => [
                'id' => $event->id,
                'event_code' => $event->event_code,
                'event_name' => $event->event_name,
                'brand' => $event->brand?->name,
                'brand_id' => $event->brand_id,
                'brand_name' => $event->brand?->name,
                'event_type' => $event->eventType?->name,
                'event_type_id' => $event->event_type_id,
                'event_type_name' => $event->eventType?->name,
                'requires_school' => (bool) $event->eventType?->requires_school,
                'venue' => $event->venue?->name,
                'venue_city' => $event->venue?->city,
                'venue_is_school' => (bool) $event->venue?->is_school,
                'event_date' => $event->event_date?->toDateString(),
                'start_time' => $event->start_time,
                'end_time' => $event->end_time,
                'sampling_target' => $event->sampling_target,
                'pic' => $event->pic?->name,
                'team_count' => $event->team_members_count,
                'jug_required' => $event->jug_required,
                'status' => $event->status->value,
                'status_label' => $event->status->label(),
            ]);

        $month = $this->resolveMonth($request->string('month')->toString());
        $monthEnd = (clone $month)->endOfMonth();

        $calendarEvents = $base
            ->whereBetween('event_date', [$month->toDateString(), $monthEnd->toDateTimeString()])
            ->orderBy('event_date')
            ->get(['id', 'event_code', 'event_name', 'event_date', 'status'])
            ->map(fn (Event $event) => [
                'id' => $event->id,
                'event_code' => $event->event_code,
                'event_name' => $event->event_name,
                'event_date' => $event->event_date?->toDateString(),
                'status' => $event->status->value,
                'status_label' => $event->status->label(),
            ])
            ->values();

        return Inertia::render('Events/Index', [
            'eventGroups' => EventGrouping::group($eventRows),
            'brandSummaries' => $brandSummaries,
            'eventCount' => $eventRows->count(),
            'calendarEvents' => $calendarEvents,
            'month' => $month->format('Y-m'),
            'filters' => [
                ...$request->only('search', 'status', 'event_type_id', 'venue_id'),
                'brand_id' => $brandId,
            ],
            'statuses' => collect(EventStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
            'canCreate' => $user->hasPermission('events.create'),
            'canUpdate' => $user->hasPermission('events.update'),
            'canDelete' => $user->hasPermission('events.delete'),
            ...$this->formOptions(confirmedOnly: false),
        ]);
    }

    /**
     * Event totals per brand with status buckets for the summary cards.
     * The query is expected to already be scoped for the user and filtered by
     * the non-brand filters, so the cards always reflect what is on screen.
     *
     * @param  Builder<Event>  $query
     * @return array<int, array<string, mixed>>
     */
    private function brandSummaries($query): array
    {
        $rows = (clone $query)
            ->whereNotNull('brand_id')
            ->toBase()
            ->selectRaw('brand_id, status, COUNT(*) as aggregate')
            ->groupBy('brand_id', 'status')
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $brandNames = Brand::query()
            ->whereIn('id', $rows->pluck('brand_id')->unique())
            ->pluck('name', 'id');

        $buckets = [
            'planning' => ['draft', 'planning', 'permission'],
            'preparation' => ['team_assignment', 'warehouse_preparation', 'kitchen_preparation'],
            'ready_ongoing' => ['ready', 'ongoing'],
            'completed' => ['completed'],
            'cancelled' => ['cancelled'],
        ];

        return $rows
            ->groupBy('brand_id')
            ->map(function ($statusRows, $brandId) use ($brandNames, $buckets): array {
                $counts = $statusRows->pluck('aggregate', 'status');

                $summary = [
                    'brand_id' => (int) $brandId,
                    'brand_name' => $brandNames->get((int) $brandId, 'Brand'),
                    'total' => (int) $statusRows->sum('aggregate'),
                ];

                foreach ($buckets as $key => $statuses) {
                    $summary[$key] = (int) collect($statuses)->sum(
                        fn (string $status) => (int) ($counts[$status] ?? 0),
                    );
                }

                return $summary;
            })
            ->sortBy('brand_name')
            ->values()
            ->all();
    }

    /**
     * Normalise the requested calendar month (YYYY-MM) into a Carbon instance.
     */
    private function resolveMonth(string $month): Carbon
    {
        if ($month !== '') {
            try {
                return Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfMonth();
            } catch (\Exception) {
                // fall through to the current month
            }
        }

        return now()->startOfMonth();
    }

    public function create(): Response
    {
        return Inertia::render('Events/Create', $this->formOptions());
    }

    /**
     * @return array{brands: Collection, eventTypes: Collection, venues: Collection, confirmedFjps: Collection, pics: Collection}
     */
    private function formOptions(?Venue $includeVenue = null, bool $confirmedOnly = true, ?int $includeFjpId = null): array
    {
        $venues = Venue::query();

        if ($confirmedOnly) {
            $venues->usable();
        }

        $venueColumns = ['id', 'name', 'npsn', 'is_school', 'address', 'city', 'province'];

        $venues = $venues->orderBy('name')->get($venueColumns);

        if ($includeVenue && ! $venues->contains('id', $includeVenue->id)) {
            $venues->push($includeVenue->only($venueColumns));
        }

        return [
            'brands' => Brand::where('is_active', true)->get(['id', 'name']),
            'eventTypes' => EventType::where('is_active', true)->orderBy('name')->get(['id', 'brand_id', 'name', 'requires_school', 'kitchen_default']),
            'venues' => $venues,
            'confirmedFjps' => Fjp::query()
                ->with(['brand:id,name', 'eventType:id,name', 'venue:id,name,address,city,province'])
                ->where('permit_status', '!=', PermitStatus::CANCELLED)
                ->whereIn('status', [Fjp::STATUS_CONFIRM, 'approved'])
                ->where(function ($query) use ($includeFjpId) {
                    $query->whereDoesntHave(
                        'events',
                        fn ($events) => $events->where('status', '!=', EventStatus::CANCELLED->value),
                    );

                    if ($includeFjpId) {
                        $query->orWhere('id', $includeFjpId);
                    }
                })
                ->orderBy('event_date')
                ->orderBy('id')
                ->get()
                ->map(fn (Fjp $fjp) => [
                    'id' => $fjp->id,
                    'fjp_number' => $fjp->fjp_number,
                    'venue_id' => $fjp->venue_id,
                    'venue_name' => $fjp->venue?->name,
                    'venue_address' => $fjp->venue?->address ?? $fjp->address,
                    'venue_city' => $fjp->venue?->city ?? $fjp->city,
                    'venue_province' => $fjp->venue?->province ?? $fjp->province,
                    'maps_url' => $fjp->maps_url,
                    'brand_id' => $fjp->brand_id,
                    'brand_name' => $fjp->brand?->name,
                    'event_type_id' => $fjp->event_type_id,
                    'event_type_name' => $fjp->eventType?->name,
                    'event_date' => $fjp->event_date?->toDateString(),
                    'start_time' => $fjp->start_time,
                    'end_time' => $fjp->end_time,
                    'sampling_target' => $fjp->sampling_target,
                    'jumlah_siswa_sekolah' => $fjp->jumlah_siswa_sekolah,
                    'jug_required' => $fjp->jug_required,
                    'school_name' => $fjp->school_name,
                ])
                ->values(),
            'pics' => User::whereHas('roles', fn ($q) => $q->where('slug', 'pic'))->get(['id', 'name']),
        ];
    }

    public function store(Request $request)
    {
        $data = $this->validatedEvent($request);

        if (empty($data['pic_id']) && $request->user()->hasRole('pic')) {
            $data['pic_id'] = $request->user()->id;
        }

        $data['event_code'] = Event::generateCode();
        $data['status'] = EventStatus::PLANNING;
        $data['created_by'] = $request->user()->id;

        $event = Event::create($data);

        AuditLogger::log('CREATE_EVENT', 'events', $event, null, $data);

        return redirect()->route('events.show', $event)->with('success', 'Event dibuat.');
    }

    public function edit(Request $request, Event $event): Response
    {
        $this->authorizeView($request, $event);
        abort_unless($request->user()->hasPermission('events.update'), 403);

        return Inertia::render('Events/Edit', [
            'event' => [
                'id' => $event->id,
                'event_code' => $event->event_code,
                'event_name' => $event->event_name,
                'fjp_id' => $event->fjp_id,
                'brand_id' => $event->brand_id,
                'event_type_id' => $event->event_type_id,
                'venue_id' => $event->venue_id,
                'venue' => $event->venue?->only(['name', 'address', 'city', 'province']),
                'event_date' => $event->event_date?->toDateString(),
                'start_time' => $event->start_time,
                'end_time' => $event->end_time,
                'sampling_target' => $event->sampling_target,
                'notes' => $event->notes,
                'jug_required' => $event->jug_required,
                'pic_id' => $event->pic_id,
                'status' => $event->status->value,
                'is_terminal' => $event->status->isTerminal(),
            ],
            ...$this->formOptions($event->venue, includeFjpId: $event->fjp_id),
        ]);
    }

    public function update(Request $request, Event $event)
    {
        $this->authorizeView($request, $event);
        abort_unless($request->user()->hasPermission('events.update'), 403);

        if ($event->status->isTerminal()) {
            return back()->withErrors([
                'event_name' => 'Event yang sudah selesai atau dibatalkan tidak dapat diubah.',
            ]);
        }

        $data = $this->validatedEvent($request, $event);
        $old = $event->only(array_keys($data));

        $event->update($data);

        AuditLogger::log('UPDATE_EVENT', 'events', $event, $old, $data);

        return redirect()->route('events.show', $event)->with('success', 'Event diperbarui.');
    }

    public function destroy(Request $request, Event $event)
    {
        $this->authorizeView($request, $event);
        abort_unless($request->user()->hasPermission('events.delete'), 403);

        if ($event->status === EventStatus::ONGOING || $event->status->isTerminal()) {
            return back()->withErrors([
                'event' => 'Event yang sedang berjalan atau sudah selesai tidak dapat dihapus.',
            ]);
        }

        if (EventOperationalData::exists($event)) {
            return back()->withErrors([
                'event' => 'Event yang sudah memiliki data operasional tidak dapat dihapus.',
            ]);
        }

        $old = ['event_code' => $event->event_code, 'event_name' => $event->event_name, 'status' => $event->status->value];

        $event->teamMembers()->delete();
        $event->delete();

        AuditLogger::log('DELETE_EVENT', 'events', $event, $old);

        return redirect()->route('events.index')->with('success', 'Event dihapus.');
    }

    /** @return array<string, mixed> */
    private function validatedEvent(Request $request, ?Event $event = null): array
    {
        $data = $request->validate([
            'event_name' => ['nullable', 'string', 'max:255'],
            'fjp_id' => ['nullable', 'exists:fjp,id'],
            'brand_id' => ['nullable', 'exists:brands,id'],
            'event_type_id' => ['nullable', 'exists:event_types,id'],
            'venue_id' => ['nullable', 'exists:venues,id'],
            'event_date' => ['nullable', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'sampling_target' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
            'jug_required' => ['boolean'],
            'pic_id' => ['nullable', 'exists:users,id'],
        ]);

        if (! empty($data['fjp_id'])) {
            $fjp = Fjp::with('venue')->find($data['fjp_id']);
            $isCurrentFjp = $event !== null && (int) $event->fjp_id === (int) $data['fjp_id'];

            if (! $isCurrentFjp && Event::query()
                ->where('fjp_id', $data['fjp_id'])
                ->where('status', '!=', EventStatus::CANCELLED->value)
                ->exists()) {
                throw ValidationException::withMessages([
                    'fjp_id' => 'FJP ini sudah memiliki event. Buka event terkait untuk melanjutkan.',
                ]);
            }

            $isSchedulable = $fjp
                && in_array($fjp->status, [Fjp::STATUS_CONFIRM, 'approved'], true);

            if (! $isCurrentFjp && ! $isSchedulable) {
                throw ValidationException::withMessages([
                    'fjp_id' => 'Event hanya dapat dijadwalkan dari FJP yang disetujui (approved/confirm).',
                ]);
            }

            $venueId = $fjp->venue_id
                ?? ($data['venue_id'] ?? null)
                ?? ($isCurrentFjp ? $event->venue_id : null);

            if (! $venueId) {
                throw ValidationException::withMessages([
                    'venue_id' => 'FJP tersebut belum memiliki lokasi. Pilih venue terlebih dahulu.',
                ]);
            }

            if (! $fjp->venue_id) {
                $fjp->update(['venue_id' => $venueId]);
            }

            $data['venue_id'] = $venueId;
            $data['brand_id'] = $fjp->brand_id ?? ($isCurrentFjp ? $event->brand_id : null);
            $data['event_type_id'] = $fjp->event_type_id ?? ($isCurrentFjp ? $event->event_type_id : null);

            if (blank($data['event_date'] ?? null) && $fjp->event_date) {
                $data['event_date'] = $fjp->event_date->toDateString();
            }

            if (blank($data['start_time'] ?? null)) {
                $data['start_time'] = $fjp->start_time;
            }

            if (blank($data['end_time'] ?? null)) {
                $data['end_time'] = $fjp->end_time;
            }

            if (($data['sampling_target'] ?? null) === null) {
                $data['sampling_target'] = $fjp->jumlah_siswa_sekolah ?? $fjp->sampling_target;
            }

            if (! array_key_exists('jug_required', $data)) {
                $data['jug_required'] = (bool) $fjp->jug_required;
            }
        }

        $this->ensureEventTypeMatchesBrand($data);

        if (blank($data['event_name'] ?? null)) {
            $data['event_name'] = Event::composeName(
                Brand::find($data['brand_id'] ?? null)?->name,
                EventType::find($data['event_type_id'] ?? null)?->name,
                Venue::find($data['venue_id'] ?? null)?->name,
            );
        }

        return $data;
    }

    /** @param array<string, mixed> $data */
    private function ensureEventTypeMatchesBrand(array $data): void
    {
        if (empty($data['event_type_id'])) {
            return;
        }

        $type = EventType::find($data['event_type_id']);

        if ($type?->brand_id && (int) $type->brand_id !== (int) ($data['brand_id'] ?? 0)) {
            throw ValidationException::withMessages([
                'event_type_id' => 'Jenis event tidak sesuai dengan brand yang dipilih.',
            ]);
        }
    }

    public function show(Event $event, EventReadinessService $readiness): Response
    {
        $this->authorizeView(request(), $event);

        $event->load([
            'brand', 'venue', 'eventType', 'pic',
            'fjp',
            'teamMembers.user:id,name', 'teamMembers.personnel:id,name,role',
            'permit.documents',
            'stockRequests.items.product:id,name,sku,unit', 'stockRequests.returns',
            'kitchenRequest.reports.preparer:id,name',
            'kitchenRequest.reports.media',
            'attendances.user:id,name',
            'reports.submitter:id,name', 'reports.verifier:id,name',
            'quickReports.user:id,name',
            'documents.uploader:id,name',
        ]);

        $ready = $readiness->evaluate($event);
        $attendanceByUser = $event->attendances->keyBy('user_id');
        $user = request()->user();

        $canViewFjp = $user->hasPermission('fjp.view') || $user->hasPermission('events.close');
        $canViewMrv = $user->hasPermission('warehouse.view');
        $canViewKitchen = $user->hasPermission('kitchen.view');
        $canViewQuick = $user->hasPermission('quick_reports.view');
        $canViewAudit = $user->hasPermission('events.update')
            || $user->hasPermission('events.close')
            || $user->hasPermission('admin.audit.view');
        $canAssignTeam = $user->hasPermission('events.assign_team');
        $displayStatus = $canViewFjp && $event->fjp
            ? $this->generalFjpStatus((string) $event->fjp->status)
            : $event->status->value;
        $displayStatusLabel = $canViewFjp && $event->fjp
            ? $this->fjpStatusLabel((string) $event->fjp->status)
            : $event->status->label();

        return Inertia::render('Events/Show', [
            'event' => [
                'id' => $event->id,
                'event_code' => $event->event_code,
                'event_name' => $event->event_name,
                'brand' => $event->brand?->name,
                'event_type' => $event->eventType?->name,
                'venue' => $event->venue?->name,
                'event_date' => $event->event_date?->toDateString(),
                'start_time' => $event->start_time,
                'end_time' => $event->end_time,
                'sampling_target' => $event->sampling_target,
                'notes' => $event->notes,
                'jug_required' => $event->jug_required,
                'status' => $event->status->value,
                'status_label' => $event->status->label(),
                'display_status' => $displayStatus,
                'display_status_label' => $displayStatusLabel,
                'pic' => $event->pic?->name,
            ],
            'readiness' => [
                'modules' => [
                    'team' => $ready['modules']['team'],
                ],
            ],
            'fjp' => $canViewFjp ? ($event->fjp ? [
                'id' => $event->fjp->id,
                'fjp_number' => $event->fjp->fjp_number,
                'status' => $this->generalFjpStatus((string) $event->fjp->status),
                'status_label' => $this->fjpStatusLabel((string) $event->fjp->status),
                'event_name' => $event->fjp->event_name,
                'school_name' => $event->fjp->school_name,
                'npsn' => $event->fjp->npsn,
                'address' => $event->fjp->address,
                'maps_url' => $event->fjp->maps_url,
                'jumlah_siswa_sekolah' => $event->fjp->jumlah_siswa_sekolah,
                'jumlah_siswa_dapodik' => $event->fjp->jumlah_siswa_dapodik,
                'difference' => $event->fjp->student_count_difference,
                'dapodik_source' => $event->fjp->dapodik_source,
                'dapodik_synced_at' => $event->fjp->dapodik_synced_at?->toIso8601String(),
                'permit_status' => $event->fjp->permit_status->value,
                'permit_status_label' => $event->fjp->permit_status->label(),
                'rejection_reason' => $event->fjp->rejection_reason,
                'cancellation_reason' => $event->fjp->cancellation_reason,
            ] : null) : null,
            'permit' => $canViewFjp ? ($event->permit ? [
                'id' => $event->permit->id,
                'permit_number' => $event->permit->permit_number,
                'status' => $event->permit->status->value,
                'status_label' => $event->permit->status->label(),
                'deadline' => $event->permit->deadline?->toDateString(),
                'reason' => $event->permit->reason,
                'documents' => $event->permit->documents->map(fn ($d) => [
                    'id' => $d->id,
                    'category' => $d->category,
                    'version' => $d->version,
                    'original_name' => $d->original_name,
                    'url' => route('permits.documents.download', $d->id),
                ])->values(),
            ] : null) : null,
            'team' => $event->teamMembers->map(function ($m) use ($attendanceByUser) {
                $attendance = $m->user_id ? $attendanceByUser->get($m->user_id) : null;

                return [
                    'id' => $m->id,
                    'name' => $m->memberName(),
                    'position' => $m->position,
                    'user_id' => $m->user_id,
                    'schedule_date' => $m->schedule_date?->toDateString(),
                    'report_time' => $m->report_time,
                    'start_time' => $m->start_time,
                    'end_time' => $m->end_time,
                    'task' => $m->task,
                    'schedule_notes' => $m->schedule_notes,
                    'attendance' => $attendance ? [
                        'check_in_at' => $attendance->check_in_at?->toIso8601String(),
                        'check_out_at' => $attendance->check_out_at?->toIso8601String(),
                        'status' => $attendance->status,
                        'is_complete' => $attendance->isComplete(),
                    ] : null,
                ];
            })->values(),
            'attendances' => $event->attendances->map(fn (Attendance $a) => [
                'id' => $a->id,
                'user' => $a->user?->name,
                'planned_start' => $a->planned_start?->toIso8601String(),
                'planned_end' => $a->planned_end?->toIso8601String(),
                'check_in_at' => $a->check_in_at?->toIso8601String(),
                'check_out_at' => $a->check_out_at?->toIso8601String(),
                'status' => $a->status,
                'is_complete' => $a->isComplete(),
            ])->values(),
            'stockRequests' => $canViewMrv ? $event->stockRequests->map(fn (StockRequest $r) => [
                'id' => $r->id,
                'mrv_number' => $r->mrv_number,
                'status' => $r->status->value,
                'status_label' => $r->status->label(),
                'notes' => $r->notes,
                'rejection_reason' => $r->rejection_reason,
                'created_at' => $r->created_at?->toIso8601String(),
                'items' => $r->items->map(fn ($i) => [
                    'id' => $i->id,
                    'product' => $i->product?->name,
                    'sku' => $i->product?->sku,
                    'unit' => $i->product?->unit,
                    'quantity_requested' => $i->quantity_requested,
                    'quantity_issued' => $i->quantity_issued,
                    'quantity_returned' => $i->quantity_returned,
                    'quantity_damaged' => $i->quantity_damaged,
                ])->values(),
                'returns' => $r->returns->map(fn ($rt) => [
                    'id' => $rt->id,
                    'return_number' => $rt->return_number,
                    'status' => $rt->status,
                ])->values(),
            ])->values() : [],
            'kitchen' => $canViewKitchen ? ($event->kitchenRequest ? [
                'id' => $event->kitchenRequest->id,
                'status' => $event->kitchenRequest->status->value,
                'status_label' => $event->kitchenRequest->status->label(),
                'jug_quantity' => $event->kitchenRequest->jug_quantity,
                'preparation_date' => $event->kitchenRequest->preparation_date?->toDateString(),
                'preparation_time' => $event->kitchenRequest->preparation_time,
                'assignee' => $event->kitchenRequest->assignee?->name,
                'notes' => $event->kitchenRequest->notes,
                'reports' => $event->kitchenRequest->reports->map(function (KitchenReport $r) {
                    $media = $r->media->groupBy('type');

                    return [
                        'id' => $r->id,
                        'prepared_quantity' => $r->prepared_quantity,
                        'condition' => $r->condition,
                        'notes' => $r->notes,
                        'prepared_by' => $r->preparer?->name,
                        'prepared_at' => $r->prepared_at?->toIso8601String(),
                        'washing_at' => $r->washing_at?->toIso8601String(),
                        'cooking_at' => $r->cooking_at?->toIso8601String(),
                        'ready_at' => $r->ready_at?->toIso8601String(),
                        'photo_url' => $r->photo_path ? route('kitchen.reports.photo', $r->id) : null,
                        'washing_photo_url' => $r->washing_photo_path ? route('kitchen.reports.washing-photo', $r->id) : null,
                        'ready_photo_url' => $r->ready_photo_path ? route('kitchen.reports.ready-photo', $r->id) : null,
                        'media' => [
                            'washing' => $this->kitchenMediaPayload($media->get('washing', collect())),
                            'cooking' => $this->kitchenMediaPayload($media->get('cooking', collect())),
                            'ready' => $this->kitchenMediaPayload($media->get('ready', collect())),
                        ],
                    ];
                })->values(),
            ] : null) : null,
            'reports' => $event->reports->map(fn (EventReport $r) => [
                'id' => $r->id,
                'status' => $r->status->value,
                'status_label' => $r->status->label(),
                'sampling_target' => $r->sampling_target,
                'actual_sampling' => $r->actual_sampling,
                'achievement' => (float) $r->achievement_percentage,
                'issues' => $r->issues,
                'notes' => $r->notes,
                'revision_note' => $r->revision_note,
                'submitted_by' => $r->submitter?->name,
                'submitted_at' => $r->submitted_at?->toIso8601String(),
                'verified_by' => $r->verifier?->name,
                'verified_at' => $r->verified_at?->toIso8601String(),
            ])->values(),
            'summary' => $this->targetRealisasiSummary($event),
            'quickReports' => $canViewQuick ? $event->quickReports->map(fn (QuickReport $q) => [
                'id' => $q->id,
                'title' => $q->title,
                'body' => $q->body,
                'user' => $q->user?->name,
                'created_at' => $q->created_at?->toIso8601String(),
                'photo_url' => $q->photo_path ? route('quick-reports.photo', $q->id) : null,
            ])->values() : [],
            'documents' => $event->documents->map(fn (Document $d) => [
                'id' => $d->id,
                'category' => $d->category,
                'original_name' => $d->original_name,
                'mime' => $d->mime,
                'size' => $d->size,
                'uploaded_by' => $d->uploader?->name,
                'created_at' => $d->created_at?->toIso8601String(),
                'url' => route('documents.download', $d->id),
            ])->values(),
            'auditTrail' => $canViewAudit ? $this->auditTrail($event) : [],
            'transitions' => collect($event->status->allowedTransitions())
                ->map(fn (EventStatus $s) => ['value' => $s->value, 'label' => $s->label()])
                ->values(),
            'fjpStatusOptions' => [
                ['value' => Fjp::STATUS_PLAN, 'label' => 'Plan'],
                ['value' => Fjp::STATUS_CONFIRM, 'label' => 'Confirm'],
                ['value' => Fjp::STATUS_CANCEL, 'label' => 'Cancel'],
                ['value' => Fjp::STATUS_RESCHEDULE, 'label' => 'Reschedule'],
                ['value' => Fjp::STATUS_DONE, 'label' => 'Done'],
            ],
            'assignmentPool' => [
                'users' => $canAssignTeam
                    ? User::whereHas('roles', fn ($q) => $q->where('slug', 'team'))->get(['id', 'name'])
                    : [],
                'personnel' => $canAssignTeam
                    ? Personnel::where('is_active', true)->get(['id', 'name', 'role'])
                    : [],
            ],
            'can' => [
                'assign_team' => $canAssignTeam,
                'manage' => $user->hasPermission('events.update'),
                'update_fjp_status' => $user->hasPermission('fjp.status.update'),
                'close' => $user->hasPermission('events.close'),
                'verify' => $user->hasPermission('reports.verify'),
                'revision' => $user->hasPermission('reports.revision'),
                'upload_document' => $user->hasPermission('events.view'),
                'create_report' => $user->hasPermission('reports.create'),
                'view_fjp' => $canViewFjp,
                'view_mrv' => $canViewMrv,
                'view_kitchen' => $canViewKitchen,
                'view_quick' => $canViewQuick,
                'view_audit' => $canViewAudit,
            ],
        ]);
    }

    private function generalFjpStatus(string $status): string
    {
        return match ($status) {
            'draft', 'submitted', 'pending' => Fjp::STATUS_PLAN,
            'approved' => Fjp::STATUS_CONFIRM,
            'rejected', 'cancelled' => Fjp::STATUS_CANCEL,
            default => $status,
        };
    }

    private function fjpStatusLabel(string $status): string
    {
        return match ($this->generalFjpStatus($status)) {
            Fjp::STATUS_PLAN => 'Plan',
            Fjp::STATUS_CONFIRM => 'Confirm',
            Fjp::STATUS_CANCEL => 'Cancel',
            Fjp::STATUS_RESCHEDULE => 'Reschedule',
            Fjp::STATUS_DONE => 'Done',
            default => ucfirst($status),
        };
    }

    /**
     * Recent audit entries tied to the event and its child records.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function auditTrail(Event $event): Collection
    {
        $groups = [
            Event::class => [$event->id],
            EventReport::class => $event->reports->modelKeys(),
            StockRequest::class => $event->stockRequests->modelKeys(),
            StockReturn::class => $event->stockRequests->flatMap(fn ($r) => $r->returns->modelKeys())->all(),
            KitchenRequest::class => $event->kitchenRequest ? [$event->kitchenRequest->id] : [],
            Attendance::class => $event->attendances->modelKeys(),
            QuickReport::class => $event->quickReports->modelKeys(),
            Document::class => $event->documents->modelKeys(),
            PermitRequest::class => $event->permit ? [$event->permit->id] : [],
        ];

        return AuditLog::query()
            ->with('user:id,name')
            ->where(function ($query) use ($groups) {
                foreach ($groups as $type => $ids) {
                    if ($ids === []) {
                        continue;
                    }

                    $query->orWhere(function ($sub) use ($type, $ids) {
                        $sub->where('record_type', $type)->whereIn('record_id', $ids);
                    });
                }
            })
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn (AuditLog $log) => [
                'id' => $log->id,
                'action' => $log->action,
                'module' => $log->module,
                'user' => $log->user?->name,
                'role' => $log->role,
                'old_values' => $log->old_values,
                'new_values' => $log->new_values,
                'created_at' => $log->created_at?->toIso8601String(),
            ])
            ->values();
    }

    /**
     * Event-level sampling target vs. realisasi from verified reports.
     * "has_data" is false until at least one verified report exists so the UI
     * can show "Belum ada data" instead of a misleading zero.
     *
     * @return array{target: int|null, realisasi: int|null, achievement: float|null, has_data: bool}
     */
    private function targetRealisasiSummary(Event $event): array
    {
        $verified = $event->reports->filter(
            fn (EventReport $r) => $r->status === ReportStatus::VERIFIED && $r->actual_sampling !== null
        );

        if ($verified->isEmpty()) {
            return [
                'target' => $event->sampling_target,
                'realisasi' => null,
                'achievement' => null,
                'has_data' => false,
            ];
        }

        $target = (int) $verified->sum('sampling_target');
        $realisasi = (int) $verified->sum('actual_sampling');

        return [
            'target' => $target,
            'realisasi' => $realisasi,
            'achievement' => $target > 0 ? round(($realisasi / $target) * 100, 2) : 0.0,
            'has_data' => true,
        ];
    }

    public function updateStatus(Request $request, Event $event)
    {
        $this->authorizeView($request, $event);

        $data = $request->validate([
            'status' => ['required', Rule::enum(EventStatus::class)],
        ]);

        $target = EventStatus::from($data['status']);

        if ($target === $event->status) {
            return back()->with('info', 'Status event tidak berubah.');
        }

        if (! $event->status->canTransitionTo($target)) {
            return back()->withErrors([
                'status' => "Status {$event->status->label()} tidak dapat langsung diubah ke {$target->label()}.",
            ]);
        }

        $old = ['status' => $event->status->value];
        $event->update(['status' => $target]);

        AuditLogger::log('UPDATE_EVENT', 'events', $event, $old, ['status' => $target->value]);

        return back()->with('success', 'Status event diperbarui.');
    }

    /**
     * Close an event once its operational prerequisites are met. This is the
     * only sanctioned path to the COMPLETED state so an event can never be
     * closed while mandatory reports, kitchen or stock work is still open.
     */
    public function close(Request $request, Event $event, EventReadinessService $readiness)
    {
        abort_unless($request->user()->hasPermission('events.close'), 403);
        $this->authorizeView($request, $event);

        if ($event->status->isTerminal()) {
            return back()->withErrors(['close' => 'Event sudah selesai atau dibatalkan.']);
        }

        $blockers = $this->closeBlockers($event, $readiness);

        if ($blockers !== []) {
            return back()->withErrors(['close' => 'Event belum dapat ditutup: '.implode(' ', $blockers)]);
        }

        $old = ['status' => $event->status->value];
        $event->update(['status' => EventStatus::COMPLETED]);

        AuditLogger::log('CLOSE_EVENT', 'events', $event, $old, ['status' => EventStatus::COMPLETED->value]);

        return back()->with('success', 'Event berhasil ditutup.');
    }

    /**
     * @return array<int, string>
     */
    private function closeBlockers(Event $event, EventReadinessService $readiness): array
    {
        $event->loadMissing(['permit', 'teamMembers', 'stockRequests', 'kitchenRequest', 'reports']);
        $blockers = [];

        if ($event->event_date === null) {
            $blockers[] = 'Tanggal pelaksanaan belum ditetapkan.';
        } elseif ($event->event_date->isFuture()) {
            $blockers[] = 'Tanggal pelaksanaan belum terlewati.';
        }

        if ($event->permit && $event->permit->status !== PermitStatus::APPROVED) {
            $blockers[] = 'Perizinan belum disetujui.';
        }

        if ($event->teamMembers->isEmpty()) {
            $blockers[] = 'Belum ada anggota tim yang ditugaskan.';
        }

        $unresolvedMrv = $event->stockRequests->contains(
            fn ($r) => in_array($r->status->value, ['pending', 'approved'], true),
        );
        if ($unresolvedMrv) {
            $blockers[] = 'Masih ada permintaan stok (MRV) yang belum direkonsiliasi.';
        }

        if ($event->jug_required) {
            $kitchen = $event->kitchenRequest;
            if (! $kitchen || ! in_array($kitchen->status->value, ['ready', 'dispatched'], true)) {
                $blockers[] = 'Proses Kitchen belum selesai.';
            }
        }

        if ($event->reports->isEmpty()) {
            $blockers[] = 'Belum ada laporan kegiatan.';
        } elseif ($event->reports->contains(fn ($report) => in_array($report->status->value, ['draft', 'submitted', 'revision'], true))) {
            $blockers[] = 'Masih ada laporan yang belum diverifikasi.';
        }

        return $blockers;
    }

    public function assignTeam(Request $request, Event $event)
    {
        $this->authorizeView($request, $event);

        $data = $request->validate([
            'user_id' => ['nullable', 'required_without:personnel_id', 'exists:users,id'],
            'personnel_id' => ['nullable', 'required_without:user_id', 'exists:personnel,id'],
            'position' => ['required', 'string', 'max:50'],
            ...$this->teamScheduleRules(),
        ]);

        $duplicate = $event->teamMembers()->where(function ($q) use ($data) {
            $q->when($data['user_id'] ?? null, fn ($qq) => $qq->where('user_id', $data['user_id']))
                ->when($data['personnel_id'] ?? null, fn ($qq) => $qq->orWhere('personnel_id', $data['personnel_id']));
        })->first();

        if ($duplicate) {
            return back()->withErrors(['user_id' => 'Anggota tim tersebut sudah ditugaskan di event ini.']);
        }

        $data['event_id'] = $event->id;
        $data['assigned_by'] = $request->user()->id;

        $member = $event->teamMembers()->create($data);

        AuditLogger::log('ASSIGN_TEAM', 'events', $event, null, $data);

        return back()->with('success', 'Anggota tim ditugaskan.')->with('member_id', $member->id);
    }

    public function updateTeam(Request $request, Event $event, EventTeamMember $member)
    {
        $this->authorizeView($request, $event);
        abort_unless($member->event_id === $event->id, 404);

        $data = $request->validate([
            'position' => ['sometimes', 'required', 'string', 'max:50'],
            ...$this->teamScheduleRules(),
        ]);

        $member->update($data);

        AuditLogger::log('UPDATE_TEAM_SCHEDULE', 'events', $event, null, [
            'member_id' => $member->id,
            ...$data,
        ]);

        return back()->with('success', 'Jadwal anggota tim diperbarui.');
    }

    /** @return array<string, array<int, string>> */
    private function teamScheduleRules(): array
    {
        return [
            'schedule_date' => ['nullable', 'date'],
            'report_time' => ['nullable', 'date_format:H:i'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after_or_equal:start_time'],
            'task' => ['nullable', 'string', 'max:255'],
            'schedule_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function removeTeam(Request $request, Event $event, EventTeamMember $member)
    {
        $this->authorizeView($request, $event);
        abort_unless($member->event_id === $event->id, 404);

        AuditLogger::log('REMOVE_TEAM', 'events', $event, ['member_id' => $member->id]);
        $member->delete();

        return back()->with('success', 'Anggota tim dihapus.');
    }

    private function scopeForUser($query, $user): void
    {
        if ($user->hasRole('pic') && ! $user->hasFullAccess()) {
            $query->where('pic_id', $user->id);
        }

        if ($user->hasRole('team') && ! $user->hasFullAccess() && ! $user->hasRole('pic')) {
            $query->whereHas('teamMembers', fn ($q) => $q->where('user_id', $user->id));
        }
    }

    private function kitchenMediaPayload(Collection $media): array
    {
        return $media->map(fn ($item) => [
            'id' => $item->id,
            'type' => $item->type,
            'url' => route('kitchen.reports.media', $item->id),
            'original_name' => $item->original_name,
            'size' => $item->size,
        ])->values()->all();
    }

    private function authorizeView(Request $request, Event $event): void
    {
        abort_unless(EventAccess::allows($request->user(), $event), 403);
    }
}
