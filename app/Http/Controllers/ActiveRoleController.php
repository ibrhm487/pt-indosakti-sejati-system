<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ActiveRoleController extends Controller
{
    /**
     * Switch the user's active role. Route access stays unioned across all
     * assigned roles; the active role drives workspace menu and data scoping.
     */
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $role = $user->roles()
            ->whereKey($request->integer('role_id'))
            ->first();

        if (! $role) {
            return back()->with('error', 'Peran tidak ditemukan pada akun Anda.');
        }

        if ($user->active_role_id !== $role->id) {
            $oldRoleId = $user->active_role_id;

            $user->update(['active_role_id' => $role->id]);

            AuditLogger::log(
                'switch_role',
                'auth',
                $user,
                ['active_role_id' => $oldRoleId],
                ['active_role_id' => $role->id],
            );
        }

        return back()->with('success', "Peran aktif diganti menjadi {$role->name}.");
    }
}
