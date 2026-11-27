<?php

namespace App\Services\IngenieriaEconomica;

use App\Models\IngenieriaEconomica\CalculoActivo;
use App\Models\IngenieriaEconomica\Evaluacion;
use App\Models\IngenieriaEconomica\InformeEconomico;
use App\Models\IngenieriaEconomica\Proyecto;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Brick\Math\BigDecimal;
use DomainException;
use Illuminate\Support\Str;
use Throwable;

class InformeEconomicoService
{
    public function __construct(
        private CalidadProyectoService $calidad,
        private ReferenciaDocumentalService $referencias,
        private ArchivoDocumentalService $archivos,
        private ComparacionIncrementalService $comparacion,
    ) {}

    public function preparar(Proyecto $p, Evaluacion $evaluacion, array $d, User $usuario): array
    {
        $r = $evaluacion->instantanea;
        unset($r['insumos']);
        $ids = array_keys($r['alternativas']);
        $sensibilidad = $this->sensibilidad($p,$evaluacion,$r,$d['sensibilidad_id'] ?? null);
        $calculos = $this->calculos($p,$ids,$d['calculos'] ?? []);
        $calidad = $this->calidad->revisar($p,$evaluacion,$sensibilidad,$calculos);
        $estado = $d['estado'] ?? 'preliminar';

        return [
            'condicion_fiscal' => isset($r['fiscal']) ? null : 'La evaluación guardada corresponde a una perspectiva sin cálculo tributario. '
                .EvaluacionFiscalService::PENDIENTE.' si se requiere para este caso.',
            'estado'=>$estado,'evaluacion_id'=>$evaluacion->id,'evaluacion_fecha'=>$evaluacion->created_at->toIso8601String(),
            'evaluacion'=>$r,'descripcion_problema'=>$p->descripcion,
            'sensibilidad'=>$sensibilidad,'calculos'=>$calculos,'calidad'=>$calidad,
            'evidencias'=>$this->evidencias($p,$evaluacion,$r,$sensibilidad,$calculos),
            'resumen'=>$this->resumen($r),
            'responsable'=>['id'=>$usuario->id_usuario,'nombre'=>trim($usuario->nombre.' '.$usuario->apellido)],
            'estudiantes'=>$d['estudiantes'] ?? null,'docentes'=>$d['docentes'] ?? null,'comentarios'=>$d['comentarios'] ?? null,
        ];
    }

    public function guardar(Proyecto $p, array $snapshot): InformeEconomico
    {
        if ($snapshot['estado'] === 'validado' && ! $snapshot['calidad']['puede_validar']) {
            throw new DomainException('El informe aún tiene requisitos críticos pendientes. Puedes guardar un informe preliminar.');
        }
        $snapshot['folio'] = (string) Str::uuid();
        $snapshot['fecha_emision'] = now()->toIso8601String();
        $pdf = $this->pdf($snapshot);
        $ruta = 'informes/'.$snapshot['folio'].'.pdf';
        $this->archivos->disco()->put($ruta,$pdf);
        try {
            return $p->informesEconomicos()->create([
                'evaluacion_id'=>$snapshot['evaluacion_id'],'id_usuario'=>$snapshot['responsable']['id'],
                'estado'=>$snapshot['estado'],'folio'=>$snapshot['folio'],'sha256'=>hash('sha256',$pdf),'instantanea'=>$snapshot,
            ]);
        } catch (Throwable $error) {
            $this->archivos->disco()->delete($ruta);
            throw $error;
        }
    }

    public function pdf(array $informe): string
    {
        $pdf = Pdf::setOption(['isRemoteEnabled'=>false,'isPhpEnabled'=>false,'isJavascriptEnabled'=>false])
            ->loadView('ingenieria-economica.documental.pdf',compact('informe'))->setPaper('letter');
        $pdf->render();
        $dompdf = $pdf->getDomPDF();
        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont('DejaVu Sans');
        $canvas->page_text(440,765,'Página {PAGE_NUM} de {PAGE_COUNT}',$font,8,[0.21,0.29,0.35]);

        return $pdf->output();
    }

    private function sensibilidad(Proyecto $p, Evaluacion $evaluacion, array $r, ?int $id): ?array
    {
        if (! $id) return null;
        $s = $p->analisisSensibilidad()->findOrFail($id);
        $datos = $s->instantanea;
        $base = $datos['base'];
        $e = $r['escenario'];
        if ((int) $s->escenario_id !== (int) $evaluacion->escenario_id || $s->perspectiva !== $r['perspectiva']
            || $base['moneda'] !== $r['moneda']
            || array_diff_key($r['alternativas'],$base['alternativas']) || array_diff_key($base['alternativas'],$r['alternativas'])
            || $base['escenario']['periodicidad'] !== $e['periodicidad'] || $base['escenario']['base'] !== $e['base']
            || $base['escenario']['horizonte'] !== $e['horizonte'] || ! BigDecimal::of($base['escenario']['anual'])->isEqualTo($e['anual'])) {
            throw new DomainException('La sensibilidad debe coincidir con la base de la evaluación: escenario, alternativas, perspectiva, moneda y periodicidad.');
        }
        foreach ($r['alternativas'] as $aid => $a) {
            foreach (['van','vpc','caue'] as $k) {
                if (! BigDecimal::of($a['indicadores'][$k])->isEqualTo($base['alternativas'][$aid]['indicadores'][$k])) {
                    throw new DomainException('Los indicadores de la base de sensibilidad difieren de la evaluación seleccionada.');
                }
            }
        }

        return [
            'id'=>$s->id,'nombre'=>$s->nombre,'estado'=>$s->estado,'version'=>$s->version_motor,
            'datos'=>array_intersect_key($datos,array_flip(['configuracion','simulaciones','ejes','equilibrio','cruces_matriz','advertencias'])),
        ];
    }

