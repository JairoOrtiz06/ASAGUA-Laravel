<?php

namespace App\Http\Controllers;

use App\Http\Requests\AjusteRequest;
use App\Models\CuentaContable;
use App\Models\Partida;
use App\Services\AjusteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AjusteController extends Controller
{
    public function index(): View
    {
        $ajustes = Partida::query()
            ->ajustes()
            ->with('usuario')
            ->withCount('detalles')
            ->orderByDesc('fecha')
            ->orderByDesc('numero_partida')
            ->paginate(20);

        return view('ajustes.index', compact('ajustes'));
    }

    public function create(): View
    {
        $cuentas = CuentaContable::query()
            ->where('estado', true)
            ->orderBy('codigo_cuenta')
            ->get();

        return view('ajustes.create', [
            'cuentas' => $cuentas,
            'tiposAjuste' => AjusteService::TIPOS_AJUSTE,
        ]);
    }

    public function store(AjusteRequest $request, AjusteService $service): RedirectResponse
    {
        $partida = $service->registrar($request->user(), $request->validated());

        return redirect()->route('ajustes.show', $partida)
            ->with('status', "Ajuste registrado en la partida #{$partida->numero_partida}.");
    }

    public function show(Partida $partida): View
    {
        abort_unless($partida->esAjuste(), 404);

        $partida->load(['usuario', 'detalles.cuenta']);
        $totales = $partida->detalles()
            ->selectRaw('COALESCE(SUM(debe), 0) AS debe, COALESCE(SUM(haber), 0) AS haber')
            ->first();

        return view('ajustes.show', compact('partida', 'totales'));
    }
}
