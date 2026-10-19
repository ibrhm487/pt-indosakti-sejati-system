<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $query = Event::query()
            ->with(['brand:id,name', 'pic:id,name', 'teamMembers.user:id,name', 'teamMembers.personnel:id,name,role'])
            ->whereHas('teamMembers');

        if ($user->hasRole('pic') && ! $user->isSystemAdmin()) {
            $query->where('pic_id', $user->id);
        }

        if ($user->hasRole('team') && ! $user->hasAnyRole(['pic', 'system_admin', 'admin_event'])) {
            $query->whereHas('teamMembers', fn ($q) => $q->where('user_id', $user->id));
        }

        $today = now()->toDateString();
        $eventsCollection = $query->get();
        $upcoming = $eventsCollection
            ->filter(fn (Event $event) => $event->event_date === null || $event->event_date->toDateString() >= $today)
            ->sortBy('event_date')
            ->values();
        $past = $eventsCollection
            ->filter(fn (Event $event) => $event->event_date !== null && $event->event_date->toDateString() < $today)
            ->sortByDesc('event_date')
            ->values();

        $events = $upcoming->concat($past)->map(fn (Event $event) => [
            'id' => $event->id,
            'event_name' => $event->event_name,
            'event_date' => $event->event_date?->toDateString(),
            'brand' => $event->brand?->name,
            'pic' => $event->pic?->name,
            'status' => $event->status->value,
            'status_label' => $event->status->label(),
            'is_today' => $event->event_date?->toDateString() === $today,
            'members' => $event->teamMembers->map(fn ($m) => [
                'name' => $m->memberName(),
                'position' => $m->position,
            ]),
        ]);

        return Inertia::render('Team/Index', [
            'events' => $events,
            'teamMembers' => User::whereHas('roles', fn ($q) => $q->where('slug', 'team'))->get(['id', 'name', 'email']),
            'activeRole' => $user->activeRoleSlug(),
            'canCreateReport' => $user->hasPermission('reports.create'),
            'canQuickReport' => $user->hasPermission('quick_reports.create'),
            'summary' => [
                'total' => $events->count(),
                'today' => $events->where('is_today', true)->count(),
                'upcoming' => $upcoming->count(),
                'completed' => $events->where('status', 'completed')->count(),
            ],
        ]);
    }
}
