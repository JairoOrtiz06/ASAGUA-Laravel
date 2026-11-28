<?php

namespace App\Http\Controllers\IngenieriaEconomica;

use App\Http\Controllers\Controller;
use App\Services\IngenieriaEconomica\ContextoEvaluacion;
use App\Http\Requests\IngenieriaEconomica\ConceptoRequest;
use App\Http\Requests\IngenieriaEconomica\ConsultaConceptosRequest;
use App\Models\IngenieriaEconomica\Concepto;
use App\Models\IngenieriaEconomica\Proyecto;
use App\Services\IngenieriaEconomica\ProyeccionFlujosService;
use App\Services\IngenieriaEconomica\DependenciasEconomicas;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ConceptoController extends Controller
{
    public function index(ConsultaConceptosRequest $request, Proyecto $proyecto): View
    {
        $filtros = $request->validated();
        $alternativas = $proyecto->alternativas()->orderBy('nombre')->pluck('nombre', 'id');
        $conceptos = $proyecto->conceptos()->where('alternativa_id', $filtros['alternativa_id'] ?? null);
        foreach (['tipo_concepto', 'estado_dato'] as $campo) {
            if (isset($filtros[$campo])) {
                $conceptos->where($campo, $filtros[$campo]);
            }
        }

        return view('ingenieria-economica.conceptos.index', [
            'proyecto' => $proyecto,
            'alternativas' => $alternativas,
            'filtros' => $filtros,
            'conceptos' => $conceptos->orderBy('nombre')->orderBy('id')->paginate(20)->withQueryString(),
        ]);
    }

    public function create(ConsultaConceptosRequest $request, Proyecto $proyecto): View
    {
        Gate::authorize('update', $proyecto);
        $alternativa = $request->validated('alternativa_id');
        $concepto = new Concepto([
            'moneda' => ContextoEvaluacion::actual()?->datos['moneda'] ?? 'USD', 'periodicidad' => 'unico', 'momento_pago' => 'inicio',
            'estado_dato' => 'provisional',
            'clasificacion_decision' => $alternativa === null ? 'comun' : 'pendiente_clasificacion',
        ]);
        $concepto->alternativa_id = $alternativa;

        return $this->formulario($proyecto, $concepto);
    }

    public function previsualizar(ConceptoRequest $request, Proyecto $proyecto, ProyeccionFlujosService $proyector): JsonResponse
    {
        $escenario = $proyecto->escenarios()->findOrFail($request->validated('escenario_calendario'));
        try {
            $resultado = $proyector->previsualizarConcepto($proyecto, $escenario, new Concepto($request->datosConcepto()));
        } catch (DomainException $error) {
            throw ValidationException::withMessages(['escenario_calendario' => $error->getMessage()]);
        }

        return response()->json($resultado);
    }

    public function previsualizarEdicion(ConceptoRequest $request, Proyecto $proyecto, Concepto $concepto, ProyeccionFlujosService $proyector): JsonResponse
    {
        return $this->previsualizar($request, $proyecto, $proyector);
    }

    public function store(ConceptoRequest $request, Proyecto $proyecto): RedirectResponse
    {
        $concepto = $proyecto->conceptos()->make($request->datosConcepto());
        $concepto->alternativa_id = $request->validated('alternativa_id');
        $concepto->save();

        return $this->detalle($proyecto, $concepto, 'Concepto registrado.');
    }

    public function show(Proyecto $proyecto, Concepto $concepto): View
    {
        Gate::authorize('view', $proyecto);
        $concepto->load([
            'alternativa:id,nombre',
            'fuentes' => fn ($query) => $query->latest('id'),
            'fuentes.documento:id_documento,nombre_documento',
            'fuentes.detalle:id_detalle,id_partida',
            'fuentes.detalle.partida:id_partida,numero_partida',
        ]);

        return view('ingenieria-economica.conceptos.show', compact('proyecto', 'concepto'));
    }

    public function edit(Proyecto $proyecto, Concepto $concepto): View
    {
        Gate::authorize('update', $proyecto);

        return $this->formulario($proyecto, $concepto);
    }

    public function update(ConceptoRequest $request, Proyecto $proyecto, Concepto $concepto): RedirectResponse
    {
        $concepto->update($request->datosConcepto());

        return $this->detalle($proyecto, $concepto, 'Concepto actualizado.');
    }

    public function destroy(Proyecto $proyecto, Concepto $concepto, DependenciasEconomicas $dependencias): RedirectResponse
    {
        Gate::authorize('update', $proyecto);

        DB::transaction(function () use ($proyecto, $concepto, $dependencias): void {
            Proyecto::query()->lockForUpdate()->findOrFail($proyecto->id);
            $actual = $proyecto->conceptos()->lockForUpdate()->findOrFail($concepto->id);
            $motivo = match (true) {
                $actual->estado_dato === 'confirmado' => 'El concepto está confirmado. Puedes corregir sus datos desde Editar.',
                $actual->fuentes()->exists() => 'El concepto tiene fuentes. Revisa las referencias en su detalle.',
                $dependencias->vinculado($proyecto, 'concepto', $actual->id) => 'El concepto tiene evidencias vinculadas. Revisa los vínculos desde Respaldo documental.',
                $dependencias->enHistorial($proyecto, 'concepto', $actual->id) => 'El concepto figura en una evaluación o simulación histórica y se conserva.',
                default => null,
            };
            if ($motivo) {
                throw ValidationException::withMessages(['concepto' => $motivo]);
            }
            $actual->delete();
        });

        return redirect()->to(ContextoEvaluacion::enlace('conceptos.index', [
            'proyecto' => $proyecto, 'alternativa_id' => $concepto->alternativa_id,
        ]))->with('status', 'Concepto eliminado.');
    }

    private function formulario(Proyecto $proyecto, Concepto $concepto): View
    {
        $concepto->load('alternativa:id,nombre');

        return view('ingenieria-economica.conceptos.form', [
            'proyecto' => $proyecto, 'concepto' => $concepto,
            'alternativas' => $proyecto->alternativas()->orderBy('nombre')->pluck('nombre', 'id'),
            'escenarios' => $proyecto->escenarios()->orderByDesc('es_principal')->orderBy('nombre')->pluck('nombre', 'id'),
        ]);
    }

    private function detalle(Proyecto $proyecto, Concepto $concepto, string $mensaje): RedirectResponse
    {
        return redirect()->to(ContextoEvaluacion::enlace('conceptos.show', [$proyecto, $concepto]))->with('status', $mensaje);
    }
}
