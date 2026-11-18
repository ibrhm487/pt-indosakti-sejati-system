<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public const PENDING_USER_ID = 'auth.pending_user_id';

    public const PENDING_REMEMBER = 'auth.pending_remember';

    public const PENDING_AT = 'auth.pending_at';

    public const PENDING_TTL_SECONDS = 900;

    /**
     * Display the login view.
     */
    public function create(Request $request): Response
    {
        $pendingUser = $this->pendingUser($request);
        $roles = [];

        if ($pendingUser) {
            $roles = $pendingUser->roles->map(fn ($role) => [
                'id' => $role->id,
                'slug' => $role->slug,
                'name' => $role->name,
            ])->values()->all();
        }

        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
            'showRoleSelection' => count($roles) > 1,
            'roles' => $roles,
            'pendingEmail' => $pendingUser?->email,
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $user = $request->user()->loadMissing('roles');

        if ($user->roles->isEmpty()) {
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors([
                'email' => 'Akun Anda belum memiliki peran. Hubungi administrator.',
            ]);
        }

        if ($user->roles->count() === 1) {
            $role = $user->roles->first();

            if ($user->active_role_id !== $role->id) {
                $user->update(['active_role_id' => $role->id]);
            }

            $request->session()->forget([
                self::PENDING_USER_ID,
                self::PENDING_REMEMBER,
                self::PENDING_AT,
            ]);

            $request->session()->regenerate();

            return redirect()
                ->intended(route('dashboard', absolute: false))
                ->with('success', "Selamat datang, {$user->name}.");
        }

        $request->session()->regenerate();
        $request->session()->put([
            self::PENDING_USER_ID => $user->id,
            self::PENDING_REMEMBER => $request->boolean('remember'),
            self::PENDING_AT => now()->timestamp,
        ]);

        Auth::guard('web')->logout();

        return redirect()
            ->route('login')
            ->with('info', 'Pilih peran Anda untuk melanjutkan.');
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/')->with('success', 'Anda telah keluar.');
    }

    /**
     * Resolve the user waiting to pick one of their roles at login.
     */
    private function pendingUser(Request $request): ?User
    {
        $userId = $request->session()->get(self::PENDING_USER_ID);
        $storedAt = (int) $request->session()->get(self::PENDING_AT, 0);

        if (! $userId) {
            return null;
        }

        if ($storedAt > 0 && now()->timestamp - $storedAt > self::PENDING_TTL_SECONDS) {
            $request->session()->forget([
                self::PENDING_USER_ID,
                self::PENDING_REMEMBER,
                self::PENDING_AT,
            ]);

            return null;
        }

        $user = User::with(['roles:id,slug,name'])
            ->whereKey($userId)
            ->where('is_active', true)
            ->first();

        if (! $user || $user->roles->count() < 2) {
            $request->session()->forget([
                self::PENDING_USER_ID,
                self::PENDING_REMEMBER,
                self::PENDING_AT,
            ]);

            return null;
        }

        return $user;
    }
}
