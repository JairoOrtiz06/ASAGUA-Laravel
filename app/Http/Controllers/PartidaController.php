<?php

namespace App\Http\Controllers;

use App\Http\Requests\PartidaRequest;
use App\Models\CuentaContable;
use App\Models\DocumentoFuente;
use App\Models\Partida;
use App\Services\PartidaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PartidaController extends Controller
{
    public function index(): View
    {
        $partidas = Partida::query()
            ->with('usuario')
            ->withCount('detalles')
            ->orderByDesc('fecha')
            ->orderByDesc('numero_partida')
            ->paginate(20);

        return view('partidas.index', compact('partidas'));
    }

    public function create(): View
    {
        $cuentas = CuentaContable::query()
            ->where('estado', true)
            ->where('nivel', 4)
            ->orderBy('codigo_cuenta')
            ->get();
        $documentos = DocumentoFuente::query()
            ->select(['id_documento', 'nombre_documento', 'tipo_documento', 'fecha_creacion'])
            ->orderByDesc('id_documento')
            ->get();

        return view('partidas.create', compact('cuentas', 'documentos'));
    }

    public function store(PartidaRequest $request, PartidaService $service): RedirectResponse
    {
        $partida = $service->registrar($request->user(), $request->validated());

        return redirect()->route('partidas.show', $partida)
            ->with('status', "Partida #{$partida->numero_partida} registrada correctamente.");
    }

    public function show(Partida $partida): View
    {
        $partida->load(['usuario', 'detalles.cuenta']);
        $totales = $partida->detalles()
            ->selectRaw('COALESCE(SUM(debe), 0) AS debe, COALESCE(SUM(haber), 0) AS haber')
            ->first();

        return view('partidas.show', compact('partida', 'totales'));
    }
}
