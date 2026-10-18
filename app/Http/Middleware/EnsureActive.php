<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Logs out any authenticated session whose account has been deactivated.
 * The flag is re-read from the database on every request so an admin's
 * "nonaktifkan" action takes effect immediately, not only on next login.
 */
class EnsureActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->is_active) {
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                abort(403, 'Akun Anda telah dinonaktifkan.');
            }

            return redirect()->route('login')->withErrors([
                'email' => 'Akun Anda telah dinonaktifkan. Hubungi administrator untuk mengaktifkan kembali.',
            ]);
        }

        return $next($request);
    }
}
