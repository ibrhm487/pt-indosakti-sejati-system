<?php

namespace App\Http\Controllers;

use App\Enums\EventStatus;
use App\Enums\ReportStatus;
use App\Models\Event;
use App\Models\EventReport;
use App\Services\AuditLogger;
use App\Support\ReportAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        $query = $this->accessibleReports($request)->with(['event:id,event_name,event_date', 'submitter:id,name', 'verifier:id,name']);

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        if ($from = $request->string('date_from')->toString()) {
            $query->whereHas('event', fn (Builder $q) => $q->whereDate('event_date', '>=', $from));
        }

        if ($to = $request->string('date_to')->toString()) {
            $query->whereHas('event', fn (Builder $q) => $q->whereDate('event_date', '<=', $to));
        }

        $reports = $query->latest()->paginate(15)->withQueryString()->through(fn (EventReport $r) => [
            'id' => $r->id,
            'event' => $r->event?->event_name,
            'event_date' => $r->event?->event_date?->toDateString(),
            'sampling_target' => $r->sampling_target,
            'actual_sampling' => $r->actual_sampling,
            'achievement' => (float) $r->achievement_percentage,
            'status' => $r->status->value,
            'status_label' => $r->status->label(),
            'submitted_by' => $r->submitter?->name,
        ]);

        return Inertia::render('Reports/Index', [
            'reports' => $reports,
            'filters' => $request->only('status', 'date_from', 'date_to'),
            'statuses' => collect(ReportStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
            'canCreate' => $request->user()->hasPermission('reports.create'),
            'events' => $this->reportableEvents($request)
                ->orderBy('event_name')
                ->get(['id', 'event_name', 'event_date', 'sampling_target']),
        ]);
    }

    public function byEvent(Request $request): Response
    {
        $query = $this->visibleEvents($request)->with([
            'brand:id,name',
            'venue:id,name',
            'pic:id,name',
            'latestReport.submitter:id,name',
            'latestReport.verifier:id,name',
        ]);

        if ($search = $request->string('search')->toString()) {
            $query->where(function (Builder $query) use ($search) {
                $query->where('event_name', 'like', "%{$search}%")
                    ->orWhere('event_code', 'like', "%{$search}%");
            });
        }

        if ($status = $request->string('status')->toString()) {
            $query->whereHas('latestReport', fn (Builder $reportQuery) => $reportQuery->where('status', $status));
        }

        $events = $query->latest('event_date')->paginate(15)->withQueryString()->through(fn (Event $event) => [
            'id' => $event->id,
            'event_code' => $event->event_code,
            'event_name' => $event->event_name,
            'brand' => $event->brand?->name,
            'venue' => $event->venue?->name,
            'event_date' => $event->event_date?->toDateString(),
            'pic' => $event->pic?->name,
            'sampling_target' => $event->sampling_target,
            'report' => $event->latestReport ? [
                'id' => $event->latestReport->id,
                'sampling_target' => $event->latestReport->sampling_target,
                'actual_sampling' => $event->latestReport->actual_sampling,
                'achievement' => (float) $event->latestReport->achievement_percentage,
                'status' => $event->latestReport->status->value,
                'status_label' => $event->latestReport->status->label(),
                'submitted_by' => $event->latestReport->submitter?->name,
                'verified_by' => $event->latestReport->verifier?->name,
            ] : null,
        ]);

        return Inertia::render('Reports/ByEvent', [
            'events' => $events,
            'filters' => $request->only('search', 'status'),
            'statuses' => collect(ReportStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Reports/Create', [
            'events' => $this->reportableEvents($request)
                ->orderBy('event_name')
                ->get(['id', 'event_name', 'event_date', 'sampling_target']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $event = $this->reportableEvents($request)->findOrFail($data['event_id']);

        $report = EventReport::create([
            ...$data,
            'sampling_target' => $data['sampling_target'] ?? $event->sampling_target,
            'status' => ReportStatus::DRAFT,
            'submitted_by' => $request->user()->id,
        ]);

        $report->update(['achievement_percentage' => $report->calculateAchievement()]);

        AuditLogger::log('CREATE_REPORT', 'reports', $report, null, $data);

        return redirect()->route('reports.show', $report)->with('success', 'Laporan dibuat.');
    }

    public function show(Request $request, EventReport $report): Response
    {
        $this->authorizeReportAccess($request, $report);
        $report->load(['event:id,event_name,event_date,sampling_target', 'submitter:id,name', 'verifier:id,name', 'documents']);

        return Inertia::render('Reports/Show', [
            'report' => [
                'id' => $report->id,
                'event' => $report->event?->event_name,
                'event_id' => $report->event_id,
                'event_date' => $report->event?->event_date?->toDateString(),
                'sampling_target' => $report->sampling_target,
                'actual_sampling' => $report->actual_sampling,
                'achievement' => (float) $report->achievement_percentage,
                'issues' => $report->issues,
                'notes' => $report->notes,
                'status' => $report->status->value,
                'status_label' => $report->status->label(),
                'revision_note' => $report->revision_note,
                'submitted_by' => $report->submitter?->name,
                'submitted_at' => $report->submitted_at?->toIso8601String(),
                'verified_by' => $report->verifier?->name,
                'verified_at' => $report->verified_at?->toIso8601String(),
            ],
            'documents' => $report->documents->map(fn ($d) => [
                'id' => $d->id,
                'category' => $d->category,
                'original_name' => $d->original_name,
            ]),
            'can' => [
                'verify' => request()->user()->hasPermission('reports.verify'),
                'revision' => request()->user()->hasPermission('reports.revision'),
                'submit' => $report->submitted_by === $request->user()->id
                    && in_array($report->status, [ReportStatus::DRAFT, ReportStatus::REVISION], true),
            ],
        ]);
    }

    public function update(Request $request, EventReport $report)
    {
        $this->authorizeReportAccess($request, $report);
        abort_unless($report->status === ReportStatus::DRAFT || $report->status === ReportStatus::REVISION, 403);
        abort_unless($report->submitted_by === $request->user()->id, 403);

        $data = $this->validated($request, $report);

        // Re-pointing a report to another event must only be possible when the
        // target event is inside the user's own reportable scope; a plain
        // exists:events,id check would let a PIC attach reports to any event.
        if (
            array_key_exists('event_id', $data)
            && (int) $data['event_id'] !== $report->event_id
            && ! $this->reportableEvents($request)->whereKey($data['event_id'])->exists()
        ) {
            abort(403, 'Anda tidak memiliki akses ke event tersebut.');
        }

        $old = $report->only(array_keys($data));

        $report->update([
            ...$data,
            'achievement_percentage' => $report->calculateAchievement(),
        ]);

        AuditLogger::log('UPDATE_REPORT', 'reports', $report, $old, $data);

        return back()->with('success', 'Laporan diperbarui.');
    }

    public function submit(Request $request, EventReport $report)
    {
        $this->authorizeReportAccess($request, $report);
        abort_unless($report->status === ReportStatus::DRAFT || $report->status === ReportStatus::REVISION, 403);
        abort_unless($report->submitted_by === $request->user()->id, 403);

        $old = ['status' => $report->status->value];
        $report->update([
            'status' => ReportStatus::SUBMITTED,
            'submitted_by' => $request->user()->id,
            'submitted_at' => now(),
        ]);

        AuditLogger::log('SUBMIT_REPORT', 'reports', $report, $old, ['status' => 'submitted']);

        return back()->with('success', 'Laporan diajukan untuk verifikasi.');
    }

    public function verify(Request $request, EventReport $report)
    {
        abort_unless($request->user()->hasPermission('reports.verify'), 403);
        $this->authorizeReportAccess($request, $report);
        abort_unless($report->status === ReportStatus::SUBMITTED, 403);

        $old = ['status' => $report->status->value];
        $report->update([
            'status' => ReportStatus::VERIFIED,
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
        ]);

        AuditLogger::log('VERIFY_REPORT', 'reports', $report, $old, ['status' => 'verified']);

        return back()->with('success', 'Laporan terverifikasi. Event dapat ditutup bila seluruh prasyarat terpenuhi.');
    }

    public function revision(Request $request, EventReport $report)
    {
        abort_unless($request->user()->hasPermission('reports.revision'), 403);
        $this->authorizeReportAccess($request, $report);
        abort_unless($report->status === ReportStatus::SUBMITTED, 403);

        $data = $request->validate(['revision_note' => ['required', 'string', 'min:5', 'max:1000']]);

        $old = ['status' => $report->status->value];
        $report->update([
            'status' => ReportStatus::REVISION,
            'revision_note' => $data['revision_note'],
        ]);

        AuditLogger::log('REVISION_REPORT', 'reports', $report, $old, ['status' => 'revision', 'note' => $data['revision_note']]);

        return back()->with('success', 'Permintaan revisi dikirim.');
    }

    private function validated(Request $request, ?EventReport $report = null): array
    {
        return $request->validate([
            'event_id' => [$report?->exists ? 'sometimes' : 'required', 'exists:events,id'],
            'sampling_target' => ['nullable', 'integer', 'min:0'],
            'actual_sampling' => ['required', 'integer', 'min:0'],
            'issues' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    private function visibleEvents(Request $request): Builder
    {
        $user = $request->user();
        $query = Event::query();

        if ($user->isSystemAdmin() || $user->activeRoleSlug() === 'admin_event') {
            return $query;
        }

        return match ($user->activeRoleSlug()) {
            'pic' => $query->where('pic_id', $user->id),
            'team' => $query->whereHas('teamMembers', fn (Builder $members) => $members->where('user_id', $user->id)),
            default => abort(403),
        };
    }

    private function reportableEvents(Request $request): Builder
    {
        return $this->visibleEvents($request)->whereIn('status', [
            EventStatus::READY,
            EventStatus::ONGOING,
            EventStatus::COMPLETED,
        ]);
    }

    private function accessibleReports(Request $request): Builder
    {
        return EventReport::query()->whereIn(
            'event_id',
            $this->visibleEvents($request)->select('events.id'),
        );
    }

    private function authorizeReportAccess(Request $request, EventReport $report): void
    {
        abort_unless(ReportAccess::allows($request->user(), $report), 404);
    }
}
