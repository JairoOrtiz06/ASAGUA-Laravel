<?php

namespace App\Services\IngenieriaEconomica;

use App\Models\IngenieriaEconomica\Evaluacion;
use App\Models\IngenieriaEconomica\Proyecto;
use Brick\Math\BigDecimal;
use DomainException;

class ProgresoEvaluacionService
{
    public function __construct(
        private ProyeccionFlujosService $proyeccion,
        private EvaluacionService $motor,
        private ConversionTasasService $tasas,
        private CalidadProyectoService $calidad,
        private InformeEconomicoService $informes,
    ) {}

    public function revisar(Proyecto $proyecto, ?ContextoEvaluacion $contexto): array
    {
        $pasos = [
            $this->paso('sin-iniciar', 'Selecciona las alternativas que deseas comparar.'),
            $this->paso('sin-iniciar', 'Selecciona un escenario para revisar su plazo y tasa.'),
            $this->paso('sin-iniciar', 'Define primero el alcance de la evaluación.'),
            $this->paso('sin-iniciar', 'Elige un escenario y las alternativas para proyectar sus movimientos.'),
            $this->paso('sin-iniciar', 'Aplica una selección para evaluar sus datos.'),
            $this->paso('sin-iniciar', 'Guarda una evaluación para interpretar sus resultados.'),
            $this->paso('sin-iniciar', 'Selecciona una evaluación guardada para revisar el expediente.'),
        ];
        $acciones = [];
        if (! $contexto) {
            $hayAlternativas = $proyecto->alternativas_count > 0;
            $hayEscenarios = $proyecto->escenarios_count > 0;
            if ($hayAlternativas) {
                $pasos[0] = $this->paso('en-progreso', 'Hay alternativas registradas. Falta elegir cuáles evaluar.');
            }
            if ($hayEscenarios) {
                $pasos[1] = $this->paso('en-progreso', 'Hay escenarios disponibles. Aplica el que deseas utilizar.');
            }
            $indice = ! $hayAlternativas ? 0 : ($hayEscenarios ? 4 : 1);
            if ($hayAlternativas && $hayEscenarios) {
                $pasos[4] = $this->paso('sin-iniciar', 'Selecciona el escenario y las alternativas que deseas evaluar para continuar.');
            }
            $acciones[] = $this->accion($indice, match (true) {
                ! $hayAlternativas => 'alternativas.index',
                ! $hayEscenarios => 'escenarios.index',
                default => 'evaluaciones.index',
            }, match (true) {
                ! $hayAlternativas => 'Agregar una alternativa',
                ! $hayEscenarios => 'Configurar un escenario',
                default => 'Seleccionar evaluación',
            }, $pasos[$indice]['motivo']);

            return ['pasos' => $pasos, 'accion' => $acciones[0], 'evaluacion' => null, 'nombres' => []];
        }

        $escenario = $contexto->escenario;
        $datos = $contexto->datos;
        $evaluacion = $contexto->evaluacionCompatible($proyecto);
        $resultado = $evaluacion?->instantanea;
        $nombres = $contexto->historica
            ? array_map(fn ($a) => $a['nombre'], $resultado['alternativas'])
            : $proyecto->alternativas()->whereKey($datos['alternativas'])->pluck('nombre', 'id')->all();
        $insumos = null;

        if ($contexto->historica) {
            $pasos[0] = $this->paso('completado', 'Alternativas identificadas en la versión guardada.');
            foreach ($resultado['alternativas'] as $alternativa) {
                if (($alternativa['viabilidad'] ?? null) !== 'viable') {
                    $pasos[0] = $this->paso('revision', $alternativa['nombre'].' no tiene viabilidad confirmada en esta versión.');
                    break;
                }
            }
            $pasos[1] = ($resultado['escenario']['estado_tasa'] ?? null) === 'validada'
                ? $this->paso('completado', 'La tasa y el plazo corresponden a la instantánea guardada.')
                : $this->paso('revision', 'La versión se calculó con una TMAR sin confirmar documentalmente.');
            $pasos[2] = $this->paso('en-progreso', 'Los conceptos de esta versión se consultan en su memoria.');
            $pasos[3] = $this->paso('en-progreso', 'Consulta los movimientos incluidos y excluidos en la versión guardada.');
        } else {
            $insumos = $this->proyeccion->insumos($proyecto, $escenario, $datos['alternativas']);
            $pasos[0] = $this->paso('completado', 'Las alternativas seleccionadas tienen viabilidad técnica registrada.');
            foreach ($insumos['alternativas'] as $id => $alternativa) {
                if ($alternativa['viabilidad_tecnica'] !== 'viable') {
                    $motivo = $alternativa['nombre'].($alternativa['viabilidad_tecnica'] === 'no_viable'
                        ? ' figura como técnicamente inviable.' : ' tiene viabilidad técnica pendiente de confirmar.');
                    $pasos[0] = $this->paso('revision', $motivo);
                    $acciones[30] = $this->accion(0, 'alternativas.edit', 'Revisar alternativa', $motivo, [$id]);
                    break;
                }
            }

            $fiscalNoAnual = $datos['perspectiva'] === 'fiscal' && $contexto->periodicidadEfectiva() !== 'anual';
            $configuracion = $this->configuracion($proyecto, $contexto);
            $pasos[1] = $configuracion;
            if ($configuracion['estado'] === 'en-progreso') {
                $acciones[20] = $this->accion(1, 'escenarios.edit', 'Corregir escenario', $configuracion['motivo'], [$escenario->id]);
            } elseif ($fiscalNoAnual) {
                $motivo = 'La evaluación después de impuestos requiere periodicidad anual.';
                $pasos[1] = $this->paso('revision', $motivo);
                $acciones[20] = $this->accion(1, 'evaluaciones.index', 'Seleccionar periodicidad anual', $motivo);
            }
            if ($escenario->horizonte_anios >= 1 && $escenario->horizonte_anios <= ProyeccionFlujosService::MAX_ANIOS
                && ($escenario->fecha_base ?? $proyecto->fecha_base)) {
                $this->revisarFlujos($proyecto, $contexto, $insumos, $pasos, $acciones);
            }
        }

        $alcance = null;
        if ($evaluacion) {
            $this->revisarVersion($proyecto, $contexto, $evaluacion, $insumos, $pasos, $acciones, $alcance);
        } elseif (! isset($acciones[20]) && $pasos[2]['estado'] === 'completado' && $pasos[3]['estado'] === 'completado') {
            $fiscal = $datos['perspectiva'] === 'fiscal';
            $pasos[4] = $this->paso('en-progreso', $fiscal
                ? 'Hay movimientos utilizables. Completa los parámetros y el respaldo fiscal para obtener una vista previa.'
                : 'Hay datos para calcular. Genera una vista previa y guarda la evaluación.');
            $acciones[50] = $this->accion(4, 'evaluaciones.index', 'Calcular evaluación', $pasos[4]['motivo']);
        }

        ksort($acciones);

        return ['pasos' => $pasos, 'accion' => reset($acciones) ?: null, 'evaluacion' => $evaluacion, 'nombres' => $nombres, 'alcance' => $alcance];
    }

