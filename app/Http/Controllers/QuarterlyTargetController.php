<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\QuarterlyTarget;
use App\Services\QuarterlyTargetMetrics;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class QuarterlyTargetController extends Controller
{
    /**
     * Operational "Target Kuartal per Brand" page (admin + permitter):
     * one row per active brand for the selected year/quarter.
     */
    public function index(Request $request, QuarterlyTargetMetrics $metrics): Response
    {
        $year = $this->year($request);
        $quarter = $this->quarter($request);
        [$start, $end] = $this->quarterBounds($year, $quarter);

        $actualByBrand = $metrics->issuedTotalsByBrand($start, $end);

        $brands = Brand::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'quarterly_target_cups'])
            ->map(function (Brand $brand) use ($year, $quarter, $actualByBrand) {
                $configured = QuarterlyTarget::query()
                    ->where('brand_id', $brand->id)
                    ->where('year', $year)
                    ->where('quarter', $quarter)
                    ->first(['target_cups', 'target_shifts']);

                $target = (int) ($configured?->target_cups ?? $brand->quarterly_target_cups ?? 0);
                $actual = (int) ($actualByBrand[$brand->id]['cups'] ?? 0);
                $actualShifts = (int) ($actualByBrand[$brand->id]['shifts'] ?? 0);
                $targetShifts = (int) ($configured?->target_shifts ?? 0);

                return [
                    'id' => $brand->id,
                    'name' => $brand->name,
                    'target_cups' => $target,
                    'actual_cups' => $actual,
                    'percentage' => $target > 0 ? round(($actual / $target) * 100, 1) : 0.0,
                    'target_shifts' => $targetShifts,
                    'actual_shifts' => $actualShifts,
                    'shift_percentage' => $targetShifts > 0 ? round(($actualShifts / $targetShifts) * 100, 1) : 0.0,
                    'is_configured' => $configured !== null,
                ];
            })
            ->values();

        return Inertia::render('Targets/Quarterly', [
            'brands' => $brands,
            'filters' => ['year' => $year, 'quarter' => $quarter],
            'quarters' => collect(range(1, 4))->map(fn ($q) => [
                'value' => $q,
                'label' => "Q{$q}",
            ])->values(),
        ]);
    }

    /**
     * PUT quarterly-targets/{brand} — single-quarter update from the
     * per-brand operational table.
     */
    public function updateForBrand(Request $request, Brand $brand)
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'quarter' => ['required', 'integer', 'min:1', 'max:4'],
            'target_cups' => ['required', 'integer', 'min:0'],
            'target_shifts' => ['sometimes', 'integer', 'min:0'],
        ]);

        $target = QuarterlyTarget::query()->firstOrNew([
            'brand_id' => $brand->id,
            'year' => $data['year'],
            'quarter' => $data['quarter'],
        ]);
        $target->start_month = ($data['quarter'] - 1) * 3 + 1;
        $target->target_cups = $data['target_cups'];
        $target->target_shifts = $data['target_shifts'] ?? $target->target_shifts ?? 0;
        $target->save();

        return back()->with('success', 'Target quartal berhasil disimpan.');
    }

    /**
     * Admin settings page: edit all four quarters for one brand.
     */
    public function adminIndex(Request $request): Response
    {
        return $this->renderBrandQuarterEditor(
            $request,
            'Admin/QuarterlyTargets/Index',
        );
    }

    /**
     * FJP workspace tab: same brand/quarter editor for permitter.
     */
    public function fjpIndex(Request $request): Response
    {
        return $this->renderBrandQuarterEditor(
            $request,
            'Fjp/QuarterlyTargets',
        );
    }

    /**
     * PUT admin/quarterly-targets & PUT /fjp/target-quartal — bulk update
     * of the four quarters for a single brand.
     */
    public function update(Request $request)
    {
        $data = $request->validate([
            'brand_id' => ['required', 'integer', 'exists:brands,id'],
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'targets' => ['required', 'array', 'min:1', 'max:4'],
            'targets.*.quarter' => ['required', 'integer', 'min:1', 'max:4'],
            'targets.*.start_month' => ['required', 'integer', 'min:1', 'max:12'],
            'targets.*.target_cups' => ['required', 'integer', 'min:0'],
            'targets.*.target_shifts' => ['sometimes', 'integer', 'min:0'],
        ]);

        foreach ($data['targets'] as $t) {
            $target = QuarterlyTarget::query()->firstOrNew([
                'brand_id' => $data['brand_id'],
                'year' => $data['year'],
                'quarter' => $t['quarter'],
            ]);
            $target->start_month = $t['start_month'];
            $target->target_cups = $t['target_cups'];
            $target->target_shifts = $t['target_shifts'] ?? $target->target_shifts ?? 0;
            $target->save();
        }

        return back()->with('success', 'Target quartal berhasil disimpan.');
    }

    private function renderBrandQuarterEditor(Request $request, string $component): Response
    {
        $year = $this->year($request);

        $brands = Brand::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $brand = $brands->firstWhere('id', $request->integer('brand'))
            ?? $brands->first();

        $targets = $brand
            ? $this->quarterRowsFor($brand->id, $year)
            : collect();

        return Inertia::render($component, [
            'brands' => $brands->values(),
            'brandId' => $brand?->id,
            'year' => $year,
            'years' => range($year - 2, $year + 1),
            'targets' => $targets,
        ]);
    }

    /**
     * @return Collection<int, array{id: int|null, quarter: int, start_month: int, target_cups: int, target_shifts: int}>
     */
    private function quarterRowsFor(int $brandId, int $year)
    {
        $configured = QuarterlyTarget::query()
            ->where('brand_id', $brandId)
            ->where('year', $year)
            ->get()
            ->keyBy('quarter');

        return collect(range(1, 4))->map(function ($q) use ($configured) {
            $row = $configured->get($q);

            return [
                'id' => $row?->id,
                'quarter' => $q,
                'start_month' => $row?->start_month ?? ($q - 1) * 3 + 1,
                'target_cups' => (int) ($row->target_cups ?? 0),
                'target_shifts' => (int) ($row->target_shifts ?? 0),
            ];
        })->values();
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function quarterBounds(int $year, int $quarter): array
    {
        $start = Carbon::create($year, ($quarter - 1) * 3 + 1, 1)->startOfDay();

        return [$start, (clone $start)->addMonths(3)->subSecond()];
    }

    private function year(Request $request): int
    {
        $year = (int) $request->integer('year');

        return $year ?: now()->year;
    }

    private function quarter(Request $request): int
    {
        $quarter = (int) $request->integer('quarter');

        return $quarter >= 1 && $quarter <= 4
            ? $quarter
            : (int) ceil(now()->month / 3);
    }
}
