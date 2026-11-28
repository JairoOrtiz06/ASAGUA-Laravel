<?php

namespace App\Services\IngenieriaEconomica;

use App\Models\IngenieriaEconomica\Activo;
use App\Models\IngenieriaEconomica\Escenario;
use App\Models\IngenieriaEconomica\Evaluacion;
use App\Models\IngenieriaEconomica\Proyecto;
use Brick\Math\BigDecimal;

class CalidadProyectoService
{
    public const ESTADOS = ['validado'=>'Validado','pendiente'=>'Pendiente','na'=>'No aplicable','revision'=>'Requiere revisión'];

    public function __construct(private ProyeccionFlujosService $proyeccion, private ReferenciaDocumentalService $referencias, private ArchivoDocumentalService $archivos) {}

    public function revisar(Proyecto $p, ?Evaluacion $evaluacion = null, ?array $sensibilidad = null, array $calculos = [], ?array $vigencia = null): array
    {
        $r = $evaluacion?->instantanea;
        $esc = $evaluacion ? $p->escenarios()->find($evaluacion->escenario_id) : $p->escenarioPrincipal()->first() ?? $p->escenarios()->first();
        $ids = $r ? array_keys($r['alternativas']) : $p->alternativas()->pluck('id')->all();
        $alternativas = $p->alternativas()->whereKey($ids)->get();
        $conceptos = $p->conceptos()->where(fn ($q) => $q->whereNull('alternativa_id')->orWhereIn('alternativa_id',$ids))->with('fuentes')->get();
        $activos = $p->activos()->where(fn ($q) => $q->whereNull('alternativa_id')->orWhereIn('alternativa_id',$ids))->withCount('calculos')->get();
        $destinos = [
            'escenario' => $esc ? [$esc->id] : [],
            'alternativa' => $ids,
            'concepto' => $conceptos->modelKeys(),
            'activo' => $activos->modelKeys(),
        ];
        $evidencias = $p->evidenciasDocumentales()->whereHas('vinculos', function ($consulta) use ($destinos) {
            $consulta->where(function ($consulta) use ($destinos) {
                foreach ($destinos as $tipo => $ids) {
                    $consulta->orWhere(fn ($vinculo) => $vinculo->where('tipo', $tipo)->whereIn('destino_id', $ids));
                }
            });
        })->with('vinculos')->get();
        $respaldoPorEvidencia = $evidencias->mapWithKeys(fn ($doc) => [
            $doc->id => $this->referencias->faltaRespaldo($p, $doc, $this->archivos, conciliado: true),
        ]);
        $validas = $evidencias->where('estado','validado')->where('calidad','documentado')
            ->filter(fn ($doc) => $respaldoPorEvidencia[$doc->id] === null);
        $asociadas = fn ($docs, $tipo, $id) => $docs->filter(fn ($doc) => $doc->vinculos
            ->contains(fn ($v) => $v->tipo === $tipo && (int) $v->destino_id === (int) $id));
        $tieneRespaldo = fn ($tipo,$id) => $asociadas($validas,$tipo,$id)->isNotEmpty();
        $detalleRespaldo = function ($tipo, $id) use ($evidencias, $asociadas, $respaldoPorEvidencia): string {
            $docs = $asociadas($evidencias, $tipo, $id);
            if ($docs->isEmpty()) {
                return 'Falta vincular evidencia central. Una cita anterior no certifica el documento.';
            }

            return $docs->map(function ($doc) use ($respaldoPorEvidencia) {
                $requisitos = array_filter([
                    $respaldoPorEvidencia[$doc->id],
                    match ($doc->estado) {
                        'existente', 'pendiente' => 'Falta revisar contenido y procedencia.',
                        'revisado' => 'Falta validar el documento tras la revisión.',
                        default => null,
                    },
                    $doc->calidad === 'documentado' ? null : 'La calidad registrada requiere corroboración documental.',
                ]);

                return $doc->titulo.' · '.($requisitos ? implode(' ', $requisitos) : 'Evidencia validada.');
            })->implode(' ');
        };

        $requisitos = [];
        $agregar = function (string $grupo, string $requisito, string $estado, string $detalle, bool $critico = false,
            ?array $destino = null, ?string $categoria = null) use (&$requisitos): void {
            $categoria ??= match ($grupo) {
                'Escenario','Conceptos','Activos','Sensibilidad' => 'Respaldo documental',
                'Alternativas' => 'Condiciones técnicas',
                'Flujos','Evaluación','Proyecto' => 'Calidad de datos',
                default => 'Avisos informativos',
            };
            $requisitos[] = compact('grupo','requisito','estado','detalle','critico','destino','categoria');
        };
        $faltaProyecto = blank($p->nombre) || blank($p->descripcion) || ! $p->fecha_base || ! $p->horizonte_anios;
        $agregar('Proyecto','Identificación, descripción, fecha base y horizonte',$faltaProyecto?'pendiente':'validado',
            $faltaProyecto?'Completa los datos del proyecto.':'Campos de identificación completos.',true);
        $agregar('Proyecto','Alternativas registradas',$alternativas->isEmpty()?'pendiente':'validado',$alternativas->count().' alternativa(s) en el alcance.',true);
        $tasaCompleta = $esc && $esc->tasa_efectiva_anual !== null && $esc->tasa_original !== null && $esc->modalidad_tasa && $esc->periodicidad_tasa;
        $agregar('Escenario','TMAR y periodicidades',$tasaCompleta?'validado':'pendiente','Control estructural de la cotización y tasa efectiva.',true);
        $baseTasa = $esc?->fecha_base ?? $p->fecha_base;
        $respaldosTasa = $esc ? $asociadas($validas, 'escenario', $esc->id) : collect();
        $soloCotizacionesAnteriores = fn ($docs, $fecha) => $docs->isNotEmpty() && $fecha && $docs->every(
            fn ($doc) => $doc->tipo === 'cotizacion' && $doc->fecha_documento->lt($fecha));
        $soloCotizacionHistorica = $soloCotizacionesAnteriores($respaldosTasa, $baseTasa);
        $tasaValidada = $tasaCompleta && $esc->estado_tasa === 'validada' && filled($esc->fuente_tasa)
            && $respaldosTasa->isNotEmpty() && ! $soloCotizacionHistorica;
        $detalleTasa = $esc ? $detalleRespaldo('escenario', $esc->id) : 'Falta registrar un escenario.';
        $detalleTasa .= $esc && ($esc->estado_tasa !== 'validada' || blank($esc->fuente_tasa))
            ? ' Confirma el estado y la fuente de la tasa en el escenario.' : '';
        $agregar('Escenario','Validación documental de TMAR',$tasaValidada?'validado':($soloCotizacionHistorica?'revision':'pendiente'),
            $soloCotizacionHistorica ? $detalleTasa.' Falta documentar vigencia para la fecha base. Una cotización histórica por sí sola no acredita la tasa.' : $detalleTasa,
            true, $esc ? ['escenario',$esc->id] : null);
        foreach ($alternativas as $a) {
            $nombre = $r['alternativas'][$a->id]['nombre'] ?? $a->nombre;
            $agregar('Alternativas','Viabilidad · '.$nombre,match($a->viabilidad_tecnica) {
                'viable' => $tieneRespaldo('alternativa',$a->id) ? 'validado' : 'pendiente',
                'no_viable' => 'revision', default => 'pendiente',
            },'Estado técnico registrado: '.$a->viabilidad_tecnica.'. '.$detalleRespaldo('alternativa',$a->id),true,['alternativa',$a->id]);
            $conCostos = $conceptos->contains(fn ($c) => ((int) $c->alternativa_id === (int) $a->id || $c->alternativa_id === null)
                && in_array($c->naturaleza_economica,['desembolso_real','costo_oportunidad'],true) && $c->clasificacion_decision !== 'hundido');
            $agregar('Alternativas','Costos · '.$nombre,$conCostos?'validado':'pendiente',
                'Control de registro. Un costo cero se debe documentar.',true,['alternativa',$a->id],'Calidad de datos');
        }
        $agregar('Alternativas','Equivalencia de servicio',count($ids)<2?'na':(! empty($r['equivalencia'])?'validado':'pendiente'),
            'Declaración de equivalencia registrada con la evaluación.',true);
        $duplicados = $conceptos->groupBy(fn ($c) => implode('|',[$c->alternativa_id,$c->tipo_concepto,$c->monto,$c->moneda,
            $c->fecha_real?->toDateString(),$c->fecha_programada?->toDateString(),$c->periodicidad,$c->periodo_inicio,$c->momento_pago]))
            ->filter(fn ($items) => $items->count() > 1);
        $agregar('Conceptos','Posibles duplicaciones',$duplicados->isEmpty()?'validado':'revision',
            $duplicados->isEmpty()?'Sin coincidencias exactas en la firma revisada.':$duplicados->count().' grupo(s) con coincidencias que requieren revisión.',true);
        foreach ($conceptos as $c) {
            $historico = $c->clasificacion_decision === 'hundido' && $p->tipo_decision !== 'historica';
            $detalle = $detalleRespaldo('concepto',$c->id);
            $requiereVigencia = $p->tipo_decision !== 'historica' && ! $c->fecha_real
                && $soloCotizacionesAnteriores($asociadas($validas, 'concepto', $c->id), $c->fecha_programada ?? $baseTasa);
            if ($requiereVigencia) {
                $detalle .= ' Falta documentar la vigencia del importe proyectado. Contrasta la cotización histórica con un respaldo aplicable.';
            }
            $requiereRevision = $requiereVigencia || $c->clasificacion_decision === 'pendiente_clasificacion' || ($r && $c->moneda !== $r['moneda']);
            $agregar('Conceptos',$r['origenes']['c'.$c->id]['nombre'] ?? $c->nombre, $historico?'na':(
                $requiereRevision ? 'revision' : ($c->estado_dato === 'confirmado' && $tieneRespaldo('concepto',$c->id) ? 'validado':'pendiente')
            ), $historico?'Costo hundido para esta decisión.':"Dato {$c->estado_dato} · {$c->periodicidad} · {$c->moneda} · {$c->clasificacion_decision}. ".$detalle,
                true,['concepto',$c->id]);
        }

        $consistente = $r ? $this->flujosValidos($r) : false;
        $agregar('Flujos','Fechas, saldos y perspectiva',$r?($consistente?'validado':'revision'):'pendiente',
            'Contrasta fechas con su intervalo, neto con beneficios menos costos y exclusión de oportunidad en caja.',true);
        $exclusiones = $r ? collect($r['alternativas'])->sum(fn ($a) => count($a['excluidos'])) : 0;
        $agregar('Flujos','Exclusiones y comunes',$r?($consistente?'validado':'revision'):'pendiente',
            "$exclusiones exclusión(es) explícita(s). Se contrasta el neto común y su retiro en la memoria incremental.",true);

        $vigencia ??= $evaluacion ? $this->vigencia($p, $evaluacion, $esc) : null;
        $agregar('Evaluación','Instantánea vigente',($vigencia['vigente'] ?? false)?'validado':($r?'revision':'pendiente'),
            $vigencia['motivo'] ?? 'Selecciona una evaluación guardada para comprobar sus insumos.',true);
        $indicadores = $r && collect($r['alternativas'])->every(fn ($a) => isset($a['indicadores']['van'],$a['indicadores']['vpc'],$a['indicadores']['caue']));
        $agregar('Evaluación','Indicadores, versión y perspectiva',$indicadores?'validado':'pendiente',
            $r ? "Motor {$r['version']} · {$r['perspectiva']}. TIR y RCB pueden no aplicar." : 'Selecciona una evaluación guardada.',true);
        $avisos = $r ? [...$r['advertencias'], ...collect($r['alternativas'])->flatMap(fn ($a) => $a['avisos'])->all()] : [];
        foreach (array_unique($avisos) as $aviso) {
            $categoria = match (true) {
                str_starts_with($aviso, 'La comparabilidad técnica') => 'Condiciones técnicas',
                str_contains($aviso, 'estimado o provisional'), str_starts_with($aviso, 'La TMAR'),
                str_starts_with($aviso, 'Justifica la valoración') => 'Respaldo documental',
                default => 'Calidad de datos',
            };
            $agregar('Evaluación','Advertencia del motor','revision',$aviso,true,
                $evaluacion ? ['evaluacion',$evaluacion->id] : null,$categoria);
        }
        if ($r && ! $avisos) {
            $agregar('Evaluación','Advertencias del motor','validado','El motor no registró avisos.');
        }
        if ($r && collect($r['alternativas'])->contains(fn ($a) => $a['indicadores']['rcb'] === null)) {
            $agregar('Evaluación','Aplicabilidad de indicadores','na','La RCB puede no aplicar si faltan beneficios cuantificados o costos positivos. La memoria indica el motivo.',
                false, $evaluacion ? ['evaluacion',$evaluacion->id] : null, 'Avisos informativos');
        }

        $agregar('Sensibilidad','Variables, simulaciones y versión',$sensibilidad
            ? (($sensibilidad['estado'] === 'validado' && ! empty($sensibilidad['datos']['simulaciones']))?'validado':'revision')
            : 'na',$sensibilidad ? $sensibilidad['nombre'].' · '.$sensibilidad['estado'].' · '.count($sensibilidad['datos']['simulaciones']).' simulación(es).' : 'No se seleccionó un análisis.',true);
        foreach ($calculos as $calculo) {
            $activo = $activos->firstWhere('id', $calculo['activo_id']);
            $vigenteActivo = $activo && $this->coincideActivo($activo, $calculo['datos']);
            $agregar('Activos', 'Insumos del cálculo #'.$calculo['id'], $vigenteActivo ? 'validado' : 'revision',
                $vigenteActivo ? 'Los datos del activo capturados por este cálculo siguen disponibles y coinciden.'
                    : 'Los datos actuales del activo difieren de los capturados. Revisa el cálculo antes de una nueva emisión validada.',
                true, $activo ? ['activo', $activo->id] : null);
        }
        $agregar('Activos','Activos del alcance',$activos->isEmpty()?'na':'validado',$activos->count().' activo(s) registrado(s).');
        foreach ($activos as $a) {
            $reconocido = in_array($a->clasificacion_contable,['activo_tangible','activo_intangible'],true);
            $valido = $reconocido && ($a->vida_indefinida || ($a->vida_util_anios > 0 && $a->fecha_disponible_uso))
                && BigDecimal::of($a->valor_residual)->isLessThanOrEqualTo($a->baseCapitalizada()) && $a->calculos_count > 0 && $tieneRespaldo('activo',$a->id);
            $agregar('Activos',$a->nombre,$a->clasificacion_contable === 'gasto'?'na':($valido?'validado':'revision'),
                'Comprueba clasificación, vida, residual y cálculo. '.$detalleRespaldo('activo',$a->id),true,['activo',$a->id]);
            $agregar('Activos','Tratamiento fiscal · '.$a->nombre,
                filled($a->efecto_tributario) ? ($tieneRespaldo('activo',$a->id)?'validado':'pendiente') : 'na',
                'Revisa el tratamiento documentado del activo. El cálculo, si corresponde, se configura desde la evaluación fiscal.',false,['activo',$a->id]);
        }

        $bloqueos = array_values(array_filter($requisitos,fn ($i) => $i['critico'] && in_array($i['estado'],['revision','pendiente'],true)));

        return [
            'requisitos'=>$requisitos,'bloqueos'=>$bloqueos,'puede_validar'=>$bloqueos===[], 'vigencia'=>$vigencia,
            'conteos'=>array_count_values(array_column($requisitos,'estado')),
            'faltantes'=>array_values(array_filter($requisitos,fn ($i) => $i['estado']==='pendiente' && (
                in_array($i['grupo'],['Escenario','Conceptos','Activos','Alternativas'],true)))),
        ];
    }

