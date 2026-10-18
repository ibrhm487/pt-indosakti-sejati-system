<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class AuditLogger
{
    public static function log(
        string $action,
        string $module,
        ?Model $record = null,
        ?array $oldValues = null,
        ?array $newValues = null,
    ): AuditLog {
        $user = auth()->user();

        return AuditLog::create([
            'user_id' => $user?->id,
            'role' => $user?->activeRoleSlug(),
            'action' => $action,
            'module' => $module,
            'record_type' => $record?->getMorphClass(),
            'record_id' => $record?->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
        ]);
    }
}
