<?php

namespace App\Http\Controllers;

use App\Enums\EventStatus;
use App\Enums\MrvStatus;
use App\Enums\ReportStatus;
use App\Models\Brand;
use App\Models\Event;
use App\Models\EventReport;
use App\Models\Fjp;
use App\Models\KitchenRequest;
use App\Models\PermitRequest;
use App\Models\Product;
use App\Models\QuarterlyTarget;
use App\Models\StockRequest;
use App\Services\QuarterlyTargetMetrics;
use App\Support\BrandContext;
use App\Support\EventGrouping;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request, QuarterlyTargetMetrics $quarterlyTargetMetrics): Response
    {
        $user = $request->user();
        $role = $user->activeRoleSlug();

        $brandId = BrandContext::resolve($request);
        $dateFrom = $request->string('date_from')->toString() ?: null;
        $dateTo = $request->string('date_to')->toString() ?: null;

        return Inertia::render('Dashboard', [
            'role' => $role,
            'filters' => [
                'brand_id' => $brandId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'brands' => Brand::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'stats' => $this->statsForRole($user, $role, $brandId, $dateFrom, $dateTo),
            'quarterly' => $this->quarterlyTargets($brandId, $quarterlyTargetMetrics),
            'upcomingGroups' => $this->upcomingGroups($user, $role, $brandId, $dateFrom, $dateTo),
            'trend' => $this->monthlyTrend($user, $role, $brandId),
            // FJP student figures are scoped by fjp.view; roles without that
            // permission (team, kitchen, warehouse, admin_event) get no data.
            'schoolComparison' => $user->hasPermission('fjp.view') ? $this->schoolComparison($brandId) : [],
        ]);
    }

    /** Indonesian short month labels (app locale stays English). */
    private const MONTH_LABELS = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
        7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des',
    ];

    /**
     * Events scheduled vs completed per month for the last 6 months,
     * scoped the same way as the role's stat cards.
     */
    private function monthlyTrend($user, ?string $role, ?int $brandId = null): array
    {
        $query = Event::query();

        if ($role === 'pic') {
            $query->where('pic_id', $user->id);
        } elseif ($role === 'team') {
            $query->whereHas('teamMembers', fn ($q) => $q->where('user_id', $user->id));
        }

        if ($brandId) {
            $query->where('brand_id', $brandId);
        }

        $events = (clone $query)
            ->whereDate('event_date', '>=', now()->subMonths(5)->startOfMonth())
            ->get(['event_date', 'status']);

        return collect(range(5, 0))
            ->map(fn ($i) => now()->subMonths($i))
            ->map(function ($month) use ($events) {
                $inMonth = $events->filter(
                    fn (Event $event) => $event->event_date?->isSameMonth($month) ?? false,
                );

                return [
                    'month' => self::MONTH_LABELS[(int) $month->format('n')].' '.$month->format('y'),
                    'total' => $inMonth->count(),
                    'completed' => $inMonth->filter(
                        fn (Event $event) => $event->status === EventStatus::COMPLETED,
                    )->count(),
                ];
            })
            ->values()
            ->all();
    }

    private function statsForRole($user, ?string $role, ?int $brandId = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $eventQuery = Event::query();
        $reportQuery = EventReport::query();

        if ($role === 'pic') {
            $eventQuery->where('pic_id', $user->id);
        } elseif ($role === 'team') {
            $eventQuery->whereHas('teamMembers', fn ($q) => $q->where('user_id', $user->id));
        }

        if ($brandId) {
            $eventQuery->where('brand_id', $brandId);
        }

        if ($dateFrom) {
            $eventQuery->whereDate('event_date', '>=', $dateFrom);
        }

        if ($dateTo) {
            $eventQuery->whereDate('event_date', '<=', $dateTo);
        }

        // Event reports inherit the same ownership scope as their events, and
        // follow the dashboard period/brand filters so counters stay consistent.
        if (in_array($role, ['pic', 'team'], true) || $brandId || $dateFrom || $dateTo) {
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

    private function quarterlyTargets(?int $brandId, QuarterlyTargetMetrics $metrics): array
    {
        $year = now()->year;
        $quarter = (int) ceil(now()->month / 3);
        $start = Carbon::create($year, ($quarter - 1) * 3 + 1, 1)->startOfDay();
        $end = (clone $start)->addMonths(3)->subSecond();
        $actualByBrand = $metrics->issuedTotalsByBrand($start, $end);
        $targets = QuarterlyTarget::query()
            ->where('year', $year)
            ->where('quarter', $quarter)
            ->whereNotNull('brand_id')
            ->get(['brand_id', 'target_cups', 'target_shifts'])
            ->keyBy('brand_id');

        return DB::table('brands')
            ->where('is_active', true)
            ->when($brandId, fn ($q) => $q->where('id', $brandId))
            ->orderBy('name')
            ->get(['id', 'name', 'quarterly_target_cups'])
            ->map(function ($brand) use ($targets, $actualByBrand) {
                $configured = $targets->get($brand->id);
                $target = (int) ($configured?->target_cups ?? $brand->quarterly_target_cups ?? 0);
                $targetShifts = (int) ($configured?->target_shifts ?? 0);
                $actual = (int) ($actualByBrand[$brand->id]['cups'] ?? 0);
                $actualShifts = (int) ($actualByBrand[$brand->id]['shifts'] ?? 0);

                return [
                    'brand' => $brand->name,
                    'actual' => $actual,
                    'target' => $target,
                    'percentage' => $target > 0 ? round(($actual / $target) * 100, 1) : 0.0,
                    'actual_shifts' => $actualShifts,
                    'target_shifts' => $targetShifts,
                    'shift_percentage' => $targetShifts > 0
                        ? round(($actualShifts / $targetShifts) * 100, 1)
                        : 0.0,
                ];
            })
            ->all();
    }

    private function upcomingGroups($user, ?string $role, ?int $brandId = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $query = Event::query()
            ->with(['brand:id,name', 'eventType:id,name,requires_school', 'venue:id,name,city,is_school'])
            ->orderBy('event_date');

        if ($dateFrom || $dateTo) {
            if ($dateFrom) {
                $query->whereDate('event_date', '>=', $dateFrom);
            }
            if ($dateTo) {
                $query->whereDate('event_date', '<=', $dateTo);
            }
        } else {
            $query->whereDate('event_date', '>=', now());
        }

        if ($brandId) {
            $query->where('brand_id', $brandId);
        }

        if ($role === 'pic') {
            $query->where('pic_id', $user->id);
        }

        if ($role === 'team') {
            $query->whereHas('teamMembers', fn ($q) => $q->where('user_id', $user->id));
        }

        $rows = $query->get()->map(fn (Event $event) => [
            'id' => $event->id,
            'event_name' => $event->event_name,
            'brand' => $event->brand?->name,
            'brand_id' => $event->brand_id,
            'brand_name' => $event->brand?->name,
            'event_type' => $event->eventType?->name,
            'event_type_id' => $event->event_type_id,
            'event_type_name' => $event->eventType?->name,
            'requires_school' => (bool) $event->eventType?->requires_school,
            'venue' => $event->venue?->name,
            'venue_city' => $event->venue?->city,
            'event_date' => $event->event_date?->toDateString(),
            'status' => $event->status->value,
            'status_label' => $event->status->label(),
        ]);

        return EventGrouping::group($rows);
    }

    private function schoolComparison(?int $brandId = null): array
    {
        return Fjp::query()
            ->whereNotNull('jumlah_siswa_sekolah')
            ->whereNotNull('jumlah_siswa_dapodik')
            ->when($brandId, fn ($q) => $q->where('brand_id', $brandId))
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
