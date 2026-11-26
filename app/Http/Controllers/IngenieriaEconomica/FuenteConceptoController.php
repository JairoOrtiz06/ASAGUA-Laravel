<?php

namespace App\Http\Controllers\IngenieriaEconomica;

use App\Http\Controllers\Controller;
use App\Http\Requests\IngenieriaEconomica\FuenteConceptoRequest;
use App\Models\IngenieriaEconomica\Concepto;
use App\Models\IngenieriaEconomica\FuenteConcepto;
use App\Models\IngenieriaEconomica\Proyecto;
use App\Services\IngenieriaEconomica\DependenciasEconomicas;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class FuenteConceptoController extends Controller
{
    public function create(Proyecto $proyecto, Concepto $concepto): View
    {
        Gate::authorize('update', $proyecto);

        return view('ingenieria-economica.conceptos.fuentes', ['proyecto' => $proyecto, 'concepto' => $concepto, 'fuente' => new FuenteConcepto]);
    }

    public function store(FuenteConceptoRequest $request, Proyecto $proyecto, Concepto $concepto): RedirectResponse
    {
        DB::transaction(function () use ($request, $proyecto, $concepto): void {
            $actual = $proyecto->conceptos()->lockForUpdate()->findOrFail($concepto->id);
            $actual->fuentes()->create($request->validated());
        });

        return $this->detalle($proyecto, $concepto, 'Evidencia registrada.');
    }

    public function edit(Proyecto $proyecto, Concepto $concepto, FuenteConcepto $fuente): View
    {
        Gate::authorize('update', $proyecto);

        return view('ingenieria-economica.conceptos.fuentes', compact('proyecto', 'concepto', 'fuente'));
    }

    public function update(FuenteConceptoRequest $request, Proyecto $proyecto, Concepto $concepto, FuenteConcepto $fuente): RedirectResponse
    {
        $fuente->update($request->validated());

        return $this->detalle($proyecto, $concepto, 'Evidencia actualizada.');
    }

    private function detalle(Proyecto $proyecto, Concepto $concepto, string $mensaje): RedirectResponse
    {
        return redirect()->route('ingenieria-economica.proyectos.conceptos.show', [$proyecto, $concepto])->with('status', $mensaje);
    }

    public function destroy(Proyecto $proyecto, Concepto $concepto, FuenteConcepto $fuente, DependenciasEconomicas $dependencias): RedirectResponse
    {
        Gate::authorize('update', $proyecto);
        DB::transaction(function () use ($proyecto, $concepto, $fuente, $dependencias): void {
            Proyecto::query()->lockForUpdate()->findOrFail($proyecto->id);
            $actual = $proyecto->conceptos()->lockForUpdate()->findOrFail($concepto->id);
            $cita = $actual->fuentes()->lockForUpdate()->findOrFail($fuente->id);
            if ($dependencias->enHistorial($proyecto, 'concepto', $actual->id) || $dependencias->vinculado($proyecto, 'concepto', $actual->id)) {
                throw ValidationException::withMessages(['fuente' => 'El concepto tiene respaldo vinculado o uso histórico. La referencia se conserva.']);
            }
            $cita->delete();
        });

        return $this->detalle($proyecto, $concepto, 'Referencia retirada. El documento original se conserva.');
    }
}
