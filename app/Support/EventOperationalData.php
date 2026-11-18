<?php

namespace App\Support;

use App\Models\Event;

class EventOperationalData
{
    /**
     * Determine whether an event already carries records that make it unsafe
     * to delete or cancel automatically.
     */
    public static function exists(Event $event): bool
    {
        return $event->attendances()->exists()
            || $event->reports()->exists()
            || $event->quickReports()->exists()
            || $event->stockRequests()->exists()
            || $event->kitchenRequest()->exists()
            || $event->permit()->exists()
            || $event->documents()->exists();
    }
}
