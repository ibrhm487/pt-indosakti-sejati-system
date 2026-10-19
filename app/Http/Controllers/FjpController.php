<?php

namespace App\Http\Controllers;

use App\Enums\EventStatus;
use App\Enums\PermitStatus;
use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Event;
use App\Models\EventType;
use App\Models\Fjp;
use App\Models\User;
use App\Models\Venue;
use App\Services\AuditLogger;
use App\Services\DapoApiService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class FjpController extends Controller
{
    private const DAPODIK_SOURCE = 'Dapodik (dapo.kemendikdasmen.go.id)';

    /** Inline event-status options shown as an editable dropdown in the FJP list. */
    private const STATUS_OPTIONS = [
        'plan' => 'Plan',
        'confirm' => 'Confirm',
        'cancel' => 'Cancel',
        'reschedule' => 'Reschedule',
        'done' => 'Done',
    ];

    public function index(Request $request): Response
    {
        $base = Fjp::query();

        if ($search = $request->string('search')->toString()) {
            $base->where(function ($q) use ($search) {
                $q->where('event_name', 'like', "%{$search}%")
                    ->orWhere('fjp_number', 'like', "%{$search}%")
                    ->orWhere('school_name', 'like', "%{$search}%");
            });
        }

        $counts = (clone $base)
            ->selectRaw('permit_status, count(*) as aggregate')
            ->groupBy('permit_status')
            ->pluck('aggregate', 'permit_status');

        $statusCounts = collect(PermitStatus::cases())
            ->mapWithKeys(fn (PermitStatus $s) => [$s->value => (int) ($counts[$s->value] ?? 0)]);

        $query = clone $base;

        if ($status = $request->string('permit_status')->toString()) {
            $query->where('permit_status', $status);
        }

        $fjps = $query->with(['brand:id,name'])
            ->withCount('attachments')
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Fjp $fjp) => [
                'id' => $fjp->id,
                'attachments_count' => (int) $fjp->attachments_count,
                'fjp_number' => $fjp->fjp_number,
                'event_name' => $fjp->event_name,
                'brand' => $fjp->brand?->name,
                'brand_id' => $fjp->brand_id,
                'event_type_id' => $fjp->event_type_id,
                'venue_id' => $fjp->venue_id,
                'event_date' => $fjp->event_date?->toDateString(),
                'start_time' => $fjp->start_time,
                'end_time' => $fjp->end_time,
                'sampling_target' => $fjp->sampling_target,
                'jug_required' => $fjp->jug_required,
                'school_name' => $fjp->school_name,
                'npsn' => $fjp->npsn,
                'address' => $fjp->address,
                'maps_url' => $fjp->maps_url,
                'sekolah' => $fjp->jumlah_siswa_sekolah,
                'dapodik' => $fjp->jumlah_siswa_dapodik,
                'difference' => $fjp->student_count_difference,
                'jumlah_siswa_sekolah' => $fjp->jumlah_siswa_sekolah,
                'jumlah_siswa_dapodik' => $fjp->jumlah_siswa_dapodik,
                'dapodik_source' => $fjp->dapodik_source,
                'status' => $fjp->status,
                'status_label' => self::STATUS_OPTIONS[$fjp->status] ?? ucfirst((string) $fjp->status),
                'notes' => $fjp->notes,
                'permit_status' => $fjp->permit_status->value,
                'permit_status_label' => $fjp->permit_status->label(),
            ]);

        return Inertia::render('Fjp/Index', [
            'fjps' => $fjps,
            'filters' => $request->only('search', 'permit_status'),
            'permitStatuses' => collect(PermitStatus::cases())->map(fn ($s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ]),
            'statusCounts' => $statusCounts,
            'totalCount' => (clone $base)->count(),
            'statusOptions' => collect(self::STATUS_OPTIONS)->map(fn ($label, $value) => [
                'value' => $value,
                'label' => $label,
            ])->values(),
            'formOptions' => $this->formOptions(),
        ]);
    }

    /**
     * Inline update of the FJP status, note, and/or coordinates from the list view.
     */
    public function updateInline(Request $request, Fjp $fjp)
    {
        $data = $request->validate([
            'status' => ['sometimes', 'string', Rule::in(array_keys(self::STATUS_OPTIONS))],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'address' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'maps_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
        ]);

        if ($data === []) {
            return back();
        }

        $old = $fjp->only(array_keys($data));

        $fjp->update($data);

        AuditLogger::log('UPDATE_FJP_INLINE', 'fjp', $fjp, $old, $data);

        return isset($data['status'])
            ? back()->with('success', 'Status FJP diperbarui.')
            : back();
    }

    public function create(): Response
    {
        return Inertia::render('Fjp/Create', ['formOptions' => $this->formOptions()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        if (($data['jumlah_siswa_dapodik'] ?? null) === null && ($data['dapodik_source'] ?? null) === 'Manual') {
            $data['dapodik_source'] = null;
        }
        $data['dapodik_synced_at'] = ($data['dapodik_source'] ?? null) === self::DAPODIK_SOURCE
            ? now()
            : null;
        $data['created_by'] = $request->user()->id;
        $data['permit_status'] = PermitStatus::DRAFT;
        $data['status'] = 'draft';
        $data['fjp_number'] = Fjp::generateNumber();
        $data['event_name'] = $data['event_name'] ?? ($data['school_name'] ?? null);

        $fjp = Fjp::create($data);

        AuditLogger::log('CREATE_FJP', 'fjp', $fjp, null, $data);

        return redirect()->route('fjp.show', $fjp)->with('success', 'FJP berhasil dibuat.');
    }

    public function show(Fjp $fjp): Response
    {
        $fjp->load(['attachments.uploader:id,name', 'events' => fn ($query) => $query->latest()]);

        $user = request()->user();

        return Inertia::render('Fjp/Show', [
            'fjp' => [
                'id' => $fjp->id,
                'fjp_number' => $fjp->fjp_number,
                'event_name' => $fjp->event_name,
                'school_name' => $fjp->school_name,
                'npsn' => $fjp->npsn,
                'address' => $fjp->address,
                'maps_url' => $fjp->maps_url,
                'jumlah_siswa_sekolah' => $fjp->jumlah_siswa_sekolah,
                'jumlah_siswa_dapodik' => $fjp->jumlah_siswa_dapodik,
                'difference' => $fjp->student_count_difference,
                'dapodik_source' => $fjp->dapodik_source,
                'dapodik_synced_at' => $fjp->dapodik_synced_at?->toIso8601String(),
                'notes' => $fjp->notes,
                'event_date' => $fjp->event_date?->toDateString(),
                'sampling_target' => $fjp->sampling_target,
                'jug_required' => $fjp->jug_required,
                'permit_status' => $fjp->permit_status->value,
                'permit_status_label' => $fjp->permit_status->label(),
                'rejection_reason' => $fjp->rejection_reason,
                'cancellation_reason' => $fjp->cancellation_reason,
            ],
            'attachments' => $fjp->attachments->map(fn ($a) => [
                'id' => $a->id,
                'category' => $a->category,
                'original_name' => $a->original_name,
                'uploaded_at' => $a->created_at?->toIso8601String(),
                'uploaded_by' => $a->uploader?->name,
                'url' => route('fjp.attachments.download', $a->id),
            ]),
            'history' => AuditLog::query()
                ->where('module', 'fjp')
                ->where('record_type', $fjp->getMorphClass())
                ->where('record_id', $fjp->id)
                ->whereIn('action', [
                    'CREATE_FJP', 'UPDATE_FJP', 'UPDATE_FJP_INLINE', 'DELETE_FJP',
                    'FJP_SUBMIT', 'FJP_APPROVE', 'FJP_REJECT', 'FJP_CANCEL',
                    'SYNC_DAPODIK', 'UPLOAD_FJP_ATTACHMENT',
                ])
                ->with('user:id,name')
                ->latest()
                ->limit(30)
                ->get()
                ->map(fn (AuditLog $log) => [
                    'id' => $log->id,
                    'action' => $log->action,
                    'user' => $log->user?->name,
                    'role' => $log->role,
                    'created_at' => $log->created_at?->toIso8601String(),
                    'reason' => $log->new_values['reason'] ?? null,
                ]),
            'events' => $fjp->events->map(fn (Event $event) => [
                'id' => $event->id,
                'event_code' => $event->event_code,
                'event_name' => $event->event_name,
                'event_date' => $event->event_date?->toDateString(),
                'status' => $event->status->value,
                'status_label' => $event->status->label(),
                'url' => route('events.show', $event->id),
            ])->values(),
            'pics' => User::whereHas('roles', fn ($q) => $q->where('slug', 'pic'))
                ->orderBy('name')
                ->get(['id', 'name']),
            'can' => [
                'update' => $user->hasPermission('fjp.update'),
                'delete' => $user->hasPermission('fjp.delete'),
                'create_event' => $user->hasPermission('events.create'),
                'approve' => $user->hasPermission('permits.approve'),
                'reject' => $user->hasPermission('permits.reject'),
                'cancel' => $user->hasPermission('permits.update'),
            ],
        ]);
    }

    /**
     * Spin up an operational event from an approved FJP, seeding the event with
     * the FJP's scheduling details so the PIC never re-types shared data.
     */
    public function createEvent(Request $request, Fjp $fjp)
    {
        abort_unless($request->user()->hasPermission('events.create'), 403);

        if ($fjp->permit_status !== PermitStatus::APPROVED) {
            return back()->withErrors([
                'event' => 'Event hanya dapat dibuat dari FJP yang perizinannya sudah disetujui.',
            ]);
        }

        $alreadyExists = $fjp->events()
            ->whereNot('status', EventStatus::CANCELLED)
            ->exists();

        if ($alreadyExists) {
            return back()->withErrors([
                'event' => 'FJP ini sudah memiliki event aktif. Buka event terkait untuk melanjutkan.',
            ]);
        }

        $data = $request->validate([
            'pic_id' => ['nullable', 'exists:users,id'],
            'event_date' => ['nullable', 'date'],
            'sampling_target' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $event = Event::create([
            'event_code' => Event::generateCode(),
            'event_name' => $fjp->event_name ?: ($fjp->school_name ?: 'Event '.$fjp->fjp_number),
            'fjp_id' => $fjp->id,
            'brand_id' => $fjp->brand_id,
            'event_type_id' => $fjp->event_type_id,
            'venue_id' => $fjp->venue_id,
            'event_date' => $data['event_date'] ?? $fjp->event_date?->toDateString(),
            'start_time' => $fjp->start_time,
            'end_time' => $fjp->end_time,
            'sampling_target' => $data['sampling_target'] ?? $fjp->sampling_target,
            'notes' => $data['notes'] ?? $fjp->notes,
            'jug_required' => $fjp->jug_required,
            'status' => EventStatus::PLANNING,
            'pic_id' => $data['pic_id'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        AuditLogger::log('CREATE_EVENT_FROM_FJP', 'events', $event, null, [
            'fjp_id' => $fjp->id,
            'pic_id' => $event->pic_id,
        ]);

        return redirect()->route('events.show', $event)->with('success', 'Event berhasil dibuat dari FJP.');
    }

    public function edit(Fjp $fjp): Response
    {
        return Inertia::render('Fjp/Edit', [
            'fjp' => $fjp,
            'formOptions' => $this->formOptions(),
        ]);
    }

    public function update(Request $request, Fjp $fjp)
    {
        $data = $this->validated($request);

        if (($data['jumlah_siswa_dapodik'] ?? null) === null && ($data['dapodik_source'] ?? null) === 'Manual') {
            $data['dapodik_source'] = null;
        }

        $source = $data['dapodik_source'] ?? null;

        if ($source !== self::DAPODIK_SOURCE) {
            $data['dapodik_synced_at'] = null;
        } elseif (
            $fjp->dapodik_source !== $source
            || $fjp->npsn !== ($data['npsn'] ?? null)
            || $fjp->jumlah_siswa_dapodik !== ($data['jumlah_siswa_dapodik'] ?? null)
        ) {
            $data['dapodik_synced_at'] = now();
        }

        $old = $fjp->only(array_merge(array_keys($data), ['dapodik_synced_at']));

        $fjp->update($data);

        AuditLogger::log('UPDATE_FJP', 'fjp', $fjp, $old, $data);

        return redirect()->route('fjp.show', $fjp)->with('success', 'FJP diperbarui.');
    }

    public function destroy(Fjp $fjp)
    {
        AuditLogger::log('DELETE_FJP', 'fjp', $fjp);
        $fjp->delete();

        return redirect()->route('fjp.index')->with('success', 'FJP dihapus.');
    }

    /**
     * Sync the school's data from the public Dapodik database. Updates only
     * the Dapodik-sourced fields; the school-reported student count is never
     * overwritten.
     */
    public function syncDapodik(Request $request, Fjp $fjp, DapoApiService $dapo)
    {
        $data = $request->validate([
            'npsn' => ['nullable', 'string', 'max:20'],
        ]);

        $npsn = trim((string) ($data['npsn'] ?? $fjp->npsn));
        try {
            $detail = $npsn !== '' ? $dapo->detail($npsn) : null;
        } catch (RuntimeException) {
            return back()->with('error', 'Layanan Dapodik sedang tidak tersedia. Silakan coba lagi.');
        }

        $old = ['jumlah_siswa_dapodik' => $fjp->jumlah_siswa_dapodik];

        if ($detail) {
            $fjp->update([
                'npsn' => $detail['npsn'] ?? $npsn,
                'school_name' => $fjp->school_name ?: ($detail['school_name'] ?? null),
                'address' => $fjp->address ?: ($detail['address'] ?? null),
                'maps_url' => $fjp->maps_url ?: ($detail['maps_url'] ?? null),
                'jumlah_siswa_dapodik' => $detail['jumlah_siswa'] ?? $fjp->jumlah_siswa_dapodik,
                'dapodik_source' => 'Dapodik (dapo.kemendikdasmen.go.id)',
                'dapodik_synced_at' => now(),
            ]);
        }

        AuditLogger::log('SYNC_DAPODIK', 'fjp', $fjp, $old, [
            'jumlah_siswa_dapodik' => $fjp->jumlah_siswa_dapodik,
            'source' => 'Dapodik (dapo.kemendikdasmen.go.id)',
        ]);

        return back()->with(
            $detail ? 'success' : 'error',
            $detail ? 'Data Dapodik diperbarui.' : 'Data sekolah tidak ditemukan di Dapodik.'
        );
    }

    public function uploadAttachment(Request $request, Fjp $fjp)
    {
        $data = $request->validate([
            'category' => ['nullable', 'string', 'max:50'],
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx'],
        ]);

        $file = $request->file('file');
        $path = $file->store('fjp/'.$fjp->id);

        $attachment = $fjp->attachments()->create([
            'category' => $data['category'] ?? 'other',
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => $request->user()->id,
        ]);

        AuditLogger::log('UPLOAD_FJP_ATTACHMENT', 'fjp', $fjp, null, ['name' => $attachment->original_name]);

        return back()->with('success', 'Lampiran diunggah.');
    }

    /**
     * Permit workflow transitions for an FJP. Every action maps to a target
     * status that must be reachable from the current one (see
     * PermitStatus::allowedTransitions); invalid jumps — e.g. approving a
     * draft directly or reviving a cancelled FJP — are rejected.
     */
    public function workflow(Request $request, Fjp $fjp)
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['submit', 'approve', 'reject', 'cancel'])],
            'reason' => ['nullable', 'string', 'min:5', 'max:1000'],
        ]);

        $action = $data['action'];
        $old = ['permit_status' => $fjp->permit_status->value];

        $requiredPermission = match ($action) {
            'submit' => 'fjp.update',
            'approve' => 'permits.approve',
            'reject' => 'permits.reject',
            'cancel' => 'permits.update',
        };
        $this->authorizeFjpAction($request, [$requiredPermission]);

        $newStatus = match ($action) {
            'submit' => PermitStatus::SUBMITTED,
            'approve' => PermitStatus::APPROVED,
            'reject' => PermitStatus::REJECTED,
            'cancel' => PermitStatus::CANCELLED,
        };

        if (! $fjp->permit_status->canTransitionTo($newStatus)) {
            return back()->withErrors([
                'permit_status' => sprintf(
                    'Transisi %s → %s tidak diizinkan. Status saat ini: %s.',
                    $fjp->permit_status->label(),
                    $newStatus->label(),
                    $fjp->permit_status->label(),
                ),
            ]);
        }

        switch ($action) {
            case 'submit':
                $fjp->update(['permit_status' => PermitStatus::SUBMITTED, 'status' => 'submitted']);
                break;
            case 'approve':
                $fjp->update(['permit_status' => PermitStatus::APPROVED, 'status' => 'approved']);
                break;
            case 'reject':
                if (empty($data['reason'])) {
                    return back()->withErrors(['reason' => 'Alasan penolakan wajib diisi.']);
                }
                $fjp->update([
                    'permit_status' => PermitStatus::REJECTED,
                    'status' => 'rejected',
                    'rejection_reason' => $data['reason'],
                ]);
                break;
            case 'cancel':
                if (empty($data['reason'])) {
                    return back()->withErrors(['reason' => 'Alasan pembatalan wajib diisi.']);
                }
                $fjp->update([
                    'permit_status' => PermitStatus::CANCELLED,
                    'status' => 'cancelled',
                    'cancellation_reason' => $data['reason'],
                ]);
                break;
        }

        AuditLogger::log('FJP_'.strtoupper($action), 'fjp', $fjp, $old, [
            'permit_status' => $fjp->permit_status->value,
            'reason' => $data['reason'] ?? null,
        ]);

        return back()->with('success', 'Status FJP diperbarui.');
    }

    private function authorizeFjpAction(Request $request, array $permissions): void
    {
        foreach ($permissions as $permission) {
            if ($request->user()->hasPermission($permission)) {
                return;
            }
        }

        abort(403, 'Anda tidak memiliki izin untuk tindakan ini.');
    }

    /**
     * Master-data selects shared by the FJP create/edit/index forms.
     *
     * @return array{brands: Collection<int, Brand>, eventTypes: Collection<int, EventType>, venues: Collection<int, Venue>}
     */
    private function formOptions(): array
    {
        return [
            'brands' => Brand::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'eventTypes' => EventType::orderBy('name')->get(['id', 'name']),
            'venues' => Venue::orderBy('name')->get(['id', 'name']),
        ];
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'event_name' => ['nullable', 'string', 'max:255'],
            'brand_id' => ['nullable', 'exists:brands,id'],
            'event_type_id' => ['nullable', 'exists:event_types,id'],
            'venue_id' => ['nullable', 'exists:venues,id'],
            'school_name' => ['nullable', 'string', 'max:255'],
            'npsn' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:1000'],
            'maps_url' => ['nullable', 'string', 'max:2048'],
            'jumlah_siswa_sekolah' => ['nullable', 'integer', 'min:0'],
            'jumlah_siswa_dapodik' => ['nullable', 'integer', 'min:0'],
            'dapodik_source' => ['nullable', Rule::in(['Manual', self::DAPODIK_SOURCE])],
            'event_date' => ['nullable', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'sampling_target' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
            'jug_required' => ['sometimes', 'boolean'],
        ]);
    }
}
