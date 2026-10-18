<?php

namespace App\Http\Controllers;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\QuickReport;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

class QuickReportController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $query = QuickReport::query()
            ->whereIn('event_id', $this->visibleEvents($user)->select('events.id'))
            ->with(['event:id,event_name,event_date', 'user:id,name']);

        if ($user->activeRoleSlug() === 'team' && ! $user->isSystemAdmin()) {
            $query->where('user_id', $user->id);
        }

        $reports = $query->latest()->paginate(15)->withQueryString()->through(fn (QuickReport $report) => [
            'id' => $report->id,
            'title' => $report->title,
            'body' => $report->body,
            'photo_url' => $report->photo_path
                ? route('quick-reports.photo', $report->id)
                : null,
            'created_at' => $report->created_at?->toIso8601String(),
            'event' => $report->event?->event_name,
            'event_date' => $report->event?->event_date?->toDateString(),
            'user' => $report->user?->name,
        ]);

        return Inertia::render('QuickReports/Index', [
            'reports' => $reports,
            'canCreate' => $user->hasPermission('quick_reports.create'),
            'events' => $user->activeRoleSlug() === 'team' || $user->isSystemAdmin()
                ? $this->reportableEvents($user)
                    ->orderBy('event_date')
                    ->get(['id', 'event_name', 'event_date'])
                : [],
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->activeRoleSlug() === 'team' || $user->isSystemAdmin(), 403);

        $events = $this->reportableEvents($user)
            ->orderBy('event_date')
            ->get(['id', 'event_name', 'event_date']);

        return Inertia::render('QuickReports/Create', [
            'events' => $events,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->activeRoleSlug() === 'team' || $user->isSystemAdmin(), 403);

        $data = $request->validate([
            'event_id' => ['required', 'integer', 'exists:events,id'],
            'title' => ['required', 'string', 'max:180'],
            'body' => ['nullable', 'string', 'max:3000'],
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ]);
        $event = $this->reportableEvents($user)->findOrFail($data['event_id']);
        $photoPath = $request->file('photo')->store('quick-reports/'.$event->id);

        if ($photoPath === false) {
            throw new \RuntimeException('The quick report photo could not be stored.');
        }

        try {
            DB::transaction(function () use ($data, $event, $user, $photoPath) {
                $report = QuickReport::create([
                    'event_id' => $event->id,
                    'user_id' => $user->id,
                    'title' => $data['title'],
                    'body' => $data['body'] ?? null,
                    'photo_path' => $photoPath,
                ]);

                AuditLogger::log('CREATE_QUICK_REPORT', 'quick_reports', $report, null, [
                    'event_id' => $event->id,
                    'title' => $report->title,
                ]);
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($photoPath);

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
            403,
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

    /**
     * @return Builder<Event>
     */
    private function visibleEvents(User $user): Builder
    {
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

    /**
     * @return Builder<Event>
     */
    private function reportableEvents(User $user): Builder
    {
        return $this->visibleEvents($user)->whereIn('status', [
            EventStatus::READY,
            EventStatus::ONGOING,
            EventStatus::COMPLETED,
        ]);
    }
}
