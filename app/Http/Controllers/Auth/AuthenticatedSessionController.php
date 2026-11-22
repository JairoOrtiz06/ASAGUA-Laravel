<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $credentials = $request->validated();
        $credentials['activo'] = true;

        if (! Auth::attempt($credentials)) {
            return back()
                ->withInput($request->only('usuario'))
                ->withErrors(['usuario' => 'El usuario o la contraseña son incorrectos, o la cuenta está desactivada.']);
        }

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
