<?php

namespace App\Http\Controllers\IngenieriaEconomica;

use App\Http\Controllers\Controller;
use App\Http\Requests\IngenieriaEconomica\AjusteFlujoRequest;
use App\Http\Requests\IngenieriaEconomica\ConsultaFlujosRequest;
use App\Models\IngenieriaEconomica\AjusteFlujo;
use App\Models\IngenieriaEconomica\Proyecto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AjusteFlujoController extends Controller
{
    public function create(ConsultaFlujosRequest $request, Proyecto $proyecto): View
    {
        Gate::authorize('update', $proyecto);

        return view('ingenieria-economica.flujos.ajuste', [
            'proyecto' => $proyecto,
            'alternativas' => $proyecto->alternativas()->orderBy('nombre')->pluck('nombre', 'id'),
            'escenarios' => $proyecto->escenarios()->orderByDesc('es_principal')->orderBy('nombre')->pluck('nombre', 'id'),
            'datos' => $request->validated(),
        ]);
    }

    public function store(AjusteFlujoRequest $request, Proyecto $proyecto): RedirectResponse
    {
        $escenario = $proyecto->escenarios()->findOrFail($request->validated('escenario_id'));
        $alternativa = $proyecto->alternativas()->findOrFail($request->validated('alternativa_id'));

        $ajuste = $escenario->ajustesFlujo()->make($request->safe()->except(['escenario_id', 'alternativa_id']));
        $ajuste->alternativa()->associate($alternativa);
        $ajuste->usuario()->associate($request->user());
        $ajuste->save();

        return redirect()->route('ingenieria-economica.proyectos.flujos.index', [
            'proyecto' => $proyecto, 'escenario_id' => $escenario->id,
            'alternativas' => [$alternativa->id], 'moneda' => $ajuste->moneda,
        ])->with('status', 'Ajuste manual registrado.');
    }

    public function destroy(Proyecto $proyecto, int $ajuste): RedirectResponse
    {
        Gate::authorize('update', $proyecto);
        $registro = AjusteFlujo::query()
            ->whereIn('escenario_id', $proyecto->escenarios()->select('id'))
            ->whereIn('alternativa_id', $proyecto->alternativas()->select('id'))
            ->findOrFail($ajuste);
        $registro->delete();

        return redirect()->route('ingenieria-economica.proyectos.flujos.index', [
            'proyecto' => $proyecto, 'escenario_id' => $registro->escenario_id,
            'alternativas' => [$registro->alternativa_id], 'moneda' => $registro->moneda,
        ])->with('status', 'Ajuste manual eliminado.');
    }
}
