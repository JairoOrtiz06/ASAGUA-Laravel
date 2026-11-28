<?php

namespace App\Http\Controllers\IngenieriaEconomica;

use App\Http\Controllers\Controller;
use App\Http\Requests\IngenieriaEconomica\InformeEconomicoRequest;
use App\Models\IngenieriaEconomica\CalculoActivo;
use App\Models\IngenieriaEconomica\Evaluacion;
use App\Models\IngenieriaEconomica\Proyecto;
use App\Services\IngenieriaEconomica\ArchivoDocumentalService;
use App\Services\IngenieriaEconomica\ContextoEvaluacion;
use App\Services\IngenieriaEconomica\CalidadProyectoService;
use App\Services\IngenieriaEconomica\GuardarEvaluacionService;
use App\Services\IngenieriaEconomica\InformeEconomicoService;
use App\Services\IngenieriaEconomica\ReferenciaDocumentalService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InformeEconomicoController extends Controller
{
    private const RUTA = 'ingenieria-economica.proyectos.informes.';

    public function index(InformeEconomicoRequest $request, Proyecto $proyecto, InformeEconomicoService $servicio): View
    {
        $evaluaciones = $proyecto->evaluaciones()
            ->select(['id','escenario_id','perspectiva','moneda','created_at','instantanea->escenario->nombre as escenario_historico','instantanea->perspectiva as perspectiva_historica'])->latest('id')->get();
        $sensibilidades = $proyecto->analisisSensibilidad()->select(['id','nombre','escenario_id','perspectiva'])->latest('id')->get();
        $calculos = CalculoActivo::query()->whereHas('activo',fn ($q)=>$q->where('proyecto_id',$proyecto->id))
            ->with('activo:id,nombre')->select(['id','activo_id','metodo'])->latest('id')->get();
        $historial = $proyecto->informesEconomicos()->select(['id','evaluacion_id','estado','folio','created_at'])->latest('id')->paginate(20)->withQueryString();
        $informe = null;
        $aviso = null;
        $d = $this->seleccion($request, $proyecto);
        if (! empty($d['evaluacion_id'])) {
            try {
                $evaluacion = $proyecto->evaluaciones()->findOrFail($d['evaluacion_id']);
                $this->aplicarVersion($request, $evaluacion, $d);
                $informe = $servicio->preparar($proyecto, $evaluacion, $d, $request->user());
            } catch (DomainException $error) {
                $aviso = $error->getMessage();
            }
        }

        return view('ingenieria-economica.documental.resultados',compact('proyecto','evaluaciones','sensibilidades','calculos','historial','informe','aviso','d'));
    }

    public function calidad(InformeEconomicoRequest $request, Proyecto $proyecto, InformeEconomicoService $servicio, CalidadProyectoService $proyectoCalidad): View
    {
        $d = $this->seleccion($request, $proyecto);
        $evaluacion = isset($d['evaluacion_id']) ? $proyecto->evaluaciones()->findOrFail($d['evaluacion_id']) : null;
        $calidad = $aviso = null;
        if ($evaluacion) {
            $this->aplicarVersion($request, $evaluacion, $d);
            try {
                $calidad = $servicio->revisarAlcance($proyecto, $evaluacion, $d)['calidad'];
            } catch (DomainException $error) {
                $aviso = $error->getMessage();
            }
        }

        if (! $evaluacion && ! ContextoEvaluacion::actual() && empty($d['sensibilidad_id']) && empty($d['calculos'])) {
            $calidad = $proyectoCalidad->revisar($proyecto);
        }

        return view('ingenieria-economica.documental.calidad', compact('proyecto', 'evaluacion', 'calidad', 'aviso', 'd'));
    }

    private function seleccion(InformeEconomicoRequest $request, Proyecto $proyecto): array
    {
        $d = $request->validated();
        $contexto = ContextoEvaluacion::actual();
        if (! $request->boolean('alcance_aplicado')) {
            $d += $contexto?->complementos ?? [];
        }
        if (! isset($d['evaluacion_id']) && ($evaluacion = $contexto?->evaluacionCompatible($proyecto))) {
            $d['evaluacion_id'] = $evaluacion->id;
        }

        return $d;
    }

    private function aplicarVersion(\Illuminate\Http\Request $request, Evaluacion $evaluacion, array $datos): void
    {
        $request->attributes->set(ContextoEvaluacion::class, new ContextoEvaluacion(
            ContextoEvaluacion::desdeInstantanea($evaluacion->instantanea),
            null,
            $evaluacion,
            array_intersect_key($datos, ['sensibilidad_id' => true, 'calculos' => true]),
        ));
    }

    public function referencias(Proyecto $proyecto, ReferenciaDocumentalService $servicio): View
    {
        Gate::authorize('view',$proyecto);
        $items = $proyecto->evidenciasDocumentales()->with(['vinculos'=>fn ($q)=>$q->whereIn('tipo',ReferenciaDocumentalService::CONTABLES)])
            ->whereHas('vinculos',fn ($q)=>$q->whereIn('tipo',ReferenciaDocumentalService::CONTABLES))->latest('id')->paginate(20);
        $referencias = $items->getCollection()->flatMap->vinculos
            ->mapWithKeys(fn ($v)=>[$v->id=>$servicio->resolver($proyecto,$v->tipo,$v->destino_id)]);
        $previas = $servicio->fuentesPrevias($proyecto, contables: true);

        return view('ingenieria-economica.documental.referencias',compact('proyecto','items','referencias','previas'));
    }

    public function preview(InformeEconomicoRequest $request, Proyecto $proyecto, InformeEconomicoService $servicio, GuardarEvaluacionService $firmas): Response|RedirectResponse
    {
        Gate::authorize('update',$proyecto);
        $d = $request->safe()->except('firma');
        try {
            $informe = $this->preparar($proyecto,$servicio,$request,$d);
        } catch (DomainException $error) {
            return $this->error($proyecto,$d,$error);
        }
        $evaluacion = $proyecto->evaluaciones()->findOrFail($d['evaluacion_id']);
        $this->aplicarVersion($request, $evaluacion, $d);
        $firma = $firmas->firma($informe,$request->user());
        $guardado = null;

        return response()->view('ingenieria-economica.documental.informe',compact('proyecto','informe','firma','d','guardado'))
            ->header('Cache-Control','private, no-store');
    }

    public function store(InformeEconomicoRequest $request, Proyecto $proyecto, InformeEconomicoService $servicio, GuardarEvaluacionService $firmas): RedirectResponse
    {
        Gate::authorize('update',$proyecto);
        $d = $request->safe()->except('firma');
        try {
            $guardado = DB::transaction(function () use ($proyecto, $servicio, $request, $d, $firmas) {
                Proyecto::query()->lockForUpdate()->findOrFail($proyecto->id);
                $informe = $this->preparar($proyecto,$servicio,$request,$d);
                if (! hash_equals($firmas->firma($informe,$request->user()),$request->validated('firma'))) {
                    throw new DomainException('Cambió algún dato o evidencia desde la vista previa. Genera otra antes de guardar.');
                }

                return $servicio->guardar($proyecto,$informe);
            });
        } catch (DomainException $error) {
            return $this->error($proyecto,$d,$error);
        }

        return redirect()->route(self::RUTA.'show',[$proyecto,$guardado])->with('status','Versión y PDF guardados.');
    }

    public function show(Proyecto $proyecto, string $informe): View
    {
        Gate::authorize('view',$proyecto);
        $guardado = $proyecto->informesEconomicos()->findOrFail($informe);
        $informe = $guardado->instantanea;
        $evaluacion = $proyecto->evaluaciones()->findOrFail($guardado->evaluacion_id);
        $seleccion = array_filter([
            'sensibilidad_id' => $informe['sensibilidad']['id'] ?? null,
            'calculos' => array_column($informe['calculos'] ?? [], 'id'),
        ]);
        $this->aplicarVersion(request(), $evaluacion, $seleccion);
        $posteriores = $proyecto->evidenciasDocumentales()
            ->whereHas('vinculos',fn ($q)=>$q->where('tipo','informe')->where('destino_id',$guardado->id))
            ->get(['id','titulo','estado']);

        return view('ingenieria-economica.documental.informe',compact('proyecto','guardado','informe','posteriores'));
    }

    public function download(Proyecto $proyecto, string $informe, ArchivoDocumentalService $archivos): StreamedResponse
    {
        Gate::authorize('view',$proyecto);
        $version = $proyecto->informesEconomicos()->select(['id','folio','sha256'])->findOrFail($informe);

        return $archivos->descargarInforme($version->folio,$version->sha256);
    }

    private function preparar(Proyecto $p, InformeEconomicoService $servicio, InformeEconomicoRequest $request, array $d): array
    {
        return $servicio->preparar($p,$p->evaluaciones()->findOrFail($d['evaluacion_id']),$d,$request->user());
    }

    private function error(Proyecto $p, array $d, DomainException $error): RedirectResponse
    {
        return redirect()->to(ContextoEvaluacion::enlace('informes.index', [$p, ...array_intersect_key($d, ['evaluacion_id' => true, 'sensibilidad_id' => true, 'calculos' => true])]))
            ->withErrors(['informe'=>$error->getMessage()])->withInput($d);
    }
}
