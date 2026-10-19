<?php

namespace App\Http\Controllers;

use App\Enums\EventStatus;
use App\Enums\MrvStatus;
use App\Enums\ReportStatus;
use App\Models\Event;
use App\Models\EventReport;
use App\Models\Fjp;
use App\Models\KitchenRequest;
use App\Models\PermitRequest;
use App\Models\Product;
use App\Models\QuarterlyTarget;
use App\Models\StockRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $role = $user->activeRoleSlug();

        return Inertia::render('Dashboard', [
            'role' => $role,
            'stats' => $this->statsForRole($user, $role),
            'quarterly' => $this->quarterlyTargets(),
            'upcomingEvents' => $this->upcomingEvents($user, $role),
            // FJP student figures are scoped by fjp.view; roles without that
            // permission (team, kitchen, warehouse, admin_event) get no data.
            'schoolComparison' => $user->hasPermission('fjp.view') ? $this->schoolComparison() : [],
        ]);
    }

    private function statsForRole($user, ?string $role): array
    {
        $eventQuery = Event::query();
        $reportQuery = EventReport::query();

        if ($role === 'pic') {
            $eventQuery->where('pic_id', $user->id);
        } elseif ($role === 'team') {
            $eventQuery->whereHas('teamMembers', fn ($q) => $q->where('user_id', $user->id));
        }

        // Event reports inherit the same ownership scope as their events.
        if (in_array($role, ['pic', 'team'], true)) {
            $reportQuery->whereIn('event_id', (clone $eventQuery)->select('events.id'));
        }

        $base = [
            'total_events' => (clone $eventQuery)->count(),
            'active_events' => (clone $eventQuery)->whereIn('status', [EventStatus::READY, EventStatus::ONGOING])->count(),
            'upcoming_events' => (clone $eventQuery)->whereDate('event_date', '>=', now())->count(),
            'completed_events' => (clone $eventQuery)->where('status', EventStatus::COMPLETED)->count(),
            'pending_reports' => (clone $reportQuery)->where('status', ReportStatus::SUBMITTED)->count(),
            'events_revision' => (clone $reportQuery)->where('status', ReportStatus::REVISION)->count(),
        ];

        $roleStats = match ($role) {
            // Team and Admin per Event only receive the scoped event/report
            // summary above; cross-module counters (permits, MRV, kitchen) are
            // outside their permissions (§6: dashboard follows scope).
            'team', 'admin_event' => [],
            'warehouse' => [
                'total_sku' => Product::count(),
                'low_stock' => Product::query()->whereHas('inventory', fn ($q) => $q->whereColumn('qty_on_hand', '<=', 'products.minimum_stock'))->count(),
                'pending_requests' => StockRequest::query()->where('status', MrvStatus::PENDING)->count(),
            ],
            'kitchen' => [
                'pending' => KitchenRequest::query()->where('status', 'pending')->count(),
                'preparing' => KitchenRequest::query()->where('status', 'preparing')->count(),
                'ready' => KitchenRequest::query()->where('status', 'ready')->count(),
                'dispatched' => KitchenRequest::query()->where('status', 'dispatched')->count(),
            ],
            'permitter' => [
                'pending_permits' => PermitRequest::query()->whereIn('status', ['submitted', 'pending'])->count(),
                'approved_permits' => PermitRequest::query()->where('status', 'approved')->count(),
                'draft_fjp' => Fjp::query()->where('permit_status', 'draft')->count(),
            ],
            default => [
                'pending_permits' => PermitRequest::query()->whereIn('status', ['submitted', 'pending'])->count(),
                'mrv_pending' => StockRequest::query()->where('status', MrvStatus::PENDING)->count(),
                'kitchen_pending' => KitchenRequest::query()->whereIn('status', ['pending', 'preparing'])->count(),
            ],
        };

        return array_merge($base, $roleStats);
    }

    private function quarterlyTargets(): array
    {
        $year = now()->year;
        $quarter = (int) ceil(now()->month / 3);
        $start = Carbon::create($year, ($quarter - 1) * 3 + 1, 1)->startOfDay();
        $end = (clone $start)->addMonths(3)->subSecond();
        $latestVerifiedReportIds = EventReport::query()
            ->selectRaw('MAX(id)')
            ->where('status', ReportStatus::VERIFIED)
            ->whereBetween('verified_at', [$start, $end])
            ->groupBy('event_id');
        $actualByBrand = EventReport::query()
            ->join('events', 'event_reports.event_id', '=', 'events.id')
            ->whereNotNull('events.brand_id')
            ->whereIn('event_reports.id', $latestVerifiedReportIds)
            ->groupBy('events.brand_id')
            ->selectRaw('events.brand_id, SUM(event_reports.actual_sampling) as actual')
            ->pluck('actual', 'brand_id');
        $targets = QuarterlyTarget::query()
            ->where('year', $year)
            ->where('quarter', $quarter)
            ->whereNotNull('brand_id')
            ->pluck('target_cups', 'brand_id');

        return DB::table('brands')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'quarterly_target_cups'])
            ->map(function ($brand) use ($targets, $actualByBrand) {
                $target = (int) ($targets[$brand->id] ?? $brand->quarterly_target_cups ?? 0);
                $actual = (int) ($actualByBrand[$brand->id] ?? 0);

                return [
                    'brand' => $brand->name,
                    'actual' => $actual,
                    'target' => $target,
                    'percentage' => $target > 0 ? round(($actual / $target) * 100, 1) : 0.0,
                ];
            })
            ->all();
    }

    private function upcomingEvents($user, ?string $role): array
    {
        $query = Event::query()
            ->with(['brand:id,name', 'venue:id,name'])
            ->whereDate('event_date', '>=', now())
            ->orderBy('event_date')
            ->limit(5);

        if ($role === 'pic') {
            $query->where('pic_id', $user->id);
        }

        if ($role === 'team') {
            $query->whereHas('teamMembers', fn ($q) => $q->where('user_id', $user->id));
        }

        return $query->get()->map(fn (Event $event) => [
            'id' => $event->id,
            'event_name' => $event->event_name,
            'brand' => $event->brand?->name,
            'venue' => $event->venue?->name,
            'event_date' => $event->event_date?->toDateString(),
            'status' => $event->status->value,
            'status_label' => $event->status->label(),
        ])->all();
    }

    private function schoolComparison(): array
    {
        return Fjp::query()
            ->whereNotNull('jumlah_siswa_sekolah')
            ->whereNotNull('jumlah_siswa_dapodik')
            ->latest()
            ->limit(5)
            ->get(['id', 'event_name', 'jumlah_siswa_sekolah', 'jumlah_siswa_dapodik'])
            ->map(fn (Fjp $fjp) => [
                'id' => $fjp->id,
                'event_name' => $fjp->event_name,
                'sekolah' => $fjp->jumlah_siswa_sekolah,
                'dapodik' => $fjp->jumlah_siswa_dapodik,
                'difference' => $fjp->student_count_difference,
            ])
            ->all();
    }
}
