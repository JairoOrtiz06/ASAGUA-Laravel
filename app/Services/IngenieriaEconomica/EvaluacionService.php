<?php

declare(strict_types=1);

namespace App\Services\IngenieriaEconomica;

use App\Models\IngenieriaEconomica\Escenario;
use App\Models\IngenieriaEconomica\Evaluacion;
use App\Models\IngenieriaEconomica\Proyecto;
use Brick\Math\BigDecimal;
use DomainException;

class EvaluacionService
{
    public const VERSION = '4.0.0';

    public const CONVENCION = 'Los movimientos se agrupan al cierre del período elegido para descontar. El período cero se valora en la fecha base. Se preservan las fechas originales.';

    public const HORIZONTE = 'Se compara el mismo horizonte finito. No se presume una vida útil, cadena de reemplazos ni valor residual automático. Los reemplazos y rescates deben estar reflejados en los movimientos.';

    public function __construct(
        private ProyeccionFlujosService $proyeccion,
        private ConversionTasasService $tasas,
        private IndicadoresFinancierosService $indicadores,
        private ComparacionIncrementalService $comparacion,
        private EvaluacionFiscalService $fiscal,
    ) {}

    public function preparar(Proyecto $proyecto, Escenario $escenario, array $datos, ?array $insumos = null, bool $incluirInsumos = true): array
    {
        if ((int) $escenario->proyecto_id !== (int) $proyecto->id) {
            throw new DomainException('Selecciona un escenario del proyecto.');
        }
        if ($escenario->tasa_efectiva_anual === null) {
            throw new DomainException('Configura la TMAR efectiva anual en el escenario antes de evaluar. Una tasa de 0 % debe registrarse expresamente.');
        }

        $periodicidad = $datos['periodicidad'] === 'escenario' ? $escenario->periodicidad : $datos['periodicidad'];
        $anual = BigDecimal::of($datos['tasa_simulada'] ?? $escenario->tasa_efectiva_anual);
        $tasa = $this->tasas->convertir((string) $anual, 'efectiva', 'anual', $periodicidad);
        $ids = array_map('intval', $datos['alternativas']);
        $referencia = (int) ($datos['referencia_id'] ?? $ids[0]);
        if (! in_array($referencia, $ids, true)) {
            throw new DomainException('La referencia debe ser una de las alternativas seleccionadas.');
        }

        $insumos ??= $this->proyeccion->insumos($proyecto, $escenario, $ids);
        $flujo = $this->proyeccion->generar($proyecto, $escenario, $ids, $datos['moneda'], $periodicidad, insumos: $insumos);
        $perspectiva = $datos['perspectiva'];
        $esFiscal = $perspectiva === Evaluacion::PERSPECTIVA_FISCAL;
        if ($esFiscal && empty($datos['fiscal'])) {
            throw new DomainException(EvaluacionFiscalService::PENDIENTE);
        }
        $origenes = $alternativas = [];
        foreach ($flujo['resultado'] as $id => $resultado) {
            $alternativas[$id] = $this->valorarAlternativa($resultado, $esFiscal ? 'caja' : $perspectiva, $tasa, $anual, $escenario->horizonte_anios, $origenes);
        }
        ksort($origenes, SORT_STRING);
        $equivalencia = (bool) ($datos['equivalencia'] ?? false);

        $resultado = [
            ...($incluirInsumos ? ['insumos' => $insumos] : []),
            'version' => $esFiscal ? '4.1-fiscal' : self::VERSION,
            'proyecto' => $proyecto->only(['id', 'nombre', 'organizacion', 'tipo_decision']),
            'escenario' => [
                'id' => $escenario->id, 'nombre' => $escenario->nombre,
                'base' => $flujo['base']->toDateString(), 'fin' => $flujo['fin']->toDateString(),
                'horizonte' => $escenario->horizonte_anios, 'periodicidad' => $periodicidad,
                'periodicidad_escenario' => $escenario->periodicidad,
                'original' => $escenario->tasa_original, 'modalidad_tasa' => $escenario->modalidad_tasa,
                'periodicidad_tasa' => $escenario->periodicidad_tasa, 'capitalizaciones_anuales' => $escenario->capitalizaciones_anuales,
                'anual' => (string) $anual, 'tasa' => (string) $tasa,
                'estado_tasa' => $escenario->estado_tasa, 'fuente_tasa' => $escenario->fuente_tasa,
            ],
            'moneda' => $datos['moneda'], 'perspectiva' => $perspectiva,
            'convencion' => self::CONVENCION, 'supuesto_horizonte' => self::HORIZONTE,
            'supuestos' => $datos['supuestos'] ?? null, 'equivalencia' => $equivalencia,
            'advertencias' => $escenario->estado_tasa === 'validada' ? [] : ['La TMAR del escenario no está validada documentalmente.'],
            'referencia' => $referencia, 'alternativas' => $alternativas, 'origenes' => $origenes,
        ];

        if ($esFiscal) {
            $resultado = $this->fiscal->aplicar($proyecto, $resultado, $datos['fiscal']);
        }
        $resultado['comparaciones'] = $this->comparacion->comparar($resultado['alternativas'], $referencia, $equivalencia);

        return $resultado;
    }

