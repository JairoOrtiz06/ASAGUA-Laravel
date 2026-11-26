<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->newQuery()->useWritePdo()->whereKey($user->getKey())->where('activo', true)->exists()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'usuario' => 'Tu sesión se cerró porque la cuenta ya no está activa. Si necesitas acceso, contacta al administrador.',
            ]);
        }

        return $next($request);
    }
}
