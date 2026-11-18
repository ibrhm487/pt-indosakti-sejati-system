<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Event;
use App\Models\StockRequest;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\PayrollService;
use App\Support\EventAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Throwable;

class AttendanceController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $canManage = $user->hasPermission('attendance.manage');
        $query = Attendance::query()
            ->with(['event:id,event_name,event_date', 'user:id,name'])
            ->withCount([
                'media as pre_departure_photos_count' => fn (Builder $media) => $media
                    ->where('stage', 'pre_departure')
                    ->where('type', 'photo'),
            ]);

        if ($user->activeRoleSlug() === 'team' && ! $canManage) {
            $query->where('user_id', $user->id);
        } elseif (! $user->hasFullAccess()) {
            $query->whereIn('event_id', $this->visibleEvents($user)->select('id'));
        }

        if ($eventId = $request->integer('event_id')) {
            $query->where('event_id', $eventId);
        }

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        $attendances = $query->latest()->paginate(20)->withQueryString()->through(fn (Attendance $a) => [
            'id' => $a->id,
            'event' => $a->event?->event_name,
            'event_date' => $a->event?->event_date?->toDateString(),
            'user' => $a->user?->name,
            'user_id' => $a->user_id,
            'check_in_at' => $a->check_in_at?->toIso8601String(),
            'check_out_at' => $a->check_out_at?->toIso8601String(),
            'duration_minutes' => $a->durationMinutes(),
            'status' => $a->status,
            'is_complete' => $a->isComplete(),
            'pre_departure_photos_count' => (int) $a->pre_departure_photos_count,
        ]);
        $events = $this->visibleEvents($user)
            ->orderBy('event_name')
            ->get(['id', 'event_name']);
        $requestedEventId = $request->integer('event_id');
        $initialEventId = $requestedEventId > 0
            ? ($events->contains('id', $requestedEventId) ? $requestedEventId : null)
            : ($events->count() === 1 ? $events->first()->id : null);

        return Inertia::render('Attendance/Index', [
            'attendances' => $attendances,
            'events' => $events,
            'initialEventId' => $initialEventId,
            'users' => $canManage ? User::orderBy('name')->get(['id', 'name']) : [],
            'currentUser' => ['id' => $user->id, 'name' => $user->name],
            'filters' => $request->only('event_id', 'status'),
            'can' => [
                'create' => $user->hasPermission('attendance.create'),
                'manage' => $canManage,
                'payroll' => $user->hasPermission('attendance.payroll'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $canManage = $user->hasPermission('attendance.manage');

        $rules = [
            'event_id' => ['required', 'exists:events,id'],
            'planned_start' => ['nullable', 'date'],
            'planned_end' => ['nullable', 'date'],
        ];

        if ($canManage) {
            $rules['user_id'] = ['required', 'exists:users,id'];
        }

        $data = $request->validate($rules);

        $event = $this->visibleEvents($user)->findOrFail($data['event_id']);

        if (! $canManage) {
            $data['user_id'] = $user->id;
        }

        $attendance = Attendance::updateOrCreate(
            ['event_id' => $event->id, 'user_id' => $data['user_id']],
            [...$data, 'status' => 'planned'],
        );

        AuditLogger::log('CREATE_ATTENDANCE', 'attendance', $attendance, null, $data);

        return back()->with('success', 'Jadwal absensi ditambahkan.');
    }

    public function checkIn(Request $request, Attendance $attendance)
    {
        $this->authorizeSelfOrManage($request, $attendance);

        if ($attendance->check_in_at) {
            return back()->with('error', 'Sudah check-in.');
        }

        if ($attendance->event?->event_date?->isFuture()) {
            return back()->with('error', 'Check-in hanya dapat dilakukan pada hari pelaksanaan event.');
        }

        if (! $attendance->media()
            ->where('stage', 'pre_departure')
            ->where('type', 'photo')
            ->exists()) {
            return back()->withErrors([
                'check_in' => 'Unggah foto dokumentasi sebelum berangkat terlebih dahulu.',
            ]);
        }

        $old = ['check_in_at' => null];
        $attendance->update(['check_in_at' => now(), 'status' => 'in_progress']);

        AuditLogger::log('CHECK_IN', 'attendance', $attendance, $old, ['check_in_at' => $attendance->check_in_at?->toIso8601String()]);

        return back()->with('success', 'Check-in dicatat.');
    }

    public function uploadPreDepartureEvidence(
        Request $request,
        Attendance $attendance,
    ): RedirectResponse {
        $this->authorizeSelfOrManage($request, $attendance);

        if ($attendance->check_in_at || $attendance->check_out_at) {
            return back()->withErrors([
                'photos' => 'Foto sebelum berangkat tidak dapat ditambah setelah check-in.',
            ]);
        }

        $data = $request->validate([
            'photos' => ['required', 'array', 'min:1'],
            'photos.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ]);
        $storedPaths = [];

        try {
            DB::transaction(function () use ($attendance, $data, &$storedPaths): void {
                $lockedAttendance = Attendance::query()
                    ->lockForUpdate()
                    ->findOrFail($attendance->id);

                if ($lockedAttendance->check_in_at || $lockedAttendance->check_out_at) {
                    throw ValidationException::withMessages([
                        'photos' => 'Foto sebelum berangkat tidak dapat ditambah setelah check-in.',
                    ]);
                }

                foreach ($data['photos'] as $file) {
                    $path = $file->store('attendance/'.$lockedAttendance->id.'/pre-departure');

                    if ($path === false) {
                        throw new RuntimeException('Attendance evidence could not be stored.');
                    }

                    $storedPaths[] = $path;
                    $lockedAttendance->media()->create([
                        'type' => 'photo',
                        'stage' => 'pre_departure',
                        'path' => $path,
                        'mime' => $file->getMimeType() ?: null,
                        'size' => (int) $file->getSize(),
                        'original_name' => $file->getClientOriginalName(),
                    ]);
                }
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storedPaths);

            throw $exception;
        }

        AuditLogger::log('UPLOAD_ATTENDANCE_EVIDENCE', 'attendance', $attendance, null, [
            'stage' => 'pre_departure',
            'photos' => count($data['photos']),
        ]);

        return back()->with('success', 'Foto sebelum berangkat berhasil diunggah.');
    }

    public function checkOut(Request $request, Attendance $attendance)
    {
        $this->authorizeSelfOrManage($request, $attendance);

        if (! $attendance->check_in_at) {
            return back()->withErrors(['check_out' => 'Belum check-in.']);
        }

        if ($attendance->check_out_at) {
            return back()->with('error', 'Sudah check-out.');
        }

        $attendance->update(['check_out_at' => now(), 'status' => 'completed']);

        AuditLogger::log('CHECK_OUT', 'attendance', $attendance, null, ['check_out_at' => $attendance->check_out_at?->toIso8601String()]);

        return back()->with('success', 'Check-out dicatat.');
    }

    public function invalidate(Request $request, Attendance $attendance)
    {
        $this->authorizeManage($request, $attendance);

        $data = $request->validate(['correction_note' => ['required', 'string', 'min:5', 'max:1000']]);

        $old = ['status' => $attendance->status];
        $attendance->update(['status' => 'invalid', 'correction_note' => $data['correction_note']]);

        AuditLogger::log('INVALIDATE_ATTENDANCE', 'attendance', $attendance, $old, ['status' => 'invalid']);

        return back()->with('success', 'Absensi ditandai tidak valid (tidak dihitung untuk payroll).');
    }

    public function correct(Request $request, Attendance $attendance)
    {
        $this->authorizeManage($request, $attendance);

        $data = $request->validate([
            'check_in_at' => ['nullable', 'date'],
            'check_out_at' => ['nullable', 'date'],
            'correction_note' => ['required', 'string', 'min:5', 'max:1000'],
        ], [
            'check_out_at.date' => 'Format waktu check-out tidak valid.',
        ]);

        if ($data['check_in_at'] && $data['check_out_at'] && strtotime($data['check_out_at']) <= strtotime($data['check_in_at'])) {
            return back()->withErrors(['check_out_at' => 'Check-out harus setelah check-in.']);
        }

        $old = [
            'check_in_at' => $attendance->check_in_at?->toIso8601String(),
            'check_out_at' => $attendance->check_out_at?->toIso8601String(),
            'status' => $attendance->status,
        ];

        $status = match (true) {
            ($data['check_in_at'] ?? null) !== null && ($data['check_out_at'] ?? null) !== null => 'completed',
            ($data['check_in_at'] ?? null) !== null => 'in_progress',
            default => 'planned',
        };

        $attendance->update([
            'check_in_at' => $data['check_in_at'] ?? null,
            'check_out_at' => $data['check_out_at'] ?? null,
            'status' => $status,
            'correction_note' => $data['correction_note'],
        ]);

        AuditLogger::log('CORRECT_ATTENDANCE', 'attendance', $attendance, $old, [
            'check_in_at' => $attendance->check_in_at?->toIso8601String(),
            'check_out_at' => $attendance->check_out_at?->toIso8601String(),
            'status' => $status,
            'correction_note' => $data['correction_note'],
        ]);

        return back()->with('success', 'Waktu absensi dikoreksi.');
    }

    public function payroll(Request $request, PayrollService $payroll): Response
    {
        $user = $request->user();
        $eventId = $request->integer('event_id');
        $event = $eventId ? $this->visibleEvents($user)->find($eventId) : null;

        $attendances = Attendance::query()
            ->with(['user:id,name', 'event:id,event_name,event_date'])
            ->when($eventId, fn (Builder $q) => $q->where('event_id', $eventId))
            ->when(! $user->hasFullAccess(), fn (Builder $q) => $q->whereIn('event_id', $this->visibleEvents($user)->select('id')))
            ->get();

        $issuedRequestsQuery = StockRequest::query()
            ->with([
                'teamMember:id,name',
                'event:id,event_name,event_date',
                'event.teamMembers.user:id,name',
            ])
            ->whereNotNull('issued_at')
            ->when(
                ! $user->hasFullAccess(),
                fn (Builder $query) => $query->whereIn(
                    'event_id',
                    $this->visibleEvents($user)->select('id'),
                ),
            )
            ->orderBy('issued_at')
            ->orderBy('id');
        $issuedRequests = $issuedRequestsQuery->get();
        $summary = $payroll->summarize($attendances, $issuedRequests, $eventId ?: null);
        $creditedShiftKeys = $payroll->eligibleShiftRecords($issuedRequests)
            ->mapWithKeys(fn (array $shift) => [
                $shift['request']->id.'|'.$shift['user_id'] => true,
            ]);

        $rows = $issuedRequests
            ->when($eventId, fn ($requests) => $requests->where('event_id', $eventId))
            ->flatMap(fn (StockRequest $stockRequest) => $payroll->shiftParticipants($stockRequest)
                ->map(fn (array $participant) => [
                    'id' => $stockRequest->id,
                    'user_id' => $participant['user_id'],
                    'mrv_number' => $stockRequest->mrv_number,
                    'user' => $participant['user'],
                    'event' => $stockRequest->event?->event_name
                        ?? $stockRequest->activity_type
                        ?? 'Kegiatan sampling',
                    'event_date' => ($stockRequest->activity_date
                        ?? $stockRequest->event?->event_date
                        ?? $stockRequest->issued_at)?->toDateString(),
                    'issued_at' => $stockRequest->issued_at?->toIso8601String(),
                    'status' => $stockRequest->status->label(),
                    'qualifies' => isset($creditedShiftKeys[$stockRequest->id.'|'.$participant['user_id']]),
                ]))
            ->values();

        return Inertia::render('Attendance/Payroll', [
            'event' => $event?->only(['id', 'event_name']),
            'events' => $this->visibleEvents($user)->orderBy('event_name')->get(['id', 'event_name']),
            'rows' => $rows,
            'summary' => $summary,
            'filters' => ['event_id' => $eventId],
        ]);
    }

    /**
     * Events whose attendance the given user may read.
     *
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
            'team' => $query->whereHas('teamMembers', fn (Builder $m) => $m->where('user_id', $user->id)),
            default => abort(403),
        };
    }

    private function authorizeSelfOrManage(Request $request, Attendance $attendance): void
    {
        $user = $request->user();

        if ($attendance->user_id === $user->id) {
            return;
        }

        abort_unless(
            $user->hasPermission('attendance.manage') && EventAccess::allows($user, $attendance->event),
            403,
        );
    }

    private function authorizeManage(Request $request, Attendance $attendance): void
    {
        $user = $request->user();

        abort_unless(
            $user->hasPermission('attendance.manage') && EventAccess::allows($user, $attendance->event),
            403,
        );
    }
}