    private function valorarAlternativa(array $resultado, string $perspectiva, BigDecimal $tasa, BigDecimal $anual, int $horizonte, array &$origenes): array
    {
        $periodos = $datos = [];
        $avisos = $resultado['avisos'];
        $incluidos = 0;
        foreach ($resultado['periodos'] as $numero => $periodo) {
            $beneficios = $costos = $comun = BigDecimal::zero();
            $movimientos = [];
            $detallesContables = [];

            $ordenados = $periodo['movimientos'];
            usort($ordenados, fn (array $a, array $b) => ($a['fecha'] <=> $b['fecha'])
                ?: (($b['clasificacion'] === 'comun') <=> ($a['clasificacion'] === 'comun')));

            foreach ($ordenados as $movimiento) {
                $captura = $this->capturar($movimiento, $origenes);
                $naturaleza = $movimiento['naturaleza'];
                if (in_array($naturaleza, ['costo_oportunidad', 'ahorro_estimado'], true)) {
                    if ($perspectiva === 'caja') {
                        $captura['motivo'] = 'Valoración económica fuera de caja.';
                    } elseif (! $this->justificado($movimiento)) {
                        $captura['motivo'] = 'Valoración económica sin evidencia ni justificación registrada.';
                        $avisos[] = 'Justifica la valoración de «'.$movimiento['nombre'].'» para incorporarla.';
                    }
                }

                // Un mismo detalle contable, fecha, dirección e importe se cuenta una vez.
                if ($captura['motivo'] === null && $movimiento['concepto']) {
                    $referencias = $movimiento['concepto']->fuentes->pluck('id_detalle')->filter()->unique()->all();
                    $claves = array_map(fn ($id) => implode('|', [$id, $captura['fecha'], $captura['direccion'], (string) $movimiento['monto']->strippedOfTrailingZeros()]), $referencias);
                    foreach ($claves as $clave) {
                        if (isset($detallesContables[$clave])) {
                            $captura['motivo'] = 'Importe ya registrado con el mismo detalle contable, fecha y dirección.';
                            $avisos[] = 'Se detectó un posible duplicado contable en «'.$movimiento['nombre'].'». Revisa la asignación.';

                            break;
                        }
                    }
                    if ($captura['motivo'] === null) {
                        foreach ($claves as $clave) {
                            $detallesContables[$clave] = true;
                        }
                    }
                }

                $movimientos[] = $captura;
                if ($captura['motivo'] !== null) {
                    continue;
                }
                $incluidos++;
                $monto = $movimiento['monto'];
                $entrada = $movimiento['direccion'] === 'entrada';
                if ($entrada) {
                    $beneficios = $beneficios->plus($monto);
                } else {
                    $costos = $costos->plus($monto);
                }
                if ($movimiento['clasificacion'] === 'comun') {
                    $comun = $comun->plus($entrada ? $monto : $monto->negated());
                }
            }

            $datos[$numero] = compact('beneficios', 'costos');
            $periodos[$numero] = [
                'desde' => $periodo['desde']->toDateString(), 'hasta' => $periodo['hasta']->toDateString(),
                'neto_comun' => (string) $comun, 'movimientos' => $movimientos,
            ];
        }

        if ($incluidos === 0) {
            $avisos[] = 'No hay movimientos incluidos en esta perspectiva. Un total de cero no acredita ausencia de costos.';
        }

        $indicadores = $this->indicadores->calcular($datos, $tasa, $anual, $horizonte);
        foreach ($indicadores['periodos'] as $numero => $fila) {
            $periodos[$numero] += $fila;
        }
        unset($indicadores['periodos']);

        $excluidos = [];
        foreach ($resultado['excluidos'] as $movimiento) {
            $excluidos[] = $this->capturar($movimiento, $origenes);
        }

        return [
            'nombre' => $resultado['alternativa']->nombre, 'viabilidad' => $resultado['alternativa']->viabilidad_tecnica,
            'observaciones' => $resultado['alternativa']->observaciones,
            'indicadores' => $indicadores, 'periodos' => $periodos,
            'avisos' => array_values(array_unique($avisos)), 'excluidos' => $excluidos,
        ];
    }

