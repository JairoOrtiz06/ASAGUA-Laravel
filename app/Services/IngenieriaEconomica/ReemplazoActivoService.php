<?php

declare(strict_types=1);

namespace App\Services\IngenieriaEconomica;

use App\Models\IngenieriaEconomica\Activo;
use App\Models\IngenieriaEconomica\Escenario;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use DomainException;

class ReemplazoActivoService
{
    public const VERSION = '5.0.0';

    public const POLITICA = 'Horizonte finito común. Se reconocen la venta del activo actual y los costos de retiro inmediato solo al reemplazar. Conservar renuncia a ese ingreso neto. No se registra ese costo de oportunidad por segunda vez.';

    public function __construct(
        private ConversionTasasService $tasas,
        private ComparacionIncrementalService $comparacion,
        private IndicadoresFinancierosService $indicadores,
    ) {}

    public function calcular(Activo $existente, ?Activo $nuevo, Escenario $escenario, array $d): array
    {
        if ((int) $escenario->proyecto_id !== (int) $existente->proyecto_id
            || ($nuevo && ((int) $nuevo->proyecto_id !== (int) $existente->proyecto_id || $nuevo->id === $existente->id))) {
            throw new DomainException('Escenario y activos deben pertenecer al mismo proyecto y los activos deben ser distintos.');
        }
        if ($nuevo && $nuevo->moneda !== $existente->moneda) {
            throw new DomainException('Registra ambas alternativas en la misma moneda.');
        }
        if ($escenario->tasa_efectiva_anual === null) {
            throw new DomainException('Configura la TMAR del escenario antes de comparar.');
        }
        $n = $escenario->horizonte_anios;
        if ($n < 1 || $n > ProyeccionFlujosService::MAX_ANIOS) {
            throw new DomainException('El horizonte de reemplazo debe estar entre 1 y '.ProyeccionFlujosService::MAX_ANIOS.' años.');
        }

        $base = ($escenario->fecha_base ?? $existente->proyecto->fecha_base)->toImmutable();
        $anual = BigDecimal::of($escenario->tasa_efectiva_anual);
        $tasa = $this->tasas->convertir((string) $anual, 'efectiva', 'anual', $escenario->periodicidad);
        $m = $this->tasas->frecuencia($escenario->periodicidad);
        $ventaNeta = BigDecimal::of($d['venta_actual'])->minus($d['retiro_ahora']);
        $costoNuevo = BigDecimal::of($d['precio'])->plus($d['instalacion'])->plus($d['puesta_marcha']);
        $conservar = $this->opcion($d['conservar'], [], $d['politica'], $n, $m, $base, $tasa, $anual);
        $reemplazar = $this->opcion($d['reemplazar'], [
            ['Compra del activo nuevo', 'costos', BigDecimal::of($d['precio'])],
            ['Instalación', 'costos', BigDecimal::of($d['instalacion'])],
            ['Puesta en marcha', 'costos', BigDecimal::of($d['puesta_marcha'])],
            ['Venta del activo actual', 'beneficios', BigDecimal::of($d['venta_actual'])],
            ['Retiro inmediato', 'costos', BigDecimal::of($d['retiro_ahora'])],
        ], $d['politica'], $n, $m, $base, $tasa, $anual);
        $viabilidades = array_filter([$existente->alternativa?->viabilidad_tecnica, $nuevo?->alternativa?->viabilidad_tecnica]);
        $sinCostos = BigDecimal::of($conservar['vpc'])->isZero() || BigDecimal::of($reemplazar['vpc'])->isZero();
        $criterio = $this->comparacion->restriccionTecnica($viabilidades, (bool) ($d['equivalencia'] ?? false))
            ?? match (true) {
                $sinCostos && (empty($d['ceros_documentados']) || blank($d['valoracion_fuente'] ?? null))
                    => 'Comparación preliminar. Un costo cero requiere respaldo antes de identificar una opción de menor costo.',
                $escenario->estado_tasa !== 'validada' => 'Comparación preliminar. Confirma la TMAR antes de recomendar una opción.',
                default => 'Con servicio equivalente, menor CAUE neto indica menor costo anual dentro del horizonte documentado.',
            };
        $incremental = [];
        foreach ($reemplazar['periodos'] as $periodo => $b) {
            $a = $conservar['periodos'][$periodo];
            $incremental[$periodo] = [
                'fecha' => $b['fecha'], 'factor' => $b['factor'],
                'neto' => (string) BigDecimal::of($b['neto'])->minus($a['neto']),
                'vp' => (string) BigDecimal::of($b['vp'])->minus($a['vp']),
            ];
        }

        return [
            'estado' => 'calculado', 'version' => self::VERSION,
            'activo' => $existente->only(['id', 'nombre', 'moneda', 'clasificacion_contable']),
            'nuevo' => $nuevo?->only(['id', 'nombre', 'moneda', 'clasificacion_contable']),
            'escenario' => [
                'id' => $escenario->id, 'nombre' => $escenario->nombre, 'base' => $base->toDateString(),
                'cierre' => $base->addYearsNoOverflow($n)->toDateString(), 'horizonte' => $n,
                'periodicidad' => $escenario->periodicidad, 'tasa_periodo' => (string) $tasa,
                'tasa_anual' => (string) $anual, 'estado_tasa' => $escenario->estado_tasa, 'fuente_tasa' => $escenario->fuente_tasa,
            ],
            'parametros' => $d,
            'convencion' => self::POLITICA,
            'criterio' => $criterio,
            'mercado_actual' => (string) BigDecimal::of($d['mercado_actual']),
            'oportunidad_conservar' => (string) $ventaNeta,
            'inversion_adicional' => (string) $costoNuevo->minus($ventaNeta),
            'conservar' => $conservar, 'reemplazar' => $reemplazar, 'incremental' => $incremental,
            'van_incremental' => (string) BigDecimal::of($reemplazar['van'])->minus($conservar['van']),
            'diferencia_caue' => (string) BigDecimal::of($reemplazar['caue_neto'])->minus($conservar['caue_neto']),
            'vidas_economicas' => [
                'conservar' => $this->vidaEconomica($d['conservar'], $ventaNeta, $anual),
                'reemplazar' => $this->vidaEconomica($d['reemplazar'], $costoNuevo, $anual),
            ],
        ];
    }

