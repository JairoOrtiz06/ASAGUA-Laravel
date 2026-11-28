<?php

namespace App\Http\Controllers\IngenieriaEconomica;

use App\Http\Controllers\Controller;
use App\Services\IngenieriaEconomica\ContextoEvaluacion;
use App\Http\Requests\IngenieriaEconomica\EvidenciaDocumentalRequest;
use App\Models\IngenieriaEconomica\EvidenciaDocumental;
use App\Models\IngenieriaEconomica\Proyecto;
use App\Models\IngenieriaEconomica\VinculoDocumental;
use App\Services\IngenieriaEconomica\ArchivoDocumentalService;
use App\Services\IngenieriaEconomica\DependenciasEconomicas;
use App\Services\IngenieriaEconomica\ReferenciaDocumentalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class EvidenciaDocumentalController extends Controller
{
    public function index(Request $request, Proyecto $proyecto, ReferenciaDocumentalService $referencias): View
    {
        Gate::authorize('view', $proyecto);
        $filtros = $this->seleccion($request);
        $destino = isset($filtros['registro']) ? $referencias->resolver($proyecto, $filtros['tipo'], (int) $filtros['registro']) : null;
        abort_if(isset($filtros['registro']) && ! $destino, 404);
        $historica = $proyecto->evaluaciones()->latest('id');
        $contextoEvaluacion = ContextoEvaluacion::actual();
        $idVersion = $request->integer('evaluacion_fuentes') ?: $contextoEvaluacion?->historica?->id;
        $evaluacion = $idVersion ? $historica->findOrFail($idVersion) : ($contextoEvaluacion ? null : $historica->first());
        $versiones = $proyecto->evaluaciones()->select(['id', 'created_at', 'instantanea->escenario->nombre as escenario_historico'])
            ->latest('id')->simplePaginate(10, ['*'], 'versiones')->withQueryString();
        $evidencias = $proyecto->evidenciasDocumentales()->withExists(['vinculos as vinculado_al_destino' => fn ($q) => $q
            ->where('tipo', $filtros['tipo'] ?? 'proyecto')->where('destino_id', $destino['id'] ?? $proyecto->id)])
            ->when($destino, fn ($q) => $q->orderByDesc('vinculado_al_destino'))->latest('id')->paginate(20)->withQueryString();
        $previas = $referencias->fuentesPrevias($proyecto);
        $visibles = fn ($q) => $q->where(function ($destinos) use ($previas, $evaluacion) {
            $destinos->where(fn ($v) => $v->where('tipo','concepto')->whereIn('destino_id',$previas['conceptos']->pluck('id')))
                ->orWhere(fn ($v) => $v->where('tipo','activo')->whereIn('destino_id',$previas['activos']->pluck('id')))
                ->when($evaluacion, fn ($q) => $q->orWhere(fn ($v) => $v->where('tipo','evaluacion')->where('destino_id',$evaluacion->id)));
        });
        $centrales = $proyecto->evidenciasDocumentales()->select('ie_documentos_economicos.id', 'titulo', 'estado')
            ->withWhereHas('vinculos', $visibles)->get()
            ->flatMap(fn ($e) => $e->vinculos->map(fn ($v) => ['clave' => $v->tipo.':'.$v->destino_id, 'evidencia' => $e]))
            ->groupBy('clave');

        return view('ingenieria-economica.documental.evidencias', [
            'proyecto' => $proyecto, 'evidencias' => $evidencias, 'previas' => $previas,
            'contexto' => $this->contexto($request), 'filtros' => $filtros, 'destino' => $destino, 'evaluacion' => $evaluacion, 'versiones' => $versiones, 'centrales' => $centrales,
        ]);
    }

    public function create(Request $request, Proyecto $proyecto): View
    {
        Gate::authorize('update', $proyecto);

        $contexto = $this->contexto($request);

        return view('ingenieria-economica.documental.form', compact('proyecto', 'contexto'));
    }

    public function store(EvidenciaDocumentalRequest $request, Proyecto $proyecto, ArchivoDocumentalService $archivos): RedirectResponse
    {
        $adjunto = $request->hasFile('archivo') ? $archivos->subir($request->file('archivo')) : [];
        try {
            $item = DB::transaction(function () use ($request, $proyecto, $adjunto) {
                $item = $proyecto->evidenciasDocumentales()->make($request->safe()->except('archivo'));
                $item->forceFill($adjunto + ['estado' => $adjunto ? 'existente' : 'pendiente']);
                $item->usuario()->associate($request->user());
                $item->save();
                $this->evento($item, $request, 'registro', ['estado' => $item->estado, 'sha256' => $item->sha256]);

                return $item;
            });
        } catch (Throwable $error) {
            if ($adjunto) {
                $archivos->disco()->delete('evidencias/'.$adjunto['archivo']);
            }
            throw $error;
        }

        return $this->detalle($request, $proyecto, $item);
    }

    public function show(Request $request, Proyecto $proyecto, string $evidencia, ReferenciaDocumentalService $referencias, ArchivoDocumentalService $archivos): View
    {
        $item = $this->buscar($proyecto, $evidencia);
        $item->load(['usuario:id_usuario,nombre,apellido','vinculos','historial.usuario:id_usuario,nombre,apellido']);
        $datos = $item->vinculos->mapWithKeys(fn ($v) => [$v->id => $referencias->resolver($proyecto, $v->tipo, $v->destino_id)]);
        $filtros = $this->seleccion($request);
        $tipo = $filtros['tipo'] ?? null;
        $registro = $filtros['registro'] ?? null;
        if (! $tipo && is_string($request->old('tipo_vinculo')) && isset(VinculoDocumental::TIPOS[$request->old('tipo_vinculo')])) {
            $tipo = $request->old('tipo_vinculo');
            $registro = filter_var($request->old('destino_id'), FILTER_VALIDATE_INT) ?: null;
        }
        $seleccionado = $tipo && $registro ? $referencias->resolver($proyecto, $tipo, (int) $registro) : null;
        if (isset($filtros['registro'])) {
            abort_unless($seleccionado, 404);
        }
        $opciones = $tipo ? $referencias->opciones($proyecto, $tipo, $filtros['q'] ?? '')->withQueryString() : null;
        $faltaRespaldo = $referencias->faltaRespaldo($proyecto, $item, $archivos);

        $contexto = $this->contexto($request);

        return view('ingenieria-economica.documental.evidencia', compact(
            'proyecto', 'item', 'datos', 'tipo', 'filtros', 'contexto', 'seleccionado', 'opciones', 'faltaRespaldo'
        ));
    }

    private function seleccion(Request $request): array
    {
        return $request->validate([
            'tipo' => ['nullable', 'required_with:registro', Rule::in(array_keys(VinculoDocumental::TIPOS))],
            'registro' => ['nullable', 'integer', 'min:1'],
            'q' => ['nullable', 'string', 'max:100'],
            'pagina' => ['nullable', 'integer', 'between:1,100000'],
        ]);
    }

    public function download(Proyecto $proyecto, string $evidencia, ArchivoDocumentalService $archivos): StreamedResponse
    {
        $item = $this->buscar($proyecto, $evidencia);
        abort_if($item->archivo === null, 404);

        return $archivos->descargarEvidencia($item->archivo, $item->mime, $item->sha256, $item->id);
    }

    public function adjuntar(EvidenciaDocumentalRequest $request, Proyecto $proyecto, string $evidencia, ArchivoDocumentalService $archivos): RedirectResponse
    {
        $item = DB::transaction(function () use ($request, $proyecto, $evidencia, $archivos) {
            $item = $proyecto->evidenciasDocumentales()->lockForUpdate()->findOrFail($evidencia);
            if ($item->archivo !== null) {
                throw ValidationException::withMessages(['archivo' => 'Este registro ya tiene un adjunto. Registra el respaldo nuevo como otra evidencia.']);
            }
            $adjunto = $archivos->subir($request->file('archivo'));
            try {
                $anterior = $item->estado;
                $item->forceFill($adjunto + ['estado' => 'existente'])->save();
                $this->evento($item, $request, 'adjunto', ['anterior' => $anterior, 'estado' => 'existente', 'sha256' => $item->sha256]);
            } catch (Throwable $error) {
                $archivos->disco()->delete('evidencias/'.$adjunto['archivo']);
                throw $error;
            }

            return $item;
        });

        return $this->detalle($request, $proyecto, $item);
    }

    public function estado(EvidenciaDocumentalRequest $request, Proyecto $proyecto, string $evidencia, ReferenciaDocumentalService $referencias, ArchivoDocumentalService $archivos): RedirectResponse
    {
        $item = DB::transaction(function () use ($request, $proyecto, $evidencia, $referencias, $archivos) {
            $item = $proyecto->evidenciasDocumentales()->lockForUpdate()->findOrFail($evidencia);
            $estado = $request->validated('estado');
            if ($estado === 'validado') {
                if ($item->estado !== 'revisado') {
                    throw ValidationException::withMessages(['estado' => 'Falta revisión previa. Selecciona Revisado y registra qué comprobaste.']);
                }
                $item->load('vinculos');
                if ($falta = $referencias->faltaRespaldo($proyecto, $item, $archivos)) {
                    throw ValidationException::withMessages(['estado' => $falta]);
                }
            }
            $this->evento($item, $request, 'estado', ['anterior'=>$item->estado,'estado'=>$estado,'nota'=>$request->validated('nota')]);
            $item->estado = $estado;
            $item->save();

            return $item;
        });

        return $this->detalle($request, $proyecto, $item);
    }

    public function vincular(EvidenciaDocumentalRequest $request, Proyecto $proyecto, string $evidencia, ReferenciaDocumentalService $referencias): RedirectResponse
    {
        $item = DB::transaction(function () use ($request, $proyecto, $evidencia, $referencias) {
            Proyecto::query()->lockForUpdate()->findOrFail($proyecto->id);
            $item = $proyecto->evidenciasDocumentales()->lockForUpdate()->findOrFail($evidencia);
            $d = $request->validated();
            $tipo = $d['tipo_vinculo'];
            $referencia = $referencias->resolver($proyecto, $tipo, (int) $d['destino_id']);
            if (! $referencia) {
                throw ValidationException::withMessages(['destino_id' => 'La referencia no existe o no pertenece a este proyecto.']);
            }
            $v = $item->vinculos()->firstOrNew(['tipo'=>$tipo,'destino_id'=>$d['destino_id']]);
            $anterior = $v->exists ? $v->only(['conciliacion','nota']) : null;
            $v->fill([
                'etiqueta'=>mb_substr($referencia['etiqueta'], 0, 255),'nota'=>$d['nota'],
                'conciliacion'=>in_array($tipo, ReferenciaDocumentalService::CONTABLES, true) ? ($d['conciliacion'] ?? 'pendiente') : null,
            ]);
            if (! $v->exists || $v->isDirty()) {
                $v->save();
                $this->evento($item, $request, 'vinculo', ['anterior'=>$anterior] + $v->only(['tipo','destino_id','etiqueta','conciliacion','nota']));
                if ($item->estado === 'validado') {
                    $item->estado = 'revisado';
                    $item->save();
                    $this->evento($item, $request, 'estado', ['anterior'=>'validado','estado'=>'revisado','nota'=>'Cambió un vínculo documental.']);
                }
            }

            return $item;
        });

        return $this->detalle($request, $proyecto, $item);
    }

    public function desvincular(EvidenciaDocumentalRequest $request, Proyecto $proyecto, string $evidencia, string $vinculo): RedirectResponse
    {
        $item = DB::transaction(function () use ($request, $proyecto, $evidencia, $vinculo) {
            Proyecto::query()->lockForUpdate()->findOrFail($proyecto->id);
            $item = $proyecto->evidenciasDocumentales()->lockForUpdate()->findOrFail($evidencia);
            $relacion = $item->vinculos()->findOrFail($vinculo);
            $this->evento($item, $request, 'desvinculo', [
                'anterior' => $relacion->only(['tipo','destino_id','etiqueta','conciliacion','nota']),
                'nota' => $request->validated('nota'),
            ]);
            $relacion->delete();

            return $item;
        });

        return $this->detalle($request, $proyecto, $item)->with('status', 'Vínculo retirado. La evidencia y su archivo se conservan.');
    }

    public function destroy(Request $request, Proyecto $proyecto, string $evidencia, DependenciasEconomicas $dependencias, ArchivoDocumentalService $archivos): RedirectResponse
    {
        Gate::authorize('update', $proyecto);
        DB::transaction(function () use ($proyecto, $evidencia, $dependencias, $archivos): void {
            Proyecto::query()->lockForUpdate()->findOrFail($proyecto->id);
            $item = $proyecto->evidenciasDocumentales()->lockForUpdate()->findOrFail($evidencia);
            if (! in_array($item->estado, ['existente','pendiente'], true)
                || $item->vinculos()->exists() || $item->historial()->where('accion', '!=', 'registro')->where('accion', '!=', 'adjunto')->exists()
                || $dependencias->enHistorial($proyecto, 'evidencia', $item->id)) {
                throw ValidationException::withMessages(['evidencia' => 'Solo se puede eliminar una evidencia inicial sin vínculos, revisiones ni uso histórico.']);
            }
            $archivo = $item->archivo;
            $item->historial()->delete();
            $item->delete();

            if ($archivo && preg_match('/^[a-f0-9-]{36}\.(pdf|png|jpg)$/D', $archivo)) {
                DB::afterCommit(function () use ($archivo, $archivos): void {
                    if (! EvidenciaDocumental::query()->where('archivo', $archivo)->exists()) {
                        try {
                            $archivos->disco()->delete('evidencias/'.$archivo);
                        } catch (Throwable $error) {
                            report($error);
                        }
                    }
                });
            }
        });

        return redirect()->to(ContextoEvaluacion::enlace('evidencias.index', [$proyecto, ...$this->contexto($request)]))->with('status', 'Evidencia inicial eliminada.');
    }

    private function buscar(Proyecto $proyecto, string $id): EvidenciaDocumental
    {
        Gate::authorize('view',$proyecto);

        return $proyecto->evidenciasDocumentales()->findOrFail($id);
    }

    private function evento(EvidenciaDocumental $item, EvidenciaDocumentalRequest $request, string $accion, array $datos): void
    {
        $item->historial()->create(['id_usuario'=>$request->user()->id_usuario,'accion'=>$accion,'datos'=>$datos]);
    }

    private function contexto(Request $request): array
    {
        return array_filter(array_intersect_key($request->query(), array_flip(['tipo', 'registro', 'origen', 'evaluacion_fuentes'])), 'is_scalar');
    }

    private function detalle(Request $request, Proyecto $proyecto, EvidenciaDocumental $item): RedirectResponse
    {
        return redirect()->to(ContextoEvaluacion::enlace('evidencias.show', [$proyecto, $item, ...$this->contexto($request)]))->with('status', 'Expediente actualizado.');
    }
}
