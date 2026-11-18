<?php

namespace App\Http\Controllers;

use App\Enums\EventStatus;
use App\Enums\RoleSlug;
use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Event;
use App\Models\EventType;
use App\Models\Fjp;
use App\Models\Personnel;
use App\Models\User;
use App\Models\Venue;
use App\Services\ApproveFjpAndAssignTeam;
use App\Services\AuditLogger;
use App\Services\DapoApiService;
use App\Services\FjpVenueResolver;
use App\Support\BrandContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class FjpController extends Controller
{
    private const DAPODIK_SOURCE = 'Dapodik (dapo.kemendikdasmen.go.id)';

    /** Inline event-status options shown as an editable dropdown in the FJP list. */
    private const STATUS_OPTIONS = [
        Fjp::STATUS_PLAN => 'Plan',
        Fjp::STATUS_CONFIRM => 'Confirm',
        Fjp::STATUS_CANCEL => 'Cancel',
        Fjp::STATUS_RESCHEDULE => 'Reschedule',
        Fjp::STATUS_DONE => 'Done',
    ];

    private const PERMITTER_VALIDATION_STATUSES = ['MILO SACHET', 'MILO UHT', 'NO MILO'];

    public function index(Request $request): Response
    {
        $base = Fjp::query();

        if ($search = $request->string('search')->toString()) {
            $base->where(function ($q) use ($search) {
                $q->where('event_name', 'like', "%{$search}%")
                    ->orWhere('fjp_number', 'like', "%{$search}%")
                    ->orWhere('school_name', 'like', "%{$search}%");
            });
        }

        $brandId = BrandContext::resolve($request);

        if ($brandId) {
            $base->where('brand_id', $brandId);
        }

        $this->whereJabodetabek($base);

        if ($from = $request->string('date_from')->toString()) {
            $base->whereDate('event_date', '>=', $from);
        }

        if ($to = $request->string('date_to')->toString()) {
            $base->whereDate('event_date', '<=', $to);
        }

        $counts = (clone $base)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->get();

        $statusCounts = collect(self::STATUS_OPTIONS)
            ->mapWithKeys(fn (string $label, string $value) => [
                $value => (int) $counts
                    ->filter(fn ($count) => $this->generalStatus((string) $count->status) === $value)
                    ->sum('aggregate'),
            ]);

        $query = clone $base;

        if ($status = $request->string('status')->toString()) {
            $legacyStatuses = match ($status) {
                Fjp::STATUS_PLAN => [Fjp::STATUS_PLAN, 'draft', 'submitted', 'pending'],
                Fjp::STATUS_CONFIRM => [Fjp::STATUS_CONFIRM, 'approved'],
                Fjp::STATUS_CANCEL => [Fjp::STATUS_CANCEL, 'cancelled', 'rejected'],
                default => [$status],
            };
            $query->whereIn('status', $legacyStatuses);
        }

        $user = $request->user();
        $canAssignTeam = $this->canAssignFjpTeam($user);
        $canAssignTeamMembers = $this->canAssignFjpTeamMembers($user);
        $teamScheduleDate = today()->addDay()->toDateString();
        $teamScheduleCandidates = $canAssignTeamMembers
            ? Fjp::query()
                ->with('venue:id,city,province')
                ->where(function ($query) use ($teamScheduleDate): void {
                    $query->whereDate('event_date', '>=', $teamScheduleDate)
                        ->orWhereNotNull('team_group_id');
                })
                ->where('status', Fjp::STATUS_CONFIRM)
                ->whereNotNull('team_group_id')
                ->whereDoesntHave('events')
                ->where('school_name', 'like', 'SD%')
                ->where(function ($query) {
                    foreach (['Jakarta', 'Bogor', 'Depok', 'Tangerang', 'Bekasi'] as $region) {
                        $query->orWhere('city', 'like', "%{$region}%")
                            ->orWhereHas('venue', fn ($venue) => $venue
                                ->where('city', 'like', "%{$region}%"));
                    }
                })
                ->orderBy('event_date')
                ->orderBy('school_name')
                ->orderBy('id')
                ->get()
                ->map(fn (Fjp $fjp) => [
                    'id' => $fjp->id,
                    'fjp_number' => $fjp->fjp_number,
                    'school_name' => $fjp->school_name ?? $fjp->event_name,
                    'city' => $fjp->city ?? $fjp->venue?->city,
                    'event_date' => $fjp->event_date?->toDateString(),
                    'student_count' => $fjp->jumlah_siswa_sekolah ?? 0,
                    'team_group_id' => $fjp->team_group_id,
                    'team_group_label' => $fjp->team_group_label,
                ])
                ->values()
            : collect();

        $pageFjps = $query->with(['brand:id,name'])
            ->withCount([
                'events as active_events_count' => fn ($events) => $events
                    ->where('status', '!=', 'cancelled'),
            ])
            ->withCount('attachments')
            ->orderByRaw('event_date IS NULL')
            ->orderBy('event_date')
            ->orderBy('start_time')
            ->orderBy('school_name')
            ->orderBy('id')
            ->paginate(match ((int) $request->integer('per_page', 25)) {
                50 => 50,
                100 => 100,
                default => 25,
            })
            ->withQueryString();

        $pageFjpIds = $pageFjps->getCollection()->modelKeys();
        $plannedGroupKeys = $pageFjps->getCollection()
            ->filter(fn (Fjp $fjp): bool => $fjp->team_group_id !== null)
            ->mapWithKeys(fn (Fjp $fjp): array => [
                $fjp->id => ($fjp->event_date?->toDateString() ?? '').'|'.$fjp->team_group_id,
            ]);
        $plannedGroupFjpIds = [];
        $plannedTeamGroupSchedules = [];

        if ($plannedGroupKeys->isNotEmpty()) {
            $wherePlannedGroups = function (Builder $query) use ($plannedGroupKeys): void {
                foreach ($plannedGroupKeys->unique() as $key) {
                    [$date, $teamGroupId] = explode('|', $key, 2);

                    $query->orWhere(function ($group) use ($date, $teamGroupId): void {
                        $group->where('team_group_id', $teamGroupId);

                        if ($date === '') {
                            $group->whereNull('event_date');
                        } else {
                            $group->whereDate('event_date', $date);
                        }
                    });
                }
            };

            Fjp::query()
                ->where($wherePlannedGroups)
                ->where('status', Fjp::STATUS_CONFIRM)
                ->whereDoesntHave('events')
                ->get(['id', 'event_date', 'team_group_id'])
                ->each(function (Fjp $fjp) use (&$plannedGroupFjpIds): void {
                    $key = ($fjp->event_date?->toDateString() ?? '').'|'.$fjp->team_group_id;
                    $plannedGroupFjpIds[$key][] = (int) $fjp->id;
                });

            Fjp::query()
                ->where($wherePlannedGroups)
                ->get(['id', 'event_date', 'team_group_id', 'school_name', 'event_name', 'start_time'])
                ->groupBy(fn (Fjp $fjp): string => ($fjp->event_date?->toDateString() ?? '').'|'.$fjp->team_group_id)
                ->each(function (Collection $group, string $key) use (&$plannedTeamGroupSchedules): void {
                    $plannedTeamGroupSchedules[$key] = $group
                        ->sortBy(fn (Fjp $fjp): string => (
                            ($fjp->start_time !== null ? substr((string) $fjp->start_time, 0, 5) : '99:99')
                            .'|'.($fjp->school_name ?: $fjp->event_name)
                            .'|'.$fjp->id
                        ))
                        ->map(fn (Fjp $fjp): array => [
                            'fjp_id' => (int) $fjp->id,
                            'school_name' => $fjp->school_name ?: $fjp->event_name,
                            'start_time' => $fjp->start_time !== null
                                ? substr((string) $fjp->start_time, 0, 5)
                                : null,
                        ])
                        ->values()
                        ->all();
                });
        }

        $pageEventDates = $pageFjps->getCollection()
            ->map(fn (Fjp $fjp) => $fjp->event_date?->toDateString())
            ->filter()
            ->unique()
            ->values()
            ->all();
        $pageTeamGroupIds = $pageFjpIds === []
            ? []
            : Event::query()
                ->whereIn('fjp_id', $pageFjpIds)
                ->whereNotNull('team_group_id')
                ->pluck('team_group_id')
                ->unique()
                ->all();
        $sameDayAreaFjpIds = $pageEventDates === []
            ? []
            : Fjp::query()
                ->where(function ($query) use ($pageEventDates): void {
                    foreach ($pageEventDates as $eventDate) {
                        $query->orWhereDate('event_date', $eventDate);
                    }
                })
                ->tap(fn (Builder $query) => $this->whereJabodetabek($query))
                ->pluck('id')
                ->all();
        $relatedFjpIds = array_values(array_unique(array_merge($pageFjpIds, $sameDayAreaFjpIds)));
        $teamEvents = $pageFjpIds === []
            ? collect()
            : Event::query()
                ->where('status', '!=', 'cancelled')
                ->where(function ($events) use ($relatedFjpIds, $pageTeamGroupIds) {
                    $events->whereIn('fjp_id', $relatedFjpIds);

                    if ($pageTeamGroupIds !== []) {
                        $events->orWhereIn('team_group_id', $pageTeamGroupIds);
                    }
                })
                ->with([
                    'fjp:id,school_name,event_name,start_time,address,city,province,team_group_label,status',
                    'brand:id,name',
                    'venue:id,name,address,city,province',
                    'pic:id,name',
                    'teamMembers.user:id,name,phone',
                    'teamMembers.personnel:id,name,role,phone',
                ])
                ->withExists([
                    'attendances as has_attendance_activity' => fn ($query) => $query
                        ->where(fn ($attendances) => $attendances
                            ->whereNotNull('check_in_at')
                            ->orWhereNotNull('check_out_at')),
                    'attendances as has_attendance_media' => fn ($query) => $query
                        ->whereHas('media'),
                    'reports as has_reports',
                    'quickReports as has_quick_reports',
                    'stockRequests as has_stock_requests',
                ])
                ->get([
                    'id', 'fjp_id', 'team_group_id', 'brand_id', 'venue_id', 'pic_id',
                    'event_name', 'event_date', 'start_time',
                ]);

        $teamsByFjpId = [];
        $teamSchedules = [];
        $eventSchedulesByFjpId = $teamEvents
            ->mapWithKeys(fn (Event $event): array => [
                (int) $event->fjp_id => [
                    'event_date' => $event->event_date?->toDateString(),
                    'school_name' => $event->fjp?->school_name
                        ?: $event->fjp?->event_name
                        ?: $event->venue?->name
                        ?: $event->event_name,
                    'brand' => $event->brand?->name,
                    'location' => collect([
                        $event->venue?->name,
                        $event->fjp?->address ?: $event->venue?->address,
                        $event->fjp?->city ?: $event->venue?->city,
                        $event->fjp?->province ?: $event->venue?->province,
                    ])->filter()->implode(', '),
                    'team' => $event->fjp?->team_group_label,
                    'pic' => $event->pic?->name,
                    'sampling_target' => $event->sampling_target,
                    'members' => $canAssignTeamMembers ? $event->teamMembers
                        ->map(fn ($member): array => [
                            'personnel_id' => $member->personnel_id === null ? null : (int) $member->personnel_id,
                            'user_id' => $member->user_id === null ? null : (int) $member->user_id,
                            'position' => $member->position,
                            'name' => $member->memberName(),
                            'phone' => $member->personnel?->phone ?? $member->user?->phone,
                        ])
                        ->values()
                        ->all() : [],
                    'start_time' => $event->start_time !== null
                        ? substr((string) $event->start_time, 0, 5)
                        : null,
                ],
            ])
            ->all();

        foreach ($teamEvents->groupBy(fn (Event $event): string => $this->teamGroupingKey($event, true)) as $teamGroup) {
            $team = [
                'group_id' => $this->teamGroupingKey($teamGroup->first(), true),
                'team_group_id' => $teamGroup->first()?->team_group_id,
                'event_date' => $teamGroup->first()?->event_date?->toDateString(),
                'members' => $teamGroup
                    ->flatMap(fn (Event $event) => $event->teamMembers->map(fn ($member) => [
                        'personnel_id' => $member->personnel_id === null ? null : (int) $member->personnel_id,
                        'user_id' => $member->user_id === null ? null : (int) $member->user_id,
                        'position' => $member->position,
                        'name' => $member->memberName(),
                        'phone' => $member->personnel?->phone ?? $member->user?->phone,
                    ]))
                    ->unique(fn (array $member): string => $member['position'].'|'.(
                        $member['personnel_id'] !== null
                            ? 'personnel:'.$member['personnel_id']
                            : 'user:'.($member['user_id'] ?? $member['name'])
                    ))
                    ->values()
                    ->all(),
                'editable' => $teamGroup->first()?->team_group_id !== null
                    && $teamGroup->every(fn (Event $event): bool => $this->isTeamEventEditable($event)),
                'schools' => $teamGroup
                    ->map(fn (Event $event): ?string => $event->fjp?->school_name ?: $event->fjp?->event_name)
                    ->filter()
                    ->unique()
                    ->values()
                    ->all(),
                'school_schedule' => $teamGroup
                    ->map(function (Event $event): ?array {
                        $schoolName = $event->fjp?->school_name ?: $event->fjp?->event_name;

                        if ($schoolName === null) {
                            return null;
                        }

                        $startTime = $event->start_time ?: $event->fjp?->start_time;

                        return [
                            'fjp_id' => (int) $event->fjp_id,
                            'school_name' => $schoolName,
                            'start_time' => $startTime !== null
                                ? substr((string) $startTime, 0, 5)
                                : null,
                        ];
                    })
                    ->filter()
                    ->unique('fjp_id')
                    ->sortBy(fn (array $school): string => ($school['start_time'] ?? '99:99').'|'.$school['school_name'])
                    ->values()
                    ->all(),
            ];

            $teamSchedules[] = $team;

            foreach ($teamGroup as $event) {
                $teamsByFjpId[(int) $event->fjp_id] = $team;
            }
        }

        $fjps = $pageFjps
            ->through(function (Fjp $fjp) use (
                $teamsByFjpId,
                $eventSchedulesByFjpId,
                $plannedGroupFjpIds,
                $plannedTeamGroupSchedules,
                $canAssignTeamMembers,
            ): array {
                $team = $teamsByFjpId[(int) $fjp->id] ?? null;
                $teamMembers = collect($team['members'] ?? []);
                $hasCompleteTeam = $teamMembers->contains('position', 'Team Leader')
                    && $teamMembers->contains('position', 'SPG');

                return [
                    'id' => $fjp->id,
                    'attachments_count' => (int) $fjp->attachments_count,
                    'fjp_number' => $fjp->fjp_number,
                    'event_name' => $fjp->event_name,
                    'brand' => $fjp->brand?->name,
                    'brand_id' => $fjp->brand_id,
                    'event_type_id' => $fjp->event_type_id,
                    'venue_id' => $fjp->venue_id,
                    'event_date' => $fjp->event_date?->toDateString(),
                    'start_time' => $fjp->start_time,
                    'sampling_target' => $fjp->sampling_target,
                    'jug_required' => $fjp->jug_required,
                    'school_name' => $fjp->school_name,
                    'npsn' => $fjp->npsn,
                    'address' => $fjp->address,
                    'city' => $fjp->city,
                    'province' => $fjp->province,
                    'kecamatan' => $fjp->kecamatan,
                    'kelurahan' => $fjp->kelurahan,
                    'maps_url' => $fjp->maps_url,
                    'sekolah' => $fjp->jumlah_siswa_sekolah,
                    'dapodik' => $fjp->jumlah_siswa_dapodik,
                    'difference' => $fjp->student_count_difference,
                    'jumlah_siswa_sekolah' => $fjp->jumlah_siswa_sekolah,
                    'jumlah_siswa_dapodik' => $fjp->jumlah_siswa_dapodik,
                    'dapodik_source' => $fjp->dapodik_source,
                    'status' => $this->generalStatus((string) $fjp->status),
                    'status_label' => self::STATUS_OPTIONS[$this->generalStatus((string) $fjp->status)]
                        ?? ucfirst((string) $fjp->status),
                    'notes' => $fjp->notes,
                    'permitter_total_outlet_canteen_sell_drink' => $fjp->permitter_total_outlet_canteen_sell_drink,
                    'permitter_quantity_with_milo' => $fjp->permitter_quantity_with_milo,
                    'permitter_status' => $fjp->permitter_status,
                    'permitter_quantity_without_milo' => $fjp->permitter_quantity_without_milo,
                    'team_sampling_quantity_with_milo' => $fjp->team_sampling_quantity_with_milo,
                    'team_sampling_quantity_without_milo' => $fjp->team_sampling_quantity_without_milo,
                    'has_event' => $fjp->active_events_count > 0,
                    'team' => $team === null
                        ? null
                        : [
                            ...$team,
                            'members' => $canAssignTeamMembers ? $team['members'] : [],
                        ],
                    'event_schedule' => $eventSchedulesByFjpId[(int) $fjp->id] ?? null,
                    ...$this->scheduleAssignmentStatus($fjp, $hasCompleteTeam, $teamMembers->isNotEmpty()),
                    'planned_team_group_id' => $fjp->team_group_id,
                    'planned_team_group_label' => $fjp->team_group_label,
                    'planned_team_group_fjp_ids' => $fjp->team_group_id === null
                        ? []
                        : ($plannedGroupFjpIds[($fjp->event_date?->toDateString() ?? '').'|'.$fjp->team_group_id] ?? []),
                    'planned_team_group_schedule' => $fjp->team_group_id === null
                        ? []
                        : ($plannedTeamGroupSchedules[($fjp->event_date?->toDateString() ?? '').'|'.$fjp->team_group_id] ?? []),
                ];
            });

        return Inertia::render('Fjp/Index', [
            'fjps' => $fjps,
            'teamSchedules' => $canAssignTeamMembers ? $teamSchedules : [],
            'filters' => [
                ...$request->only('search', 'status', 'date_from', 'date_to'),
                'city' => 'Jabodetabek',
                'brand_id' => $brandId,
                'per_page' => match ((int) $request->integer('per_page', 25)) {
                    50 => 50,
                    100 => 100,
                    default => 25,
                },
            ],
            'statusCounts' => $statusCounts,
            'totalCount' => (clone $base)->count(),
            'canAssignTeam' => $canAssignTeam,
            'canAssignTeamMembers' => $canAssignTeamMembers,
            'canManageTeamSchedules' => $canAssignTeamMembers,
            'teamScheduleDate' => $teamScheduleDate,
            'teamScheduleCandidates' => $teamScheduleCandidates,
            'teamPersonnel' => $canAssignTeamMembers
                ? Personnel::query()
                    ->where('is_active', true)
                    ->whereHas('user', fn ($query) => $query
                        ->where('is_active', true)
                        ->whereHas('roles', fn ($roles) => $roles->where('slug', 'team')))
                    ->with('user:id,name,phone')
                    ->orderBy('name')
                    ->get(['id', 'user_id', 'name', 'role', 'phone'])
                    ->map(fn (Personnel $personnel): array => [
                        'id' => (int) $personnel->id,
                        'user_id' => (int) $personnel->user_id,
                        'name' => $personnel->name,
                        'role' => $personnel->role,
                        'phone' => $personnel->phone ?? $personnel->user?->phone,
                    ])
                : [],
            'canUpdateStatus' => $user->hasPermission('fjp.status.update'),
            'statusOptions' => collect(self::STATUS_OPTIONS)->map(fn ($label, $value) => [
                'value' => $value,
                'label' => $label,
            ])->values(),
            'formOptions' => $this->formOptions(),
        ]);
    }

    public function teamAssignmentIndex(Request $request): Response
    {
        $user = $request->user();
        $canAssignTeam = $this->canAssignFjpTeam($user);
        $canAssignTeamMembers = $this->canAssignFjpTeamMembers($user);

        abort_unless($canAssignTeam || $canAssignTeamMembers, 403);

        $query = Fjp::query()
            ->with([
                'brand:id,name',
                'venue:id,name,address,city,province',
            ])
            ->where('status', Fjp::STATUS_CONFIRM);

        if (! $canAssignTeam) {
            $query->whereNotNull('team_group_id');
        }

        $this->whereJabodetabek($query);

        if ($search = $request->string('search')->toString()) {
            $query->where(function (Builder $query) use ($search): void {
                $query->where('event_name', 'like', "%{$search}%")
                    ->orWhere('school_name', 'like', "%{$search}%")
                    ->orWhere('fjp_number', 'like', "%{$search}%");
            });
        }

        if ($brandId = BrandContext::resolve($request)) {
            $query->where('brand_id', $brandId);
        }

        if ($from = $request->string('date_from')->toString()) {
            $query->whereDate('event_date', '>=', $from);
        }

        if ($to = $request->string('date_to')->toString()) {
            $query->whereDate('event_date', '<=', $to);
        }

        $pageFjps = $query
            ->orderByRaw('event_date IS NULL')
            ->orderBy('event_date')
            ->orderBy('start_time')
            ->orderBy('school_name')
            ->orderBy('id')
            ->paginate(match ((int) $request->integer('per_page', 50)) {
                100 => 100,
                default => 50,
            })
            ->withQueryString();

        $teamGroupKeys = $pageFjps->getCollection()
            ->filter(fn (Fjp $fjp): bool => $fjp->team_group_id !== null)
            ->map(fn (Fjp $fjp): string => ($fjp->event_date?->toDateString() ?? '').'|'.$fjp->team_group_id)
            ->unique()
            ->values();
        $teamGroups = [];

        if ($teamGroupKeys->isNotEmpty()) {
            $whereTeamGroups = function (Builder $query) use ($teamGroupKeys): void {
                foreach ($teamGroupKeys as $key) {
                    [$date, $teamGroupId] = explode('|', $key, 2);

                    $query->orWhere(function (Builder $group) use ($date, $teamGroupId): void {
                        $group->where('team_group_id', $teamGroupId);

                        if ($date === '') {
                            $group->whereNull('event_date');
                        } else {
                            $group->whereDate('event_date', $date);
                        }
                    });
                }
            };

            $groupSchools = Fjp::query()
                ->where($whereTeamGroups)
                ->where('status', Fjp::STATUS_CONFIRM)
                ->get(['id', 'event_date', 'team_group_id', 'school_name', 'event_name', 'start_time']);
            $pendingGroupFjpIds = Fjp::query()
                ->where($whereTeamGroups)
                ->where('status', Fjp::STATUS_CONFIRM)
                ->whereDoesntHave('events')
                ->get(['id', 'event_date', 'team_group_id'])
                ->groupBy(fn (Fjp $fjp): string => ($fjp->event_date?->toDateString() ?? '').'|'.$fjp->team_group_id)
                ->map(fn (Collection $group): array => $group->pluck('id')->map(fn ($id): int => (int) $id)->all());
            $groupEvents = Event::query()
                ->where($whereTeamGroups)
                ->where('status', '!=', EventStatus::CANCELLED->value)
                ->with([
                    'teamMembers.user:id,name,phone',
                    'teamMembers.personnel:id,name,role,phone',
                ])
                ->get(['id', 'fjp_id', 'team_group_id', 'event_date'])
                ->groupBy(fn (Event $event): string => ($event->event_date?->toDateString() ?? '').'|'.$event->team_group_id);
            $schedulesByGroup = $groupSchools
                ->groupBy(fn (Fjp $fjp): string => ($fjp->event_date?->toDateString() ?? '').'|'.$fjp->team_group_id)
                ->map(fn (Collection $group): array => $group
                    ->sortBy(fn (Fjp $fjp): string => (
                        ($fjp->start_time !== null ? substr((string) $fjp->start_time, 0, 5) : '99:99')
                        .'|'.($fjp->school_name ?: $fjp->event_name)
                        .'|'.$fjp->id
                    ))
                    ->map(fn (Fjp $fjp): array => [
                        'fjp_id' => (int) $fjp->id,
                        'school_name' => $fjp->school_name ?: $fjp->event_name,
                        'start_time' => $fjp->start_time !== null
                            ? substr((string) $fjp->start_time, 0, 5)
                            : null,
                    ])
                    ->values()
                    ->all());
            $groupSchools
                ->groupBy(fn (Fjp $fjp): string => ($fjp->event_date?->toDateString() ?? '').'|'.$fjp->team_group_id)
                ->each(function (Collection $schools, string $key) use (
                    &$teamGroups,
                    $groupEvents,
                    $pendingGroupFjpIds,
                    $schedulesByGroup,
                    $canAssignTeamMembers,
                ): void {
                    $events = $groupEvents->get($key, collect());
                    $members = $events
                        ->flatMap(fn (Event $event) => $event->teamMembers->map(fn ($member): array => [
                            'personnel_id' => $member->personnel_id === null ? null : (int) $member->personnel_id,
                            'user_id' => $member->user_id === null ? null : (int) $member->user_id,
                            'position' => $member->position,
                            'name' => $member->memberName(),
                            'phone' => $member->personnel?->phone ?? $member->user?->phone,
                        ]))
                        ->unique(fn (array $member): string => $member['position'].'|'.(
                            $member['personnel_id'] !== null
                                ? 'personnel:'.$member['personnel_id']
                                : 'user:'.($member['user_id'] ?? $member['name'])
                        ))
                        ->values()
                        ->all();
                    $positions = collect($members)->pluck('position');

                    $teamGroups[$key] = [
                        'fjp_ids' => $schools->pluck('id')->map(fn ($id): int => (int) $id)->all(),
                        'pending_fjp_ids' => $pendingGroupFjpIds->get($key, []),
                        'members' => $canAssignTeamMembers ? $members : [],
                        'has_event' => $events->isNotEmpty(),
                        'editable' => $events->isEmpty() || $events->every(
                            fn (Event $event): bool => $this->isTeamEventEditable($event),
                        ),
                        'schedule' => $schedulesByGroup->get($key, []),
                        'assignment_status_label' => $positions->contains('Team Leader') && $positions->contains('SPG')
                            ? 'Jadwal siap dilaksanakan'
                            : ($positions->isNotEmpty()
                                ? 'Anggota sedang ditugaskan'
                                : 'Menunggu pembagian anggota oleh PIC'),
                    ];
                });
        }

        $scheduleOrderByFjpId = collect($teamGroups)
            ->flatMap(fn (array $team): array => collect($team['schedule'])
                ->values()
                ->map(fn (array $school, int $index): array => [
                    'fjp_id' => $school['fjp_id'],
                    'school_order' => $index + 1,
                    'start_time' => $school['start_time'],
                ])
                ->all())
            ->keyBy('fjp_id');

        $rows = $pageFjps->through(function (Fjp $fjp) use (
            $teamGroups,
            $scheduleOrderByFjpId,
            $canAssignTeamMembers,
        ): array {
            $groupKey = $fjp->team_group_id === null
                ? null
                : ($fjp->event_date?->toDateString() ?? '').'|'.$fjp->team_group_id;
            $team = $groupKey === null ? null : ($teamGroups[$groupKey] ?? null);

            return [
                'id' => (int) $fjp->id,
                'event_date' => $fjp->event_date?->toDateString(),
                'start_time' => $fjp->start_time !== null ? substr((string) $fjp->start_time, 0, 5) : null,
                'school_name' => $fjp->school_name ?: $fjp->event_name,
                'venue' => $fjp->venue?->name ?: $fjp->school_name ?: $fjp->event_name,
                'location' => collect([
                    $fjp->address ?: $fjp->venue?->address,
                    $fjp->city ?: $fjp->venue?->city,
                    $fjp->province ?: $fjp->venue?->province,
                ])->filter()->implode(', '),
                'brand' => $fjp->brand?->name,
                'team_group_id' => $fjp->team_group_id,
                'team_group_label' => $fjp->team_group_label,
                'team_group_fjp_ids' => $team['fjp_ids'] ?? [(int) $fjp->id],
                'team_group_pending_fjp_ids' => $team['pending_fjp_ids'] ?? [(int) $fjp->id],
                'team_members' => $canAssignTeamMembers ? ($team['members'] ?? []) : [],
                'team_has_event' => $team['has_event'] ?? false,
                'team_editable' => $team['editable'] ?? false,
                'team_assignment_status' => $team['assignment_status_label'] ?? 'Menunggu pembagian tim oleh Permitter',
                'school_order' => $scheduleOrderByFjpId->get($fjp->id)['school_order'] ?? null,
                'remark' => $fjp->notes,
            ];
        });

        return Inertia::render('Fjp/TeamAssignment', [
            'rows' => $rows,
            'filters' => [
                'search' => $request->string('search')->toString(),
                'brand_id' => BrandContext::resolve($request),
                'date_from' => $request->string('date_from')->toString(),
                'date_to' => $request->string('date_to')->toString(),
                'per_page' => $pageFjps->perPage(),
            ],
            'totalCount' => $pageFjps->total(),
            'brands' => Brand::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'teamPersonnel' => $canAssignTeamMembers
                ? Personnel::query()
                    ->where('is_active', true)
                    ->whereHas('user', fn (Builder $query) => $query
                        ->where('is_active', true)
                        ->whereHas('roles', fn (Builder $roles) => $roles->where('slug', 'team')))
                    ->with('user:id,name,phone')
                    ->orderBy('name')
                    ->get(['id', 'user_id', 'name', 'role', 'phone'])
                    ->map(fn (Personnel $personnel): array => [
                        'id' => (int) $personnel->id,
                        'user_id' => (int) $personnel->user_id,
                        'name' => $personnel->name,
                        'role' => $personnel->role,
                        'phone' => $personnel->phone ?? $personnel->user?->phone,
                    ])
                : [],
            'canAssignTeam' => $canAssignTeam,
            'canAssignTeamMembers' => $canAssignTeamMembers,
        ]);
    }

    /**
     * Inline update of FJP notes and location data from the list view.
     */
    public function updateInline(Request $request, Fjp $fjp, FjpVenueResolver $venues)
    {
        $data = $request->validate([
            'school_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'npsn' => ['sometimes', 'nullable', 'string', 'max:20'],
            'address' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'province' => ['sometimes', 'nullable', 'string', 'max:100'],
            'maps_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'jumlah_siswa_dapodik' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'dapodik_source' => ['sometimes', 'nullable', 'string', Rule::in(['Manual', self::DAPODIK_SOURCE])],
            'event_date' => ['sometimes', 'nullable', 'date'],
            'start_time' => ['sometimes', 'nullable', 'date_format:H:i'],
            'jumlah_siswa_sekolah' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'kecamatan' => ['sometimes', 'nullable', 'string', 'max:100'],
            'kelurahan' => ['sometimes', 'nullable', 'string', 'max:100'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'permitter_total_outlet_canteen_sell_drink' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'permitter_quantity_with_milo' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'permitter_status' => ['sometimes', 'nullable', Rule::in(self::PERMITTER_VALIDATION_STATUSES)],
            'permitter_quantity_without_milo' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'team_sampling_quantity_with_milo' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'team_sampling_quantity_without_milo' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'team_number' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:25'],
        ]);

        if ($data === []) {
            return back()->with('info', 'Tidak ada perubahan untuk disimpan.');
        }

        if (array_key_exists('jumlah_siswa_sekolah', $data)) {
            $data['sampling_target'] = $data['jumlah_siswa_sekolah'];
        }

        if (array_key_exists('dapodik_source', $data)) {
            $data['dapodik_synced_at'] = now();
        }

        $this->validatePermittersAgainstTotal(array_merge(
            $fjp->only([
                'permitter_total_outlet_canteen_sell_drink',
                'permitter_quantity_with_milo',
                'permitter_quantity_without_milo',
            ]),
            $data,
        ));

        $old = $fjp->only(array_keys($data));

        if (array_key_exists('team_number', $data)) {
            abort_unless($this->canAssignFjpTeam($request->user()), 403);

            if ($fjp->status !== Fjp::STATUS_CONFIRM) {
                throw ValidationException::withMessages([
                    'team_number' => 'Kelompok tim hanya dapat dibagikan setelah status FJP Confirm.',
                ]);
            }

            if ($fjp->events()->exists()) {
                throw ValidationException::withMessages([
                    'team_number' => 'Kelompok tim tidak dapat diubah setelah FJP memiliki Event.',
                ]);
            }

            $teamNumber = $data['team_number'];
            unset($data['team_number']);

            if ($teamNumber === null) {
                $data['team_group_id'] = null;
                $data['team_group_label'] = null;
            } else {
                $existingTeamQuery = Fjp::query()
                    ->where('team_group_label', "Tim {$teamNumber}")
                    ->whereNotNull('team_group_id');

                if ($fjp->brand_id === null) {
                    $existingTeamQuery->whereNull('brand_id');
                } else {
                    $existingTeamQuery->where('brand_id', $fjp->brand_id);
                }

                if ($fjp->event_date === null) {
                    $existingTeamQuery->whereNull('event_date');
                } else {
                    $existingTeamQuery->whereDate('event_date', $fjp->event_date->toDateString());
                }

                $data['team_group_id'] = $existingTeamQuery->value('team_group_id') ?: (string) Str::uuid();
                $data['team_group_label'] = "Tim {$teamNumber}";
            }
        }

        $fjp->update($data);
        $venues->syncLocation($fjp);

        if (array_key_exists('event_date', $data) || array_key_exists('start_time', $data)) {
            $fjp->events()->update(array_filter([
                'event_date' => $data['event_date'] ?? null,
                'start_time' => $data['start_time'] ?? null,
            ], fn ($value): bool => $value !== null));
        }

        AuditLogger::log('UPDATE_FJP_INLINE', 'fjp', $fjp, $old, $data);

        return back()->with('success', 'Perubahan FJP disimpan.');
    }

    public function updateStatus(Request $request, Fjp $fjp)
    {
        $data = $request->validate([
            'status' => ['required', 'string', Rule::in(array_keys(self::STATUS_OPTIONS))],
        ]);

        DB::transaction(function () use ($fjp, $data): void {
            $lockedFjp = Fjp::query()->lockForUpdate()->findOrFail($fjp->id);

            $oldStatus = (string) $lockedFjp->status;

            if ($oldStatus === $data['status']) {
                return;
            }

            $lockedFjp->update(['status' => $data['status']]);

            $this->syncEventStatusesFromFjp($lockedFjp, $data['status']);

            AuditLogger::log('UPDATE_FJP_STATUS', 'fjp', $lockedFjp, ['status' => $oldStatus], [
                'status' => $data['status'],
            ]);
        });

        return back()->with('success', 'Status FJP diperbarui.');
    }

    public function teamSchedulePreview(Request $request): JsonResponse
    {
        $this->authorizeFjpTeamAssignment($request);

        $data = $request->validate([
            'event_date' => ['required', 'date_format:Y-m-d'],
        ]);

        $events = Event::query()
            ->whereDate('event_date', $data['event_date'])
            ->with([
                'fjp:id,school_name,event_name,npsn,city,jumlah_siswa_sekolah,status',
                'venue:id,name,city',
                'teamMembers.user:id,name',
                'teamMembers.personnel:id,name',
            ])
            ->withExists([
                'attendances as has_attendance_activity' => fn ($query) => $query
                    ->where(fn ($attendances) => $attendances
                        ->whereNotNull('check_in_at')
                        ->orWhereNotNull('check_out_at')),
                'attendances as has_attendance_media' => fn ($query) => $query
                    ->whereHas('media'),
                'reports as has_reports',
                'quickReports as has_quick_reports',
                'stockRequests as has_stock_requests',
            ])
            ->orderBy('id')
            ->get();

        $teams = $events
            ->groupBy(fn (Event $event): string => $this->teamGroupingKey($event, true))
            ->map(function (Collection $teamEvents): ?array {
                if ($teamEvents->contains(fn (Event $event): bool => (
                    $event->status === EventStatus::CANCELLED
                    || $event->fjp?->status !== Fjp::STATUS_CONFIRM
                ))) {
                    return null;
                }

                $members = $teamEvents
                    ->flatMap(fn (Event $event) => $event->teamMembers->map(fn ($member) => [
                        'position' => $member->position,
                        'name' => $member->memberName(),
                        'user_id' => $member->user_id === null ? null : (int) $member->user_id,
                    ]))
                    ->unique(fn (array $member): string => $member['position'].'|'.($member['user_id'] ?? $member['name']))
                    ->values();

                if ($members->isEmpty()) {
                    return null;
                }

                $schools = $teamEvents
                    ->map(fn (Event $event): array => [
                        'name' => $event->fjp?->school_name
                            ?: $event->fjp?->event_name
                            ?: $event->venue?->name
                            ?: $event->event_name,
                        'city' => $event->fjp?->city ?: $event->venue?->city,
                        'student_count' => $event->fjp?->jumlah_siswa_sekolah ?? $event->sampling_target ?? 0,
                    ])
                    ->unique(fn (array $school): string => $school['name'].'|'.$school['city'])
                    ->values();
                $teamGroupId = $teamEvents->first()?->team_group_id;

                return [
                    'id' => $teamGroupId ?? 'legacy-'.$teamEvents->first()?->id,
                    'team_group_id' => $teamGroupId,
                    'event_ids' => $teamEvents->modelKeys(),
                    'editable' => $teamGroupId !== null
                        && $teamEvents->every(fn (Event $event): bool => $this->isTeamEventEditable($event)),
                    'members' => $members->all(),
                    'schools' => $schools->all(),
                    'total_students' => (int) $schools->sum('student_count'),
                ];
            })
            ->filter()
            ->values();

        return response()->json([
            'event_date' => $data['event_date'],
            'teams' => $teams,
        ]);
    }

    public function updateTeamScheduleGroup(
        Request $request,
        string $teamGroupId,
        ApproveFjpAndAssignTeam $approval,
    ): RedirectResponse {
        $this->authorizeFjpTeamAssignment($request);

        if ($request->exists('team_leader_personnel_id') || $request->exists('spg_personnel_ids')) {
            $data = $request->validate([
                'team_leader_personnel_id' => ['required', 'integer', 'exists:personnel,id'],
                'spg_personnel_ids' => ['required', 'array', 'size:1'],
                'spg_personnel_ids.*' => ['required', 'integer', 'distinct', 'exists:personnel,id'],
            ]);

            if (in_array((int) $data['team_leader_personnel_id'], array_map('intval', $data['spg_personnel_ids']), true)) {
                throw ValidationException::withMessages([
                    'spg_personnel_ids' => 'Team Leader tidak dapat dipilih kembali sebagai SPG.',
                ]);
            }

            $updatedEvents = $approval->updatePersonnelTeamGroupMembers(
                $teamGroupId,
                $request->user(),
                (int) $data['team_leader_personnel_id'],
                array_map('intval', $data['spg_personnel_ids']),
            );

            return back()->with(
                'success',
                "Personel pada {$updatedEvents} sekolah berhasil diperbarui.",
            );
        }

        $data = $request->validate([
            'team_leader_id' => ['required', 'integer', 'exists:users,id'],
            'spg_user_id' => ['required', 'integer', 'exists:users,id', 'different:team_leader_id'],
        ]);

        $personnelByUserId = Personnel::query()
            ->whereIn('user_id', [$data['team_leader_id'], $data['spg_user_id']])
            ->get()
            ->keyBy('user_id');

        $teamLeader = $personnelByUserId->get((int) $data['team_leader_id']);
        $spg = $personnelByUserId->get((int) $data['spg_user_id']);

        if (! $teamLeader || ! $spg) {
            throw ValidationException::withMessages([
                'team_leader_id' => 'Pilih personel dari master data yang sudah terhubung ke akun Tim.',
            ]);
        }

        $updatedEvents = $approval->updatePersonnelTeamGroupMembers(
            $teamGroupId,
            $request->user(),
            (int) $teamLeader->id,
            [(int) $spg->id],
        );

        return back()->with(
            'success',
            "Anggota tim pada {$updatedEvents} sekolah berhasil diperbarui.",
        );
    }

    public function removePersonnelFromTeamScheduleGroup(
        Request $request,
        string $teamGroupId,
        Personnel $personnel,
        ApproveFjpAndAssignTeam $approval,
    ): RedirectResponse {
        $this->authorizeFjpTeamAssignment($request);

        $removedMembers = $approval->removePersonnelFromTeamGroup($teamGroupId, $personnel);

        return back()->with('success', "{$removedMembers} penugasan personel berhasil dihapus.");
    }

    public function addSchoolToTeamScheduleGroup(
        Request $request,
        string $teamGroupId,
        ApproveFjpAndAssignTeam $approval,
    ): RedirectResponse {
        $this->authorizeFjpTeamAssignment($request);

        $data = $request->validate([
            'fjp_id' => ['required', 'integer', 'exists:fjp,id'],
        ]);

        $approval->addSchoolToTeamGroup(
            $teamGroupId,
            (int) $data['fjp_id'],
            $request->user(),
        );

        return back()->with('success', 'Sekolah berhasil ditambahkan ke tim.');
    }

    private function teamGroupingKey(Event $event, bool $preferTeamGroupId = false): string
    {
        $date = $event->event_date?->toDateString() ?? 'no-date';

        if ($preferTeamGroupId && $event->team_group_id !== null) {
            return $date.'|group:'.$event->team_group_id;
        }

        $members = $event->teamMembers
            ->map(fn ($member): string => implode(':', [
                $member->user_id ? 'user' : 'personnel',
                $member->user_id ?? $member->personnel_id ?? 0,
                $member->position ?? '',
            ]))
            ->sort()
            ->implode('|');

        return $date.'|'.($members !== '' ? $members : 'group:'.($event->team_group_id ?? $event->id));
    }

    private function isTeamEventEditable(Event $event): bool
    {
        return in_array($event->status, [
            EventStatus::PLANNING,
            EventStatus::TEAM_ASSIGNMENT,
        ], true)
            && $event->fjp?->status === Fjp::STATUS_CONFIRM
            && ! $event->has_attendance_activity
            && ! $event->has_attendance_media
            && ! $event->has_reports
            && ! $event->has_quick_reports
            && ! $event->has_stock_requests;
    }

    private function syncEventStatusesFromFjp(Fjp $fjp, string $fjpStatus): void
    {
        $preparationStatuses = [
            EventStatus::DRAFT,
            EventStatus::PLANNING,
            EventStatus::PERMISSION,
            EventStatus::TEAM_ASSIGNMENT,
        ];

        $target = match ($fjpStatus) {
            Fjp::STATUS_PLAN, Fjp::STATUS_RESCHEDULE => EventStatus::PLANNING,
            Fjp::STATUS_CONFIRM => EventStatus::TEAM_ASSIGNMENT,
            Fjp::STATUS_CANCEL => EventStatus::CANCELLED,
            default => null,
        };

        if ($target === null) {
            return;
        }

        $events = $fjp->events()->get();

        foreach ($events as $event) {
            $canSync = $target === EventStatus::CANCELLED
                ? $event->status !== EventStatus::COMPLETED
                : in_array($event->status, $preparationStatuses, true);

            if (! $canSync || $event->status === $target) {
                continue;
            }

            $oldStatus = $event->status->value;
            $event->update(['status' => $target]);

            AuditLogger::log('SYNC_EVENT_STATUS_FROM_FJP', 'events', $event, [
                'status' => $oldStatus,
            ], [
                'status' => $target->value,
                'fjp_id' => $fjp->id,
            ]);
        }
    }

    public function store(Request $request, FjpVenueResolver $venues)
    {
        $data = $this->validated($request);
        if (($data['jumlah_siswa_dapodik'] ?? null) === null && ($data['dapodik_source'] ?? null) === 'Manual') {
            $data['dapodik_source'] = null;
        }
        $data['dapodik_synced_at'] = ($data['dapodik_source'] ?? null) === self::DAPODIK_SOURCE
            ? now()
            : null;
        $fjp = DB::transaction(function () use ($data, $request, $venues): Fjp {
            $data['created_by'] = $request->user()->id;
            $data['status'] = Fjp::STATUS_PLAN;
            $data['fjp_number'] = Fjp::generateNumber();
            $data['event_name'] = $data['event_name'] ?? ($data['school_name'] ?? null);
            $fjp = Fjp::create($data);
            $venues->ensureVenue($fjp);

            return $fjp;
        });

        AuditLogger::log('CREATE_FJP', 'fjp', $fjp, null, $data);

        return redirect()->route('fjp.show', $fjp)->with('success', 'FJP berhasil dibuat.');
    }

    public function storeDapodikSchool(
        Request $request,
        DapoApiService $dapo,
        FjpVenueResolver $venues,
    ): RedirectResponse {
        $data = $request->validate([
            'npsn' => ['required', 'string', 'max:20'],
        ]);

        try {
            $detail = $dapo->detail($data['npsn']);
        } catch (RuntimeException) {
            return back()->with('error', 'Layanan Dapodik sedang tidak tersedia. Silakan coba lagi.');
        }

        $schoolName = trim((string) ($detail['school_name'] ?? ''));
        $city = trim((string) ($detail['kabupaten'] ?? ''));

        if ($detail === null || $schoolName === '' || ! $this->isJabodetabek((string) ($detail['provinsi'] ?? ''), $city)) {
            throw ValidationException::withMessages([
                'npsn' => 'Pilih sekolah Jabodetabek yang terdaftar di Dapodik.',
            ]);
        }

        $fjp = DB::transaction(function () use ($data, $detail, $schoolName, $request, $venues): Fjp {
            $fjp = Fjp::create([
                'fjp_number' => Fjp::generateNumber(),
                'event_name' => $schoolName,
                'school_name' => $schoolName,
                'brand_id' => $data['brand_id'] ?? null,
                'event_type_id' => $data['event_type_id'] ?? null,
                'npsn' => $detail['npsn'] ?? $data['npsn'],
                'address' => $detail['address'] ?? null,
                'city' => $detail['kabupaten'] ?? null,
                'province' => $detail['provinsi'] ?? null,
                'kecamatan' => $detail['kecamatan'] ?? null,
                'kelurahan' => $detail['kelurahan'] ?? null,
                'maps_url' => $detail['maps_url'] ?? null,
                'jumlah_siswa_dapodik' => $detail['jumlah_siswa'] ?? null,
                'dapodik_source' => self::DAPODIK_SOURCE,
                'dapodik_synced_at' => now(),
                'status' => Fjp::STATUS_PLAN,
                'created_by' => $request->user()->id,
            ]);

            $venues->ensureVenue($fjp);

            return $fjp;
        });

        AuditLogger::log('CREATE_FJP', 'fjp', $fjp, null, [
            'school_name' => $fjp->school_name,
            'npsn' => $fjp->npsn,
            'source' => self::DAPODIK_SOURCE,
        ]);

        return back()->with('success', 'Sekolah Dapodik ditambahkan sebagai FJP draft. Lengkapi brand dan jadwal saat diperlukan.');
    }

    public function storeDapodikSchools(
        Request $request,
        DapoApiService $dapo,
        FjpVenueResolver $venues,
    ): RedirectResponse {
        $canAssignTeam = $this->canAssignFjpTeam($request->user());
        $requestsTeamGrouping = collect($request->input('schools', []))->contains(
            fn ($school): bool => is_array($school)
                && isset($school['team_number'])
                && $school['team_number'] !== '',
        );

        abort_if($requestsTeamGrouping && ! $canAssignTeam, 403);

        $data = $request->validate([
            'schools' => ['required', 'array', 'min:1', 'max:25'],
            'schools.*.npsn' => ['required', 'string', 'max:20', 'distinct'],
            'schools.*.jumlah_siswa_sekolah' => ['nullable', 'integer', 'min:0'],
            'schools.*.team_number' => ['nullable', 'integer', 'min:1', 'max:25'],
            'schools.*.event_date' => ['nullable', 'date'],
            'schools.*.start_time' => ['nullable', 'date_format:H:i'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'event_type_id' => ['nullable', 'integer', 'exists:event_types,id'],
        ]);

        if (! empty($data['brand_id'])) {
            $schoolSamplingType = EventType::query()
                ->where('brand_id', $data['brand_id'])
                ->where('is_active', true)
                ->where(function ($query): void {
                    $query->where('slug', 'like', '%-school-sampling')
                        ->orWhere('name', 'like', '%School Sampling%');
                })
                ->first();

            if ($schoolSamplingType) {
                $data['event_type_id'] = $schoolSamplingType->id;
            }
        }

        if (! empty($data['event_type_id'])) {
            $eventType = EventType::query()->find($data['event_type_id']);

            if (
                $eventType?->brand_id
                && ! empty($data['brand_id'])
                && (int) $eventType->brand_id !== (int) $data['brand_id']
            ) {
                throw ValidationException::withMessages([
                    'event_type_id' => 'Jenis event tidak sesuai dengan brand yang dipilih.',
                ]);
            }
        }

        $schools = [];

        foreach ($data['schools'] as $index => $school) {
            try {
                $detail = $dapo->detail($school['npsn']);
            } catch (RuntimeException) {
                throw ValidationException::withMessages([
                    "schools.{$index}.npsn" => 'Layanan Dapodik sedang tidak tersedia. Silakan coba lagi.',
                ]);
            }

            $schoolName = trim((string) ($detail['school_name'] ?? ''));
            $city = trim((string) ($detail['kabupaten'] ?? ''));
            $dapodikNpsn = trim((string) ($detail['npsn'] ?? $school['npsn']));

            if (
                $detail === null
                || $schoolName === ''
                || $dapodikNpsn !== $school['npsn']
                || ! $this->isJabodetabek((string) ($detail['provinsi'] ?? ''), $city)
            ) {
                throw ValidationException::withMessages([
                    "schools.{$index}.npsn" => 'Pilih sekolah Jabodetabek yang terdaftar di Dapodik.',
                ]);
            }

            $detail['npsn'] = $dapodikNpsn;

            $schools[] = [
                'detail' => $detail,
                'jumlah_siswa_sekolah' => $school['jumlah_siswa_sekolah'] ?? null,
                'team_number' => $school['team_number'] ?? null,
                'event_date' => $school['event_date'] ?? null,
                'start_time' => $school['start_time'] ?? null,
            ];
        }

        $teamGroupIds = [];

        foreach ($schools as $school) {
            $teamNumber = $school['team_number'];

            if ($teamNumber === null) {
                continue;
            }

            $groupKey = implode('|', [
                (string) ($data['brand_id'] ?? ''),
                (string) ($school['event_date'] ?? ''),
                (string) $teamNumber,
            ]);

            if (array_key_exists($groupKey, $teamGroupIds)) {
                continue;
            }

            $existingTeamQuery = Fjp::query()
                ->where('team_group_label', "Tim {$teamNumber}")
                ->whereNotNull('team_group_id');

            if (empty($data['brand_id'])) {
                $existingTeamQuery->whereNull('brand_id');
            } else {
                $existingTeamQuery->where('brand_id', $data['brand_id']);
            }

            if ($school['event_date'] === null) {
                $existingTeamQuery->whereNull('event_date');
            } else {
                $existingTeamQuery->whereDate('event_date', $school['event_date']);
            }

            $teamGroupIds[$groupKey] = (string) (
                $existingTeamQuery->value('team_group_id') ?? Str::uuid()
            );
        }

        DB::transaction(function () use ($schools, $request, $venues, $teamGroupIds, $data): void {
            foreach ($schools as $school) {
                $detail = $school['detail'];
                $schoolName = trim((string) $detail['school_name']);
                $teamNumber = $school['team_number'];
                $teamGroupKey = implode('|', [
                    (string) ($data['brand_id'] ?? ''),
                    (string) ($school['event_date'] ?? ''),
                    (string) ($teamNumber ?? ''),
                ]);
                $fjp = Fjp::create([
                    'fjp_number' => Fjp::generateNumber(),
                    'event_name' => $schoolName,
                    'school_name' => $schoolName,
                    'brand_id' => $data['brand_id'] ?? null,
                    'event_type_id' => $data['event_type_id'] ?? null,
                    'npsn' => $detail['npsn'],
                    'address' => $detail['address'] ?? null,
                    'city' => $detail['kabupaten'] ?? null,
                    'province' => $detail['provinsi'] ?? null,
                    'kecamatan' => $detail['kecamatan'] ?? null,
                    'kelurahan' => $detail['kelurahan'] ?? null,
                    'maps_url' => $detail['maps_url'] ?? null,
                    'jumlah_siswa_sekolah' => $school['jumlah_siswa_sekolah'],
                    'jumlah_siswa_dapodik' => $detail['jumlah_siswa'] ?? null,
                    'sampling_target' => $school['jumlah_siswa_sekolah'],
                    'dapodik_source' => self::DAPODIK_SOURCE,
                    'dapodik_synced_at' => now(),
                    'event_date' => $school['event_date'],
                    'start_time' => $school['start_time'],
                    'status' => Fjp::STATUS_PLAN,
                    'created_by' => $request->user()->id,
                    'team_group_id' => $teamNumber === null ? null : $teamGroupIds[$teamGroupKey],
                    'team_group_label' => $teamNumber === null ? null : "Tim {$teamNumber}",
                ]);

                $venues->ensureVenue($fjp);

                AuditLogger::log('CREATE_FJP', 'fjp', $fjp, null, [
                    'school_name' => $fjp->school_name,
                    'npsn' => $fjp->npsn,
                    'source' => self::DAPODIK_SOURCE,
                    'team_group_id' => $fjp->team_group_id,
                ]);
            }
        });

        return back()->with(
            'success',
            count($schools).' FJP draft berhasil ditambahkan dari Dapodik.',
        );
    }

    public function show(Fjp $fjp): Response
    {
        $fjp->load([
            'attachments.uploader:id,name',
            'events' => fn ($query) => $query->latest(),
            'venue:id,name,address,city',
        ]);

        $user = request()->user();

        return Inertia::render('Fjp/Show', [
            'fjp' => [
                'id' => $fjp->id,
                'fjp_number' => $fjp->fjp_number,
                'event_name' => $fjp->event_name,
                'school_name' => $fjp->school_name,
                'npsn' => $fjp->npsn,
                'address' => $fjp->address,
                'city' => $fjp->city,
                'province' => $fjp->province,
                'kecamatan' => $fjp->kecamatan,
                'kelurahan' => $fjp->kelurahan,
                'maps_url' => $fjp->maps_url,
                'venue_id' => $fjp->venue_id,
                'venue_name' => $fjp->venue?->name,
                'jumlah_siswa_sekolah' => $fjp->jumlah_siswa_sekolah,
                'jumlah_siswa_dapodik' => $fjp->jumlah_siswa_dapodik,
                'difference' => $fjp->student_count_difference,
                'dapodik_source' => $fjp->dapodik_source,
                'dapodik_synced_at' => $fjp->dapodik_synced_at?->toIso8601String(),
                'notes' => $fjp->notes,
                'permitter_total_outlet_canteen_sell_drink' => $fjp->permitter_total_outlet_canteen_sell_drink,
                'permitter_quantity_with_milo' => $fjp->permitter_quantity_with_milo,
                'permitter_status' => $fjp->permitter_status,
                'permitter_quantity_without_milo' => $fjp->permitter_quantity_without_milo,
                'team_sampling_quantity_with_milo' => $fjp->team_sampling_quantity_with_milo,
                'team_sampling_quantity_without_milo' => $fjp->team_sampling_quantity_without_milo,
                'event_date' => $fjp->event_date?->toDateString(),
                'sampling_target' => $fjp->sampling_target,
                'jug_required' => $fjp->jug_required,
                'status' => $this->generalStatus((string) $fjp->status),
                'status_label' => self::STATUS_OPTIONS[$this->generalStatus((string) $fjp->status)]
                    ?? ucfirst((string) $fjp->status),
            ],
            'attachments' => $fjp->attachments->map(fn ($a) => [
                'id' => $a->id,
                'category' => $a->category,
                'original_name' => $a->original_name,
                'uploaded_at' => $a->created_at?->toIso8601String(),
                'uploaded_by' => $a->uploader?->name,
                'url' => route('fjp.attachments.download', $a->id),
            ]),
            'history' => AuditLog::query()
                ->where('module', 'fjp')
                ->where('record_type', $fjp->getMorphClass())
                ->where('record_id', $fjp->id)
                ->whereIn('action', [
                    'CREATE_FJP', 'UPDATE_FJP', 'UPDATE_FJP_INLINE', 'DELETE_FJP',
                    'SYNC_DAPODIK', 'UPLOAD_FJP_ATTACHMENT',
                ])
                ->with('user:id,name')
                ->latest()
                ->limit(30)
                ->get()
                ->map(fn (AuditLog $log) => [
                    'id' => $log->id,
                    'action' => $log->action,
                    'user' => $log->user?->name,
                    'role' => $log->role,
                    'created_at' => $log->created_at?->toIso8601String(),
                    'reason' => $log->new_values['reason'] ?? null,
                ]),
            'events' => $fjp->events->map(fn (Event $event) => [
                'id' => $event->id,
                'event_code' => $event->event_code,
                'event_name' => $event->event_name,
                'event_date' => $event->event_date?->toDateString(),
                'status' => $event->status->value,
                'status_label' => $event->status->label(),
                'url' => route('events.show', $event->id),
            ])->values(),
            'can' => [
                'update' => $user->hasPermission('fjp.update'),
                'delete' => $user->hasPermission('fjp.delete'),
            ],
        ]);
    }

    public function edit(Fjp $fjp): Response
    {
        return Inertia::render('Fjp/Edit', [
            'fjp' => $fjp,
            'formOptions' => $this->formOptions(),
        ]);
    }

    public function update(Request $request, Fjp $fjp, FjpVenueResolver $venues)
    {
        $data = $this->validated($request);

        if (($data['jumlah_siswa_dapodik'] ?? null) === null && ($data['dapodik_source'] ?? null) === 'Manual') {
            $data['dapodik_source'] = null;
        }

        $source = $data['dapodik_source'] ?? null;

        if ($source !== self::DAPODIK_SOURCE) {
            $data['dapodik_synced_at'] = null;
        } elseif (
            $fjp->dapodik_source !== $source
            || $fjp->npsn !== ($data['npsn'] ?? null)
            || $fjp->jumlah_siswa_dapodik !== ($data['jumlah_siswa_dapodik'] ?? null)
        ) {
            $data['dapodik_synced_at'] = now();
        }

        $old = $fjp->only(array_merge(array_keys($data), ['dapodik_synced_at']));

        $fjp->update($data);

        $venues->syncLocation($fjp);

        AuditLogger::log('UPDATE_FJP', 'fjp', $fjp, $old, $data);

        return redirect()->route('fjp.show', $fjp)->with('success', 'FJP diperbarui.');
    }

    public function destroy(Fjp $fjp)
    {
        AuditLogger::log('DELETE_FJP', 'fjp', $fjp);
        $fjp->delete();

        return redirect()->route('fjp.index')->with('success', 'FJP dihapus.');
    }

    /**
     * Sync the school's data from the public Dapodik database. Updates only
     * the Dapodik-sourced fields; the school-reported student count is never
     * overwritten.
     */
    public function syncDapodik(Request $request, Fjp $fjp, DapoApiService $dapo)
    {
        $data = $request->validate([
            'npsn' => ['nullable', 'string', 'max:20'],
        ]);

        $npsn = trim((string) ($data['npsn'] ?? $fjp->npsn));
        try {
            $detail = $npsn !== '' ? $dapo->detail($npsn) : null;
        } catch (RuntimeException) {
            return back()->with('error', 'Layanan Dapodik sedang tidak tersedia. Silakan coba lagi.');
        }

        $old = ['jumlah_siswa_dapodik' => $fjp->jumlah_siswa_dapodik];
        $npsnChanged = trim((string) $fjp->npsn) !== $npsn;

        if ($detail) {
            $fjp->update([
                'npsn' => $detail['npsn'] ?? $npsn,
                'school_name' => $npsnChanged
                    ? ($detail['school_name'] ?? null)
                    : ($fjp->school_name ?: ($detail['school_name'] ?? null)),
                'address' => $npsnChanged
                    ? ($detail['address'] ?? null)
                    : ($fjp->address ?: ($detail['address'] ?? null)),
                'city' => $npsnChanged
                    ? ($detail['kabupaten'] ?? null)
                    : ($fjp->city ?: ($detail['kabupaten'] ?? null)),
                'province' => $npsnChanged
                    ? ($detail['provinsi'] ?? null)
                    : ($fjp->province ?: ($detail['provinsi'] ?? null)),
                'kecamatan' => $npsnChanged
                    ? ($detail['kecamatan'] ?? null)
                    : ($fjp->kecamatan ?: ($detail['kecamatan'] ?? null)),
                'kelurahan' => $npsnChanged
                    ? ($detail['kelurahan'] ?? null)
                    : ($fjp->kelurahan ?: ($detail['kelurahan'] ?? null)),
                'maps_url' => $npsnChanged
                    ? ($detail['maps_url'] ?? null)
                    : ($fjp->maps_url ?: ($detail['maps_url'] ?? null)),
                'jumlah_siswa_dapodik' => $detail['jumlah_siswa'] ?? $fjp->jumlah_siswa_dapodik,
                'dapodik_source' => 'Dapodik (dapo.kemendikdasmen.go.id)',
                'dapodik_synced_at' => now(),
            ]);
        }

        AuditLogger::log('SYNC_DAPODIK', 'fjp', $fjp, $old, [
            'jumlah_siswa_dapodik' => $fjp->jumlah_siswa_dapodik,
            'source' => 'Dapodik (dapo.kemendikdasmen.go.id)',
        ]);

        return back()->with(
            $detail ? 'success' : 'error',
            $detail ? 'Data Dapodik diperbarui.' : 'Data sekolah tidak ditemukan di Dapodik.'
        );
    }

    public function uploadAttachment(Request $request, Fjp $fjp)
    {
        $data = $request->validate([
            'category' => ['nullable', Rule::in(['supporting', 'school', 'other'])],
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx'],
        ]);

        $file = $request->file('file');
        $path = $file->store('fjp/'.$fjp->id);

        $attachment = $fjp->attachments()->create([
            'category' => $data['category'] ?? 'other',
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => $request->user()->id,
        ]);

        AuditLogger::log('UPLOAD_FJP_ATTACHMENT', 'fjp', $fjp, null, ['name' => $attachment->original_name]);

        return back()->with('success', 'Lampiran diunggah.');
    }

    public function assignTeamGroup(Request $request, ApproveFjpAndAssignTeam $approval)
    {
        $this->authorizeFjpTeamAssignment($request);

        if ($request->exists('team_leader_personnel_id') || $request->exists('spg_personnel_ids')) {
            $data = $request->validate([
                'fjp_ids' => ['required', 'array', 'min:1'],
                'fjp_ids.*' => ['required', 'integer', 'distinct', 'exists:fjp,id'],
                'team_leader_personnel_id' => ['required', 'integer', 'exists:personnel,id'],
                'spg_personnel_ids' => ['required', 'array', 'size:1'],
                'spg_personnel_ids.*' => ['required', 'integer', 'distinct', 'exists:personnel,id'],
            ]);

            if (in_array((int) $data['team_leader_personnel_id'], array_map('intval', $data['spg_personnel_ids']), true)) {
                throw ValidationException::withMessages([
                    'spg_personnel_ids' => 'Team Leader tidak dapat dipilih kembali sebagai SPG.',
                ]);
            }

            $fjps = Fjp::query()->whereIn('id', $data['fjp_ids'])->get();
            $events = $approval->handlePersonnelGroup(
                $fjps->all(),
                $request->user(),
                (int) $data['team_leader_personnel_id'],
                array_map('intval', $data['spg_personnel_ids']),
            );

            return back()->with(
                'success',
                count($events).' FJP berhasil ditugaskan kepada Team Leader dan personel SPG.',
            );
        }

        $data = $request->validate([
            'fjp_ids' => ['required', 'array', 'min:1'],
            'fjp_ids.*' => ['required', 'integer', 'distinct', 'exists:fjp,id'],
            'team_leader_id' => ['required', 'integer', 'exists:users,id', 'different:spg_user_id'],
            'spg_user_id' => ['required', 'integer', 'exists:users,id', 'different:team_leader_id'],
        ]);

        $teamLeader = Personnel::query()->where('user_id', $data['team_leader_id'])->first();
        $spg = Personnel::query()->where('user_id', $data['spg_user_id'])->first();

        if (! $teamLeader || ! $spg) {
            throw ValidationException::withMessages([
                'fjp_ids' => 'Pilih personel dari master data yang sudah terhubung ke akun Tim.',
            ]);
        }

        $fjps = Fjp::query()->whereIn('id', $data['fjp_ids'])->get();
        $events = $approval->handlePersonnelGroup(
            $fjps->all(),
            $request->user(),
            (int) $teamLeader->id,
            [(int) $spg->id],
        );

        return back()->with(
            'success',
            count($events).' sekolah berhasil dikelompokkan dan ditugaskan kepada Team Leader dan SPG.',
        );
    }

    public function scheduleTeamGroups(Request $request, ApproveFjpAndAssignTeam $approval)
    {
        $this->authorizeFjpTeamAssignment($request);

        $data = $request->validate([
            'event_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:tomorrow'],
            'groups' => ['required', 'array', 'min:1'],
            'groups.*.fjp_ids' => ['required', 'array', 'min:1'],
            'groups.*.fjp_ids.*' => ['required', 'integer', 'distinct', 'exists:fjp,id'],
            'groups.*.team_leader_id' => ['required', 'integer', 'exists:users,id'],
            'groups.*.spg_user_id' => ['required', 'integer', 'exists:users,id', 'different:team_leader_id'],
        ]);

        $teamMemberIds = collect($data['groups'])
            ->flatMap(fn (array $group): array => [
                (int) $group['team_leader_id'],
                (int) $group['spg_user_id'],
            ]);

        $requiredMemberCount = count($data['groups']) * 2;

        if ($teamMemberIds->unique()->count() !== $requiredMemberCount) {
            throw ValidationException::withMessages([
                'groups' => 'Setiap tim harus menggunakan pasangan TL/SPG yang berbeda.',
            ]);
        }

        $events = $approval->scheduleGroups(
            $data['groups'],
            $request->user(),
            $data['event_date'],
        );

        return back()->with(
            'success',
            count($events).' sekolah Jabodetabek berhasil dijadwalkan dan dibagi ke tim.',
        );
    }

    /**
     * Master-data selects shared by the FJP create/edit/index forms.
     *
     * @return array{brands: Collection<int, Brand>, eventTypes: Collection<int, EventType>, cities: Collection<int, string>, provinces: Collection<int, string>}
     */
    private function formOptions(): array
    {
        return [
            'brands' => Brand::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'eventTypes' => EventType::where('is_active', true)->orderBy('name')->get(['id', 'brand_id', 'name', 'slug']),
            'cities' => collect()
                ->merge(Venue::query()->whereNotNull('city')->distinct()->pluck('city'))
                ->merge(Fjp::query()->whereNotNull('city')->distinct()->pluck('city'))
                ->filter()
                ->unique()
                ->sort()
                ->values(),
            'provinces' => collect()
                ->merge(Venue::query()->whereNotNull('province')->distinct()->pluck('province'))
                ->merge(Fjp::query()->whereNotNull('province')->distinct()->pluck('province'))
                ->filter()
                ->unique()
                ->sort()
                ->values(),
        ];
    }

    private function generalStatus(string $status): string
    {
        return match ($status) {
            'draft', 'submitted', 'pending' => Fjp::STATUS_PLAN,
            'approved' => Fjp::STATUS_CONFIRM,
            'rejected', 'cancelled' => Fjp::STATUS_CANCEL,
            default => $status,
        };
    }

    /**
     * @return array{assignment_status: string, assignment_status_label: string}
     */
    private function scheduleAssignmentStatus(
        Fjp $fjp,
        bool $hasCompleteTeam,
        bool $hasAssignedMembers,
    ): array {
        if ($fjp->status === Fjp::STATUS_CONFIRM) {
            return match (true) {
                $hasCompleteTeam => [
                    'assignment_status' => 'ready',
                    'assignment_status_label' => 'Jadwal siap dilaksanakan',
                ],
                $hasAssignedMembers => [
                    'assignment_status' => 'assigning_members',
                    'assignment_status_label' => 'Anggota sedang ditugaskan',
                ],
                $fjp->team_group_id !== null => [
                    'assignment_status' => 'awaiting_members',
                    'assignment_status_label' => 'Menunggu pembagian anggota oleh PIC',
                ],
                default => [
                    'assignment_status' => 'awaiting_team',
                    'assignment_status_label' => 'Menunggu pembagian tim oleh Permitter',
                ],
            };
        }

        if ($fjp->status === Fjp::STATUS_CANCEL) {
            return [
                'assignment_status' => 'cancelled',
                'assignment_status_label' => 'FJP dibatalkan',
            ];
        }

        if ($fjp->status === Fjp::STATUS_DONE) {
            return [
                'assignment_status' => 'done',
                'assignment_status_label' => 'Event selesai',
            ];
        }

        return [
            'assignment_status' => 'awaiting_approval',
            'assignment_status_label' => 'Menunggu FJP disetujui',
        ];
    }

    private function isJabodetabek(string $province, string $city): bool
    {
        $province = mb_strtolower($province);
        $city = mb_strtolower($city);

        if (str_contains($province, 'jakarta') || str_contains($city, 'jakarta')) {
            return true;
        }

        return collect(['bogor', 'depok', 'bekasi', 'tangerang'])
            ->contains(fn (string $area): bool => str_contains($city, $area));
    }

    private function canAssignFjpTeam(User $user): bool
    {
        return $user->hasPermission('fjp.assign_team')
            && ($user->hasRole(RoleSlug::PERMITTER) || $user->hasFullAccess());
    }

    private function canAssignFjpTeamMembers(User $user): bool
    {
        return $user->hasPermission('fjp.assign_team_members')
            && ($user->hasRole(RoleSlug::PIC) || $user->hasFullAccess());
    }

    private function authorizeFjpTeamAssignment(Request $request): void
    {
        abort_unless($this->canAssignFjpTeamMembers($request->user()), 403);
    }

    private function whereJabodetabek(Builder $query): void
    {
        $query->where(function (Builder $query): void {
            foreach (['Jakarta', 'Bogor', 'Depok', 'Tangerang', 'Bekasi'] as $region) {
                $query->orWhere('city', 'like', "%{$region}%")
                    ->orWhereHas('venue', fn (Builder $venue) => $venue->where('city', 'like', "%{$region}%"));
            }
        });
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'event_name' => ['nullable', 'string', 'max:255'],
            'brand_id' => ['nullable', 'exists:brands,id'],
            'event_type_id' => ['nullable', 'exists:event_types,id'],
            'school_name' => ['nullable', 'string', 'max:255'],
            'npsn' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:1000'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'kecamatan' => ['nullable', 'string', 'max:100'],
            'kelurahan' => ['nullable', 'string', 'max:100'],
            'maps_url' => ['nullable', 'string', 'max:2048'],
            'jumlah_siswa_sekolah' => ['nullable', 'integer', 'min:0'],
            'jumlah_siswa_dapodik' => ['nullable', 'integer', 'min:0'],
            'dapodik_source' => ['nullable', Rule::in(['Manual', self::DAPODIK_SOURCE])],
            'event_date' => ['nullable', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'sampling_target' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
            'permitter_total_outlet_canteen_sell_drink' => ['nullable', 'integer', 'min:0'],
            'permitter_quantity_with_milo' => ['nullable', 'integer', 'min:0'],
            'permitter_status' => ['nullable', Rule::in(self::PERMITTER_VALIDATION_STATUSES)],
            'permitter_quantity_without_milo' => ['nullable', 'integer', 'min:0'],
            'team_sampling_quantity_with_milo' => ['nullable', 'integer', 'min:0'],
            'team_sampling_quantity_without_milo' => ['nullable', 'integer', 'min:0'],
            'jug_required' => ['sometimes', 'boolean'],
        ]);

        $this->validatePermittersAgainstTotal($data);

        if (
            ($data['sampling_target'] ?? null) === null
            && ($data['jumlah_siswa_sekolah'] ?? null) !== null
        ) {
            $data['sampling_target'] = $data['jumlah_siswa_sekolah'];
        }

        if (! empty($data['event_type_id'])) {
            $type = EventType::find($data['event_type_id']);

            if ($type?->brand_id && (int) $type->brand_id !== (int) ($data['brand_id'] ?? 0)) {
                throw ValidationException::withMessages([
                    'event_type_id' => 'Jenis event tidak sesuai dengan brand yang dipilih.',
                ]);
            }
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validatePermittersAgainstTotal(array $data): void
    {
        $totalOutlets = $data['permitter_total_outlet_canteen_sell_drink'] ?? null;
        $withMilo = $data['permitter_quantity_with_milo'] ?? null;
        $withoutMilo = $data['permitter_quantity_without_milo'] ?? null;

        if (
            $totalOutlets !== null
            && $withMilo !== null
            && $withoutMilo !== null
            && $withMilo + $withoutMilo > $totalOutlets
        ) {
            throw ValidationException::withMessages([
                'permitter_total_outlet_canteen_sell_drink' => 'Jumlah kuantitas with dan without MILO tidak boleh melebihi total outlet.',
            ]);
        }
    }
}
