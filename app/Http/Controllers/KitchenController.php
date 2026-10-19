<?php

namespace App\Http\Controllers;

use App\Enums\KitchenStatus;
use App\Models\Event;
use App\Models\KitchenReport;
use App\Models\KitchenRequest;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\EventAccess;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class KitchenController extends Controller
{
    public function index(Request $request): Response
    {
        $query = KitchenRequest::query()->with(['event:id,event_name,event_date,jug_required', 'assignee:id,name']);

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        $requests = $query->latest()->paginate(15)->withQueryString()->through(fn (KitchenRequest $r) => [
            'id' => $r->id,
            'event' => $r->event?->event_name,
            'event_date' => $r->event?->event_date?->toDateString(),
            'jug_quantity' => $r->jug_quantity,
            'preparation_date' => $r->preparation_date?->toDateString(),
            'assignee' => $r->assignee?->name,
            'status' => $r->status->value,
            'status_label' => $r->status->label(),
        ]);

        return Inertia::render('Kitchen/Index', [
            'requests' => $requests,
            'filters' => $request->only('status'),
            'statuses' => collect(KitchenStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
            'canCreate' => $request->user()->hasPermission('kitchen.manage'),
            ...$this->formOptions(),
        ]);
    }

    /**
     * Events that require JUG but have no kitchen request yet.
     */
    public function create(): Response
    {
        return Inertia::render('Kitchen/Create', $this->formOptions());
    }

    /**
     * @return array{events: Collection, staff: Collection}
     */
    private function formOptions(): array
    {
        return [
            'events' => Event::where('jug_required', true)
                ->whereDoesntHave('kitchenRequest')
                ->orderBy('event_name')
                ->get(['id', 'event_name', 'event_date', 'sampling_target']),
            'staff' => User::whereHas('roles', fn ($q) => $q->where('slug', 'kitchen'))->get(['id', 'name']),
        ];
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'event_id' => ['required', 'exists:events,id'],
            'jug_quantity' => ['required', 'integer', 'min:1'],
            'preparation_date' => ['nullable', 'date'],
            'preparation_time' => ['nullable', 'date_format:H:i'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'notes' => ['nullable', 'string'],
        ]);

        $event = Event::findOrFail($data['event_id']);

        if (! $event->jug_required) {
            return back()->withErrors(['event_id' => 'Event ini tidak membutuhkan JUG.']);
        }

        try {
            $kitchen = KitchenRequest::create([
                ...$data,
                'status' => KitchenStatus::PENDING,
            ]);
        } catch (UniqueConstraintViolationException) {
            // event_id is unique: a concurrent double submit must fail gently
            // instead of surfacing as a raw database error.
            return back()->withErrors(['event_id' => 'Event ini sudah memiliki tugas kitchen.']);
        }

        AuditLogger::log('CREATE_KITCHEN', 'kitchen', $kitchen, null, $data);

        return redirect()->route('kitchen.show', $kitchen)->with('success', 'Tugas kitchen dibuat.');
    }

    public function show(Request $request, KitchenRequest $kitchen): Response
    {
        $kitchen->load(['event:id,event_name,event_date,sampling_target', 'assignee:id,name', 'reports.preparer:id,name']);

        return Inertia::render('Kitchen/Show', [
            'kitchen' => [
                'id' => $kitchen->id,
                'event' => $kitchen->event?->event_name,
                'event_id' => $kitchen->event_id,
                'event_date' => $kitchen->event?->event_date?->toDateString(),
                'jug_quantity' => $kitchen->jug_quantity,
                'preparation_date' => $kitchen->preparation_date?->toDateString(),
                'preparation_time' => $kitchen->preparation_time,
                'assignee' => $kitchen->assignee?->name,
                'notes' => $kitchen->notes,
                'status' => $kitchen->status->value,
                'status_label' => $kitchen->status->label(),
            ],
            'reports' => $kitchen->reports->map(fn ($r) => [
                'id' => $r->id,
                'prepared_quantity' => $r->prepared_quantity,
                'condition' => $r->condition,
                'notes' => $r->notes,
                'prepared_by' => $r->preparer?->name,
                'prepared_at' => $r->prepared_at?->toIso8601String(),
                'photo_url' => $r->photo_path ? route('kitchen.reports.photo', $r->id) : null,
                'washing_photo_url' => $r->washing_photo_path ? route('kitchen.reports.washing-photo', $r->id) : null,
            ]),
            'statuses' => collect(KitchenStatus::cases())
                ->reject(fn ($s) => $s === KitchenStatus::NOT_REQUIRED)
                ->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()])->values(),
            'can' => ['manage' => $request->user()->hasPermission('kitchen.manage')],
        ]);
    }

    public function updateStatus(Request $request, KitchenRequest $kitchen)
    {
        abort_unless($request->user()->hasPermission('kitchen.manage'), 403);

        $data = $request->validate([
            'status' => ['required', Rule::enum(KitchenStatus::class)],
        ]);

        $target = KitchenStatus::from($data['status']);

        if ($target === $kitchen->status) {
            return back();
        }

        if (! $kitchen->status->canTransitionTo($target)) {
            return back()->withErrors([
                'status' => "Status {$kitchen->status->label()} tidak dapat langsung diubah ke {$target->label()}.",
            ]);
        }

        $old = ['status' => $kitchen->status->value];
        $kitchen->update(['status' => $target]);

        AuditLogger::log('UPDATE_KITCHEN', 'kitchen', $kitchen, $old, ['status' => $target->value]);

        return back()->with('success', 'Status kitchen diperbarui.');
    }

    public function addReport(Request $request, KitchenRequest $kitchen)
    {
        abort_unless($request->user()->hasPermission('kitchen.manage'), 403);

        $data = $request->validate([
            'prepared_quantity' => ['required', 'integer', 'min:0'],
            'condition' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
            'photo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:4096'],
            'washing_photo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:4096'],
        ]);

        $reportData = [
            'prepared_quantity' => $data['prepared_quantity'],
            'condition' => $data['condition'] ?? null,
            'notes' => $data['notes'] ?? null,
            'prepared_by' => $request->user()->id,
            'prepared_at' => now(),
        ];

        if ($request->hasFile('photo')) {
            $reportData['photo_path'] = $request->file('photo')->store('kitchen/reports');
        }

        if ($request->hasFile('washing_photo')) {
            $reportData['washing_photo_path'] = $request->file('washing_photo')->store('kitchen/reports/washing');
        }

        $report = $kitchen->reports()->create($reportData);

        AuditLogger::log('KITCHEN_REPORT', 'kitchen', $kitchen, null, $reportData);

        return back()->with('success', 'Laporan kesiapan kitchen dicatat.');
    }

    public function downloadPhoto(KitchenReport $report): SymfonyResponse
    {
        $this->authorizePhotoAccess($report);

        abort_unless($report->photo_path && $this->photoExists($report->photo_path), 404);

        return $this->streamPhoto($report->photo_path);
    }

    public function downloadWashingPhoto(KitchenReport $report): SymfonyResponse
    {
        $this->authorizePhotoAccess($report);

        abort_unless($report->washing_photo_path && $this->photoExists($report->washing_photo_path), 404);

        return $this->streamPhoto($report->washing_photo_path);
    }

    private function authorizePhotoAccess(KitchenReport $report): void
    {
        $user = request()->user();

        abort_unless($user->hasPermission('kitchen.view'), 403);

        $kitchen = $report->kitchenRequest()->with('event')->firstOrFail();

        abort_unless(EventAccess::allows($user, $kitchen->event), 403);
    }

    private function photoExists(string $path): bool
    {
        return Storage::disk('local')->exists($path) || Storage::disk('public')->exists($path);
    }

    private function streamPhoto(string $path): SymfonyResponse
    {
        if (Storage::disk('local')->exists($path)) {
            return Storage::disk('local')->response($path);
        }

        return Storage::disk('public')->response($path);
    }
}
