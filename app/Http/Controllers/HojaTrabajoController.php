<?php

namespace App\Http\Controllers;

use App\Http\Requests\HojaTrabajoRequest;
use App\Services\HojaTrabajoContableService;
use App\Services\PartidaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HojaTrabajoController extends Controller
{
    public function index(HojaTrabajoRequest $request, HojaTrabajoContableService $service, PartidaService $partidas): View
    {
        $hoja = $service->generar(
            $request->validated('fecha_inicio'),
            $request->validated('fecha_fin'),
        );

        return view('hoja-trabajo.index', ['hoja' => $hoja, 'ultimoCierre' => $partidas->ultimoCierre()]);
    }

    public function cerrar(Request $request, PartidaService $partidas): RedirectResponse
    {
        $datos = $request->validate([
            'fecha_corte' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'confirmar_cierre' => ['accepted'],
        ]);

        $partidas->cerrarHasta($request->user(), $datos['fecha_corte']);

        return redirect()->route('hoja-trabajo.index')->with('status', 'Cierre contable registrado.');
    }
}
