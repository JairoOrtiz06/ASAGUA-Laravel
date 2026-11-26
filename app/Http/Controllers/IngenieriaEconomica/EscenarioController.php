<?php

namespace App\Http\Controllers\IngenieriaEconomica;

use App\Http\Controllers\Controller;
use App\Http\Requests\IngenieriaEconomica\EscenarioRequest;
use App\Models\IngenieriaEconomica\Escenario;
use App\Models\IngenieriaEconomica\Proyecto;
use App\Services\IngenieriaEconomica\EscenarioPrincipalService;
use App\Services\IngenieriaEconomica\ConversionTasasService;
use App\Services\IngenieriaEconomica\DecimalFinanciero;
use Illuminate\Http\JsonResponse;
use App\Services\IngenieriaEconomica\DependenciasEconomicas;
use App\Models\IngenieriaEconomica\CalculoActivo;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Illuminate\Validation\ValidationException;

class EscenarioController extends Controller
{
    public function create(Proyecto $proyecto): View
    {
        Gate::authorize('update', $proyecto);

        $escenario = new Escenario([
            'horizonte_anios' => $proyecto->horizonte_anios,
            'periodicidad' => 'anual',
            'estado_tasa' => 'pendiente',
        ]);

        return view('ingenieria-economica.escenarios.form', compact('proyecto', 'escenario'));
    }

    public function previsualizar(EscenarioRequest $request, Proyecto $proyecto, ConversionTasasService $tasas): JsonResponse
    {
        $d = $request->datosEscenario();
        if ($d['tasa_efectiva_anual'] === null) {
            return response()->json(['resumen' => 'TMAR pendiente. Ingresa la tasa original, su modalidad y periodicidad.']);
        }

        $destino = $request->validated('periodicidad_descuento') ?? $d['periodicidad'];
        $equivalente = $tasas->convertir($d['tasa_efectiva_anual'], 'efectiva', 'anual', $destino);
        $m = $d['capitalizaciones_anuales'];
        $k = $tasas->frecuencia($destino);
        $formula = $d['modalidad_tasa'] === 'nominal' ? "(1 + j/$m)^$m − 1" : "(1 + i)^$m − 1";

        return response()->json(['resumen' => [
            'Cotización original: '.DecimalFinanciero::porcentaje((string) $d['tasa_original']).' % · '.Escenario::MODALIDADES[$d['modalidad_tasa']]
                .' · '.Escenario::PERIODOS_TASA[$d['periodicidad_tasa']].'.',
            'TMAR efectiva anual: '.DecimalFinanciero::porcentaje($d['tasa_efectiva_anual']).' %.',
            'Tasa efectiva para descuento '.strtolower(Escenario::PERIODOS[$destino]).': '
                .DecimalFinanciero::mostrar($equivalente->withPointMovedRight(2), 12).' %.',
            "Equivalencia: TEA = $formula. Tasa de descuento = (1 + TEA)^(1/$k) − 1. i es la efectiva original y j la nominal anual.",
            'Estado: '.Escenario::ESTADOS_TASA[$d['estado_tasa']].'. Fuente: '.($d['fuente_tasa'] ?? 'Sin registrar').'.',
        ]]);
    }

    public function store(EscenarioRequest $request, Proyecto $proyecto): RedirectResponse
    {
        $proyecto->escenarios()->create($request->datosEscenario());

        return $this->volver($proyecto, 'Escenario registrado.');
    }

    public function edit(Proyecto $proyecto, Escenario $escenario): View
    {
        Gate::authorize('update', $proyecto);

        return view('ingenieria-economica.escenarios.form', compact('proyecto', 'escenario'));
    }

    public function update(EscenarioRequest $request, Proyecto $proyecto, Escenario $escenario): RedirectResponse
    {
        $escenario->update($request->datosEscenario());

        return $this->volver($proyecto, 'Escenario actualizado.');
    }

    public function principal(Proyecto $proyecto, Escenario $escenario, EscenarioPrincipalService $service): RedirectResponse
    {
        Gate::authorize('update', $proyecto);

        $service->asignar($proyecto, $escenario);

        return $this->volver($proyecto, 'Escenario principal actualizado.');
    }

    private function volver(Proyecto $proyecto, string $mensaje): RedirectResponse
    {
        return redirect()->route('ingenieria-economica.proyectos.show', $proyecto)
            ->with('status', $mensaje)->withFragment('escenarios');
    }

    public function destroy(Proyecto $proyecto, Escenario $escenario, DependenciasEconomicas $dependencias): RedirectResponse
    {
        Gate::authorize('update', $proyecto);
        DB::transaction(function () use ($proyecto, $escenario, $dependencias): void {
            Proyecto::query()->lockForUpdate()->findOrFail($proyecto->id);
            $actual = $proyecto->escenarios()->lockForUpdate()->findOrFail($escenario->id);
            $motivo = match (true) {
                $actual->es_principal => 'El escenario es el principal. Define otro escenario como principal antes de eliminarlo.',
                $proyecto->evaluaciones()->where('escenario_id', $actual->id)->exists() => 'El escenario tiene evaluaciones guardadas.',
                $proyecto->analisisSensibilidad()->where('escenario_id', $actual->id)->exists() => 'El escenario tiene análisis de sensibilidad guardados.',
                $actual->ajustesFlujo()->exists() => 'El escenario tiene ajustes de flujo vinculados.',
                CalculoActivo::query()->where('escenario_id', $actual->id)->exists() => 'El escenario tiene cálculos de activos guardados.',
                $dependencias->vinculado($proyecto, 'escenario', $actual->id) => 'El escenario tiene evidencias vinculadas.',
                $dependencias->enHistorial($proyecto, 'escenario', $actual->id) => 'El escenario figura en el historial del proyecto.',
                default => null,
            };
            if ($motivo) {
                throw ValidationException::withMessages(['escenario' => $motivo]);
            }
            $actual->delete();
        });

        return $this->volver($proyecto, 'Escenario eliminado.');
    }
}
