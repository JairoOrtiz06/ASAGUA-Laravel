<?php

namespace App\Http\Controllers\IngenieriaEconomica;

use App\Http\Controllers\Controller;
use App\Http\Requests\IngenieriaEconomica\FlujoActivoRequest;
use App\Http\Requests\IngenieriaEconomica\FuenteActivoRequest;
use App\Models\IngenieriaEconomica\Activo;
use App\Models\IngenieriaEconomica\Proyecto;
use App\Services\IngenieriaEconomica\IntegracionActivoService;
use DomainException;
use App\Services\IngenieriaEconomica\ReferenciaDocumentalService;
use App\Services\IngenieriaEconomica\DependenciasEconomicas;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class IntegracionActivoController extends Controller
{
    public function fuente(Request $request, Proyecto $proyecto, Activo $activo, ReferenciaDocumentalService $referencias): View
    {
        Gate::authorize('update', $proyecto);

        $filtros = $request->validate(['tipo_contable'=>['nullable','in:documento,detalle'], 'q'=>['nullable','string','max:100'],
            'pagina'=>['nullable','integer','between:1,100000']]);
        $tipo = $filtros['tipo_contable'] ?? 'documento';
        $paginas = $referencias->opciones($proyecto, $tipo, $filtros['q'] ?? '')->withQueryString();
        $campo = $tipo === 'documento' ? 'id_documento' : 'id_detalle';
        $opciones = $paginas->getCollection()->mapWithKeys(fn ($ref) => [$ref['id'] => $ref['etiqueta']]);
        foreach (['id_documento'=>'documento', 'id_detalle'=>'detalle'] as $clave => $clase) {
            $idAnterior = filter_var($request->old($clave), FILTER_VALIDATE_INT);
            if ($idAnterior && ($ref = $referencias->resolver($proyecto, $clase, $idAnterior))) {
                if ($clave === $campo) {
                    $opciones->put($idAnterior, $ref['etiqueta']);
                }
            }
        }

        return view('ingenieria-economica.activos.fuente', compact('proyecto', 'activo', 'filtros', 'tipo', 'paginas', 'campo', 'opciones'));
    }

    public function guardarFuente(FuenteActivoRequest $request, Proyecto $proyecto, Activo $activo): RedirectResponse
    {
        $activo->fuentes()->create($request->validated());

        return redirect()->route('ingenieria-economica.proyectos.activos.show', [$proyecto, $activo])->with('status', 'Evidencia vinculada.');
    }

    public function flujo(Proyecto $proyecto, Activo $activo): View
    {
        Gate::authorize('update', $proyecto);

        return view('ingenieria-economica.activos.flujo', [
            'proyecto' => $proyecto, 'activo' => $activo,
            'escenarios' => $proyecto->escenarios()->orderByDesc('es_principal')->orderBy('nombre')->pluck('nombre', 'id'),
            'fuentes' => $activo->fuentes()->whereNotIn('uso', ['depreciacion', 'amortizacion', 'fiscal'])->orderBy('id')->pluck('referencia', 'id'),
            'conceptos' => $proyecto->conceptos()->where('alternativa_id', $activo->alternativa_id)
                ->where('moneda', $activo->moneda)->where('periodicidad', 'unico')->orderBy('nombre')->pluck('nombre', 'id'),
        ]);
    }

    public function guardarFlujo(FlujoActivoRequest $request, Proyecto $proyecto, Activo $activo, IntegracionActivoService $servicio): RedirectResponse
    {
        try {
            $concepto = $servicio->incorporar($proyecto, $activo, $request->validated());
        } catch (DomainException $error) {
            throw ValidationException::withMessages(['movimiento' => $error->getMessage()]);
        }

        return redirect()->route('ingenieria-economica.proyectos.conceptos.show', [$proyecto, $concepto])
            ->with('status', 'Movimiento vinculado al activo.');
    }

    public function retirarFuente(Proyecto $proyecto, Activo $activo, string $fuente, DependenciasEconomicas $dependencias): RedirectResponse
    {
        Gate::authorize('update', $proyecto);
        DB::transaction(function () use ($proyecto, $activo, $fuente, $dependencias): void {
            Proyecto::query()->lockForUpdate()->findOrFail($proyecto->id);
            $actual = $proyecto->activos()->lockForUpdate()->findOrFail($activo->id);
            $cita = $actual->fuentes()->lockForUpdate()->findOrFail($fuente);
            if ($actual->calculos()->exists() || $actual->conceptos()->exists()
                || $dependencias->enHistorial($proyecto, 'activo', $actual->id) || $dependencias->vinculado($proyecto, 'activo', $actual->id)) {
                throw ValidationException::withMessages(['fuente' => 'El activo tiene respaldos vinculados, movimientos o uso histórico. La referencia se conserva.']);
            }
            $cita->delete();
        });

        return redirect()->route('ingenieria-economica.proyectos.activos.show', [$proyecto, $activo])
            ->with('status', 'Referencia retirada. El documento original se conserva.');
    }
}
