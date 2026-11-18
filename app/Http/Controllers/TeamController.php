<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\User;
use App\Support\EventGrouping;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $query = Event::query()
            ->with([
                'brand:id,name',
                'eventType:id,name,requires_school',
                'venue:id,name,city,is_school',
                'pic:id,name',
                'teamMembers.user:id,name',
                'teamMembers.personnel:id,name,role',
            ])
            ->whereHas('teamMembers');

        if ($user->hasRole('pic') && ! $user->hasFullAccess()) {
            $query->where('pic_id', $user->id);
        }

        if ($user->hasRole('team') && ! $user->hasFullAccess() && ! $user->hasRole('pic') && ! $user->hasRole('admin_event')) {
            $query->whereHas('teamMembers', fn ($q) => $q->where('user_id', $user->id));
        }

        $today = now()->toDateString();
        $eventsCollection = $query->get();
        $groupedEvents = $eventsCollection
            ->whereNotNull('team_group_id')
            ->groupBy('team_group_id');

        $upcoming = $eventsCollection
            ->filter(fn (Event $event) => $event->event_date === null || $event->event_date->toDateString() >= $today)
            ->sortBy('event_date')
            ->values();
        $past = $eventsCollection
            ->filter(fn (Event $event) => $event->event_date !== null && $event->event_date->toDateString() < $today)
            ->sortByDesc('event_date')
            ->values();

        $mapRow = function (Event $event) use ($today, $user, $groupedEvents) {
            $myMember = $event->teamMembers->firstWhere('user_id', $user->id);
            $groupEvents = $event->team_group_id
                ? $groupedEvents->get($event->team_group_id, collect())
                : collect();

            return [
                'id' => $event->id,
                'event_code' => $event->event_code,
                'event_name' => $event->event_name,
                'event_date' => $event->event_date?->toDateString(),
                'start_time' => $event->start_time,
                'end_time' => $event->end_time,
                'sampling_target' => $event->sampling_target,
                'brand' => $event->brand?->name,
                'brand_id' => $event->brand_id,
                'brand_name' => $event->brand?->name,
                'event_type' => $event->eventType?->name,
                'event_type_id' => $event->event_type_id,
                'event_type_name' => $event->eventType?->name,
                'requires_school' => (bool) $event->eventType?->requires_school,
                'venue' => $event->venue?->name,
                'venue_city' => $event->venue?->city,
                'venue_is_school' => (bool) $event->venue?->is_school,
                'pic' => $event->pic?->name,
                'notes' => $event->notes,
                'status' => $event->status->value,
                'status_label' => $event->status->label(),
                'is_today' => $event->event_date?->toDateString() === $today,
                'team_group_count' => $event->team_group_id ? $groupEvents->count() : null,
                'team_group_sampling_target' => $event->team_group_id
                    ? (int) $groupEvents->sum(fn (Event $groupEvent): int => (int) $groupEvent->sampling_target)
                    : null,
                'team_count' => $event->teamMembers->count(),
                'my_schedule' => $myMember ? [
                    'position' => $myMember->position,
                    'schedule_date' => $myMember->schedule_date?->toDateString(),
                    'report_time' => $myMember->report_time,
                    'start_time' => $myMember->start_time,
                    'end_time' => $myMember->end_time,
                    'task' => $myMember->task,
                    'schedule_notes' => $myMember->schedule_notes,
                ] : null,
                'members' => $event->teamMembers->map(fn ($member) => [
                    'name' => $member->memberName(),
                    'position' => $member->position,
                ])->values()->all(),
            ];
        };

        $upcomingRows = $upcoming->map($mapRow);
        $pastRows = $past->map($mapRow);

        return Inertia::render('Team/Index', [
            'upcomingGroups' => EventGrouping::group($upcomingRows, 'asc'),
            'pastGroups' => EventGrouping::group($pastRows, 'desc'),
            'teamMembers' => User::whereHas('roles', fn ($q) => $q->where('slug', 'team'))->get(['id', 'name', 'email']),
            'activeRole' => $user->activeRoleSlug(),
            'canQuickReport' => $user->hasPermission('quick_reports.create'),
            'summary' => [
                'total' => $eventsCollection->count(),
                'today' => $upcomingRows->where('is_today', true)->count(),
                'upcoming' => $upcomingRows->count(),
                'completed' => $eventsCollection
                    ->filter(fn (Event $event) => $event->status->value === 'completed')
                    ->count(),
            ],
        ]);
    }
}
