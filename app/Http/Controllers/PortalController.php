<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class PortalController extends Controller
{
    public function contador(): View
    {
        return view('portal.contador', [
            'user' => auth()->user(),
        ]);
    }
}
