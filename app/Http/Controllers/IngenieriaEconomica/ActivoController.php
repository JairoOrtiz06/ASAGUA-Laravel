<?php

namespace App\Http\Controllers\IngenieriaEconomica;

use App\Http\Controllers\Controller;
use App\Http\Requests\IngenieriaEconomica\ActivoRequest;
use App\Models\IngenieriaEconomica\Activo;
use App\Models\IngenieriaEconomica\Proyecto;
use App\Services\IngenieriaEconomica\DependenciasEconomicas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ActivoController extends Controller
{
    public function index(Proyecto $proyecto): View
    {
        Gate::authorize('view', $proyecto);

        return view('ingenieria-economica.activos.index', [
            'proyecto' => $proyecto,
            'activos' => $proyecto->activos()->with('alternativa:id,nombre')->orderBy('nombre')->orderBy('id')->paginate(20),
        ]);
    }

    public function create(Proyecto $proyecto): View
    {
        Gate::authorize('update', $proyecto);

        return $this->formulario($proyecto, new Activo([
            'moneda' => 'USD', 'porcentaje_uso' => '100', 'estado_activo' => 'previsto', 'origen_dato' => 'provisional',
            'clasificacion_contable' => 'pendiente_revision', 'costos_capitalizables' => '0',
        ]));
    }

    public function store(ActivoRequest $request, Proyecto $proyecto): RedirectResponse
    {
        $activo = $proyecto->activos()->create($request->validated());

        return $this->detalle($proyecto, $activo);
    }

    public function show(Proyecto $proyecto, Activo $activo): View
    {
        Gate::authorize('view', $proyecto);
        $activo->load([
            'alternativa:id,nombre', 'fuentes.documento:id_documento,nombre_documento',
            'fuentes.detalle:id_detalle,id_cuenta,id_partida', 'fuentes.detalle.cuenta:id_cuenta,codigo_cuenta,nombre_cuenta',
            'fuentes.detalle.partida:id_partida,numero_partida', 'conceptos:id,activo_id,nombre,activo_operacion',
        ]);

        return view('ingenieria-economica.activos.show', [
            'proyecto' => $proyecto, 'activo' => $activo,
            'ultimaValoracion' => $activo->calculos()->where('metodo','reemplazo')->latest('id')->first(),
            'ultimoCalculoContable' => $activo->calculos()->whereIn('metodo',['comparacion_depreciacion','amortizacion_lineal'])->latest('id')->first(),
            'calculos' => $activo->calculos()->select(['id', 'activo_id', 'metodo', 'version_motor', 'created_at'])->latest('id')->paginate(15),
        ]);
    }

    public function edit(Proyecto $proyecto, Activo $activo): View
    {
        Gate::authorize('update', $proyecto);

        return $this->formulario($proyecto, $activo);
    }

    public function update(ActivoRequest $request, Proyecto $proyecto, Activo $activo): RedirectResponse
    {
        $d = $request->validated() + [
            'alternativa_id' => null, 'vida_util_anios' => null, 'vida_indefinida' => false,
            'fecha_adquisicion' => null, 'fecha_disponible_uso' => null,
        ];
        DB::transaction(function () use ($proyecto, $activo, $d): void {
            $actual = $proyecto->activos()->lockForUpdate()->findOrFail($activo->id);
            if (((int) $actual->alternativa_id !== (int) $d['alternativa_id'] || $actual->moneda !== $d['moneda'])
                && $actual->conceptos()->exists()) {
                throw ValidationException::withMessages(['alternativa_id' => 'Un activo con movimientos vinculados debe conservar su ámbito y moneda.']);
            }

            $actual->update($d);
        });

        return $this->detalle($proyecto, $activo);
    }

    private function formulario(Proyecto $proyecto, Activo $activo): View
    {
        return view('ingenieria-economica.activos.form', [
            'proyecto' => $proyecto, 'activo' => $activo,
            'alternativas' => $proyecto->alternativas()->orderBy('nombre')->pluck('nombre', 'id'),
        ]);
    }

    private function detalle(Proyecto $proyecto, Activo $activo): RedirectResponse
    {
        return redirect()->route('ingenieria-economica.proyectos.activos.show', [$proyecto, $activo])->with('status', 'Activo guardado.');
    }

    public function destroy(Proyecto $proyecto, Activo $activo, DependenciasEconomicas $dependencias): RedirectResponse
    {
        Gate::authorize('update', $proyecto);
        DB::transaction(function () use ($proyecto, $activo, $dependencias): void {
            Proyecto::query()->lockForUpdate()->findOrFail($proyecto->id);
            $actual = $proyecto->activos()->lockForUpdate()->findOrFail($activo->id);
            $motivo = match (true) {
                $actual->conceptos()->exists() => 'El activo tiene conceptos económicos vinculados.',
                $actual->calculos()->exists() => 'El activo tiene cálculos guardados.',
                $actual->fuentes()->exists() => 'El activo tiene fuentes o referencias contables.',
                $dependencias->vinculado($proyecto, 'activo', $actual->id) => 'El activo tiene evidencias vinculadas.',
                $dependencias->enHistorial($proyecto, 'activo', $actual->id) => 'El activo figura en un cálculo histórico o fiscal.',
                default => null,
            };
            if ($motivo) {
                throw ValidationException::withMessages(['activo' => $motivo]);
            }
            $actual->delete();
        });

        return redirect()->route('ingenieria-economica.proyectos.activos.index', $proyecto)->with('status', 'Activo eliminado.');
    }
}