    private function calculos(Proyecto $p, array $alternativas, array $seleccion): array
    {
        $ids = array_unique(array_map('intval',$seleccion));
        $calculos = CalculoActivo::query()->whereKey($ids)->whereHas('activo',fn ($q) => $q
            ->where('proyecto_id',$p->id)->where(fn ($q) => $q->whereNull('alternativa_id')->orWhereIn('alternativa_id',$alternativas)))
            ->orderBy('id')->get();
        if (count($ids) !== $calculos->count()) {
            throw new DomainException('Selecciona cálculos de activos pertenecientes al alcance de la evaluación.');
        }

        return $calculos->map(fn ($c) => ['id'=>$c->id,'activo_id'=>$c->activo_id,'metodo'=>$c->metodo,
            'fecha'=>$c->created_at->toIso8601String(),'version'=>$c->version_motor,'datos'=>$c->instantanea])->all();
    }

    private function evidencias(Proyecto $p, Evaluacion $evaluacion, array $r, ?array $sensibilidad, array $calculos): array
    {
        $conceptos = [];
        foreach (array_keys($r['origenes']) as $clave) {
            if (preg_match('/^c[1-9]\d*$/D',$clave)) $conceptos[] = (int) substr($clave,1);
        }
        $objetivos = [
            'proyecto'=>[$p->id], 'escenario'=>[$evaluacion->escenario_id], 'evaluacion'=>[$evaluacion->id],
            'alternativa'=>array_keys($r['alternativas']), 'concepto'=>$conceptos,
            'sensibilidad'=>$sensibilidad?[$sensibilidad['id']]:[],
            'activo'=>array_column($calculos,'activo_id'),
        ];
        $aplica = fn ($v) => in_array((int) $v->destino_id, array_map('intval',$objetivos[$v->tipo] ?? []),true);

        return $p->evidenciasDocumentales()->with(['vinculos'=>fn ($q)=>$q->orderBy('id')])->orderBy('id')->get()
            ->filter(fn ($doc)=>$doc->vinculos->contains($aplica))->values()->map(function ($doc) use ($p,$aplica) {
                return [
                    'id'=>$doc->id,'titulo'=>$doc->titulo,'tipo'=>$doc->tipo,'calidad'=>$doc->calidad,
                    'fecha'=>$doc->fecha_documento->toDateString(),'estado'=>$doc->estado,'procedencia'=>$doc->procedencia,
                    'observaciones'=>$doc->observaciones,'sha256'=>$doc->sha256,'responsable'=>$doc->id_usuario,
                    'vinculos'=>$doc->vinculos->filter(fn ($v)=>$aplica($v)||in_array($v->tipo,ReferenciaDocumentalService::CONTABLES,true))
                        ->values()->map(fn ($v)=>$v->only(['tipo','destino_id','etiqueta','conciliacion','nota']) + [
                            'referencia'=>in_array($v->tipo,ReferenciaDocumentalService::CONTABLES,true)
                                ? $this->referencias->resolver($p,$v->tipo,$v->destino_id) : null,
                        ])->all(),
                ];
            })->all();
    }

    private function resumen(array $r): array
    {
        $resultados = [];
        foreach ($r['alternativas'] as $id=>$a) {
            $inversion = $recurrente = $oportunidad = BigDecimal::zero();
            foreach ($a['periodos'] as $periodo) {
                foreach ($periodo['movimientos'] as $m) {
                    if ($m['motivo'] !== null || $m['direccion'] !== 'salida') continue;
                    $importe = BigDecimal::of($m['monto']);
                    if (in_array($m['tipo'],['inversion_inicial','inversion_adicional'],true)) $inversion = $inversion->plus($importe);
                    if ($m['naturaleza'] === 'desembolso_real' && ($r['origenes'][$m['origen']]['periodicidad'] ?? 'unico') !== 'unico') {
                        $recurrente = $recurrente->plus($importe);
                    }
                    if ($m['naturaleza'] === 'costo_oportunidad') $oportunidad = $oportunidad->plus($importe);
                }
            }
            $resultados[$id] = ['inversion'=>(string)$inversion,'recurrente'=>(string)$recurrente,'oportunidad'=>(string)$oportunidad];
        }
        $interpretacion = $this->comparacion->interpretar($r);
        if ($r['perspectiva'] === 'economica' && collect($resultados)->contains(fn ($v) => ! BigDecimal::of($v['oportunidad'])->isZero())) {
            $interpretacion['conclusiones'][] = 'La diferencia económica depende de las valoraciones de oportunidad incluidas. Revisa su procedencia, dedicación y supuestos.';
        }

        return ['resultados' => $resultados] + $interpretacion;
    }
}
