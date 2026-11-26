<?php

namespace App\Http\Controllers\IngenieriaEconomica;

use App\Http\Controllers\Controller;
use App\Http\Requests\IngenieriaEconomica\AlternativaRequest;
use App\Models\IngenieriaEconomica\Alternativa;
use App\Models\IngenieriaEconomica\Proyecto;
use App\Services\IngenieriaEconomica\DependenciasEconomicas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AlternativaController extends Controller
{
    public function index(Proyecto $proyecto): RedirectResponse
    {
        Gate::authorize('view', $proyecto);

        return redirect()->route('ingenieria-economica.proyectos.show', $proyecto)->withFragment('alternativas');
    }

    public function create(Proyecto $proyecto): View
    {
        Gate::authorize('update', $proyecto);

        $alternativa = new Alternativa(['estado_implementacion' => 'no_implementada', 'viabilidad_tecnica' => 'pendiente']);

        return view('ingenieria-economica.alternativas.form', compact('proyecto', 'alternativa'));
    }

    public function store(AlternativaRequest $request, Proyecto $proyecto): RedirectResponse
    {
        $proyecto->alternativas()->create($request->validated());

        return $this->volver($proyecto, 'Alternativa registrada.');
    }

    public function edit(Proyecto $proyecto, Alternativa $alternativa): View
    {
        Gate::authorize('update', $proyecto);

        return view('ingenieria-economica.alternativas.form', compact('proyecto', 'alternativa'));
    }

    public function update(AlternativaRequest $request, Proyecto $proyecto, Alternativa $alternativa): RedirectResponse
    {
        $alternativa->update($request->validated());

        return $this->volver($proyecto, 'Alternativa actualizada.');
    }

    public function destroy(Proyecto $proyecto, Alternativa $alternativa, DependenciasEconomicas $dependencias): RedirectResponse
    {
        Gate::authorize('update', $proyecto);

        $motivo = DB::transaction(function () use ($proyecto, $alternativa, $dependencias): ?string {
            Proyecto::query()->lockForUpdate()->findOrFail($proyecto->id);
            $actual = $proyecto->alternativas()->lockForUpdate()->findOrFail($alternativa->id);
            $motivo = match (true) {
                $actual->estado_implementacion !== 'no_implementada' => 'Solo puedes eliminar alternativas sin implementar.',
                $actual->conceptos()->exists() => 'La alternativa tiene conceptos económicos vinculados.',
                $actual->activos()->exists() => 'La alternativa tiene activos vinculados.',
                $actual->ajustesFlujo()->exists() => 'La alternativa tiene ajustes de flujo vinculados.',
                $dependencias->vinculado($proyecto, 'alternativa', $actual->id) => 'La alternativa tiene evidencias vinculadas. Revisa el respaldo documental.',
                $dependencias->enHistorial($proyecto, 'alternativa', $actual->id) => 'La alternativa figura en resultados históricos o hay una versión que requiere revisión. Se conserva el registro.',
                default => null,
            };
            if ($motivo === null) {
                $actual->delete();
            }

            return $motivo;
        });

        if ($motivo !== null) {
            return redirect()->route('ingenieria-economica.proyectos.show', $proyecto)
                ->withErrors(['alternativa' => $motivo])->withFragment('alternativas');
        }

        return $this->volver($proyecto, 'Alternativa eliminada.');
    }

    private function volver(Proyecto $proyecto, string $mensaje): RedirectResponse
    {
        return redirect()->route('ingenieria-economica.proyectos.show', $proyecto)
            ->with('status', $mensaje)->withFragment('alternativas');
    }
}
