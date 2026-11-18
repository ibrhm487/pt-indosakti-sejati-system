<?php

namespace App\Http\Controllers;

use App\Enums\EventStatus;
use App\Models\Attendance;
use App\Models\Event;
use App\Models\Fjp;
use App\Models\QuickReport;
use App\Models\QuickReportMedia;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

class QuickReportController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        if ($user->activeRoleSlug() !== 'team') {
            return redirect()->route('quick-reports.history');
        }

        $reportableEvents = $user->activeRoleSlug() === 'team' || $user->hasFullAccess()
            ? $this->reportableEventOptions($user)
            : collect();
        $tasks = collect();
        $availableEvents = $reportableEvents;

        if ($user->activeRoleSlug() === 'team' && ! $user->hasFullAccess()) {
            $submittedReports = QuickReport::query()
                ->whereIn('event_id', $reportableEvents->pluck('id'))
                ->orderByDesc('id')
                ->get(['id', 'event_id', 'created_at'])
                ->unique('event_id')
                ->keyBy('event_id');

            $tasks = $reportableEvents->map(function (array $event) use ($submittedReports) {
                $report = $submittedReports->get($event['id']);
                $submitted = $report !== null;

                return [
                    ...$event,
                    'status' => $submitted ? 'submitted' : 'pending',
                    'status_label' => $submitted ? 'Sudah dikirim' : 'Belum dikirim',
                    'report_id' => $report?->id,
                    'submitted_at' => $report?->created_at?->toIso8601String(),
                ];
            });
            $availableEvents = $reportableEvents
                ->reject(fn (array $event) => $submittedReports->has($event['id']))
                ->values();
        }

        return Inertia::render('QuickReports/Index', [
            'canCreate' => $user->hasPermission('quick_reports.create'),
            'events' => $availableEvents,
            'tasks' => $tasks,
        ]);
    }

    public function history(Request $request): Response
    {
        return Inertia::render('QuickReports/History', [
            'reports' => $this->reportQuery($request->user())
                ->latest()
                ->paginate(15)
                ->withQueryString()
                ->through(fn (QuickReport $report) => $this->reportData($report)),
        ]);
    }

    public function show(Request $request, QuickReport $report): Response
    {
        $user = $request->user();
        abort_unless(
            $this->visibleEvents($user)->whereKey($report->event_id)->exists(),
            404,
        );
        $report->load([
            'event:id,event_name,event_date,brand_id,venue_id,event_type_id',
            'event.brand:id,name',
            'event.venue:id,name,city',
            'event.eventType:id,slug,name',
            'user:id,name',
            'media',
        ]);
        $attendance = Attendance::query()
            ->where('event_id', $report->event_id)
            ->where('user_id', $report->user_id)
            ->first();

        return Inertia::render('QuickReports/Show', [
            'report' => [
                ...$this->reportData($report),
                'activity_date' => $attendance?->check_in_at?->toDateString(),
                'activity_start_time' => $attendance?->check_in_at?->format('H:i'),
                'activity_end_time' => $attendance?->check_out_at?->format('H:i'),
                'attendance_status' => $attendance?->status,
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->hasPermission('quick_reports.create') && ($user->activeRoleSlug() === 'team' || $user->hasFullAccess()), 403);

        $events = $this->reportableEventOptions($user);

        if ($user->activeRoleSlug() === 'team' && ! $user->hasFullAccess()) {
            $submittedEventIds = QuickReport::query()
                ->whereIn('event_id', $events->pluck('id'))
                ->pluck('event_id');
            $events = $events
                ->reject(fn (array $event) => $submittedEventIds->contains($event['id']))
                ->values();
        }

        $requestedEventId = (int) $request->query('event', 0);
        $initialEventId = $requestedEventId > 0
            ? ($events->contains('id', $requestedEventId) ? $requestedEventId : null)
            : ($events->count() === 1 ? $events->first()['id'] : null);

        return Inertia::render('QuickReports/Create', [
            'events' => $events,
            'initialEventId' => $initialEventId,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermission('quick_reports.create') && ($user->activeRoleSlug() === 'team' || $user->hasFullAccess()), 403);

        $data = $request->validate([
            'event_id' => ['required', 'integer', 'exists:events,id'],
            'activity_date' => ['nullable', 'date_format:Y-m-d'],
            'activity_start_time' => ['nullable', 'date_format:H:i'],
            'activity_end_time' => ['nullable', 'date_format:H:i'],
            'title' => ['nullable', 'string', 'max:180'],
            'body' => ['nullable', 'string', 'max:3000'],
            'sampling_target' => ['nullable', 'integer', 'min:0'],
            'sampling_actual' => ['nullable', 'integer', 'min:0'],
            'selling_target' => ['nullable', 'numeric', 'min:0'],
            'selling_actual' => ['nullable', 'numeric', 'min:0'],
            'booth_package_1' => ['nullable', 'integer', 'min:0'],
            'booth_package_2' => ['nullable', 'integer', 'min:0'],
            'booth_package_3' => ['nullable', 'integer', 'min:0'],
            'store_package_1' => ['nullable', 'integer', 'min:0'],
            'store_package_2' => ['nullable', 'integer', 'min:0'],
            'store_package_3' => ['nullable', 'integer', 'min:0'],
            'learning' => ['nullable', 'string', 'max:3000'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'photos' => ['nullable', 'array'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'videos' => ['nullable', 'array'],
            'videos.*' => ['file', 'mimes:mp4,mov,webm', 'max:51200'],
            'video' => ['nullable', 'file', 'mimes:mp4,mov,webm', 'max:51200'],
        ]);
        $event = $this->reportableEvents($user)->findOrFail($data['event_id']);

        if (QuickReport::query()->where('event_id', $event->id)->exists()) {
            throw ValidationException::withMessages([
                'event_id' => 'Quick Report untuk event ini sudah dikirim.',
            ]);
        }

        $event->loadMissing('fjp:id,jumlah_siswa_sekolah');
        $teamMember = $event->teamMembers()->where('user_id', $user->id)->first();
        $scheduledDate = $teamMember?->schedule_date?->toDateString() ?? $event->event_date?->toDateString();
        $scheduledStart = $teamMember?->start_time ?? $event->start_time;
        $scheduledEnd = $teamMember?->end_time ?? $event->end_time;
        $activityDate = $data['activity_date'] ?? $scheduledDate;
        $activityStartTime = $data['activity_start_time']
            ?? ($scheduledStart ? substr((string) $scheduledStart, 0, 5) : null);
        $activityEndTime = $data['activity_end_time']
            ?? ($scheduledEnd ? substr((string) $scheduledEnd, 0, 5) : null);

        if (! $activityDate) {
            throw ValidationException::withMessages([
                'activity_date' => 'Tanggal kegiatan wajib diisi.',
            ]);
        }

        if (! $activityStartTime) {
            throw ValidationException::withMessages([
                'activity_start_time' => 'Jam mulai kegiatan wajib diisi.',
            ]);
        }

        if (! $activityEndTime) {
            throw ValidationException::withMessages([
                'activity_end_time' => 'Jam selesai kegiatan wajib diisi.',
            ]);
        }

        $checkInAt = Carbon::parse($activityDate.' '.$activityStartTime);
        $checkOutAt = Carbon::parse($activityDate.' '.$activityEndTime);

        if (! $checkOutAt->greaterThan($checkInAt)) {
            throw ValidationException::withMessages([
                'activity_end_time' => 'Jam selesai harus setelah jam mulai.',
            ]);
        }

        $photoFiles = collect((array) $request->file('photos', []));

        if ($request->file('photo')) {
            $photoFiles->prepend($request->file('photo'));
        }

        if ($photoFiles->isEmpty()) {
            throw ValidationException::withMessages([
                'photo' => 'Minimal satu foto kegiatan wajib diunggah.',
            ]);
        }

        $videoFiles = collect((array) $request->file('videos', []));

        if ($request->file('video')) {
            $videoFiles->push($request->file('video'));
        }

        $isSchoolSampling = $this->variantFor($event->eventType?->slug) === 'school';

        if ($isSchoolSampling && $videoFiles->isEmpty()) {
            throw ValidationException::withMessages([
                'videos' => 'Video aktivitas sampling wajib diunggah.',
            ]);
        }
        $storedPaths = [];

        try {
            DB::transaction(function () use (
                $data,
                $event,
                $user,
                $photoFiles,
                $videoFiles,
                $isSchoolSampling,
                &$storedPaths,
            ): void {
                $lockedEvent = Event::query()
                    ->with('fjp:id,status,jumlah_siswa_sekolah')
                    ->lockForUpdate()
                    ->findOrFail($event->id);

                if ($lockedEvent->quickReports()->exists()) {
                    throw ValidationException::withMessages([
                        'event_id' => 'Quick Report untuk event ini sudah dikirim.',
                    ]);
                }

                if ($isSchoolSampling) {
                    $teamMemberIds = $lockedEvent->teamMembers()
                        ->whereNotNull('user_id')
                        ->pluck('user_id');

                    if ($teamMemberIds->isEmpty()) {
                        throw ValidationException::withMessages([
                            'event_id' => 'Event belum memiliki anggota tim yang ditugaskan.',
                        ]);
                    }

                    $attendances = Attendance::query()
                        ->where('event_id', $lockedEvent->id)
                        ->whereIn('user_id', $teamMemberIds)
                        ->withCount([
                            'media as pre_departure_photos_count' => fn (Builder $media) => $media
                                ->where('stage', 'pre_departure')
                                ->where('type', 'photo'),
                        ])
                        ->get()
                        ->keyBy('user_id');

                    foreach ($teamMemberIds as $teamMemberId) {
                        $attendance = $attendances->get($teamMemberId);

                        if (
                            ! $attendance
                            || ! $attendance->qualifiesForPay()
                            || $attendance->pre_departure_photos_count < 1
                        ) {
                            throw ValidationException::withMessages([
                                'event_id' => 'Quick Report dapat dikirim setelah seluruh anggota tim menyelesaikan absensi dan mengunggah foto sebelum berangkat.',
                            ]);
                        }
                    }
                }

                $photoPayloads = $photoFiles->map(function ($file) use ($lockedEvent, &$storedPaths) {
                    $path = $file->store('quick-reports/'.$lockedEvent->id);

                    if ($path === false) {
                        throw new \RuntimeException('The quick report photo could not be stored.');
                    }

                    $storedPaths[] = $path;

                    return [
                        'path' => $path,
                        'mime' => $file->getMimeType() ?: null,
                        'size' => (int) $file->getSize(),
                        'original_name' => $file->getClientOriginalName(),
                    ];
                })->values();

                $videoPayloads = $videoFiles->map(function ($file) use ($lockedEvent, &$storedPaths) {
                    $path = $file->store('quick-reports/'.$lockedEvent->id);

                    if ($path === false) {
                        throw new \RuntimeException('The quick report video could not be stored.');
                    }

                    $storedPaths[] = $path;

                    return [
                        'path' => $path,
                        'mime' => $file->getMimeType() ?: null,
                        'size' => (int) $file->getSize(),
                        'original_name' => $file->getClientOriginalName(),
                    ];
                })->values();

                $report = QuickReport::create([
                    'event_id' => $lockedEvent->id,
                    'user_id' => $user->id,
                    'title' => $data['title'] ?? $lockedEvent->event_name,
                    'body' => $data['body'] ?? null,
                    'photo_path' => $photoPayloads->first()['path'],
                    'sampling_target' => $data['sampling_target']
                        ?? $lockedEvent->sampling_target
                        ?? $lockedEvent->fjp?->jumlah_siswa_sekolah,
                    'sampling_actual' => $data['sampling_actual'] ?? null,
                    'selling_target' => $data['selling_target'] ?? null,
                    'selling_actual' => $data['selling_actual'] ?? null,
                    'booth_package_1' => $data['booth_package_1'] ?? null,
                    'booth_package_2' => $data['booth_package_2'] ?? null,
                    'booth_package_3' => $data['booth_package_3'] ?? null,
                    'store_package_1' => $data['store_package_1'] ?? null,
                    'store_package_2' => $data['store_package_2'] ?? null,
                    'store_package_3' => $data['store_package_3'] ?? null,
                    'learning' => $data['learning'] ?? null,
                ]);

                foreach ($photoPayloads as $payload) {
                    $report->media()->create([
                        'type' => QuickReportMedia::TYPE_PHOTO,
                        ...$payload,
                    ]);
                }

                foreach ($videoPayloads as $payload) {
                    $report->media()->create([
                        'type' => QuickReportMedia::TYPE_VIDEO,
                        ...$payload,
                    ]);
                }

                if ($isSchoolSampling) {
                    $lockedEvent->update(['status' => EventStatus::COMPLETED]);

                    if ($lockedEvent->fjp && $lockedEvent->fjp->status !== Fjp::STATUS_DONE) {
                        $oldStatus = $lockedEvent->fjp->status;
                        $lockedEvent->fjp->update(['status' => Fjp::STATUS_DONE]);

                        AuditLogger::log('UPDATE_FJP_STATUS', 'fjp', $lockedEvent->fjp, [
                            'status' => $oldStatus,
                        ], [
                            'status' => Fjp::STATUS_DONE,
                            'quick_report_id' => $report->id,
                        ]);
                    }
                }

                AuditLogger::log('CREATE_QUICK_REPORT', 'quick_reports', $report, null, [
                    'event_id' => $lockedEvent->id,
                    'title' => $report->title,
                    'photos' => $photoPayloads->count(),
                    'videos' => $videoPayloads->count(),
                ]);
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storedPaths);

            throw $exception;
        }

        return redirect()->route('quick-reports.index')->with('success', 'Quick Report berhasil dikirim.');
    }

    public function downloadPhoto(QuickReport $report): SymfonyResponse
    {
        $user = request()->user();

        abort_unless($user->hasPermission('quick_reports.view'), 403);
        abort_unless(
            $this->visibleEvents($user)->whereKey($report->event_id)->exists(),
            404,
        );
        abort_unless($report->photo_path, 404);

        if (Storage::disk('local')->exists($report->photo_path)) {
            return Storage::disk('local')->response($report->photo_path);
        }

        if (Storage::disk('public')->exists($report->photo_path)) {
            return Storage::disk('public')->response($report->photo_path);
        }

        abort(404);
    }

    public function downloadMedia(QuickReport $report, QuickReportMedia $media): SymfonyResponse
    {
        $user = request()->user();

        abort_unless($user->hasPermission('quick_reports.view'), 403);
        abort_unless($media->quick_report_id === $report->id, 404);
        abort_unless(
            $this->visibleEvents($user)->whereKey($report->event_id)->exists(),
            404,
        );

        foreach (['local', 'public'] as $disk) {
            if (Storage::disk($disk)->exists($media->path)) {
                return response()->file(
                    Storage::disk($disk)->path($media->path),
                    ['Content-Type' => $media->mime ?: 'application/octet-stream'],
                );
            }
        }

        abort(404);
    }

    /**
     * @return Builder<QuickReport>
     */
    private function reportQuery(User $user): Builder
    {
        $query = QuickReport::query()
            ->whereIn('event_id', $this->visibleEvents($user)->select('events.id'))
            ->with([
                'event:id,event_name,event_date,brand_id,venue_id,event_type_id',
                'event.brand:id,name',
                'event.venue:id,name,city',
                'event.eventType:id,slug,name',
                'user:id,name',
                'media',
            ]);

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    private function reportData(QuickReport $report): array
    {
        $photos = $report->media
            ->where('type', QuickReportMedia::TYPE_PHOTO)
            ->map(fn (QuickReportMedia $media) => [
                'id' => $media->id,
                'url' => route('quick-reports.media', [$report->id, $media->id]),
            ])
            ->values();

        if ($photos->isEmpty() && $report->photo_path) {
            $photos = collect([[
                'id' => null,
                'url' => route('quick-reports.photo', $report->id),
            ]]);
        }

        $videos = $report->media
            ->where('type', QuickReportMedia::TYPE_VIDEO)
            ->map(fn (QuickReportMedia $media) => [
                'id' => $media->id,
                'url' => route('quick-reports.media', [$report->id, $media->id]),
                'mime' => $media->mime,
            ])
            ->values();

        return [
            'id' => $report->id,
            'title' => $report->title,
            'body' => $report->body,
            'photo_url' => $photos->first()['url'] ?? null,
            'photos' => $photos,
            'videos' => $videos,
            'created_at' => $report->created_at?->toIso8601String(),
            'event' => $report->event?->event_name,
            'event_date' => $report->event?->event_date?->toDateString(),
            'brand' => $report->event?->brand?->name,
            'venue' => $report->event?->venue?->name,
            'city' => $report->event?->venue?->city,
            'event_type_slug' => $report->event?->eventType?->slug,
            'event_type_name' => $report->event?->eventType?->name,
            'variant' => $this->variantFor($report->event?->eventType?->slug),
            'user' => $report->user?->name,
            'sampling_target' => $report->sampling_target,
            'sampling_actual' => $report->sampling_actual,
            'selling_target' => $report->selling_target,
            'selling_actual' => $report->selling_actual,
            'booth_package_1' => $report->booth_package_1,
            'booth_package_2' => $report->booth_package_2,
            'booth_package_3' => $report->booth_package_3,
            'store_package_1' => $report->store_package_1,
            'store_package_2' => $report->store_package_2,
            'store_package_3' => $report->store_package_3,
            'learning' => $report->learning,
        ];
    }

    /**
     * @return Builder<Event>
     */
    private function visibleEvents(User $user): Builder
    {
        $query = Event::query();

        if ($user->hasFullAccess() || $user->activeRoleSlug() === 'admin_event') {
            return $query;
        }

        return match ($user->activeRoleSlug()) {
            'pic' => $query->where('pic_id', $user->id),
            'team' => $query->whereHas('teamMembers', fn (Builder $members) => $members->where('user_id', $user->id)),
            default => abort(403),
        };
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function reportableEventOptions(User $user)
    {
        return $this->reportableEvents($user)
            ->with([
                'brand:id,name',
                'venue:id,name,city',
                'eventType:id,slug,name',
                'fjp:id,jumlah_siswa_sekolah',
                'teamMembers' => fn (HasMany $members) => $members
                    ->where('user_id', $user->id)
                    ->select('event_id', 'user_id', 'schedule_date', 'start_time', 'end_time'),
            ])
            ->orderBy('event_date')
            ->get(['id', 'event_name', 'event_date', 'start_time', 'end_time', 'sampling_target', 'fjp_id', 'brand_id', 'venue_id', 'event_type_id'])
            ->map(function (Event $event) {
                $member = $event->teamMembers->first();

                return [
                    'id' => $event->id,
                    'event_name' => $event->event_name,
                    'event_date' => $event->event_date?->toDateString(),
                    'sampling_target' => $event->sampling_target ?? $event->fjp?->jumlah_siswa_sekolah,
                    'activity_date' => $member?->schedule_date?->toDateString() ?? $event->event_date?->toDateString(),
                    'activity_start_time' => $this->scheduleTime($member?->start_time ?? $event->start_time),
                    'activity_end_time' => $this->scheduleTime($member?->end_time ?? $event->end_time),
                    'brand' => $event->brand?->name,
                    'venue' => $event->venue?->name,
                    'city' => $event->venue?->city,
                    'event_type_slug' => $event->eventType?->slug,
                    'event_type_name' => $event->eventType?->name,
                    'variant' => $this->variantFor($event->eventType?->slug),
                ];
            });
    }

    private function scheduleTime(?string $time): ?string
    {
        return $time ? substr($time, 0, 5) : null;
    }

    private function variantFor(?string $slug): string
    {
        $slug = strtolower((string) $slug);

        if (str_contains($slug, 'instore')) {
            return 'instore';
        }

        if (str_contains($slug, 'school')) {
            return 'school';
        }

        return 'general';
    }

    /**
     * @return Builder<Event>
     */
    private function reportableEvents(User $user): Builder
    {
        return $this->visibleEvents($user)->whereNotIn('status', [
            EventStatus::DRAFT->value,
            EventStatus::CANCELLED->value,
        ]);
    }
}