    private function configuracion(Proyecto $proyecto, ContextoEvaluacion $contexto): array
    {
        $escenario = $contexto->escenario;
        if (! ($escenario->fecha_base ?? $proyecto->fecha_base) || $escenario->horizonte_anios < 1
            || $escenario->horizonte_anios > ProyeccionFlujosService::MAX_ANIOS) {
            return $this->paso('en-progreso', 'Indica una fecha base y un horizonte de 1 a '.ProyeccionFlujosService::MAX_ANIOS.' años.');
        }
        if ($escenario->tasa_efectiva_anual === null || $escenario->tasa_original === null
            || ! $escenario->modalidad_tasa || ! $escenario->periodicidad_tasa) {
            return $this->paso('en-progreso', 'Registra la TMAR, su modalidad y su periodicidad. Puedes registrar 0 % expresamente.');
        }
        try {
            $this->tasas->convertir($escenario->tasa_efectiva_anual, 'efectiva', 'anual', $contexto->periodicidadEfectiva());
        } catch (DomainException $error) {
            return $this->paso('en-progreso', $error->getMessage());
        }

        return $escenario->estado_tasa === 'validada'
            ? $this->paso('completado', 'El escenario tiene plazo, periodicidad y una tasa utilizable para el cálculo.')
            : $this->paso('revision', 'La TMAR permite calcular. Su estado es '.$escenario->estado_tasa.' y requiere revisión antes de validar.');
    }

