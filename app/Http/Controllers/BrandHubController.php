<?php

namespace App\Http\Controllers;

use App\Enums\ReportStatus;
use App\Models\Brand;
use App\Models\Event;
use App\Models\EventReport;
use App\Models\Fjp;
use App\Models\Product;
use App\Models\QuarterlyTarget;
use App\Services\QuarterlyTargetMetrics;
use App\Support\EventAccess;
use App\Support\EventGrouping;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class BrandHubController extends Controller
{
    public function show(
        Request $request,
        Brand $brand,
        QuarterlyTargetMetrics $quarterlyTargetMetrics,
    ): Response {
        $user = $request->user();

        abort_unless($this->canAccessBrand($user, $brand), 403);

        $eventQuery = Event::query()->where('brand_id', $brand->id);
        EventAccess::scope($eventQuery, $user, 'id');

        $statusRows = (clone $eventQuery)
            ->toBase()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->get()
            ->pluck('aggregate', 'status');

        $bucket = fn (array $statuses): int => (int) collect($statuses)->sum(
            fn (string $status) => (int) ($statusRows[$status] ?? 0),
        );

        $events = [
            'total' => (int) $statusRows->sum(),
            'planning' => $bucket(['draft', 'planning', 'permission']),
            'preparation' => $bucket(['team_assignment', 'warehouse_preparation', 'kitchen_preparation']),
            'ready_ongoing' => $bucket(['ready', 'ongoing']),
            'completed' => $bucket(['completed']),
            'cancelled' => $bucket(['cancelled']),
            'upcoming' => (clone $eventQuery)
                ->whereDate('event_date', '>=', today())
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->count(),
        ];

        $upcomingGroups = EventGrouping::group(
            (clone $eventQuery)
                ->with(['brand:id,name', 'eventType:id,name,requires_school', 'venue:id,name,city,is_school'])
                ->whereDate('event_date', '>=', today())
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->orderBy('event_date')
                ->limit(50)
                ->get()
                ->map(fn (Event $event) => [
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
                ]),
        );

        return Inertia::render('Brands/Show', [
            'brand' => [
                'id' => $brand->id,
                'name' => $brand->name,
                'slug' => $brand->slug,
                'code' => $brand->code,
                'is_active' => $brand->is_active,
            ],
            'events' => $events,
            'quarterly' => $this->quarterlyTarget($brand, $quarterlyTargetMetrics),
            'upcomingGroups' => $upcomingGroups,
            'fjp' => $this->fjpSummary($user, $brand),
            'reports' => $this->reportSummary($user, $eventQuery),
            'inventory' => $this->inventorySummary($user, $brand),
            'quickLinks' => $this->quickLinks($user, $brand),
        ]);
    }

    private function canAccessBrand($user, Brand $brand): bool
    {
        if (! $brand->is_active) {
            return false;
        }

        if ($user->hasFullAccess()) {
            return true;
        }

        return Brand::query()
            ->whereKey($brand->id)
            ->whereHas('events', fn ($events) => EventAccess::scope($events, $user, 'id'))
            ->exists();
    }

    /**
     * @return array{cups: int, target_cups: int, cups_percentage: float, shifts: int, target_shifts: int, shift_percentage: float}
     */
    private function quarterlyTarget(Brand $brand, QuarterlyTargetMetrics $metrics): array
    {
        $year = now()->year;
        $quarter = (int) ceil(now()->month / 3);
        $start = Carbon::create($year, ($quarter - 1) * 3 + 1, 1)->startOfDay();
        $end = (clone $start)->addMonths(3)->subSecond();

        $actual = $metrics->issuedTotalsByBrand($start, $end)[$brand->id] ?? ['cups' => 0, 'shifts' => 0];
        $configured = QuarterlyTarget::query()
            ->where('year', $year)
            ->where('quarter', $quarter)
            ->where('brand_id', $brand->id)
            ->first();

        $targetCups = (int) ($configured?->target_cups ?? $brand->quarterly_target_cups ?? 0);
        $targetShifts = (int) ($configured?->target_shifts ?? 0);
        $cups = (int) ($actual['cups'] ?? 0);
        $shifts = (int) ($actual['shifts'] ?? 0);

        return [
            'cups' => $cups,
            'target_cups' => $targetCups,
            'cups_percentage' => $targetCups > 0 ? round(($cups / $targetCups) * 100, 1) : 0.0,
            'shifts' => $shifts,
            'target_shifts' => $targetShifts,
            'shift_percentage' => $targetShifts > 0 ? round(($shifts / $targetShifts) * 100, 1) : 0.0,
        ];
    }

    /**
     * @return array{total: int, confirmed: int, draft: int}|null
     */
    private function fjpSummary($user, Brand $brand): ?array
    {
        if (! $user->hasPermission('fjp.view')) {
            return null;
        }

        $query = Fjp::query()->where('brand_id', $brand->id);

        return [
            'total' => (clone $query)->count(),
            'confirmed' => (clone $query)->where('status', Fjp::STATUS_CONFIRM)->count(),
            'draft' => (clone $query)->where('status', Fjp::STATUS_PLAN)->count(),
        ];
    }

    /**
     * @return array{submitted: int, revision: int}|null
     */
    private function reportSummary($user, $eventQuery): ?array
    {
        if (! $user->hasPermission('reports.view')) {
            return null;
        }

        $query = EventReport::query()->whereIn(
            'event_id',
            (clone $eventQuery)->select('events.id'),
        );

        return [
            'submitted' => (clone $query)->where('status', ReportStatus::SUBMITTED->value)->count(),
            'revision' => (clone $query)->where('status', ReportStatus::REVISION->value)->count(),
        ];
    }

    /**
     * @return array{products: int, low_stock: int}|null
     */
    private function inventorySummary($user, Brand $brand): ?array
    {
        if (! $user->hasPermission('warehouse.view')) {
            return null;
        }

        $query = Product::query()->where('brand_id', $brand->id);

        return [
            'products' => (clone $query)->count(),
            'low_stock' => (clone $query)
                ->whereHas('inventory', fn ($inventory) => $inventory
                    ->whereColumn('qty_on_hand', '<=', 'products.minimum_stock'))
                ->count(),
        ];
    }

    /**
     * @return array<int, array{label: string, href: string}>
     */
    private function quickLinks($user, Brand $brand): array
    {
        $links = [];

        if ($user->hasPermission('events.view')) {
            $links[] = ['label' => 'Event', 'href' => route('events.index', ['brand_id' => $brand->id])];
        }

        if ($user->hasPermission('fjp.view')) {
            $links[] = ['label' => 'FJP', 'href' => route('fjp.index', ['brand_id' => $brand->id])];
        }

        if ($user->hasPermission('reports.view')) {
            $links[] = ['label' => 'Laporan per Event', 'href' => route('reports.by-event', ['brand_id' => $brand->id])];
            $links[] = ['label' => 'Rekap Harian', 'href' => route('reports.daily', ['brand_id' => $brand->id])];
        }

        if ($user->hasPermission('warehouse.view')) {
            $links[] = ['label' => 'Stok Gudang', 'href' => route('warehouse.inventory.index', ['brand_id' => $brand->id])];
        }

        if ($user->hasPermission('targets.manage')) {
            $links[] = ['label' => 'Target Kuartal', 'href' => route('quarterly-targets.index')];
        }

        return $links;
    }
}
