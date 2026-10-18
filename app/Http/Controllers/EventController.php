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
use App\Support\EventAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $query = Event::query()->with(['brand:id,name', 'venue:id,name', 'pic:id,name']);

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

        if ($brandId = $request->integer('brand_id')) {
            $query->where('brand_id', $brandId);
        }

        if ($venueId = $request->integer('venue_id')) {
            $query->where('venue_id', $venueId);
        }

        $base = clone $query;

        $events = $query->latest('event_date')->paginate(15)->withQueryString()->through(fn (Event $event) => [
            'id' => $event->id,
            'event_code' => $event->event_code,
            'event_name' => $event->event_name,
            'brand' => $event->brand?->name,
            'venue' => $event->venue?->name,
            'event_date' => $event->event_date?->toDateString(),
            'pic' => $event->pic?->name,
            'jug_required' => $event->jug_required,
            'status' => $event->status->value,
            'status_label' => $event->status->label(),
        ]);

        $month = $this->resolveMonth($request->string('month')->toString());
        $monthEnd = (clone $month)->endOfMonth();

        $calendarEvents = $base
            ->whereBetween('event_date', [$month->toDateString(), $monthEnd->toDateString()])
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
            'events' => $events,
            'calendarEvents' => $calendarEvents,
            'month' => $month->format('Y-m'),
            'filters' => $request->only('search', 'status', 'brand_id', 'venue_id'),
            'statuses' => collect(EventStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
            'canCreate' => $user->hasPermission('events.create'),
            ...$this->formOptions(),
        ]);
    }

    /**
     * Normalise the requested calendar month (YYYY-MM) into a Carbon instance.
     */
    private function resolveMonth(string $month): Carbon
    {
        if ($month !== '') {
            try {
                return Carbon::createFromFormat('Y-m', $month)->startOfMonth();
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
     * @return array{brands: Collection, eventTypes: Collection, venues: Collection, pics: Collection}
     */
    private function formOptions(): array
    {
        return [
            'brands' => Brand::where('is_active', true)->get(['id', 'name']),
            'eventTypes' => EventType::where('is_active', true)->get(['id', 'name', 'requires_school', 'kitchen_default']),
            'venues' => Venue::orderBy('name')->get(['id', 'name', 'npsn', 'is_school']),
            'pics' => User::whereHas('roles', fn ($q) => $q->where('slug', 'pic'))->get(['id', 'name']),
        ];
    }

    public function store(Request $request)
    {
        $data = $this->validatedEvent($request);

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
                'brand_id' => $event->brand_id,
                'event_type_id' => $event->event_type_id,
                'venue_id' => $event->venue_id,
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
            ...$this->formOptions(),
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

        $data = $this->validatedEvent($request);
        $old = $event->only(array_keys($data));

        $event->update($data);

        AuditLogger::log('UPDATE_EVENT', 'events', $event, $old, $data);

        return redirect()->route('events.show', $event)->with('success', 'Event diperbarui.');
    }

    /** @return array<string, mixed> */
    private function validatedEvent(Request $request): array
    {
        return $request->validate([
            'event_name' => ['required', 'string', 'max:255'],
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
                'pic' => $event->pic?->name,
            ],
            'readiness' => $ready,
            'fjp' => $canViewFjp ? ($event->fjp ? [
                'id' => $event->fjp->id,
                'fjp_number' => $event->fjp->fjp_number,
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
                'reports' => $event->kitchenRequest->reports->map(fn (KitchenReport $r) => [
                    'id' => $r->id,
                    'prepared_quantity' => $r->prepared_quantity,
                    'condition' => $r->condition,
                    'notes' => $r->notes,
                    'prepared_by' => $r->preparer?->name,
                    'prepared_at' => $r->prepared_at?->toIso8601String(),
                    'photo_url' => $r->photo_path ? route('kitchen.reports.photo', $r->id) : null,
                ])->values(),
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
            return back();
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
        if ($user->hasRole('pic') && ! $user->isSystemAdmin()) {
            $query->where('pic_id', $user->id);
        }

        if ($user->hasRole('team') && ! $user->hasAnyRole(['pic', 'system_admin'])) {
            $query->whereHas('teamMembers', fn ($q) => $q->where('user_id', $user->id));
        }
    }

    private function authorizeView(Request $request, Event $event): void
    {
        abort_unless(EventAccess::allows($request->user(), $event), 403);
    }
}
