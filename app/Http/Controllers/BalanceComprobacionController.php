<?php

namespace App\Http\Controllers;

use App\Http\Requests\LibroRequest;
use App\Services\LibroContableService;
use Illuminate\View\View;

class BalanceComprobacionController extends Controller
{
    public function index(LibroRequest $request, LibroContableService $service): View
    {
        $balance = $service->balanceComprobacion(
            $request->validated('fecha_inicio'),
            $request->validated('fecha_fin'),
        );

        return view('balances.comprobacion', compact('balance'));
    }
}
