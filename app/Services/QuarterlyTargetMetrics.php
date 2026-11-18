<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Models\Brand;
use App\Models\StockRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class QuarterlyTargetMetrics
{
    /**
     * @return array<int, array{cups: int, shifts: int}>
     */
    public function issuedTotalsByBrand(Carbon $start, Carbon $end): array
    {
        $brandIdsByName = Brand::query()
            ->where('is_active', true)
            ->get(['id', 'name'])
            ->mapWithKeys(fn (Brand $brand): array => [mb_strtolower(trim($brand->name)) => $brand->id]);

        return DB::table('stock_movements')
            ->join('stock_requests', 'stock_requests.id', '=', 'stock_movements.reference_id')
            ->where('stock_movements.reference_type', (new StockRequest)->getMorphClass())
            ->where('stock_movements.type', StockMovementType::STOCK_OUT->value)
            ->where('stock_requests.type', 'issue')
            ->whereBetween('stock_movements.created_at', [$start, $end])
            ->whereNotNull('stock_requests.brand_name')
            ->groupByRaw('LOWER(TRIM(stock_requests.brand_name))')
            ->selectRaw('LOWER(TRIM(stock_requests.brand_name)) as brand_name, COUNT(DISTINCT stock_requests.id) as shifts, SUM(stock_movements.quantity) as cups')
            ->get()
            ->mapWithKeys(function (object $row) use ($brandIdsByName): array {
                $brandId = $brandIdsByName->get(mb_strtolower(trim($row->brand_name)));

                return $brandId === null
                    ? []
                    : [$brandId => ['cups' => (int) $row->cups, 'shifts' => (int) $row->shifts]];
            })
            ->all();
    }
}
