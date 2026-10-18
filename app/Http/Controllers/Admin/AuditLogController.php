<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $query = AuditLog::query()->with('user:id,name');

        if ($module = $request->string('module')->toString()) {
            $query->where('module', $module);
        }

        if ($action = $request->string('action')->toString()) {
            $query->where('action', 'like', "%{$action}%");
        }

        if ($userId = $request->integer('user_id')) {
            $query->where('user_id', $userId);
        }

        $logs = $query->latest()->paginate(25)->withQueryString()->through(fn (AuditLog $log) => [
            'id' => $log->id,
            'user' => $log->user?->name,
            'role' => $log->role,
            'action' => $log->action,
            'module' => $log->module,
            'record_type' => class_basename($log->record_type ?? ''),
            'record_id' => $log->record_id,
            'old_values' => $log->old_values,
            'new_values' => $log->new_values,
            'ip_address' => $log->ip_address,
            'at' => $log->created_at?->toIso8601String(),
        ]);

        return Inertia::render('Admin/AuditLogs/Index', [
            'logs' => $logs,
            'filters' => $request->only('module', 'action', 'user_id'),
            'modules' => AuditLog::query()->distinct()->pluck('module')->filter()->values(),
        ]);
    }
}