    private function opcion(array $datos, array $inicial, string $politica, int $n, int $m, CarbonImmutable $base, BigDecimal $tasa, BigDecimal $anual): array
    {
        $reposiciones = $this->leerFilas($datos['reposiciones'] ?? '', true, $n - 1);
        if ($reposiciones && $politica !== 'reposiciones_documentadas') {
            throw new DomainException('Selecciona la política de reposiciones documentadas para incluir reposiciones.');
        }

        $costos = $datos;
        $vigencia = (int) $datos['vida'];
        $movimientos = [0 => $inicial];
        for ($anio = 1; $anio <= $n; $anio++) {
            if ($anio > $vigencia) {
                throw new DomainException('Una alternativa agota su vida antes del cierre. Reduce el horizonte común o documenta una reposición antes de quedar sin servicio.');
            }
            $periodo = $anio * $m;
            $movimientos[$periodo] = [
                ['Operación', 'costos', BigDecimal::of($costos['operacion'])],
                ['Mantenimiento', 'costos', BigDecimal::of($costos['mantenimiento'])],
            ];
            if (isset($reposiciones[$anio])) {
                $r = $reposiciones[$anio];
                $movimientos[$periodo][] = ['Adquisición de reposición', 'costos', BigDecimal::of($r['compra'])];
                $movimientos[$periodo][] = ['Venta del activo sustituido', 'beneficios', BigDecimal::of($r['rescate'])];
                $movimientos[$periodo][] = ['Retiro del activo sustituido', 'costos', BigDecimal::of($r['retiro'])];
                $costos = $r;
                $vigencia = $anio + $r['vida'];
            }
        }
        $movimientos[$n * $m][] = ['Valor terminal', 'beneficios', BigDecimal::of($datos['rescate'])];
        $movimientos[$n * $m][] = ['Retiro al cierre', 'costos', BigDecimal::of($datos['retiro'])];

        return $this->valorar($movimientos, $n, $m, $base, $tasa, $anual);
    }

