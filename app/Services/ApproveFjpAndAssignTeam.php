<?php

namespace App\Services;

use App\Enums\EventStatus;
use App\Enums\VenueStatus;
use App\Models\Attendance;
use App\Models\Event;
use App\Models\EventTeamMember;
use App\Models\Fjp;
use App\Models\Personnel;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ApproveFjpAndAssignTeam
{
    public function __construct(private FjpVenueResolver $venues) {}

    public function handle(
        Fjp $fjp,
        User $pic,
        int $teamLeaderId,
        int $spgId,
        ?Venue $venue = null,
        ?string $teamGroupId = null,
    ): Event {
        return DB::transaction(function () use ($fjp, $pic, $teamLeaderId, $spgId, $venue, $teamGroupId): Event {
            $fjp = Fjp::query()->lockForUpdate()->findOrFail($fjp->id);

            if ($fjp->status !== Fjp::STATUS_CONFIRM) {
                throw ValidationException::withMessages([
                    'fjp_id' => 'Tim hanya dapat dibagikan setelah status FJP Confirm.',
                ]);
            }

            if ($teamLeaderId === $spgId) {
                throw ValidationException::withMessages([
                    'spg_user_id' => 'Team Leader dan SPG harus merupakan anggota yang berbeda.',
                ]);
            }

            $teamLeader = $this->activeTeamMember($teamLeaderId, 'team_leader_id');
            $spg = $this->activeTeamMember($spgId, 'spg_user_id');

            if ($fjp->events()->where('status', '!=', EventStatus::CANCELLED->value)->exists()) {
                throw ValidationException::withMessages([
                    'fjp_id' => 'FJP ini sudah memiliki Event. Buka Event terkait untuk melanjutkan.',
                ]);
            }

            if ($venue) {
                $venue = Venue::query()->lockForUpdate()->findOrFail($venue->id);

                if ((int) $fjp->venue_id !== (int) $venue->id) {
                    throw ValidationException::withMessages([
                        'fjp_id' => 'FJP yang dipilih tidak terkait dengan venue ini.',
                    ]);
                }
            } else {
                $venue = $this->venues->ensureVenue($fjp);
            }

            if (! $venue) {
                throw ValidationException::withMessages([
                    'venue_id' => 'FJP belum memiliki venue yang dapat digunakan.',
                ]);
            }

            if ($venue->status === VenueStatus::REJECTED) {
                throw ValidationException::withMessages([
                    'venue_id' => 'Venue FJP ini telah ditolak dan tidak dapat dijadwalkan.',
                ]);
            }

            $fjp->loadMissing(['brand', 'eventType']);

            if ($fjp->eventType?->brand_id && (int) $fjp->eventType->brand_id !== (int) $fjp->brand_id) {
                throw ValidationException::withMessages([
                    'event_type_id' => 'Jenis event FJP tidak sesuai dengan brand.',
                ]);
            }

            $this->assertTeamAvailability(
                [$teamLeader->id, $spg->id],
                $fjp->event_date?->toDateString(),
                $this->scheduleTime($fjp->start_time),
                $this->scheduleTime($fjp->end_time),
                $fjp->team_group_id === null
                    ? []
                    : Event::query()->where('team_group_id', $fjp->team_group_id)->pluck('id')->all(),
            );

            $event = Event::create([
                'event_code' => Event::generateCode(),
                'event_name' => Event::composeName(
                    $fjp->brand?->name,
                    $fjp->eventType?->name,
                    $venue->name,
                ),
                'fjp_id' => $fjp->id,
                'brand_id' => $fjp->brand_id,
                'event_type_id' => $fjp->event_type_id,
                'venue_id' => $venue->id,
                'event_date' => $fjp->event_date?->toDateString(),
                'start_time' => $fjp->start_time,
                'end_time' => $fjp->end_time,
                'sampling_target' => $fjp->jumlah_siswa_sekolah ?? 0,
                'notes' => $fjp->notes,
                'jug_required' => (bool) $fjp->jug_required,
                'status' => EventStatus::TEAM_ASSIGNMENT,
                'pic_id' => $pic->id,
                'created_by' => $pic->id,
                'team_group_id' => $teamGroupId,
            ]);

            foreach ([[$teamLeader, 'Team Leader'], [$spg, 'SPG']] as [$member, $position]) {
                EventTeamMember::create([
                    'event_id' => $event->id,
                    'user_id' => $member->id,
                    'position' => $position,
                    'status' => 'assigned',
                    'assigned_by' => $pic->id,
                    'schedule_date' => $event->event_date?->toDateString(),
                    'start_time' => $this->scheduleTime($event->start_time),
                    'end_time' => $this->scheduleTime($event->end_time),
                ]);

                $event->attendances()->firstOrCreate(
                    ['user_id' => $member->id],
                    ['status' => 'planned'],
                );
            }

            AuditLogger::log('CREATE_EVENT', 'events', $event, null, [
                'fjp_id' => $fjp->id,
                'event_code' => $event->event_code,
            ]);
            AuditLogger::log('ASSIGN_TEAM', 'events', $event, null, [
                'team_leader_id' => $teamLeader->id,
                'spg_user_id' => $spg->id,
            ]);

            return $event;
        });
    }

    /**
     * @param  array<int, Fjp>  $fjps
     * @return array<int, Event>
     */
    public function handleGroup(array $fjps, User $pic, int $teamLeaderId, int $spgId): array
    {
        return DB::transaction(function () use ($fjps, $pic, $teamLeaderId, $spgId): array {
            $fjpIds = collect($fjps)->map(fn (Fjp $fjp): int => (int) $fjp->id)->unique()->values();
            $lockedFjps = Fjp::query()
                ->whereIn('id', $fjpIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($lockedFjps->count() !== $fjpIds->count()) {
                throw ValidationException::withMessages([
                    'fjp_ids' => 'Satu atau lebih FJP yang dipilih tidak lagi tersedia.',
                ]);
            }

            $dates = $lockedFjps
                ->map(fn (Fjp $fjp): ?string => $fjp->event_date?->toDateString())
                ->unique();

            if ($dates->count() !== 1 || $dates->contains(null)) {
                throw ValidationException::withMessages([
                    'fjp_ids' => 'Kelompok tim hanya dapat berisi FJP dengan tanggal pelaksanaan yang sama.',
                ]);
            }

            if ($lockedFjps->contains(fn (Fjp $fjp): bool => $fjp->status !== Fjp::STATUS_CONFIRM)) {
                throw ValidationException::withMessages([
                    'fjp_id' => 'Tim hanya dapat dibagikan setelah status FJP Confirm.',
                ]);
            }

            $plannedGroupIds = $lockedFjps
                ->pluck('team_group_id')
                ->filter()
                ->unique()
                ->values();
            $hasMatchingPlannedGroup = $plannedGroupIds->count() === 1
                && $lockedFjps->every(
                    fn (Fjp $fjp): bool => $fjp->team_group_id === $plannedGroupIds->first(),
                );

            if (! $hasMatchingPlannedGroup) {
                throw ValidationException::withMessages([
                    'fjp_ids' => 'PIC hanya dapat membagikan anggota pada kelompok tim yang sudah ditetapkan Permitter.',
                ]);
            }

            $teamGroupId = (string) $plannedGroupIds->first();
            $groupEventIds = Event::query()
                ->where('team_group_id', $teamGroupId)
                ->pluck('id')
                ->all();

            foreach ($lockedFjps as $fjp) {
                $this->assertTeamAvailability(
                    [(int) $teamLeaderId, (int) $spgId],
                    $fjp->event_date?->toDateString(),
                    $this->scheduleTime($fjp->start_time),
                    $this->scheduleTime($fjp->end_time),
                    $groupEventIds,
                );
            }

            return $lockedFjps
                ->map(fn (Fjp $fjp): Event => $this->handle(
                    $fjp,
                    $pic,
                    $teamLeaderId,
                    $spgId,
                    teamGroupId: $teamGroupId,
                ))
                ->all();
        });
    }

    /**
     * @param  array<int, Fjp>  $fjps
     * @param  array<int, int>  $spgPersonnelIds
     * @return array<int, Event>
     */
    public function handlePersonnelGroup(
        array $fjps,
        User $pic,
        int $teamLeaderPersonnelId,
        array $spgPersonnelIds,
    ): array {
        return DB::transaction(function () use ($fjps, $pic, $teamLeaderPersonnelId, $spgPersonnelIds): array {
            $personnelIds = collect([$teamLeaderPersonnelId, ...$spgPersonnelIds])
                ->map(fn (int $id): int => (int) $id)
                ->values();

            if (count($spgPersonnelIds) !== 1 || $personnelIds->unique()->count() !== $personnelIds->count()) {
                throw ValidationException::withMessages([
                    'spg_personnel_ids' => 'Pilih tepat satu SPG yang berbeda dari Team Leader.',
                ]);
            }

            $personnelById = Personnel::query()
                ->with('user.roles')
                ->whereIn('id', $personnelIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($personnelById->count() !== $personnelIds->count()) {
                throw ValidationException::withMessages([
                    'team_leader_personnel_id' => 'Satu atau lebih personel tidak lagi tersedia.',
                ]);
            }

            $teamLeader = $this->activeTeamPersonnel(
                $personnelById->get($teamLeaderPersonnelId),
                'team_leader_personnel_id',
            );
            $spgs = collect($spgPersonnelIds)
                ->map(fn (int $id): Personnel => $this->activeTeamPersonnel(
                    $personnelById->get($id),
                    'spg_personnel_ids',
                ))
                ->values();

            if (! $this->personnelHasRole($teamLeader, 'team leader')
                || ! $this->personnelHasRole($spgs->first(), 'spg')) {
                throw ValidationException::withMessages([
                    'team_leader_personnel_id' => 'Pilih satu personel berjabatan Team Leader dan satu personel berjabatan SPG.',
                ]);
            }

            $teamUserIds = collect([$teamLeader->user_id, ...$spgs->pluck('user_id')->all()])
                ->map(fn ($id): int => (int) $id)
                ->values();

            $fjpIds = collect($fjps)->map(fn (Fjp $fjp): int => (int) $fjp->id)->unique()->values();
            $lockedFjps = Fjp::query()
                ->whereIn('id', $fjpIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($lockedFjps->count() !== $fjpIds->count()) {
                throw ValidationException::withMessages([
                    'fjp_ids' => 'Satu atau lebih FJP yang dipilih tidak lagi tersedia.',
                ]);
            }

            if ($lockedFjps->contains(fn (Fjp $fjp): bool => $fjp->status !== Fjp::STATUS_CONFIRM)) {
                throw ValidationException::withMessages([
                    'fjp_id' => 'Tim hanya dapat dibagikan setelah status FJP Confirm.',
                ]);
            }

            $teamGroupIds = $lockedFjps->pluck('team_group_id')->filter()->unique();
            if ($teamGroupIds->count() !== 1 || $lockedFjps->contains(
                fn (Fjp $fjp): bool => $fjp->team_group_id !== $teamGroupIds->first(),
            )) {
                throw ValidationException::withMessages([
                    'fjp_ids' => 'PIC hanya dapat membagikan anggota pada kelompok tim yang sudah ditetapkan Permitter.',
                ]);
            }

            $teamGroupId = (string) $teamGroupIds->first();
            $groupEventIds = Event::query()
                ->where('team_group_id', $teamGroupId)
                ->pluck('id')
                ->all();

            foreach ($lockedFjps as $fjp) {
                $this->assertTeamAvailability(
                    $teamUserIds->all(),
                    $fjp->event_date?->toDateString(),
                    $this->scheduleTime($fjp->start_time),
                    $this->scheduleTime($fjp->end_time),
                    $groupEventIds,
                );
            }

            $events = $this->handleGroup(
                $lockedFjps->all(),
                $pic,
                (int) $teamLeader->user_id,
                (int) $spgs->first()->user_id,
            );

            foreach ($events as $event) {
                $event->load('teamMembers');
                $leaderMember = $event->teamMembers->firstWhere('position', 'Team Leader');
                $firstSpgMember = $event->teamMembers->firstWhere('position', 'SPG');

                if (! $leaderMember || ! $firstSpgMember) {
                    throw ValidationException::withMessages([
                        'team_group_id' => 'Roster event gagal dibuat secara lengkap.',
                    ]);
                }

                $this->assignPersonnelToMember($event, $leaderMember, $teamLeader, 'Team Leader');
                $this->assignPersonnelToMember($event, $firstSpgMember, $spgs->first(), 'SPG');

                foreach ($spgs->skip(1) as $spg) {
                    $this->createPersonnelTeamMember($event, $pic, $spg, 'SPG');
                }

                AuditLogger::log('ASSIGN_FJP_PERSONNEL', 'events', $event, null, [
                    'team_group_id' => $teamGroupId,
                    'team_leader_personnel_id' => $teamLeader->id,
                    'spg_personnel_ids' => $spgs->pluck('id')->all(),
                ]);
            }

            return $events;
        });
    }

    /**
     * @param  array<int, int>  $spgPersonnelIds
     */
    public function updatePersonnelTeamGroupMembers(
        string $teamGroupId,
        User $pic,
        int $teamLeaderPersonnelId,
        array $spgPersonnelIds,
    ): int {
        return DB::transaction(function () use ($teamGroupId, $pic, $teamLeaderPersonnelId, $spgPersonnelIds): int {
            $personnelIds = collect([$teamLeaderPersonnelId, ...$spgPersonnelIds])
                ->map(fn (int $id): int => (int) $id)
                ->values();

            if (count($spgPersonnelIds) !== 1 || $personnelIds->unique()->count() !== $personnelIds->count()) {
                throw ValidationException::withMessages([
                    'spg_personnel_ids' => 'Pilih tepat satu SPG yang berbeda dari Team Leader.',
                ]);
            }

            $personnelById = Personnel::query()
                ->with('user.roles')
                ->whereIn('id', $personnelIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($personnelById->count() !== $personnelIds->count()) {
                throw ValidationException::withMessages([
                    'team_leader_personnel_id' => 'Satu atau lebih personel tidak lagi tersedia.',
                ]);
            }

            $teamLeader = $this->activeTeamPersonnel(
                $personnelById->get($teamLeaderPersonnelId),
                'team_leader_personnel_id',
            );
            $spgs = collect($spgPersonnelIds)
                ->map(fn (int $id): Personnel => $this->activeTeamPersonnel(
                    $personnelById->get($id),
                    'spg_personnel_ids',
                ))
                ->values();

            if (! $this->personnelHasRole($teamLeader, 'team leader')
                || ! $this->personnelHasRole($spgs->first(), 'spg')) {
                throw ValidationException::withMessages([
                    'team_leader_personnel_id' => 'Pilih satu personel berjabatan Team Leader dan satu personel berjabatan SPG.',
                ]);
            }

            $teamUserIds = collect([$teamLeader->user_id, ...$spgs->pluck('user_id')->all()])
                ->map(fn ($id): int => (int) $id)
                ->values();

            $events = Event::query()
                ->where('team_group_id', $teamGroupId)
                ->with(['fjp', 'teamMembers'])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($events->isEmpty()) {
                abort(404);
            }

            $eventIds = $events->modelKeys();

            foreach ($events as $event) {
                $this->assertTeamEventEditable($event, allowPlanning: true);
                $this->assertTeamAvailability(
                    $teamUserIds->all(),
                    $event->event_date?->toDateString(),
                    $this->scheduleTime($event->start_time),
                    $this->scheduleTime($event->end_time),
                    $eventIds,
                );

                $remainingMembers = $event->teamMembers->values();
                $assignments = [
                    [$teamLeader, 'Team Leader'],
                    ...$spgs->map(fn (Personnel $personnel): array => [$personnel, 'SPG'])->all(),
                ];

                foreach ($assignments as [$personnel, $position]) {
                    $member = $remainingMembers->first(
                        fn (EventTeamMember $candidate): bool => (int) $candidate->user_id === (int) $personnel->user_id
                            || (int) $candidate->personnel_id === (int) $personnel->id,
                    );

                    if ($member) {
                        $this->assignPersonnelToMember($event, $member, $personnel, $position);
                        $remainingMembers = $remainingMembers
                            ->reject(fn (EventTeamMember $candidate): bool => $candidate->id === $member->id)
                            ->values();
                    } else {
                        $this->createPersonnelTeamMember($event, $pic, $personnel, $position);
                    }
                }

                foreach ($remainingMembers->whereIn('position', ['Team Leader', 'SPG']) as $member) {
                    $this->removeTeamMember($event, $member);
                }

                AuditLogger::log('UPDATE_TEAM_GROUP', 'events', $event, null, [
                    'team_group_id' => $teamGroupId,
                    'team_leader_personnel_id' => $teamLeader->id,
                    'spg_personnel_ids' => $spgs->pluck('id')->all(),
                ]);
            }

            return $events->count();
        });
    }

    public function removePersonnelFromTeamGroup(string $teamGroupId, Personnel $personnel): int
    {
        return DB::transaction(function () use ($teamGroupId, $personnel): int {
            $events = Event::query()
                ->where('team_group_id', $teamGroupId)
                ->with('teamMembers')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($events->isEmpty()) {
                abort(404);
            }

            foreach ($events as $event) {
                $this->assertTeamEventEditable($event, allowPlanning: true);
            }

            $isTargetMember = fn (EventTeamMember $member): bool => (int) $member->personnel_id === (int) $personnel->id
                || ($personnel->user_id !== null && (int) $member->user_id === (int) $personnel->user_id);
            $members = $events
                ->flatMap(fn (Event $event) => $event->teamMembers->filter($isTargetMember))
                ->values();

            if ($members->isEmpty()) {
                abort(404);
            }

            foreach ($events as $event) {
                foreach ($event->teamMembers->filter($isTargetMember) as $member) {
                    $this->removeTeamMember($event, $member);
                }
            }

            foreach ($events as $event) {
                AuditLogger::log('REMOVE_FJP_PERSONNEL', 'events', $event, null, [
                    'team_group_id' => $teamGroupId,
                    'personnel_id' => $personnel->id,
                ]);
            }

            return $members->count();
        });
    }

    /**
     * @param  array<int, array{fjp_ids: array<int, int>, team_leader_id: int, spg_user_id: int}>  $groups
     * @return array<int, Event>
     */
    public function scheduleGroups(array $groups, User $pic, string $eventDate): array
    {
        return DB::transaction(function () use ($groups, $pic, $eventDate): array {
            $groupSizes = collect($groups)->map(fn (array $group): int => count($group['fjp_ids']))->all();
            $fjpIds = collect($groups)
                ->flatMap(fn (array $group): array => $group['fjp_ids'])
                ->map(fn ($id): int => (int) $id)
                ->values();
            $teamMemberIds = collect($groups)
                ->flatMap(fn (array $group): array => [
                    (int) $group['team_leader_id'],
                    (int) $group['spg_user_id'],
                ])
                ->values();

            if ($groupSizes === []
                || in_array(0, $groupSizes, true)
                || $fjpIds->count() !== array_sum($groupSizes)
                || $fjpIds->unique()->count() !== array_sum($groupSizes)) {
                throw ValidationException::withMessages([
                    'groups' => 'Setiap sekolah hanya boleh dibagikan satu kali dan setiap tim harus memiliki minimal satu sekolah.',
                ]);
            }

            $requiredTeamMemberCount = count($groups) * 2;

            if (
                $teamMemberIds->count() !== $requiredTeamMemberCount
                || $teamMemberIds->unique()->count() !== $requiredTeamMemberCount
            ) {
                throw ValidationException::withMessages([
                    'groups' => 'Setiap tim harus menggunakan pasangan TL/SPG yang berbeda.',
                ]);
            }

            $lockedFjps = Fjp::query()
                ->with('venue:id,city,province')
                ->whereIn('id', $fjpIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($lockedFjps->count() !== $fjpIds->count()) {
                throw ValidationException::withMessages([
                    'groups' => 'Satu atau lebih FJP yang dipilih tidak lagi tersedia.',
                ]);
            }

            foreach ($lockedFjps as $fjp) {
                if ($fjp->status !== Fjp::STATUS_CONFIRM) {
                    throw ValidationException::withMessages([
                        'groups' => 'Tim hanya dapat dibagikan ke FJP berstatus Confirm.',
                    ]);
                }

                if ($fjp->events()->exists()) {
                    throw ValidationException::withMessages([
                        'groups' => 'FJP yang sudah memiliki Event tidak dapat dijadwalkan ulang melalui generator.',
                    ]);
                }

                $location = Str::lower(implode(' ', array_filter([
                    $fjp->city,
                    $fjp->province,
                    $fjp->venue?->city,
                    $fjp->venue?->province,
                ])));

                $isInJabodetabek = collect(['jakarta', 'bogor', 'depok', 'tangerang', 'bekasi'])
                    ->contains(fn (string $region): bool => str_contains($location, $region));

                if (! $isInJabodetabek) {
                    throw ValidationException::withMessages([
                        'groups' => 'Generator ini hanya dapat menjadwalkan FJP yang berlokasi di Jabodetabek.',
                    ]);
                }

                $oldDate = $fjp->event_date?->toDateString();
                $fjp->update(['event_date' => $eventDate]);

                if ($oldDate !== $eventDate) {
                    AuditLogger::log('FJP_RESCHEDULE_FOR_TEAM', 'fjp', $fjp, [
                        'event_date' => $oldDate,
                    ], [
                        'event_date' => $eventDate,
                    ]);
                }
            }

            $events = [];

            foreach ($groups as $group) {
                $events = [
                    ...$events,
                    ...$this->handleGroup(
                        collect($group['fjp_ids'])->map(fn ($id) => $lockedFjps->get((int) $id))->all(),
                        $pic,
                        (int) $group['team_leader_id'],
                        (int) $group['spg_user_id'],
                    ),
                ];
            }

            return $events;
        });
    }

    public function updateTeamGroupMembers(
        string $teamGroupId,
        User $pic,
        int $teamLeaderId,
        int $spgId,
    ): int {
        return DB::transaction(function () use ($teamGroupId, $teamLeaderId, $spgId): int {
            $events = Event::query()
                ->where('team_group_id', $teamGroupId)
                ->with(['fjp', 'teamMembers'])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($events->isEmpty()) {
                abort(404);
            }

            if ($teamLeaderId === $spgId) {
                throw ValidationException::withMessages([
                    'spg_user_id' => 'Team Leader dan SPG harus merupakan anggota yang berbeda.',
                ]);
            }

            $teamLeader = $this->activeTeamMember($teamLeaderId, 'team_leader_id');
            $spg = $this->activeTeamMember($spgId, 'spg_user_id');
            $eventIds = $events->modelKeys();
            $eventDate = $events->first()?->event_date?->toDateString();

            if ($eventDate === null) {
                throw ValidationException::withMessages([
                    'team_group_id' => 'Tim tanpa tanggal tidak dapat diubah.',
                ]);
            }

            foreach ($events as $event) {
                $this->assertTeamEventEditable($event, allowPlanning: true);

                $teamLeaderMember = $event->teamMembers->firstWhere('position', 'Team Leader');
                $spgMember = $event->teamMembers->firstWhere('position', 'SPG');

                if (! $teamLeaderMember || ! $spgMember) {
                    throw ValidationException::withMessages([
                        'team_group_id' => 'Roster tim harus memiliki satu Team Leader dan satu SPG.',
                    ]);
                }

                foreach ([
                    [$teamLeader->id, $teamLeaderMember],
                    [$spg->id, $spgMember],
                ] as [$userId, $member]) {
                    $this->assertTeamAvailability(
                        [$userId],
                        $member->schedule_date?->toDateString() ?? $event->event_date?->toDateString(),
                        $this->scheduleTime($member->start_time ?? $event->start_time),
                        $this->scheduleTime($member->end_time ?? $event->end_time),
                        $eventIds,
                    );
                }

                if ((int) $teamLeaderMember->user_id === $spg->id
                    || (int) $spgMember->user_id === $teamLeader->id) {
                    throw ValidationException::withMessages([
                        'team_leader_id' => 'Anggota tidak dapat bertukar posisi dalam satu kali perubahan. Simpan perubahan anggota terlebih dahulu.',
                    ]);
                }

                $this->replaceTeamMember($event, $teamLeaderMember, $teamLeader);
                $this->replaceTeamMember($event, $spgMember, $spg);

                AuditLogger::log('UPDATE_TEAM_GROUP', 'events', $event, null, [
                    'team_group_id' => $teamGroupId,
                    'team_leader_id' => $teamLeader->id,
                    'spg_user_id' => $spg->id,
                ]);
            }

            return $events->count();
        });
    }

    public function addSchoolToTeamGroup(string $teamGroupId, int $fjpId, User $pic): Event
    {
        return DB::transaction(function () use ($teamGroupId, $fjpId, $pic): Event {
            $events = Event::query()
                ->where('team_group_id', $teamGroupId)
                ->with(['fjp', 'teamMembers'])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($events->isEmpty()) {
                abort(404);
            }

            foreach ($events as $event) {
                $this->assertTeamEventEditable($event);
            }

            $eventDate = $events->first()?->event_date?->toDateString();

            if ($eventDate === null || $events->contains(
                fn (Event $event): bool => $event->event_date?->toDateString() !== $eventDate,
            )) {
                throw ValidationException::withMessages([
                    'team_group_id' => 'Tanggal pada seluruh Event dalam tim harus sama sebelum sekolah dapat ditambahkan.',
                ]);
            }

            $fjp = Fjp::query()
                ->with(['venue:id,city,province', 'brand', 'eventType'])
                ->lockForUpdate()
                ->findOrFail($fjpId);

            if ($fjp->status !== Fjp::STATUS_CONFIRM) {
                throw ValidationException::withMessages([
                    'fjp_id' => 'Sekolah hanya dapat ditambahkan jika FJP berstatus Confirm.',
                ]);
            }

            if ($fjp->events()->exists()) {
                throw ValidationException::withMessages([
                    'fjp_id' => 'FJP yang sudah memiliki Event tidak dapat ditambahkan ke tim lain.',
                ]);
            }

            if ($fjp->team_group_id !== $teamGroupId) {
                throw ValidationException::withMessages([
                    'fjp_id' => 'FJP harus terlebih dahulu ditugaskan Permitter ke kelompok tim ini.',
                ]);
            }

            if ($fjp->event_date?->toDateString() !== $eventDate) {
                throw ValidationException::withMessages([
                    'fjp_id' => 'Tanggal FJP harus sama dengan tanggal kelompok tim yang ditetapkan Permitter.',
                ]);
            }

            $location = Str::lower(implode(' ', array_filter([
                $fjp->city,
                $fjp->province,
                $fjp->venue?->city,
                $fjp->venue?->province,
            ])));
            $isInJabodetabek = collect(['jakarta', 'bogor', 'depok', 'tangerang', 'bekasi'])
                ->contains(fn (string $region): bool => str_contains($location, $region));

            if (! $isInJabodetabek) {
                throw ValidationException::withMessages([
                    'fjp_id' => 'Sekolah yang ditambahkan harus berlokasi di Jabodetabek.',
                ]);
            }

            $groupMemberKeys = $events->map(fn (Event $event): array => $event->teamMembers
                ->filter(fn (EventTeamMember $member): bool => in_array($member->position, ['Team Leader', 'SPG'], true))
                ->map(fn (EventTeamMember $member): string => $member->position.'|'.(
                    $member->personnel_id !== null
                        ? 'personnel:'.$member->personnel_id
                        : 'user:'.($member->user_id ?? $member->id)
                ))
                ->unique()
                ->sort()
                ->values()
                ->all());

            if ($groupMemberKeys->contains(
                fn (array $memberKeys): bool => $memberKeys !== $groupMemberKeys->first(),
            )) {
                throw ValidationException::withMessages([
                    'team_group_id' => 'Seluruh sekolah dalam tim harus menggunakan personel yang sama.',
                ]);
            }

            $leaderMembers = $events->first()?->teamMembers->where('position', 'Team Leader')->unique(
                fn (EventTeamMember $member): string => $member->personnel_id !== null
                    ? 'personnel:'.$member->personnel_id
                    : 'user:'.($member->user_id ?? $member->id),
            );
            $spgMembers = $events->first()?->teamMembers->where('position', 'SPG')->unique(
                fn (EventTeamMember $member): string => $member->personnel_id !== null
                    ? 'personnel:'.$member->personnel_id
                    : 'user:'.($member->user_id ?? $member->id),
            );

            if ($leaderMembers?->count() !== 1 || $spgMembers?->isEmpty()) {
                throw ValidationException::withMessages([
                    'team_group_id' => 'Kelompok harus memiliki satu Team Leader dan sedikitnya satu SPG.',
                ]);
            }

            $personnelIdsByUserId = Personnel::query()
                ->whereIn('user_id', $events->first()->teamMembers->pluck('user_id')->filter()->unique())
                ->get()
                ->keyBy('user_id');
            $teamLeaderPersonnelId = $leaderMembers->first()?->personnel_id
                ?? $personnelIdsByUserId->get($leaderMembers->first()?->user_id)?->id;
            $spgPersonnelIds = $spgMembers
                ->map(fn (EventTeamMember $member): ?int => $member->personnel_id
                    ?? $personnelIdsByUserId->get($member->user_id)?->id)
                ->filter()
                ->map(fn ($id): int => (int) $id)
                ->values()
                ->all();

            if ($teamLeaderPersonnelId === null || count($spgPersonnelIds) !== $spgMembers->count()) {
                throw ValidationException::withMessages([
                    'team_group_id' => 'Seluruh akun Tim harus terhubung ke master data Personel.',
                ]);
            }

            return $this->handlePersonnelGroup(
                [$fjp],
                $pic,
                (int) $teamLeaderPersonnelId,
                $spgPersonnelIds,
            )[0];
        });
    }

    private function assertTeamEventEditable(Event $event, bool $allowPlanning = false): void
    {
        $allowedStatuses = $allowPlanning
            ? [EventStatus::PLANNING, EventStatus::TEAM_ASSIGNMENT]
            : [EventStatus::TEAM_ASSIGNMENT];

        if (! in_array($event->status, $allowedStatuses, true)
            || $event->fjp?->status !== Fjp::STATUS_CONFIRM) {
            throw ValidationException::withMessages([
                'team_group_id' => $allowPlanning
                    ? 'Anggota tim hanya dapat diubah saat Event masih dalam tahap perencanaan atau penugasan, FJP berstatus Confirm, dan kegiatan belum dimulai.'
                    : 'Sekolah hanya dapat ditambahkan saat Event masih dalam tahap penugasan, FJP berstatus Confirm, dan kegiatan belum dimulai.',
            ]);
        }

        $hasActivity = $event->attendances()
            ->where(fn ($query) => $query
                ->whereNotNull('check_in_at')
                ->orWhereNotNull('check_out_at'))
            ->exists()
            || $event->attendances()->whereHas('media')->exists()
            || $event->reports()->exists()
            || $event->quickReports()->exists()
            || $event->stockRequests()->exists();

        if ($hasActivity) {
            throw ValidationException::withMessages([
                'team_group_id' => 'Tim tidak dapat diubah setelah aktivitas Event dimulai.',
            ]);
        }
    }

    private function replaceTeamMember(Event $event, EventTeamMember $member, User $replacement): void
    {
        $previousUserId = $member->user_id;

        if ((int) $previousUserId !== $replacement->id || $member->personnel_id !== null) {
            $member->update([
                'user_id' => $replacement->id,
                'personnel_id' => null,
            ]);

            $previousAttendance = $previousUserId === null
                ? null
                : Attendance::query()
                    ->where('event_id', $event->id)
                    ->where('user_id', $previousUserId)
                    ->first();

            if ($previousAttendance) {
                $replacementAttendanceExists = Attendance::query()
                    ->where('event_id', $event->id)
                    ->where('user_id', $replacement->id)
                    ->exists();

                if ($replacementAttendanceExists) {
                    $previousAttendance->delete();
                } else {
                    $previousAttendance->update(['user_id' => $replacement->id]);
                }
            }
        }

        $event->attendances()->firstOrCreate(
            ['user_id' => $replacement->id],
            ['status' => 'planned'],
        );
    }

    private function activeTeamMember(int $userId, string $field): User
    {
        $user = User::query()
            ->whereKey($userId)
            ->where('is_active', true)
            ->whereHas('roles', fn ($query) => $query->where('slug', 'team'))
            ->first();

        if (! $user) {
            throw ValidationException::withMessages([
                $field => 'Pilih akun anggota tim yang aktif.',
            ]);
        }

        return $user;
    }

    private function activeTeamPersonnel(?Personnel $personnel, string $field): Personnel
    {
        $personnel?->loadMissing('user.roles');

        if (
            ! $personnel
            || ! $personnel->is_active
            || ! $personnel->user
            || ! $personnel->user->is_active
            || ! $personnel->user->hasRole('team')
        ) {
            throw ValidationException::withMessages([
                $field => 'Pilih personel aktif yang terhubung ke akun role Tim aktif.',
            ]);
        }

        return $personnel;
    }

    private function personnelHasRole(Personnel $personnel, string $expectedRole): bool
    {
        return Str::lower(trim($personnel->role)) === $expectedRole;
    }

    private function assignPersonnelToMember(
        Event $event,
        EventTeamMember $member,
        Personnel $personnel,
        string $position,
    ): void {
        $this->replaceTeamMember($event, $member, $personnel->user);
        $member->update([
            'personnel_id' => $personnel->id,
            'position' => $position,
        ]);
    }

    private function createPersonnelTeamMember(
        Event $event,
        User $pic,
        Personnel $personnel,
        string $position,
    ): EventTeamMember {
        $member = $event->teamMembers()->create([
            'user_id' => $personnel->user_id,
            'personnel_id' => $personnel->id,
            'position' => $position,
            'status' => 'assigned',
            'assigned_by' => $pic->id,
            'schedule_date' => $event->event_date?->toDateString(),
            'start_time' => $this->scheduleTime($event->start_time),
            'end_time' => $this->scheduleTime($event->end_time),
        ]);

        $event->attendances()->firstOrCreate(
            ['user_id' => $personnel->user_id],
            ['status' => 'planned'],
        );

        return $member;
    }

    private function removeTeamMember(Event $event, EventTeamMember $member): void
    {
        $userId = $member->user_id;
        $member->delete();

        if ($userId !== null && ! $event->teamMembers()->where('user_id', $userId)->exists()) {
            $event->attendances()->where('user_id', $userId)->delete();
        }
    }

    /**
     * @param  array<int, int>  $userIds
     */
    private function assertTeamAvailability(
        array $userIds,
        ?string $eventDate,
        ?string $startTime,
        ?string $endTime = null,
        array $excludedEventIds = [],
    ): void {
        if ($eventDate === null) {
            return;
        }

        $members = EventTeamMember::query()
            ->whereIn('user_id', $userIds)
            ->when($excludedEventIds !== [], fn ($query) => $query->whereNotIn('event_id', $excludedEventIds))
            ->where(function ($query) use ($eventDate): void {
                $query->whereDate('schedule_date', $eventDate)
                    ->orWhereHas('event', fn ($eventQuery) => $eventQuery->whereDate('event_date', $eventDate));
            })
            ->whereHas('event', fn ($query) => $query
                ->where('status', '!=', EventStatus::CANCELLED->value))
            ->with('event:id,event_date,start_time,end_time,status')
            ->get(['user_id', 'event_id', 'schedule_date', 'start_time', 'end_time']);

        foreach ($members as $member) {
            $existingEvent = $member->event;
            $existingDate = $member->schedule_date?->toDateString()
                ?? $existingEvent?->event_date?->toDateString();

            if ($existingDate !== $eventDate) {
                continue;
            }

            $existingStartTime = $this->scheduleTime($member->start_time ?? $existingEvent?->start_time);
            $existingEndTime = $this->scheduleTime($member->end_time ?? $existingEvent?->end_time);

            if ($this->scheduleTimesOverlap($startTime, $endTime, $existingStartTime, $existingEndTime)) {
                throw ValidationException::withMessages([
                    'team_leader_id' => 'Satu atau lebih anggota tim sudah memiliki jadwal yang bentrok pada tanggal dan waktu tersebut.',
                ]);
            }
        }
    }

    private function scheduleTime(?string $time): ?string
    {
        return $time === null ? null : substr($time, 0, 5);
    }

    private function scheduleTimesOverlap(
        ?string $firstStart,
        ?string $firstEnd,
        ?string $secondStart,
        ?string $secondEnd,
    ): bool {
        if ($firstStart === null || $secondStart === null) {
            return false;
        }

        $firstStartMinutes = $this->timeInMinutes($firstStart);
        $secondStartMinutes = $this->timeInMinutes($secondStart);
        $firstEndMinutes = $firstEnd === null
            ? $firstStartMinutes + 1
            : max($firstStartMinutes + 1, $this->timeInMinutes($firstEnd));
        $secondEndMinutes = $secondEnd === null
            ? $secondStartMinutes + 1
            : max($secondStartMinutes + 1, $this->timeInMinutes($secondEnd));

        return $firstStartMinutes < $secondEndMinutes
            && $secondStartMinutes < $firstEndMinutes;
    }

    private function timeInMinutes(string $time): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $time));

        return ($hours * 60) + $minutes;
    }
}