    private function justificado(array $movimiento): bool
    {
        $concepto = $movimiento['concepto'];

        return $concepto
            ? ($concepto->fuentes->isNotEmpty() || trim($concepto->observaciones ?? '') !== '')
            : trim($movimiento['ajuste']?->justificacion ?? '') !== '';
    }

    private function capturar(array $movimiento, array &$origenes): array
    {
        $concepto = $movimiento['concepto'];
        $ajuste = $movimiento['ajuste'];
        $clave = $concepto ? 'c'.$concepto->id : 'a'.$ajuste->id;
        if (! isset($origenes[$clave])) {
            $origenes[$clave] = $concepto ? [
                'nombre' => $concepto->nombre, 'monto' => $concepto->simulacion_monto ?? $concepto->monto, 'descripcion' => $concepto->descripcion,
                'periodicidad' => $concepto->periodicidad, 'momento_pago' => $concepto->momento_pago,
                'inicio' => $concepto->periodo_inicio, 'fin' => $concepto->periodo_fin,
                'ocurrencias' => $concepto->numero_ocurrencias, 'crecimiento' => $concepto->simulacion_crecimiento ?? $concepto->porcentaje_crecimiento,
                'observaciones' => $concepto->observaciones, 'activo_id' => $concepto->activo_id, 'activo_operacion' => $concepto->activo_operacion,
                'fuentes' => $concepto->fuentes->sortBy('id')->values()->map(fn ($fuente) => [
                    'tipo' => $fuente->tipo, 'referencia' => $fuente->referencia, 'fecha' => $fuente->fecha->toDateString(),
                    'id_documento' => $fuente->id_documento, 'id_detalle' => $fuente->id_detalle,
                    'observado' => $fuente->importe_observado, 'utilizado' => $fuente->importe_utilizado,
                    'justificacion' => $fuente->justificacion,
                ])->all(),
            ] : [
                'nombre' => $ajuste->nombre, 'monto' => $ajuste->monto, 'observaciones' => $ajuste->justificacion,
                'id_usuario' => $ajuste->id_usuario, 'fuentes' => [],
            ];
        }

        return [
            'origen' => $clave, 'fecha' => $movimiento['fecha']->toDateString(), 'monto' => (string) $movimiento['monto'],
            'tipo' => $movimiento['tipo'], 'naturaleza' => $movimiento['naturaleza'], 'direccion' => $movimiento['direccion'],
            'clasificacion' => $movimiento['clasificacion'], 'estado' => $movimiento['estado'], 'observado' => $movimiento['observado'],
            'motivo' => $movimiento['motivo'] ?? null,
        ];
    }
}