    private function valorar(array $movimientos, int $n, int $m, ?CarbonImmutable $base, BigDecimal $tasa, BigDecimal $anual): array
    {
        $flujos = [];
        foreach ($movimientos as $periodo => $eventos) {
            $flujos[$periodo] = ['beneficios' => BigDecimal::zero(), 'costos' => BigDecimal::zero()];
            foreach ($eventos as [, $tipo, $importe]) {
                $flujos[$periodo][$tipo] = $flujos[$periodo][$tipo]->plus($importe);
            }
        }

        $resultado = $this->indicadores->calcular($flujos, $tasa, $anual, $n, incluirTir: false);
        $neto = BigDecimal::of($resultado['van'])->negated();
        $resultado['vpc_neto'] = (string) $neto;
        $resultado['caue_neto'] = (string) $neto->multipliedBy($resultado['factor_ap']);
        foreach ($resultado['periodos'] as $periodo => &$fila) {
            $fila['fecha'] = $base?->addMonthsNoOverflow(intdiv($periodo * 12, $m))->toDateString();
            $fila['eventos'] = array_map(fn (array $evento) => [$evento[0], $evento[1], (string) $evento[2]], $movimientos[$periodo]);
        }
        unset($fila, $resultado['tir']);

        return $resultado;
    }

    private function vidaEconomica(array $datos, BigDecimal $inicial, BigDecimal $anual): array
    {
        $candidatos = $this->leerFilas($datos['candidatos'] ?? '', false, (int) $datos['vida']);
        $filas = [];
        $mejor = null;
        foreach ($candidatos as $edad => $terminal) {
            $movimientos = [0 => [['Costo o valor de oportunidad inicial', $inicial->isNegative() ? 'beneficios' : 'costos', $inicial->abs()]]];
            $operacion = BigDecimal::of($datos['operacion'])->plus($datos['mantenimiento']);
            for ($i = 1; $i <= $edad; $i++) {
                $movimientos[$i] = [['Operación y mantenimiento', 'costos', $operacion]];
            }
            $movimientos[$edad][] = ['Rescate a la edad candidata', 'beneficios', BigDecimal::of($terminal['rescate'])];
            $movimientos[$edad][] = ['Retiro', 'costos', BigDecimal::of($terminal['retiro'])];
            $r = $this->valorar($movimientos, $edad, 1, null, $anual, $anual);
            $filas[$edad] = $terminal + ['vpc_neto' => $r['vpc_neto'], 'caue_neto' => $r['caue_neto'], 'factor_ap' => $r['factor_ap']];
            if ($mejor === null || BigDecimal::of($r['caue_neto'])->isLessThan($filas[$mejor]['caue_neto'])) {
                $mejor = $edad;
            }
        }

        return ['filas' => $filas, 'menor_caue' => $mejor];
    }

    private function leerFilas(string $texto, bool $reposicion, int $maximo): array
    {
        $campos = $reposicion
            ? ['compra', 'rescate', 'retiro', 'vida', 'operacion', 'mantenimiento', 'nota']
            : ['rescate', 'retiro', 'nota'];
        $filas = [];
        foreach (preg_split('/\R/', trim($texto), flags: PREG_SPLIT_NO_EMPTY) as $linea) {
            $partes = array_map('trim', explode(';', $linea));
            $anio = array_shift($partes);
            if (count($partes) !== count($campos) || ! ctype_digit($anio) || (int) $anio < 1 || (int) $anio > $maximo || isset($filas[(int) $anio])) {
                throw new DomainException('Revisa el formato y los años de las reposiciones o edades candidatas. Cada año debe ser válido y único.');
            }

            $fila = array_combine($campos, $partes);
            foreach ($fila as $campo => $valor) {
                $valido = match ($campo) {
                    'nota' => $valor !== '',
                    'vida' => ctype_digit($valor) && (int) $valor >= 1 && (int) $valor <= 100,
                    default => preg_match('/^\d{1,14}(?:\.\d{1,6})?$/D', $valor) === 1,
                };
                if (! $valido) {
                    throw new DomainException('Las reposiciones y edades requieren importes válidos, vida positiva cuando corresponda y una justificación.');
                }
            }
            $filas[(int) $anio] = $fila;
        }
        ksort($filas, SORT_NUMERIC);

        return $filas;
    }
}
