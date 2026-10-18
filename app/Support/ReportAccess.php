<?php

namespace App\Support;

use App\Models\EventReport;
use App\Models\User;

/**
 * Access rules for an event report, mirroring ReportController's scoping so
 * that downloads / uploads on a report can be authorized consistently.
 */
class ReportAccess
{
    public static function allows(User $user, EventReport $report): bool
    {
        if ($user->isSystemAdmin() || $user->activeRoleSlug() === 'admin_event') {
            return true;
        }

        $event = $report->event;

        if (! $event) {
            return false;
        }

        return match ($user->activeRoleSlug()) {
            'pic' => $event->pic_id === $user->id,
            'team' => $event->teamMembers()->where('user_id', $user->id)->exists(),
            default => false,
        };
    }
}
