<?php

namespace App\Http\Controllers\IngenieriaEconomica;

use App\Http\Controllers\Controller;
use App\Http\Requests\IngenieriaEconomica\ProyectoRequest;
use App\Models\IngenieriaEconomica\Proyecto;
use App\Services\IngenieriaEconomica\CalidadProyectoService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ProyectoController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Proyecto::class);

        $proyectos = Proyecto::query()
            ->whereBelongsTo($request->user(), 'usuario')
            ->latest('id')
            ->paginate(15);

        return view('ingenieria-economica.proyectos.index', compact('proyectos'));
    }

    public function create(): View
    {
        Gate::authorize('create', Proyecto::class);

        return view('ingenieria-economica.proyectos.form', ['proyecto' => new Proyecto(['estado' => 'borrador'])]);
    }

    public function store(ProyectoRequest $request): RedirectResponse
    {
        $proyecto = new Proyecto($request->validated());
        $proyecto->usuario()->associate($request->user());
        $proyecto->save();

        return redirect()->route('ingenieria-economica.proyectos.show', $proyecto)->with('status', 'Proyecto creado.');
    }

    public function show(Proyecto $proyecto, CalidadProyectoService $calidadProyecto): View
    {
        Gate::authorize('view', $proyecto);

        $proyecto->load('escenarioPrincipal');
        $alternativas = $proyecto->alternativas()->orderBy('id')->paginate(15, pageName: 'alternativas');
        $escenarios = $proyecto->escenarios()->orderByDesc('es_principal')->orderBy('id')->paginate(15, pageName: 'escenarios');

        $ultimaEvaluacion = $proyecto->evaluaciones()->latest('id')->first();
        $calidad = $calidadProyecto->revisar($proyecto, $ultimaEvaluacion);
        $siguiente = match (true) {
            $alternativas->total() === 0 => ['Agregar una alternativa', route('ingenieria-economica.proyectos.alternativas.create', $proyecto)],
            $escenarios->total() === 0 => ['Configurar un escenario', route('ingenieria-economica.proyectos.escenarios.create', $proyecto)],
            ! $proyecto->escenarioPrincipal => ['Seleccionar escenario principal', '#escenarios'],
            $proyecto->escenarioPrincipal->tasa_efectiva_anual === null => [
                'Configurar la TMAR', route('ingenieria-economica.proyectos.escenarios.edit', [$proyecto, $proyecto->escenarioPrincipal]),
            ],
            ! $proyecto->conceptos()->exists() => [
                'Registrar un concepto', route('ingenieria-economica.proyectos.conceptos.create', [$proyecto, 'alternativa_id' => $alternativas->first()?->id]),
            ],
            $ultimaEvaluacion === null => ['Proyectar flujos de efectivo', route('ingenieria-economica.proyectos.flujos.index', $proyecto)],
            ! $calidad['puede_validar'] => ['Revisar requisitos documentales', route('ingenieria-economica.proyectos.validacion', $proyecto)],
            default => ['Consultar informes', route('ingenieria-economica.proyectos.informes.index', $proyecto)],
        };

        return view('ingenieria-economica.proyectos.show', compact('proyecto', 'alternativas', 'escenarios', 'calidad', 'siguiente'));
    }

    public function edit(Proyecto $proyecto): View
    {
        Gate::authorize('update', $proyecto);

        return view('ingenieria-economica.proyectos.form', compact('proyecto'));
    }

    public function update(ProyectoRequest $request, Proyecto $proyecto): RedirectResponse
    {
        $proyecto->update($request->validated());

        return redirect()->route('ingenieria-economica.proyectos.show', $proyecto)->with('status', 'Proyecto actualizado.');
    }

    public function destroy(Proyecto $proyecto): RedirectResponse
    {
        Gate::authorize('delete', $proyecto);
        $listado = redirect()->route('ingenieria-economica.proyectos.index');

        try {
            $proyecto->getConnection()->transaction(fn () => $proyecto->delete());
        } catch (QueryException $error) {
            $estado = $error->errorInfo[0] ?? null;
            $detalle = $error->errorInfo[2] ?? '';
            if ($estado !== '23503' && ! ($estado === '23000' && str_contains($detalle, 'FOREIGN KEY constraint failed'))) {
                throw $error;
            }

            return $listado->withErrors(['proyecto' => 'El proyecto tiene registros vinculados. Solo puedes eliminar proyectos sin registros asociados.']);
        }

        return $listado->with('status', 'Proyecto eliminado.');
    }
}