    private function revisarFlujos(Proyecto $proyecto, ContextoEvaluacion $contexto, array $insumos, array &$pasos, array &$acciones): void
    {
        $datos = $contexto->datos;
        try {
            $proyeccion = $this->proyeccion->generar($proyecto, $contexto->escenario, $datos['alternativas'], $datos['moneda'], $contexto->periodicidadEfectiva(), insumos: $insumos);
        } catch (DomainException $error) {
            $pasos[3] = $this->paso('revision', $error->getMessage());
            $acciones[40] = $this->accion(3, 'flujos.index', 'Revisar flujos', $error->getMessage());

            return;
        }

        $diagnostico = $this->motor->diagnosticarFlujos($proyeccion, $datos['perspectiva']);
        $conceptos = collect($insumos['conceptos'])->keyBy('id');
        $cerosRespaldados = null;
        $ceroDocumentado = function (int $id) use ($proyecto, $conceptos, &$cerosRespaldados): bool {
            $cerosRespaldados ??= $proyecto->evidenciasDocumentales()->where('calidad', 'documentado')
                ->whereHas('vinculos', fn ($q) => $q->where('tipo', 'concepto')->whereIn('destino_id', $conceptos->keys()))
                ->with(['vinculos' => fn ($q) => $q->where('tipo', 'concepto')])->get()->flatMap->vinculos->pluck('destino_id');

            return $cerosRespaldados->contains($id);
        };
        $pasos[2] = $this->paso('completado', 'Cada alternativa tiene costos propios utilizables. Los datos comunes se registran una sola vez.');
        $pasos[3] = $this->paso('completado', 'Hay movimientos que se pueden incluir en la perspectiva elegida. Se aplicaron los ajustes del escenario.');
        foreach ($proyeccion['resultado'] as $id => $flujo) {
            $nombre = $flujo['alternativa']->nombre;
            $valorado = $diagnostico[$id];
            $costos = collect($valorado['periodos'])->flatMap(fn ($p) => $p['movimientos'])->contains(function ($m) use ($conceptos, $ceroDocumentado) {
                if ($m['motivo'] !== null || $m['direccion'] !== 'salida' || $m['clasificacion'] === 'comun'
                    || ! in_array($m['naturaleza'], ['desembolso_real', 'costo_oportunidad'], true)) {
                    return false;
                }
                if (! BigDecimal::of($m['monto'])->isZero()) {
                    return true;
                }
                $id = (int) substr($m['origen'], 1);
                $concepto = $conceptos->get($id);

                return $m['estado'] === 'confirmado' && (str_starts_with($m['origen'], 'a')
                    || ! empty($concepto['fuentes']) || filled($concepto['observaciones'] ?? null) || $ceroDocumentado($id));
            });
            foreach ($flujo['excluidos'] as $movimiento) {
                if ($movimiento['fecha']->gt($proyeccion['fin'])) {
                    $flujo['incidencias'][] = ['codigo' => 'horizonte', 'nombre' => $movimiento['nombre'], 'id' => null];
                } elseif ($movimiento['clasificacion'] === 'pendiente_clasificacion') {
                    $flujo['incidencias'][] = ['codigo' => 'clasificacion', 'nombre' => $movimiento['nombre'], 'id' => $movimiento['concepto']?->id];
                }
            }
            $incidencias = [...$flujo['incidencias'], ...$valorado['incidencias']];
            if (! $costos && ! isset($acciones[31]) && ! isset($acciones[32])) {
                $motivo = $nombre.' requiere un costo propio utilizable o un costo cero confirmado y documentado.';
                $pasos[2] = $this->paso($incidencias ? 'revision' : 'en-progreso', $motivo);
                if (! $incidencias) {
                    $acciones[31] ??= $this->accion(2, 'conceptos.index', 'Completar costos y beneficios', $motivo, ['alternativa_id' => $id]);
                }
            }
            foreach ($incidencias as $incidencia) {
                $motivo = $nombre.' · '.$incidencia['nombre'].' '.match ($incidencia['codigo']) {
                    'moneda' => 'quedó fuera por usar otra moneda.',
                    'horizonte' => 'quedó fuera del horizonte.',
                    'sin_pagos' => 'no tiene pagos programados dentro del horizonte.',
                    'clasificacion' => 'requiere clasificar el movimiento.',
                    'justificacion' => 'requiere justificar su inclusión en la perspectiva económica.',
                    'duplicado' => 'repite un detalle contable en la misma fecha, dirección e importe.',
                };
                if (! isset($acciones[40])) {
                    $pasos[3] = $this->paso('revision', $motivo);
                    $acciones[40] = $this->accion(3, 'flujos.index', 'Revisar movimientos', $motivo);
                }
                if (in_array($incidencia['codigo'], ['clasificacion', 'justificacion', 'duplicado'], true) && $incidencia['id']
                    && ! isset($acciones[32])) {
                    $pasos[2] = $this->paso('revision', $motivo);
                    $acciones[32] = $this->accion(2, 'conceptos.edit', 'Revisar concepto', $motivo, [$incidencia['id']]);
                }
            }
            if ($valorado['incluidos'] === 0 && ! isset($acciones[40])) {
                $motivo = $nombre.' no tiene movimientos incluidos en esta perspectiva.';
                $pasos[3] = $this->paso('revision', $motivo);
                $acciones[40] = $this->accion(3, 'flujos.index', 'Revisar movimientos', $motivo);
            }
        }
    }

