<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RoleSelectionController extends Controller
{
    /**
     * Finish login by storing the role the user picked.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'role_id' => ['required', 'integer'],
        ]);

        $userId = $request->session()->get(AuthenticatedSessionController::PENDING_USER_ID);
        $storedAt = (int) $request->session()->get(AuthenticatedSessionController::PENDING_AT, 0);

        if (! $userId || ($storedAt > 0 && now()->timestamp - $storedAt > AuthenticatedSessionController::PENDING_TTL_SECONDS)) {
            $this->forgetPending($request);

            return redirect()->route('login')->withErrors([
                'role_id' => 'Sesi pemilihan peran telah berakhir. Silakan masuk kembali.',
            ]);
        }

        $user = User::with(['roles:id,slug,name'])
            ->whereKey($userId)
            ->where('is_active', true)
            ->first();

        if (! $user) {
            $this->forgetPending($request);

            return redirect()->route('login')->withErrors([
                'role_id' => 'Akun tidak tersedia. Silakan masuk kembali.',
            ]);
        }

        $role = $user->roles->firstWhere('id', (int) $data['role_id']);

        if (! $role) {
            return back()->withErrors([
                'role_id' => 'Peran yang dipilih tidak tersedia pada akun Anda.',
            ]);
        }

        $oldRoleId = $user->active_role_id;

        if ($oldRoleId !== $role->id) {
            $user->update(['active_role_id' => $role->id]);
        }

        $remember = (bool) $request->session()->pull(AuthenticatedSessionController::PENDING_REMEMBER, false);

        Auth::guard('web')->login($user, $remember);

        $this->forgetPending($request);

        AuditLogger::log(
            'select_role',
            'auth',
            $user,
            ['active_role_id' => $oldRoleId],
            ['active_role_id' => $role->id],
        );

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    private function forgetPending(Request $request): void
    {
        $request->session()->forget([
            AuthenticatedSessionController::PENDING_USER_ID,
            AuthenticatedSessionController::PENDING_REMEMBER,
            AuthenticatedSessionController::PENDING_AT,
        ]);
    }
}
