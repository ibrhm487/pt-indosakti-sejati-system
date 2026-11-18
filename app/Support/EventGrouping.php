<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class EventGrouping
{
    /**
     * Group flat event rows into Brand -> Event Type -> Date buckets.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    public static function group(Collection $rows, string $dateDirection = 'asc'): array
    {
        return $rows
            ->groupBy(fn (array $row) => (string) ($row['brand_id'] ?? 'none'))
            ->map(fn (Collection $brandRows, string $brandKey) => [
                'key' => 'brand-'.$brandKey,
                'brand_id' => $brandRows->first()['brand_id'] ?? null,
                'brand_name' => $brandRows->first()['brand_name'] ?? 'Tanpa Brand',
                'count' => $brandRows->count(),
                'event_types' => self::types($brandRows, $dateDirection),
            ])
            ->sortBy(fn (array $brand) => $brand['brand_id'] === null
                ? '9999'
                : Str::lower($brand['brand_name']))
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private static function types(Collection $rows, string $dateDirection): array
    {
        return $rows
            ->groupBy(fn (array $row) => (string) ($row['event_type_id'] ?? 'none'))
            ->map(fn (Collection $typeRows, string $typeKey) => [
                'key' => 'type-'.$typeKey,
                'event_type_id' => $typeRows->first()['event_type_id'] ?? null,
                'event_type_name' => $typeRows->first()['event_type_name'] ?? 'Tanpa Jenis',
                'requires_school' => (bool) ($typeRows->first()['requires_school'] ?? false),
                'count' => $typeRows->count(),
                'dates' => self::dates($typeRows, $dateDirection),
            ])
            ->sortBy(fn (array $type) => $type['event_type_id'] === null
                ? '9999'
                : Str::lower($type['event_type_name']))
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private static function dates(Collection $rows, string $dateDirection): array
    {
        $groups = $rows->groupBy(fn (array $row) => $row['event_date'] ?? 'none');

        $dated = $groups
            ->reject(fn (Collection $items, string $date) => $date === 'none')
            ->map(fn (Collection $dateRows, string $date) => self::dateBucket($dateRows, $date));

        $dated = $dateDirection === 'desc'
            ? $dated->sortByDesc('date')
            : $dated->sortBy('date');

        $undated = $groups
            ->filter(fn (Collection $items, string $date) => $date === 'none')
            ->map(fn (Collection $dateRows) => self::dateBucket($dateRows, null));

        return $dated->concat($undated)->values()->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private static function dateBucket(Collection $rows, ?string $date): array
    {
        $sorted = $rows
            ->sortBy(fn (array $row) => sprintf(
                '%s|%s|%s',
                $row['start_time'] ?? '99:99',
                Str::lower((string) ($row['venue'] ?? '')),
                Str::lower((string) ($row['event_name'] ?? '')),
            ))
            ->values()
            ->all();

        return [
            'key' => 'date-'.($date ?? 'none'),
            'date' => $date,
            'count' => count($sorted),
            'events' => $sorted,
        ];
    }
}
