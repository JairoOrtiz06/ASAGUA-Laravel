<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class IngenieriaEconomicaController extends Controller
{
    public function index(): View
    {
        return view('ingenieria-economica.index', [
            'user' => auth()->user(),
        ]);
    }
}
