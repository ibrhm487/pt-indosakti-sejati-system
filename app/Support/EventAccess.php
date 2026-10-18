<?php

namespace App\Support;

use App\Models\Event;
use App\Models\User;

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
        if ($user->isSystemAdmin()) {
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
}
