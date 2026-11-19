<?php

namespace App\Http\Controllers;

use App\Http\Requests\HojaTrabajoRequest;
use App\Services\HojaTrabajoContableService;
use Illuminate\View\View;

class HojaTrabajoController extends Controller
{
    public function index(HojaTrabajoRequest $request, HojaTrabajoContableService $service): View
    {
        $hoja = $service->generar(
            $request->validated('fecha_inicio'),
            $request->validated('fecha_fin'),
        );

        return view('hoja-trabajo.index', compact('hoja'));
    }
}
