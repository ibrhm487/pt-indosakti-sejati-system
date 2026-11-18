<?php

namespace App\Support;

use App\Models\Event;
use App\Models\EventTeamMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Single source of truth for "may this user see / act on this event".
 * Used by the event controller, document controller and anywhere else that
 * needs the same scoping rules so a view-only role can never read another
 * event's data.
 */
class EventAccess
{
    public static function allows(User $user, Event $event): bool
    {
        if ($user->hasFullAccess()) {
            return true;
        }

        if ($user->hasAnyRole(['warehouse', 'kitchen', 'admin_event', 'permitter'])) {
            return true;
        }

        if ($user->hasRole('pic') && $event->pic_id === $user->id) {
            return true;
        }

        if ($user->hasRole('team') && $event->teamMembers()->where('user_id', $user->id)->exists()) {
            return true;
        }

        return false;
    }

    /**
     * Restrict a query to the events the user may access.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function scope(Builder $query, User $user, string $column = 'event_id'): Builder
    {
        if ($user->hasFullAccess() || $user->hasAnyRole(['warehouse', 'kitchen', 'admin_event', 'permitter'])) {
            return $query;
        }

        if ($user->hasRole('pic')) {
            return $query->whereIn(
                $column,
                Event::query()->where('pic_id', $user->id)->select('id'),
            );
        }

        if ($user->hasRole('team')) {
            return $query->whereIn(
                $column,
                EventTeamMember::query()->where('user_id', $user->id)->select('event_id'),
            );
        }

        return $query->whereRaw('1 = 0');
    }
}
