<?php

namespace App\Http\Controllers;

use App\Http\Requests\LibroRequest;
use App\Services\LibroContableService;
use Illuminate\View\View;

class EstadoResultadosController extends Controller
{
    public function index(LibroRequest $request, LibroContableService $service): View
    {
        $estado = $service->estadoResultados(
            $request->validated('fecha_inicio'),
            $request->validated('fecha_fin'),
        );

        return view('balances.resultados', compact('estado'));
    }
}