    private function coincideActivo(Activo $activo, array $datos): bool
    {
        $captura = $datos['activo'] ?? [];
        if (! $captura || $activo->only(array_keys($captura)) != $captura) {
            return false;
        }
        if (isset($datos['escenario'])) {
            // Reemplazo trae sus supuestos en la instantánea y el informe verifica la compatibilidad del escenario.
            return true;
        }

        return isset($datos['base'], $datos['residual'])
            && $activo->baseCapitalizada()->isEqualTo($datos['base'])
            && BigDecimal::of($activo->valor_residual)->isEqualTo($datos['residual'])
            && $activo->vida_util_anios === ($datos['vida'] ?? null)
            && $activo->fecha_disponible_uso?->toDateString() === ($datos['inicio'] ?? null);
    }

    public function vigencia(Proyecto $p, Evaluacion $evaluacion, ?Escenario $esc = null, ?array $insumos = null): array
    {
        $r = $evaluacion->instantanea;
        $esc ??= $p->escenarios()->find($evaluacion->escenario_id);
        if (! $esc || ! isset($r['insumos'])) {
            return ['vigente' => false, 'motivo' => 'La versión guardada carece de los insumos necesarios para acreditar vigencia.'];
        }
        if ($esc->tasa_efectiva_anual === null || $r['proyecto']['tipo_decision'] !== $p->tipo_decision
            || ($esc->fecha_base ?? $p->fecha_base)->toDateString() !== $r['escenario']['base']
            || $esc->horizonte_anios !== $r['escenario']['horizonte']
            || $esc->periodicidad !== $r['escenario']['periodicidad_escenario']
            || $esc->tasa_original !== $r['escenario']['original']
            || $esc->modalidad_tasa !== $r['escenario']['modalidad_tasa']
            || $esc->periodicidad_tasa !== $r['escenario']['periodicidad_tasa']
            || $esc->estado_tasa !== $r['escenario']['estado_tasa'] || $esc->fuente_tasa !== $r['escenario']['fuente_tasa']
            || ! BigDecimal::of($esc->tasa_efectiva_anual)->isEqualTo($r['escenario']['anual'])) {
            return ['vigente' => false, 'motivo' => 'Cambió la configuración del escenario o el tipo de decisión desde que se guardó la evaluación.'];
        }

        $ids = array_keys($r['alternativas']);
        try {
            $actuales = $insumos ?? $this->proyeccion->insumos($p, $esc, $ids);
        } catch (\DomainException) {
            return ['vigente' => false, 'motivo' => 'Las alternativas de la versión ya no están disponibles en el alcance actual.'];
        }
        if (! $this->coincidenInsumos($actuales, $r['insumos'])) {
            return ['vigente' => false, 'motivo' => 'Cambiaron alternativas, conceptos, fuentes o ajustes desde que se guardó la evaluación. Guarda una versión con insumos vigentes.'];
        }

        if (($r['perspectiva'] ?? null) === 'fiscal') {
            $fiscal = $r['fiscal'] ?? [];
            $activos = $fiscal['activos_verificados'] ?? null;
            $fuente = $fiscal['fuente'] ?? [];
            $documento = $p->evidenciasDocumentales()->with('vinculos')->find($fuente['id'] ?? null);
            if ($activos === null || ! $documento || $documento->only(['id', 'titulo', 'procedencia', 'estado', 'calidad', 'sha256'])
                != array_intersect_key($fuente, array_flip(['id', 'titulo', 'procedencia', 'estado', 'calidad', 'sha256']))
                || $documento->fecha_documento->toDateString() !== ($fuente['fecha'] ?? null)
                || $this->referencias->faltaRespaldo($p, $documento, $this->archivos, conciliado: true) !== null
                || $this->ordenar($p->activos()->whereKey(array_keys($activos))->get()->keyBy('id')->map->getAttributes()->all()) !== $this->ordenar($activos)) {
                return ['vigente' => false, 'motivo' => 'No se pudo acreditar la vigencia fiscal. Revisa el respaldo tributario y los activos utilizados en la evaluación.'];
            }
        }

        return ['vigente' => true, 'motivo' => 'Los insumos guardados coinciden con los actuales.'];
    }

