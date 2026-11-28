<?php

namespace App\Http\Controllers\IngenieriaEconomica;

use App\Http\Controllers\Controller;
use App\Http\Requests\IngenieriaEconomica\ProyectoRequest;
use App\Models\IngenieriaEconomica\Proyecto;
use App\Services\IngenieriaEconomica\ContextoEvaluacion;
use App\Services\IngenieriaEconomica\ProgresoEvaluacionService;
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

        $consulta = $request->query('buscar');
        $busqueda = is_string($consulta) ? trim($consulta) : '';
        $proyectos = Proyecto::query()->whereBelongsTo($request->user(), 'usuario');

        if ($busqueda !== '') {
            $nombre = strtr(mb_strtolower($busqueda), ['!' => '!!', '%' => '!%', '_' => '!_']);
            $proyectos->whereRaw("LOWER(nombre) LIKE ? ESCAPE '!'", ['%'.$nombre.'%']);
        }

        $proyectos = $proyectos->latest('id')->paginate(15)->appends(['buscar' => $busqueda !== '' ? $busqueda : null]);

        return view('ingenieria-economica.proyectos.index', compact('proyectos', 'busqueda'));
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

    public function show(Proyecto $proyecto, ProgresoEvaluacionService $servicio): View
    {
        Gate::authorize('view', $proyecto);

        $proyecto->load('escenarioPrincipal')->loadCount(['alternativas', 'escenarios']);
        $contexto = ContextoEvaluacion::actual();
        $progreso = $servicio->revisar($proyecto, $contexto);
        $hayInformes = ! $contexto && $proyecto->informesEconomicos()->exists();

        return view('ingenieria-economica.proyectos.show', compact('proyecto', 'contexto', 'progreso', 'hayInformes'));
    }

    public function edit(Proyecto $proyecto): View
    {
        Gate::authorize('update', $proyecto);

        return view('ingenieria-economica.proyectos.form', compact('proyecto'));
    }

    public function update(ProyectoRequest $request, Proyecto $proyecto): RedirectResponse
    {
        $proyecto->update($request->validated());

        return redirect()->to(ContextoEvaluacion::enlace('show', $proyecto))->with('status', 'Proyecto actualizado.');
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
