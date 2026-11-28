<?php

namespace App\Http\Controllers\IngenieriaEconomica;

use App\Http\Controllers\Controller;
use App\Http\Requests\IngenieriaEconomica\SensibilidadRequest;
use App\Models\IngenieriaEconomica\Proyecto;
use App\Services\IngenieriaEconomica\BaseSensibilidadService;
use App\Services\IngenieriaEconomica\ContextoEvaluacion;
use App\Services\IngenieriaEconomica\GuardarEvaluacionService;
use App\Services\IngenieriaEconomica\SensibilidadService;
use App\Services\IngenieriaEconomica\VariablesSensibilidadService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SensibilidadController extends Controller
{
    public function index(Proyecto $proyecto): View
    {
        Gate::authorize('view', $proyecto);
        $analisis = $proyecto->analisisSensibilidad()->select(['id', 'nombre', 'estado', 'perspectiva', 'created_at'])->latest('id')->paginate(20)->withQueryString();

        return view('ingenieria-economica.sensibilidad.index', compact('proyecto', 'analisis'));
    }

    public function create(Request $request, Proyecto $proyecto, BaseSensibilidadService $bases, VariablesSensibilidadService $variables): View
    {
        Gate::authorize('update', $proyecto);
        $escenarios = $proyecto->escenarios()->orderByDesc('es_principal')->orderBy('id')->get();
        $evaluaciones = $proyecto->evaluaciones()->where('instantanea->perspectiva', '!=', 'fiscal')->select(['id', 'escenario_id', 'perspectiva', 'moneda', 'created_at'])->latest('id')->get();
        $contexto = ContextoEvaluacion::actual();
        $datos = $contexto?->datos ?? [];
        $historial = $request->query->has('evaluacion_id') ? $request->integer('evaluacion_id') : $contexto?->historica?->id;
        $evaluacion = $historial ? $proyecto->evaluaciones()->findOrFail($historial) : null;
        $id = $request->integer('escenario_id') ?: ($datos['escenario_id'] ?? $escenarios->first()?->id);
        abort_if($id && ! $escenarios->contains('id', $id), 404);
        $escenario = $escenarios->find($evaluacion?->escenario_id ?? $id);
        $monedaSolicitada = $request->query('moneda', $datos['moneda'] ?? 'USD');
        $moneda = $evaluacion?->moneda ?? (is_string($monedaSolicitada) && preg_match('/^[A-Z]{3}$/D', $monedaSolicitada) ? $monedaSolicitada : 'USD');
        $alternativas = $evaluacion
            ? array_map(fn ($a) => $a['nombre'], $evaluacion->instantanea['alternativas'])
            : $proyecto->alternativas()->orderBy('id')->pluck('nombre', 'id')->all();
        $seleccionContextual = $evaluacion ? array_keys($alternativas) : ($datos['alternativas'] ?? []);
        $baseSeleccionada = $evaluacion ? ContextoEvaluacion::desdeInstantanea($evaluacion->instantanea) : $datos;
        $catalogo = [];
        $aviso = null;
        if ($escenario && $alternativas) {
            try {
                $ids = array_keys($alternativas);
                $base = $bases->capturar($proyecto, $escenario, $evaluacion, $ids, $moneda);
                $catalogo = $variables->catalogo($base, $ids, $moneda);
            } catch (DomainException $e) {
                $aviso = $e->getMessage();
            }
        }

        return view('ingenieria-economica.sensibilidad.form', compact(
            'proyecto', 'escenarios', 'evaluaciones', 'evaluacion', 'escenario', 'moneda', 'alternativas', 'catalogo', 'aviso', 'seleccionContextual', 'baseSeleccionada',
        ));
    }

    public function preview(SensibilidadRequest $request, Proyecto $proyecto, BaseSensibilidadService $bases, SensibilidadService $servicio, GuardarEvaluacionService $guardar): View|RedirectResponse
    {
        $d = $request->safe()->except(['firma', 'memoria']);
        try {
            $r = $this->generar($d, $proyecto, $bases, $servicio);
            $indice = $request->validated('memoria');
            $memoria = $indice === null ? $r['base'] : $servicio->memoria($r, (int) $indice);
        } catch (DomainException $error) {
            return $this->error($proyecto, $d, $error);
        }
        $guardado = null;
        $firma = $guardar->firma($r, $request->user());

        return view('ingenieria-economica.sensibilidad.resultado', compact('proyecto', 'r', 'guardado', 'firma', 'memoria', 'indice'));
    }

    public function store(SensibilidadRequest $request, Proyecto $proyecto, BaseSensibilidadService $bases, SensibilidadService $servicio, GuardarEvaluacionService $guardar): RedirectResponse
    {
        $d = $request->safe()->except(['firma', 'memoria']);
        try {
            $analisis = DB::transaction(function () use ($proyecto, $d, $bases, $servicio, $guardar, $request) {
                Proyecto::query()->lockForUpdate()->findOrFail($proyecto->id);
                $r = $this->generar($d, $proyecto, $bases, $servicio);
                if (! hash_equals($guardar->firma($r, $request->user()), $request->validated('firma'))) {
                    throw new DomainException('Cambió algún dato desde la vista previa. Genera una nueva simulación antes de guardar.');
                }

                $analisis = $proyecto->analisisSensibilidad()->make([
                    'nombre' => $d['nombre'], 'descripcion' => $d['descripcion'] ?? null, 'estado' => $d['estado'],
                    'perspectiva' => $d['perspectiva'], 'version_motor' => $r['version'], 'instantanea' => $r,
                ]);
                $analisis->escenario_id = $d['escenario_id'];
                $analisis->evaluacion_id = $d['evaluacion_id'] ?? null;
                $analisis->usuario()->associate($request->user());
                $analisis->save();

                return $analisis;
            });
        } catch (DomainException $error) {
            return $this->error($proyecto, $d, $error);
        }

        return redirect()->to(ContextoEvaluacion::enlace('sensibilidad.show', [$proyecto, $analisis]))->with('status', 'Análisis guardado como una nueva versión.');
    }

    public function show(Request $request, Proyecto $proyecto, string $analisis, SensibilidadService $servicio): View
    {
        Gate::authorize('view', $proyecto);
        $guardado = $proyecto->analisisSensibilidad()->findOrFail($analisis);
        $r = $guardado->instantanea;
        $indice = $request->has('memoria') ? $request->integer('memoria', -1) : null;
        $memoria = $r['base'];
        $avisoMemoria = null;
        if ($indice !== null) {
            try {
                $memoria = $servicio->memoria($r, $indice);
            } catch (DomainException $error) {
                $avisoMemoria = $error->getMessage();
            }
        }

        return view('ingenieria-economica.sensibilidad.resultado', compact('proyecto', 'guardado', 'r', 'memoria', 'indice', 'avisoMemoria'));
    }

    private function generar(array $d, Proyecto $proyecto, BaseSensibilidadService $bases, SensibilidadService $servicio): array
    {
        $escenario = $proyecto->escenarios()->findOrFail($d['escenario_id']);
        $evaluacion = empty($d['evaluacion_id']) ? null : $proyecto->evaluaciones()->findOrFail($d['evaluacion_id']);
        $base = $bases->capturar($proyecto, $escenario, $evaluacion, array_map('intval', $d['alternativas']), $d['moneda']);

        return $servicio->generar($base, $d);
    }

    private function error(Proyecto $proyecto, array $d, DomainException $error): RedirectResponse
    {
        return redirect()->to(ContextoEvaluacion::enlace('sensibilidad.create', ['proyecto' => $proyecto]
            + array_intersect_key($d, ['escenario_id' => true, 'evaluacion_id' => true, 'moneda' => true])))
            ->withErrors(['sensibilidad' => $error->getMessage()])->withInput($d);
    }
}
