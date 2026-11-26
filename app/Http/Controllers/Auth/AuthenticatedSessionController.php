<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    private const MAX_FAILED_ATTEMPTS = 5;

    private const DECAY_SECONDS = 60;

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $credentials = $request->validated();
        $credentials['activo'] = true;
        $identifier = Str::lower(trim($credentials['usuario']));
        $key = 'asagua:login:'.hash('sha256', $identifier.'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($key, self::MAX_FAILED_ATTEMPTS)) {
            $seconds = max(1, RateLimiter::availableIn($key));

            return back()
                ->withInput($request->only('usuario'))
                ->withErrors(['usuario' => "Demasiados intentos de inicio de sesión. Vuelve a intentarlo en {$seconds} s."]);
        }

        if (! Auth::attempt($credentials)) {
            RateLimiter::hit($key, self::DECAY_SECONDS);

            return back()
                ->withInput($request->only('usuario'))
                ->withErrors(['usuario' => 'No se pudo iniciar sesión con las credenciales proporcionadas.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        // `intended()` returns the first redirect target (`/dashboard`).
        // The role dispatcher on that route would require a second request,
        // while the login contract is to return the role panel immediately.
        $panel = match (Auth::user()->rol) {
            'Admin' => 'panel.admin',
            'Contador' => 'portal.contador',
            'Auditor' => 'panel.auditor',
            default => abort(403),
        };

        return redirect()->route($panel);
    }

    public function destroy(): RedirectResponse
    {
        Auth::logout();

        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('login');
    }
}
