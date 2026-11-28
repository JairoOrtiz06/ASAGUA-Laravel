<?php

namespace App\Http\Controllers\IngenieriaEconomica;

use App\Http\Controllers\Controller;
use App\Services\IngenieriaEconomica\ContextoEvaluacion;
use App\Http\Requests\IngenieriaEconomica\DepreciacionActivoRequest;
use App\Http\Requests\IngenieriaEconomica\ReemplazoActivoRequest;
use App\Models\IngenieriaEconomica\Activo;
use App\Models\IngenieriaEconomica\CalculoActivo;
use App\Models\IngenieriaEconomica\Escenario;
use App\Models\IngenieriaEconomica\Proyecto;
use App\Models\User;
use App\Services\IngenieriaEconomica\DepreciacionActivoService;
use App\Services\IngenieriaEconomica\ReemplazoActivoService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CalculoActivoController extends Controller
{
    public function depreciacion(Proyecto $proyecto, Activo $activo): View
    {
        Gate::authorize('view', $proyecto);

        return view('ingenieria-economica.activos.depreciacion', compact('proyecto', 'activo'));
    }

    public function calcularDepreciacion(DepreciacionActivoRequest $request, Proyecto $proyecto, Activo $activo, DepreciacionActivoService $motor): RedirectResponse
    {
        return DB::transaction(function () use ($request, $proyecto, $activo, $motor): RedirectResponse {
            Proyecto::query()->lockForUpdate()->findOrFail($proyecto->id);
            $activo = $proyecto->activos()->findOrFail($activo->id);
            try {
                $r = $motor->calcular($activo, $request->validated());
            } catch (DomainException $error) {
                throw ValidationException::withMessages(['calculo' => $error->getMessage()]);
            }
            $metodo = $activo->clasificacion_contable === 'activo_tangible' ? 'comparacion_depreciacion' : 'amortizacion_lineal';

            return $this->guardar($proyecto, $activo, null, $request->user(), $metodo, $r);
        });
    }

    public function reemplazo(Proyecto $proyecto, Activo $activo): View
    {
        Gate::authorize('view', $proyecto);

        return view('ingenieria-economica.activos.reemplazo', [
            'proyecto' => $proyecto, 'activo' => $activo,
            'escenarios' => $proyecto->escenarios()->orderByDesc('es_principal')->orderBy('nombre')->get(),
            'nuevos' => $proyecto->activos()->whereKeyNot($activo->id)->where('moneda', $activo->moneda)->orderBy('nombre')->pluck('nombre', 'id'),
        ]);
    }

    public function calcularReemplazo(ReemplazoActivoRequest $request, Proyecto $proyecto, Activo $activo, ReemplazoActivoService $motor): RedirectResponse
    {
        return DB::transaction(function () use ($request, $proyecto, $activo, $motor): RedirectResponse {
            Proyecto::query()->lockForUpdate()->findOrFail($proyecto->id);
            $activo = $proyecto->activos()->findOrFail($activo->id);
            $d = $request->datosCalculo();
            $escenario = $proyecto->escenarios()->findOrFail($d['escenario_id']);
            $nuevo = empty($d['nuevo_id']) ? null : $proyecto->activos()->findOrFail($d['nuevo_id']);
            try {
                $r = $motor->calcular($activo, $nuevo, $escenario, $d);
            } catch (DomainException $error) {
                throw ValidationException::withMessages(['calculo' => $error->getMessage()]);
            }
            if (! empty($d['considerar_retiro'])) {
                $r['retiro_inmediato'] = $r['oportunidad_conservar'];
            }
            if ($nuevo) {
                $r['fuentes_nuevo'] = $this->fuentes($nuevo);
            }

            return $this->guardar($proyecto, $activo, $escenario, $request->user(), 'reemplazo', $r);
        });
    }

    public function show(Proyecto $proyecto, Activo $activo, CalculoActivo $calculo): View
    {
        Gate::authorize('view', $proyecto);

        return view('ingenieria-economica.activos.calculo', [
            'proyecto' => $proyecto, 'activo' => $activo, 'calculo' => $calculo, 'r' => $calculo->instantanea,
        ]);
    }

    public function csv(Proyecto $proyecto, Activo $activo, CalculoActivo $calculo): StreamedResponse
    {
        Gate::authorize('view', $proyecto);
        $r = $calculo->instantanea;

        return response()->streamDownload(function () use ($calculo, $r): void {
            $csv = fopen('php://output', 'w');
            if ($calculo->metodo === 'reemplazo') {
                fputcsv($csv, ['opcion', 'periodo', 'fecha', 'beneficios', 'costos', 'neto', 'factor', 'valor_presente'], escape: '');
                foreach (['conservar', 'reemplazar'] as $opcion) {
                    foreach ($r[$opcion]['periodos'] as $periodo => $fila) {
                        fputcsv($csv, [$opcion, $periodo, $fila['fecha'], $fila['beneficios'], $fila['costos'], $fila['neto'], $fila['factor'], $fila['vp']], escape: '');
                    }
                }
            } else {
                fputcsv($csv, ['metodo', 'anio', 'desde', 'hasta', 'valor_inicial', 'importe', 'acumulado', 'valor_final', 'residual'], escape: '');
                foreach ($r['metodos'] as $metodo => $tabla) {
                    foreach ($tabla['filas'] as $fila) {
                        fputcsv($csv, [$metodo, $fila['anio'], $fila['desde'], $fila['hasta'], $fila['saldo_inicial'], $fila['importe'], $fila['acumulado'], $fila['saldo_final'], $fila['residual']], escape: '');
                    }
                }
            }
            fclose($csv);
        }, 'calculo-activo-'.$calculo->id.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function guardar(Proyecto $proyecto, Activo $activo, ?Escenario $escenario, User $usuario, string $metodo, array $resultado): RedirectResponse
    {
        $resultado['fuentes'] = $this->fuentes($activo);
        $calculo = $activo->calculos()->make(['metodo' => $metodo, 'version_motor' => $resultado['version'], 'instantanea' => $resultado]);
        $calculo->usuario()->associate($usuario);
        $calculo->escenario()->associate($escenario);
        $calculo->save();

        return redirect()->to(ContextoEvaluacion::enlace('activos.calculos.show', [$proyecto, $activo, $calculo]))
            ->with('status', 'Cálculo guardado como una versión nueva.');
    }

    private function fuentes(Activo $activo): array
    {
        return $activo->fuentes()->orderBy('id')->get()->map(fn ($fuente) => [
            'uso' => $fuente->uso, 'tipo' => $fuente->tipo, 'referencia' => $fuente->referencia,
            'fecha' => $fuente->fecha->toDateString(), 'importe' => $fuente->importe_observado,
            'id_documento' => $fuente->id_documento, 'id_detalle' => $fuente->id_detalle, 'justificacion' => $fuente->justificacion,
        ])->all();
    }
}
