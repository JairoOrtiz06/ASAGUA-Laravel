<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function redirectByRole(): RedirectResponse
    {
        return match (auth()->user()->rol) {
            'Admin' => redirect()->route('panel.admin'),
            'Contador' => redirect()->route('portal.contador'),
            'Auditor' => redirect()->route('panel.auditor'),
            default => abort(403),
        };
    }

    public function panel(string $role): View
    {
        return view('dashboard.panel', [
            'role' => $role,
            'user' => auth()->user(),
        ]);
    }
}
