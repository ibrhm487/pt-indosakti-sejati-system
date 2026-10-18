<?php

namespace App\Http\Controllers;

use App\Enums\PermitStatus;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\PermitRequest;
use App\Models\User;
use App\Models\Venue;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PermitController extends Controller
{
    public function index(Request $request): Response
    {
        $query = PermitRequest::query()->with(['event:id,event_name', 'venue:id,name', 'assignee:id,name']);

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        $permits = $query->latest()->paginate(15)->withQueryString()->through(fn (PermitRequest $permit) => [
            'id' => $permit->id,
            'permit_number' => $permit->permit_number,
            'event' => $permit->event?->event_name,
            'venue' => $permit->venue?->name,
            'deadline' => $permit->deadline?->toDateString(),
            'assignee' => $permit->assignee?->name,
            'status' => $permit->status->value,
            'status_label' => $permit->status->label(),
        ]);

        return Inertia::render('Permits/Index', [
            'permits' => $permits,
            'filters' => $request->only('status'),
            'statuses' => collect(PermitStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
            'canCreate' => $request->user()->hasPermission('permits.create'),
            ...$this->formOptions(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Permits/Create', $this->formOptions());
    }

    /**
     * @return array{events: Collection, venues: Collection, assignees: Collection}
     */
    private function formOptions(): array
    {
        return [
            'events' => Event::orderBy('event_name')->get(['id', 'event_name']),
            'venues' => Venue::orderBy('name')->get(['id', 'name']),
            'assignees' => User::whereHas('roles', fn ($q) => $q->whereIn('slug', ['permitter']))->get(['id', 'name']),
        ];
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'event_id' => ['required', 'exists:events,id'],
            'venue_id' => ['nullable', 'exists:venues,id'],
            'deadline' => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'notes' => ['nullable', 'string'],
        ]);

        $data['permit_number'] = PermitRequest::generateNumber();
        $data['status'] = PermitStatus::PENDING;
        $data['created_by'] = $request->user()->id;

        $permit = PermitRequest::create($data);

        AuditLogger::log('CREATE_PERMISSION', 'permits', $permit, null, $data);

        return redirect()->route('permits.show', $permit)->with('success', 'Perizinan dibuat.');
    }

    public function show(PermitRequest $permit): Response
    {
        $permit->load(['event:id,event_name', 'venue', 'assignee:id,name', 'documents.uploader:id,name']);

        return Inertia::render('Permits/Show', [
            'permit' => [
                'id' => $permit->id,
                'permit_number' => $permit->permit_number,
                'event' => $permit->event?->event_name,
                'event_id' => $permit->event_id,
                'venue' => $permit->venue?->name,
                'deadline' => $permit->deadline?->toDateString(),
                'assignee' => $permit->assignee?->name,
                'status' => $permit->status->value,
                'status_label' => $permit->status->label(),
                'notes' => $permit->notes,
                'reason' => $permit->reason,
            ],
            'documents' => $permit->documents->map(fn ($d) => [
                'id' => $d->id,
                'category' => $d->category,
                'version' => $d->version,
                'original_name' => $d->original_name,
                'uploaded_at' => $d->created_at?->toIso8601String(),
                'uploaded_by' => $d->uploader?->name,
                'url' => route('permits.documents.download', $d->id),
            ]),
            'history' => AuditLog::query()
                ->where('module', 'permits')
                ->where('record_type', $permit->getMorphClass())
                ->where('record_id', $permit->id)
                ->whereIn('action', [
                    'CREATE_PERMISSION', 'PERMIT_SUBMIT', 'PERMIT_APPROVE',
                    'PERMIT_REJECT', 'PERMIT_REVISION', 'PERMIT_CANCEL',
                    'UPLOAD_PERMIT_DOCUMENT',
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
        ]);
    }

    public function uploadDocument(Request $request, PermitRequest $permit)
    {
        $data = $request->validate([
            'category' => ['nullable', 'string', 'max:50'],
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx'],
        ]);

        $file = $request->file('file');
        $path = $file->store('permits/'.$permit->id);

        $version = $permit->documents()->max('version') + 1;

        $document = $permit->documents()->create([
            'category' => $data['category'] ?? 'supporting',
            'version' => $version,
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => $request->user()->id,
        ]);

        AuditLogger::log('UPLOAD_PERMIT_DOCUMENT', 'permits', $permit, null, ['name' => $document->original_name]);

        return back()->with('success', 'Dokumen perizinan diunggah.');
    }

    public function transition(Request $request, PermitRequest $permit)
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['submit', 'approve', 'reject', 'revision', 'cancel'])],
            'reason' => ['nullable', 'string', 'min:5', 'max:1000'],
        ]);

        $action = $data['action'];
        $old = ['status' => $permit->status->value];

        if (in_array($action, ['reject', 'revision', 'cancel'], true) && empty($data['reason'])) {
            return back()->withErrors(['reason' => 'Alasan wajib diisi untuk tindakan ini.']);
        }

        $required = match ($action) {
            'submit' => 'permits.update',
            'approve' => 'permits.approve',
            'reject' => 'permits.reject',
            'revision' => 'permits.reject',
            'cancel' => 'permits.update',
        };

        abort_unless($request->user()->hasPermission($required), 403);

        $newStatus = match ($action) {
            'submit' => PermitStatus::SUBMITTED,
            'approve' => PermitStatus::APPROVED,
            'reject' => PermitStatus::REJECTED,
            'revision' => PermitStatus::PENDING,
            'cancel' => PermitStatus::CANCELLED,
        };

        if (! $permit->status->canTransitionTo($newStatus)) {
            return back()->withErrors([
                'status' => sprintf(
                    'Transisi %s → %s tidak diizinkan. Status saat ini: %s.',
                    $permit->status->label(),
                    $newStatus->label(),
                    $permit->status->label(),
                ),
            ]);
        }

        $permit->update([
            'status' => $newStatus,
            'reason' => in_array($action, ['reject', 'revision'], true) ? $data['reason'] : $permit->reason,
            'decided_by' => $request->user()->id,
            'decided_at' => now(),
        ]);

        AuditLogger::log('PERMIT_'.strtoupper($action), 'permits', $permit, $old, [
            'status' => $permit->status->value,
            'reason' => $data['reason'] ?? null,
        ]);

        return back()->with('success', 'Status perizinan diperbarui.');
    }
}
