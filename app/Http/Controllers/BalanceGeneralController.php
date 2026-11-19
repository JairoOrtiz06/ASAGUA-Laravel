<?php

namespace App\Http\Controllers;

use App\Http\Requests\LibroRequest;
use App\Services\LibroContableService;
use Illuminate\View\View;

class BalanceGeneralController extends Controller
{
    public function index(LibroRequest $request, LibroContableService $service): View
    {
        $balance = $service->balanceGeneral(
            $request->validated('fecha_inicio'),
            $request->validated('fecha_fin'),
        );

        return view('balances.general', compact('balance'));
    }
}
