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

        $events = $query->latest('event_date')->get()->map(fn (Event $event) => [
            'id' => $event->id,
            'event_name' => $event->event_name,
            'event_date' => $event->event_date?->toDateString(),
            'brand' => $event->brand?->name,
            'pic' => $event->pic?->name,
            'members' => $event->teamMembers->map(fn ($m) => [
                'name' => $m->memberName(),
                'position' => $m->position,
            ]),
        ]);

        return Inertia::render('Team/Index', [
            'events' => $events,
            'teamMembers' => User::whereHas('roles', fn ($q) => $q->where('slug', 'team'))->get(['id', 'name', 'email']),
        ]);
    }
}
