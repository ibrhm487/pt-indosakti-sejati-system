<?php

namespace App\Http\Controllers;

use App\Enums\KitchenStatus;
use App\Models\Event;
use App\Models\KitchenReport;
use App\Models\KitchenReportMedia;
use App\Models\KitchenRequest;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\EventAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class KitchenController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $query = KitchenRequest::query()->with([
            'event:id,event_name,event_date,jug_required',
            'assignee:id,name',
            'reports:id,kitchen_request_id,washing_at,cooking_at,ready_at,washing_photo_path,photo_path,ready_photo_path',
        ]);

        EventAccess::scope($query, $user);

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        if ($eventId = $request->integer('event_id')) {
            $query->where('event_id', $eventId);
        }

        if ($date = $request->string('date')->toString()) {
            $query->where(function (Builder $query) use ($date) {
                $query->whereDate('preparation_date', $date)
                    ->orWhereHas('event', fn (Builder $eventQuery) => $eventQuery->whereDate('event_date', $date));
            });
        }

        $requests = $query->latest()->paginate(15)->withQueryString()->through(function (KitchenRequest $r) {
            $report = $r->reports->sortByDesc('id')->first();
            $stage = $this->stageOf($report);

            return [
                'id' => $r->id,
                'event' => $r->event?->event_name,
                'event_id' => $r->event_id,
                'event_date' => $r->event?->event_date?->toDateString(),
                'effective_date' => $r->preparation_date?->toDateString()
                    ?? $r->event?->event_date?->toDateString(),
                'jug_quantity' => $r->jug_quantity,
                'preparation_date' => $r->preparation_date?->toDateString(),
                'assignee' => $r->assignee?->name,
                'status' => $r->status->value,
                'status_label' => $r->status->label(),
                'stage' => $stage,
                'stage_label' => $this->stageLabel($stage),
            ];
        });

        return Inertia::render('Kitchen/Index', [
            'requests' => $requests,
            'filters' => $request->only('status', 'event_id', 'date'),
            'statuses' => collect(KitchenStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
            'canCreate' => $user->hasPermission('kitchen.manage'),
            'reportMode' => $user->activeRoleSlug() === 'kitchen',
            'filterEvents' => EventAccess::scope(Event::query()->whereHas('kitchenRequest'), $user, 'id')
                ->orderBy('event_name')
                ->get(['id', 'event_name']),
            ...$this->formOptions(),
        ]);
    }

    /**
     * Events that require JUG but have no kitchen request yet.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('Kitchen/Create', [
            ...$this->formOptions(),
            'reportMode' => $request->user()->activeRoleSlug() === 'kitchen',
        ]);
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
        abort_unless(EventAccess::allows($request->user(), $kitchen->event), 403);

        $kitchen->load([
            'event:id,event_name,event_date,sampling_target',
            'assignee:id,name',
            'reports.preparer:id,name',
            'reports.media',
        ]);

        $reports = $kitchen->reports->sortBy('id')->values();
        $current = $reports->last();
        $stage = $this->stageOf($current);

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
                'stage' => $stage,
                'stage_label' => $this->stageLabel($stage),
                'completed' => $current?->isComplete() ?? false,
            ],
            'workflow' => $current ? $this->workflowPayload($current) : null,
            'reports' => $reports->map(fn (KitchenReport $r) => $this->reportPayload($r))->values(),
            'statuses' => collect(KitchenStatus::cases())
                ->reject(fn ($s) => $s === KitchenStatus::NOT_REQUIRED)
                ->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()])->values(),
            'can' => [
                'manage' => $request->user()->hasPermission('kitchen.manage'),
                'report_mode' => $request->user()->activeRoleSlug() === 'kitchen',
            ],
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
            return back()->with('info', 'Status kitchen tidak berubah.');
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

    public function storeWashingReport(Request $request, KitchenRequest $kitchen)
    {
        abort_unless($request->user()->hasPermission('kitchen.manage'), 403);

        if ($kitchen->status === KitchenStatus::CANCELLED) {
            return back()->withErrors(['washing_photos' => 'Tugas kitchen sudah dibatalkan.']);
        }

        $report = $kitchen->currentReport();

        if ($report?->isComplete()) {
            return back()->withErrors(['washing_photos' => 'Tugas kitchen sudah selesai.']);
        }

        $data = $request->validate([
            'washing_photos' => ['required', 'array', 'min:1', 'max:20'],
            'washing_photos.*' => ['image', 'mimes:jpeg,png,jpg,webp', 'max:10240'],
            'notes' => ['nullable', 'string'],
        ]);

        $now = now();

        DB::transaction(function () use ($request, $kitchen, $report, $data, $now) {
            $report ??= new KitchenReport([
                'kitchen_request_id' => $kitchen->id,
                'prepared_quantity' => $kitchen->jug_quantity,
            ]);
            $report->save();

            $payloads = $this->storeStagePhotos($data['washing_photos'], 'kitchen/reports/washing');
            $this->attachMedia($report, KitchenReportMedia::TYPE_WASHING, $payloads);

            $update = [
                'prepared_by' => $request->user()->id,
                'washing_at' => $report->washing_at ?? $now,
                'washing_photo_path' => $report->washing_photo_path ?? $payloads[0]['path'],
            ];

            if (! $report->prepared_at) {
                $update['prepared_at'] = $now;
            }

            if (! empty($data['notes'])) {
                $update['notes'] = $data['notes'];
            }

            $report->update($update);
            $this->advanceKitchenStatus($kitchen, KitchenStatus::PREPARING);
        });

        AuditLogger::log('KITCHEN_WASHING_REPORT', 'kitchen', $kitchen, null, [
            'photos' => count($data['washing_photos']),
        ]);

        return back()->with('success', 'Laporan cuci JUG tersimpan. Lanjutkan ke tahap masak susu.');
    }

    public function storeCookingReport(Request $request, KitchenRequest $kitchen)
    {
        abort_unless($request->user()->hasPermission('kitchen.manage'), 403);

        if ($kitchen->status === KitchenStatus::CANCELLED) {
            return back()->withErrors(['cooking_photos' => 'Tugas kitchen sudah dibatalkan.']);
        }

        $report = $kitchen->currentReport();

        if (! $report || (! $report->washing_at && ! $report->washing_photo_path)) {
            return back()->withErrors(['cooking_photos' => 'Simpan laporan cuci JUG terlebih dahulu.']);
        }

        if ($report->isComplete()) {
            return back()->withErrors(['cooking_photos' => 'Tugas kitchen sudah selesai.']);
        }

        $data = $request->validate([
            'cooking_photos' => ['required', 'array', 'min:1', 'max:20'],
            'cooking_photos.*' => ['image', 'mimes:jpeg,png,jpg,webp', 'max:10240'],
            'notes' => ['nullable', 'string'],
        ]);

        $now = now();

        DB::transaction(function () use ($request, $kitchen, $report, $data, $now) {
            $payloads = $this->storeStagePhotos($data['cooking_photos'], 'kitchen/reports/cooking');
            $this->attachMedia($report, KitchenReportMedia::TYPE_COOKING, $payloads);

            $update = [
                'prepared_by' => $request->user()->id,
                'cooking_at' => $report->cooking_at ?? $now,
                'prepared_at' => $report->cooking_at ?? $now,
                'photo_path' => $report->photo_path ?? $payloads[0]['path'],
            ];

            if (! empty($data['notes'])) {
                $update['notes'] = $data['notes'];
            }

            $report->update($update);
            $this->advanceKitchenStatus($kitchen, KitchenStatus::PREPARING);
        });

        AuditLogger::log('KITCHEN_COOKING_REPORT', 'kitchen', $kitchen, null, [
            'photos' => count($data['cooking_photos']),
        ]);

        return back()->with('success', 'Laporan masak susu tersimpan. Lanjutkan ke foto JUG siap.');
    }

    public function storeReadyReport(Request $request, KitchenRequest $kitchen)
    {
        abort_unless($request->user()->hasPermission('kitchen.manage'), 403);

        if ($kitchen->status === KitchenStatus::CANCELLED) {
            return back()->withErrors(['ready_photos' => 'Tugas kitchen sudah dibatalkan.']);
        }

        $report = $kitchen->currentReport();

        if (! $report || (! $report->cooking_at && ! $report->photo_path)) {
            return back()->withErrors(['ready_photos' => 'Simpan laporan masak susu terlebih dahulu.']);
        }

        if ($report->isComplete()) {
            return back()->withErrors(['ready_photos' => 'Tugas kitchen sudah selesai.']);
        }

        $data = $request->validate([
            'ready_photos' => ['required', 'array', 'min:1', 'max:20'],
            'ready_photos.*' => ['image', 'mimes:jpeg,png,jpg,webp', 'max:10240'],
            'notes' => ['nullable', 'string'],
        ]);

        $now = now();

        DB::transaction(function () use ($request, $kitchen, $report, $data, $now) {
            $payloads = $this->storeStagePhotos($data['ready_photos'], 'kitchen/reports/ready');
            $this->attachMedia($report, KitchenReportMedia::TYPE_READY, $payloads);

            $update = [
                'prepared_by' => $request->user()->id,
                'ready_at' => $report->ready_at ?? $now,
                'ready_photo_path' => $report->ready_photo_path ?? $payloads[0]['path'],
            ];

            if (! empty($data['notes'])) {
                $update['notes'] = $data['notes'];
            }

            $report->update($update);
            $this->advanceKitchenStatus($kitchen, KitchenStatus::READY);
        });

        AuditLogger::log('KITCHEN_READY_REPORT', 'kitchen', $kitchen, null, [
            'photos' => count($data['ready_photos']),
        ]);

        return back()->with('success', 'Laporan JUG siap tersimpan. Tugas kitchen selesai.');
    }

    public function downloadMedia(KitchenReportMedia $media): SymfonyResponse
    {
        $this->authorizeMediaAccess($media);

        abort_unless($this->photoExists($media->path), 404);

        return $this->streamPhoto($media->path);
    }

    public function destroyMedia(Request $request, KitchenReportMedia $media)
    {
        abort_unless($request->user()->hasPermission('kitchen.manage'), 403);

        $this->authorizeMediaAccess($media);

        $media->loadMissing('kitchenReport');
        $report = $media->kitchenReport;

        if ($report->isComplete()) {
            return back()->withErrors(['media' => 'Tugas kitchen sudah selesai; foto tidak dapat dihapus.']);
        }

        $type = $media->type;
        $path = $media->path;
        $column = match ($type) {
            KitchenReportMedia::TYPE_WASHING => 'washing_photo_path',
            KitchenReportMedia::TYPE_COOKING => 'photo_path',
            KitchenReportMedia::TYPE_READY => 'ready_photo_path',
            default => null,
        };

        DB::transaction(function () use ($media, $report, $type, $column) {
            $media->delete();

            if ($column) {
                $next = $report->media()->where('type', $type)->orderBy('id')->first();
                $report->update([$column => $next?->path]);
            }
        });

        Storage::disk('local')->delete($path);
        Storage::disk('public')->delete($path);

        AuditLogger::log('DELETE_KITCHEN_MEDIA', 'kitchen', $report, ['path' => $path], null);

        return back()->with('success', 'Foto kitchen dihapus.');
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

        $now = now();
        $reportData = [
            'prepared_quantity' => $data['prepared_quantity'],
            'condition' => $data['condition'] ?? null,
            'notes' => $data['notes'] ?? null,
            'prepared_by' => $request->user()->id,
            'prepared_at' => $now,
        ];

        if ($request->hasFile('photo')) {
            $reportData['photo_path'] = $request->file('photo')->store('kitchen/reports/cooking');
            $reportData['cooking_at'] = $now;
        }

        if ($request->hasFile('washing_photo')) {
            $reportData['washing_photo_path'] = $request->file('washing_photo')->store('kitchen/reports/washing');
            $reportData['washing_at'] = $now;
        }

        $report = $kitchen->reports()->create($reportData);

        if ($request->hasFile('washing_photo')) {
            $report->media()->create([
                'type' => KitchenReportMedia::TYPE_WASHING,
                'path' => $reportData['washing_photo_path'],
                'mime' => $request->file('washing_photo')->getMimeType(),
                'size' => (int) $request->file('washing_photo')->getSize(),
                'original_name' => $request->file('washing_photo')->getClientOriginalName(),
            ]);
        }

        if ($request->hasFile('photo')) {
            $report->media()->create([
                'type' => KitchenReportMedia::TYPE_COOKING,
                'path' => $reportData['photo_path'],
                'mime' => $request->file('photo')->getMimeType(),
                'size' => (int) $request->file('photo')->getSize(),
                'original_name' => $request->file('photo')->getClientOriginalName(),
            ]);
        }

        AuditLogger::log('KITCHEN_REPORT', 'kitchen', $kitchen, null, $reportData);

        return back()->with('success', 'Laporan kesiapan kitchen dicatat.');
    }

    private function stageOf(?KitchenReport $report): string
    {
        if (! $report) {
            return 'washing';
        }

        $hasWashing = $report->washing_at !== null || $report->washing_photo_path !== null;
        $hasCooking = $report->cooking_at !== null || $report->photo_path !== null;
        $hasReady = $report->ready_at !== null || $report->ready_photo_path !== null;

        return match (true) {
            $hasReady => 'done',
            $hasCooking => 'ready',
            $hasWashing => 'cooking',
            default => 'washing',
        };
    }

    private function stageLabel(string $stage): string
    {
        return match ($stage) {
            'cooking' => 'Cuci selesai',
            'ready' => 'Masak selesai',
            'done' => 'JUG siap',
            default => 'Belum dicuci',
        };
    }

    private function workflowPayload(KitchenReport $report): array
    {
        return [
            'id' => $report->id,
            'washing_at' => $report->washing_at?->toIso8601String(),
            'cooking_at' => $report->cooking_at?->toIso8601String(),
            'ready_at' => $report->ready_at?->toIso8601String(),
            'stage' => $this->stageOf($report),
            'stage_label' => $this->stageLabel($this->stageOf($report)),
            'completed' => $report->isComplete(),
            'notes' => $report->notes,
            'media' => [
                'washing' => $this->mediaPayload($report, KitchenReportMedia::TYPE_WASHING),
                'cooking' => $this->mediaPayload($report, KitchenReportMedia::TYPE_COOKING),
                'ready' => $this->mediaPayload($report, KitchenReportMedia::TYPE_READY),
            ],
        ];
    }

    private function reportPayload(KitchenReport $report): array
    {
        return [
            'id' => $report->id,
            'prepared_quantity' => $report->prepared_quantity,
            'condition' => $report->condition,
            'notes' => $report->notes,
            'prepared_by' => $report->preparer?->name,
            'prepared_at' => $report->prepared_at?->toIso8601String(),
            'washing_at' => $report->washing_at?->toIso8601String(),
            'cooking_at' => $report->cooking_at?->toIso8601String(),
            'ready_at' => $report->ready_at?->toIso8601String(),
            'stage' => $this->stageOf($report),
            'stage_label' => $this->stageLabel($this->stageOf($report)),
            'photo_url' => $report->photo_path ? route('kitchen.reports.photo', $report->id) : null,
            'washing_photo_url' => $report->washing_photo_path ? route('kitchen.reports.washing-photo', $report->id) : null,
            'ready_photo_url' => $report->ready_photo_path ? route('kitchen.reports.ready-photo', $report->id) : null,
            'media' => [
                'washing' => $this->mediaPayload($report, KitchenReportMedia::TYPE_WASHING),
                'cooking' => $this->mediaPayload($report, KitchenReportMedia::TYPE_COOKING),
                'ready' => $this->mediaPayload($report, KitchenReportMedia::TYPE_READY),
            ],
        ];
    }

    private function mediaPayload(KitchenReport $report, string $type): array
    {
        return $report->media
            ->where('type', $type)
            ->values()
            ->map(fn (KitchenReportMedia $media) => [
                'id' => $media->id,
                'type' => $media->type,
                'url' => route('kitchen.reports.media', $media->id),
                'original_name' => $media->original_name,
                'size' => $media->size,
            ])
            ->all();
    }

    /**
     * @param  array<int, UploadedFile>  $files
     * @return array<int, array{path: string, mime: string|null, size: int, original_name: string}>
     */
    private function storeStagePhotos(array $files, string $directory): array
    {
        return collect($files)->map(function ($file) use ($directory) {
            $path = $file->store($directory);

            if ($path === false) {
                throw new \RuntimeException('Foto kitchen gagal disimpan.');
            }

            return [
                'path' => $path,
                'mime' => $file->getMimeType() ?: null,
                'size' => (int) $file->getSize(),
                'original_name' => $file->getClientOriginalName(),
            ];
        })->values()->all();
    }

    private function attachMedia(KitchenReport $report, string $type, array $payloads): void
    {
        foreach ($payloads as $payload) {
            $report->media()->create([
                'type' => $type,
                ...$payload,
            ]);
        }

        $report->unsetRelation('media');
    }

    private function advanceKitchenStatus(KitchenRequest $kitchen, KitchenStatus $target): void
    {
        if ($kitchen->status === $target || $kitchen->status === KitchenStatus::CANCELLED) {
            return;
        }

        if ($kitchen->status->canTransitionTo($target)) {
            $kitchen->update(['status' => $target]);
        }
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

    public function downloadReadyPhoto(KitchenReport $report): SymfonyResponse
    {
        $this->authorizePhotoAccess($report);

        abort_unless($report->ready_photo_path && $this->photoExists($report->ready_photo_path), 404);

        return $this->streamPhoto($report->ready_photo_path);
    }

    private function authorizeMediaAccess(KitchenReportMedia $media): void
    {
        $media->loadMissing('kitchenReport.kitchenRequest.event');

        $user = request()->user();

        abort_unless($user->hasPermission('kitchen.view'), 403);

        $kitchen = $media->kitchenReport?->kitchenRequest;

        abort_unless($kitchen && EventAccess::allows($user, $kitchen->event), 403);
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
