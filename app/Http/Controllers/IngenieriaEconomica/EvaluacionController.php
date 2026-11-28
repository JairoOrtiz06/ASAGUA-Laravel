<?php

namespace App\Http\Controllers\IngenieriaEconomica;

use App\Http\Controllers\Controller;
use App\Http\Requests\IngenieriaEconomica\EvaluacionRequest;
use App\Models\IngenieriaEconomica\Proyecto;
use App\Services\IngenieriaEconomica\ComparacionIncrementalService;
use App\Services\IngenieriaEconomica\EvaluacionService;
use App\Services\IngenieriaEconomica\ContextoEvaluacion;
use App\Services\IngenieriaEconomica\GuardarEvaluacionService;
use App\Services\IngenieriaEconomica\MemoriaCalculoService;
use App\Services\IngenieriaEconomica\PreferenciaEvaluacion;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class EvaluacionController extends Controller
{
    public function index(Proyecto $proyecto): View
    {
        Gate::authorize('view', $proyecto);

        return view('ingenieria-economica.evaluaciones.index', [
            'proyecto' => $proyecto,
            'escenarios' => $proyecto->escenarios()->orderByDesc('es_principal')->orderBy('nombre')->get(),
            'evidenciasFiscales' => $proyecto->evidenciasDocumentales()->where('estado', 'validado')->where('calidad', 'documentado')->orderBy('titulo')->pluck('titulo', 'id'),
            'activosFiscales' => $proyecto->activos()->orderBy('nombre')->get(['id', 'nombre', 'alternativa_id']),
            'alternativas' => $proyecto->alternativas()->orderBy('nombre')->orderBy('id')->get(),
            'evaluaciones' => $proyecto->evaluaciones()->select(['id', 'proyecto_id', 'perspectiva', 'moneda', 'version_motor', 'created_at', 'instantanea->perspectiva as perspectiva_historica'])->latest('id')->paginate(15)->withQueryString(),
        ]);
    }

    public function seleccion(Request $request, Proyecto $proyecto, PreferenciaEvaluacion $preferencia): RedirectResponse
    {
        Gate::authorize('update', $proyecto);
        $contexto = ContextoEvaluacion::resolver($proyecto, $request->only(ContextoEvaluacion::CAMPOS));
        $preferencia->guardar($request->user(), $proyecto, $contexto);

        return redirect()->route(ContextoEvaluacion::RUTA.'show', [$proyecto, ...$contexto->consulta()]);
    }

    public function preview(EvaluacionRequest $request, Proyecto $proyecto, EvaluacionService $servicio, GuardarEvaluacionService $guardar, MemoriaCalculoService $memoria, ComparacionIncrementalService $comparacion): View
    {
        $datos = $request->safe()->except('firma');
        $escenario = $proyecto->escenarios()->findOrFail($datos['escenario_id']);
        try {
            $resultado = $servicio->preparar($proyecto, $escenario, $datos);
        } catch (DomainException $error) {
            throw ValidationException::withMessages(['evaluacion' => $error->getMessage()]);
        }

        $request->attributes->set(ContextoEvaluacion::class, new ContextoEvaluacion(ContextoEvaluacion::desdeInstantanea($resultado), $escenario, null));
        $firma = $guardar->firma($resultado, $request->user());
        $evaluacion = null;

        $interpretacion = $comparacion->interpretar($resultado);

        return view('ingenieria-economica.evaluaciones.show', compact('proyecto', 'resultado', 'firma', 'datos', 'evaluacion', 'memoria', 'interpretacion'));
    }

    public function store(EvaluacionRequest $request, Proyecto $proyecto, EvaluacionService $servicio, GuardarEvaluacionService $guardar): RedirectResponse
    {
        $datos = $request->safe()->except('firma');
        try {
            $evaluacion = DB::transaction(function () use ($proyecto, $datos, $servicio, $guardar, $request) {
                Proyecto::query()->lockForUpdate()->findOrFail($proyecto->id);
                $escenario = $proyecto->escenarios()->findOrFail($datos['escenario_id']);
                $resultado = $servicio->preparar($proyecto, $escenario, $datos);

                return $guardar->guardar($proyecto, $escenario, $request->user(), $resultado, $request->validated('firma'));
            });
        } catch (DomainException $error) {
            return redirect()->to(ContextoEvaluacion::enlace('evaluaciones.index', $proyecto))
                ->withErrors(['evaluacion' => $error->getMessage()])->withInput($datos);
        }

        return redirect()->route(ContextoEvaluacion::RUTA.'evaluaciones.show', [$proyecto, $evaluacion])
            ->with('status', 'Evaluación guardada como una nueva versión.');
    }

    public function show(Proyecto $proyecto, string $evaluacion, MemoriaCalculoService $memoria, ComparacionIncrementalService $comparacion): View
    {
        Gate::authorize('view', $proyecto);
        $evaluacion = $proyecto->evaluaciones()->findOrFail($evaluacion);
        $resultado = $evaluacion->instantanea;
        $anterior = ContextoEvaluacion::actual();
        $complementos = $anterior?->historica?->id === $evaluacion->id ? $anterior->complementos : [];
        $contexto = new ContextoEvaluacion(ContextoEvaluacion::desdeInstantanea($resultado), null, $evaluacion, $complementos);
        request()->attributes->set(ContextoEvaluacion::class, $contexto);

        $interpretacion = $comparacion->interpretar($resultado);

        return view('ingenieria-economica.evaluaciones.show', compact('proyecto', 'evaluacion', 'resultado', 'memoria', 'interpretacion'));
    }
}