    public function coincidenInsumos(array $actuales, array $guardados): bool
    {
        return $this->ordenar($actuales) === $this->ordenar($guardados);
    }

    private function ordenar(array $a): array
    {
        if (! array_is_list($a)) {
            ksort($a);
        }
        foreach ($a as &$valor) {
            if (is_array($valor)) {
                $valor = $this->ordenar($valor);
            }
        }

        return $a;
    }

    public function flujosValidos(array $r): bool
    {
        foreach ($r['alternativas'] as $a) {
            foreach ($a['periodos'] as $p) {
                if (! BigDecimal::of($p['beneficios'])->minus($p['costos'])->isEqualTo($p['neto'])) {
                    return false;
                }
                $comun = BigDecimal::zero();
                foreach ($p['movimientos'] as $m) {
                    if ($m['motivo'] !== null) continue;
                    if ($m['fecha'] < $p['desde'] || $m['fecha'] > $p['hasta']
                        || ($r['perspectiva'] !== 'economica' && in_array($m['naturaleza'],['costo_oportunidad','ahorro_estimado'],true))
                        || ($r['proyecto']['tipo_decision'] !== 'historica' && $m['clasificacion'] === 'hundido')) {
                        return false;
                    }
                    if ($m['clasificacion'] === 'comun') {
                        $importe = BigDecimal::of($m['monto']);
                        $comun = $comun->plus($m['direccion'] === 'entrada' ? $importe : $importe->negated());
                    }
                }
                if (! $comun->isEqualTo($p['neto_comun'])) return false;
            }
        }
        foreach ($r['comparaciones'] as $c) {
            foreach ($c['periodos'] as $t => $p) {
                $a = $r['alternativas'][$c['id_a']]['periodos'][$t];
                $b = $r['alternativas'][$c['id_b']]['periodos'][$t];
                if (! BigDecimal::of($a['neto'])->minus($a['neto_comun'])->minus($b['neto'])->plus($b['neto_comun'])->isEqualTo($p['neto'])) return false;
            }
        }

        return true;
    }
}
