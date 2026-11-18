<?php

namespace App\Services;

use App\Enums\MaterialCategory;
use App\Enums\MrvStatus;
use App\Models\Event;
use App\Models\Product;
use App\Models\StockRequest;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CreateBulkMrvRequests
{
    /**
     * @param  Collection<int, array{
     *     id: string,
     *     total_students: int,
     *     schools: Collection<int, array{name: string, venue: string|null}>,
     *     events: Collection<int, Event>,
     *     members: Collection<int, array{user_id: int, name: string}>
     * }>  $teamGroups
     * @param  array<string, array{
     *     sampling: list<array{lot_no: string, quantity: int}>,
     *     supporting: list<array{lot_no: string, quantity: int}>
     * }>  $lotAllocations
     * @return Collection<int, StockRequest>
     */
    public function handle(
        User $requester,
        string $activityDate,
        Collection $teamGroups,
        Product $samplingProduct,
        Product $supportingProduct,
        array $lotAllocations,
        ?string $notes,
    ): Collection {
        return DB::transaction(function () use (
            $requester,
            $activityDate,
            $teamGroups,
            $samplingProduct,
            $supportingProduct,
            $lotAllocations,
            $notes,
        ): Collection {
            $requests = collect();
            $date = Carbon::parse($activityDate);

            foreach ($teamGroups as $teamGroup) {
                $firstEvent = $teamGroup['events']->first();
                $firstMember = $teamGroup['members']->first();
                $schoolNames = $teamGroup['schools']->pluck('name')->values()->all();
                $venueNames = $teamGroup['schools']->pluck('venue')->filter()->unique()->values()->all();
                $totalStudents = $teamGroup['total_students'];

                $mrv = StockRequest::create([
                    'mrv_number' => StockRequest::suggestNumber($samplingProduct->brand?->code, $date),
                    'event_id' => $firstEvent->id,
                    'activity_type' => 'MILO School Sampling',
                    'activity_date' => $activityDate,
                    'week_label' => 'Minggu '.$date->isoWeek,
                    'shift' => 'Pagi',
                    'jug_quantity' => max(1, (int) ceil($totalStudents / 250)),
                    'brand_name' => $firstEvent->brand?->name ?? 'MILO',
                    'venue_name' => $venueNames[0] ?? $schoolNames[0] ?? null,
                    'venue_names' => $venueNames ?: null,
                    'school_names' => $schoolNames,
                    'team_member_id' => $firstMember['user_id'],
                    'team_member_name' => $firstMember['name'],
                    'team_no' => $teamGroup['id'],
                    'sampling_target_wet' => $totalStudents,
                    'sampling_target_dry' => 0,
                    'type' => 'issue',
                    'status' => MrvStatus::PENDING,
                    'material_category' => MaterialCategory::WET_SAMPLE->value,
                    'material_categories' => [MaterialCategory::WET_SAMPLE->value],
                    'notes' => $notes,
                    'requested_by' => $requester->id,
                ]);

                foreach ($lotAllocations[$teamGroup['id']]['sampling'] as $allocation) {
                    $mrv->items()->create([
                        'product_id' => $samplingProduct->id,
                        'lot_no' => $allocation['lot_no'],
                        'quantity_requested' => $allocation['quantity'],
                        'cups' => $allocation['quantity'],
                        'notes' => 'MILO 3IN1 ACTIV-GO mengikuti jumlah siswa sekolah di FJP.',
                    ]);
                }

                foreach ($lotAllocations[$teamGroup['id']]['supporting'] as $allocation) {
                    $mrv->items()->create([
                        'product_id' => $supportingProduct->id,
                        'lot_no' => $allocation['lot_no'],
                        'quantity_requested' => $allocation['quantity'],
                        'notes' => 'MILO CUP 4OZ mengikuti jumlah siswa sekolah di FJP.',
                    ]);
                }

                $requests->push($mrv);
            }

            return $requests;
        });
    }
}