    private function revisarVersion(Proyecto $proyecto, ContextoEvaluacion $contexto, Evaluacion $evaluacion, ?array $insumos, array &$pasos, array &$acciones, ?array &$alcance): void
    {
        $r = $evaluacion->instantanea;
        $seleccion = $contexto->complementos;
        $version = ['evaluacion_id' => $evaluacion->id] + $seleccion;
        $vigencia = $this->calidad->vigencia($proyecto, $evaluacion, $contexto->escenario, $insumos);
        $consistente = $this->calidad->flujosValidos($r);
        $datos = ContextoEvaluacion::entrada($contexto->datos);
        $sinMovimientos = collect($r['alternativas'])->first(fn ($a) => ! collect($a['periodos'])
            ->flatMap(fn ($p) => $p['movimientos'])->contains(fn ($m) => $m['motivo'] === null));
        if ($contexto->historica && $consistente && ! $sinMovimientos) {
            $pasos[3] = $this->paso('completado', 'La memoria de la versión guardada pasa los controles de fechas y saldos.');
        }
        if (! $consistente || ($contexto->historica && $sinMovimientos)) {
            $motivo = $consistente ? $sinMovimientos['nombre'].' no tiene movimientos incluidos en esta versión.'
                : 'La memoria guardada no pasa los controles de fechas, saldos o movimientos comunes.';
            $pasos[3] = $this->paso('revision', $motivo);
            $acciones[41] = $this->accion(3, 'evaluaciones.show', 'Revisar memoria de flujos', $motivo, [$evaluacion->id]);
        }
        $indicadores = collect($r['alternativas'])->every(fn ($a) => isset($a['indicadores']['van'], $a['indicadores']['vpc'], $a['indicadores']['caue'], $a['periodos']));
        $flujosActualesListos = $contexto->historica
            || ($pasos[2]['estado'] === 'completado' && $pasos[3]['estado'] === 'completado');
        $apta = $vigencia['vigente'] && $consistente && $indicadores && ! $sinMovimientos && $flujosActualesListos;
        $motivoEvaluacion = match (true) {
            ! $vigencia['vigente'] => $vigencia['motivo'],
            ! $indicadores => 'La versión no conserva todos los indicadores o memorias requeridos.',
            ! $consistente || $sinMovimientos || ! $flujosActualesListos => 'La evaluación está guardada, pero hay datos o movimientos que requieren revisión.',
            default => 'Evaluación #'.$evaluacion->id.' guardada. '.$vigencia['motivo'],
        };
        $pasos[4] = $this->paso($apta ? 'completado' : 'revision', $motivoEvaluacion);
        if ($pasos[4]['estado'] === 'revision') {
            $acciones[50] = $this->accion(4, 'evaluaciones.index', 'Preparar una nueva evaluación', $pasos[4]['motivo'], $datos);
        }

        $comparaciones = $r['comparaciones'] ?? [];
        $unica = count($r['alternativas']) === 1;
        $equivalencia = ! empty($r['equivalencia']);
        $inviable = collect($r['alternativas'])->contains(fn ($alt) => ($alt['viabilidad'] ?? null) === 'no_viable');
        $pasos[5] = match (true) {
            $inviable => $this->paso('revision', 'Consulta las cifras sin recomendar las alternativas técnicamente inviables.'),
            $unica && empty($seleccion['sensibilidad_id']) => $this->paso('no-aplica', 'La comparación incremental no aplica con una alternativa. Puedes consultar sus indicadores.'),
            ! $unica && (! $comparaciones || ! $equivalencia) => $this->paso('revision', 'Revisa la comparación y confirma la equivalencia de servicio para interpretar las diferencias de costo.'),
            default => $this->paso('completado', $r['perspectiva'] === 'fiscal'
                ? 'La comparación está disponible. La sensibilidad no aplica a la perspectiva fiscal en esta versión.'
                : 'Los resultados guardados están disponibles para interpretar la decisión.'),
        };
        if ($pasos[5]['estado'] === 'revision') {
            $acciones[60] = $this->accion(5, 'evaluaciones.show', 'Revisar resultados', $pasos[5]['motivo'], [$evaluacion->id]);
        }

        $elegidos = array_map('intval', $seleccion['calculos'] ?? []);
        sort($elegidos);
        $emitido = $proyecto->informesEconomicos()->where('evaluacion_id', $evaluacion->id)
            ->orderByRaw('CASE WHEN estado = ? THEN 0 ELSE 1 END', ['validado'])->latest('id')->cursor()
            ->first(function ($informe) use ($seleccion, $elegidos) {
                $calculos = array_map('intval', array_column($informe->instantanea['calculos'] ?? [], 'id'));
                sort($calculos);

                return (int) ($informe->instantanea['sensibilidad']['id'] ?? 0) === (int) ($seleccion['sensibilidad_id'] ?? 0)
                    && $calculos === $elegidos;
            });
        try {
            $alcance = $this->informes->revisarAlcance($proyecto, $evaluacion, $seleccion, $vigencia);
        } catch (DomainException $error) {
            $pasos[6] = $this->paso('revision', $error->getMessage());
            if (isset($seleccion['sensibilidad_id'])) {
                $pasos[5] = $this->paso('revision', $error->getMessage());
            }
            $acciones[70] = $this->accion(6, 'informes.index', 'Revisar complementos del informe', $error->getMessage(), $version);

            return;
        }

        if (($alcance['sensibilidad']['estado'] ?? null) === 'provisional') {
            $motivo = 'El análisis de sensibilidad seleccionado tiene supuestos provisionales.';
            $pasos[5] = $this->paso('revision', $motivo);
            $acciones[61] = $this->accion(5, 'sensibilidad.show', 'Revisar análisis de sensibilidad', $motivo, [$alcance['sensibilidad']['id']]);
        }
        $bloqueo = $alcance['calidad']['bloqueos'][0] ?? null;
        $alcance['emitido'] = $emitido;
        $hito = $emitido ? 'Informe '.$emitido->estado.' emitido · '.$emitido->folio.'. ' : '';
        if ($emitido?->estado === 'validado') {
            $pasos[6] = $this->paso('completado', $hito.($bloqueo ? 'La emisión histórica se conserva. Una nueva emisión requiere revisión.' : ''));
            $acciones[80] = $this->accion(6, 'informes.show', 'Ver informe emitido', $pasos[6]['motivo'], [$emitido->id]);
        } elseif ($bloqueo) {
            $pasos[6] = $this->paso('revision', $hito.'Puedes emitir un preliminar. '.$bloqueo['requisito'].': '.$bloqueo['detalle']);
            [$tipo, $id] = $bloqueo['destino'] ?? [null, null];
            $consulta = ['contexto' => $version];
            if ($tipo && $id && $bloqueo['categoria'] === 'Respaldo documental') {
                $acciones[70] = $this->accion(6, 'evidencias.index', 'Revisar respaldo documental', $pasos[6]['motivo'],
                    $consulta + ['tipo' => $tipo, 'registro' => $id, 'origen' => 'validacion']);
            } else {
                $acciones[70] = $this->accion(6, 'validacion', 'Revisar requisitos', $pasos[6]['motivo'], $version);
            }
        } else {
            $pasos[6] = $this->paso('en-progreso', $hito.'El alcance seleccionado cumple los requisitos para preparar un informe validado.');
            $acciones[80] = $this->accion(6, 'informes.index', 'Preparar informe validado', $pasos[6]['motivo'], $version);
        }
    }

    private function paso(string $estado, string $motivo): array
    {
        return compact('estado', 'motivo');
    }

    private function accion(int $paso, string $ruta, string $titulo, string $motivo, array $parametros = []): array
    {
        return compact('paso', 'ruta', 'titulo', 'motivo', 'parametros');
    }
}
