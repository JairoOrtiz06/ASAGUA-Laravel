<?php

declare(strict_types=1);

namespace App\Services\IngenieriaEconomica;

use App\Models\IngenieriaEconomica\Escenario;
use App\Models\IngenieriaEconomica\Proyecto;
use Brick\Math\BigDecimal;
use DomainException;

class SensibilidadService
{
    public const VERSION = '6.0.0';

    public const INDICADORES = ['van' => 'VAN', 'vpc' => 'VPC', 'caue' => 'CAUE', 'rcb' => 'RCB', 'tir' => 'TIR'];

    public const MODOS = ['unidimensional' => 'Una variable', 'matriz' => 'Matriz de dos variables', 'escenarios' => 'Optimista, base y pesimista', 'equilibrio' => 'Punto de equilibrio'];

    private const MAX_CASOS = 100;

    public function __construct(
        private VariablesSensibilidadService $variables,
        private EvaluacionService $evaluacion,
        private BaseSensibilidadService $bases,
        private PuntoEquilibrioService $equilibrio,
        private ComparacionIncrementalService $comparacion,
    ) {}

    public function generar(array $base, array $d): array
    {
        $ids = array_map('intval', $d['alternativas']);
        $catalogo = $this->variables->catalogo($base, $ids, $d['moneda']);
        $normal = $this->simular($base, $d, $catalogo, [])['resultado'];
        $this->bases->comprobar($base, $normal);
        foreach ($normal['alternativas'] as $id => &$alternativa) {
            $alternativa['avisos'] = array_values(array_unique([...$alternativa['avisos'], ...($base['comparar']['avisos'][$id] ?? [])]));
        }
        unset($alternativa);
        [$casos, $ejes] = $this->plan($d);
        $punto = null;
        $cruces = [];
        $simulaciones = [];
        if (in_array($d['modo'], ['matriz', 'equilibrio'], true) && count($ids) !== 2) {
            throw new DomainException('Selecciona exactamente dos alternativas para la matriz o el equilibrio.');
        }

        if ($d['modo'] === 'equilibrio') {
            $eje = $d['ejes'][0];
            if ($eje['variable'] === 'horizonte') {
                throw new DomainException('El horizonte es discreto. Usa la sensibilidad de una variable para compararlo.');
            }
            $minimo = $this->variables->numero($eje['min'] ?? null);
            $maximo = $this->variables->numero($eje['max'] ?? null);
            if ($minimo->isGreaterThanOrEqualTo($maximo)) {
                throw new DomainException('El intervalo de equilibrio debe tener máximo mayor que mínimo.');
            }
            $this->presupuesto($base, $d, PuntoEquilibrioService::MAX_CONSULTAS, []);
            $criterio = $d['criterio'] ?? 'vpc';
            $punto = $this->equilibrio->buscar($minimo, $maximo, function (BigDecimal $x) use ($base, $d, $catalogo, $eje, $criterio): BigDecimal {
                $cambio = $eje + ['valor' => (string) $x->toScale(12, \Brick\Math\RoundingMode::HalfUp)];
                $r = $this->simular($base, $d, $catalogo, [$cambio])['resultado'];

                return BigDecimal::of($r['comparaciones'][0]['diferencia_'.$criterio]);
            });
            $punto['variable'] = $catalogo[$eje['variable']] ?? throw new DomainException('Variable no disponible.');
            $punto['modo'] = $eje['modo'];
            $punto['criterio'] = $criterio;
            $punto['es_horas_absolutas'] = str_starts_with($eje['variable'], 'horas:') && $eje['modo'] === 'absoluto';
            $punto['par'] = $this->par($normal);
            foreach ($punto['cruces'] as $i => $cruce) {
                $casos[] = ['nombre' => 'Equilibrio '.($i + 1), 'cambios' => [$eje + ['valor' => (string) BigDecimal::of($cruce['valor'])->toScale(12, \Brick\Math\RoundingMode::HalfUp)]], 'supuesto' => null];
            }
        }

        $this->presupuesto($base, $d, count($casos), $casos);
        foreach ($casos as $indice => $caso) {
            $s = $this->simular($base, $d, $catalogo, $caso['cambios']);
            $simulaciones[$indice] = $this->resumir($s, $normal, $caso);
        }
        if ($d['modo'] === 'matriz') {
            $cruces = $this->crucesMatriz($simulaciones, $ejes);
        }

        return [
            'version' => self::VERSION, 'configuracion' => $d, 'insumo_base' => $base,
            'base' => $normal, 'simulaciones' => $simulaciones, 'ejes' => $ejes, 'equilibrio' => $punto, 'cruces_matriz' => $cruces,
            'advertencias' => [...$base['advertencias'], ...$normal['advertencias'],
                'Todos los valores modificados son supuestos de simulación. Las diferencias de costo están condicionadas a la equivalencia técnica.',
                'La TIR depende de los flujos nominales. Cambiar solo la TMAR conserva sus raíces y estados.'],
        ];
    }

    public function memoria(array $analisis, int $indice): array
    {
        if ($analisis['version'] !== self::VERSION || $analisis['base']['version'] !== EvaluacionService::VERSION || ! isset($analisis['simulaciones'][$indice])) {
            throw new DomainException('No se puede reproducir la memoria solicitada con esta versión del motor.');
        }
        $base = $analisis['insumo_base'];
        $d = $analisis['configuracion'];
        $catalogo = $this->variables->catalogo($base, array_map('intval', $d['alternativas']), $d['moneda']);

        return $this->simular($base, $d, $catalogo, $analisis['simulaciones'][$indice]['cambios'])['resultado'];
    }

    private function simular(array $base, array $d, array $catalogo, array $cambios): array
    {
        $copia = $this->variables->aplicar($base, $catalogo, $cambios, $d['oportunidad'] ?? [], $d['moneda']);
        $proyecto = (new Proyecto)->setRawAttributes($base['proyecto']);
        $escenario = (new Escenario)->setRawAttributes($copia['escenario']);
        $referencia = $d['referencia_id'] ?? $d['alternativas'][1] ?? $d['alternativas'][0];
        $resultado = $this->evaluacion->preparar($proyecto, $escenario, [
            'alternativas' => $d['alternativas'], 'perspectiva' => $d['perspectiva'], 'periodicidad' => $d['periodicidad'],
            'moneda' => $d['moneda'], 'referencia_id' => $referencia, 'tasa_simulada' => $copia['tasa'], 'supuestos' => $d['supuestos'],
        ], $copia['insumos'], incluirInsumos: false);

        return ['resultado' => $resultado, 'variables' => $copia['variaciones'], 'laboral' => $copia['memoriaLaboral']];
    }

    private function plan(array $d): array
    {
        if ($d['modo'] === 'equilibrio') {
            return [[], []];
        }
        if ($d['modo'] === 'escenarios') {
            $casos = [];
            foreach (['optimista' => 'Optimista', 'pesimista' => 'Pesimista'] as $clave => $nombre) {
                $escenario = $d['escenarios'][$clave] ?? [];
                $cambios = array_values(array_filter($escenario['cambios'] ?? [], fn ($c) => ! empty($c['variable'])));
                if ($cambios === [] || empty($escenario['justificacion'])) {
                    throw new DomainException('Cada escenario optimista y pesimista requiere cambios explícitos y una justificación.');
                }
                $casos[] = ['nombre' => $nombre, 'cambios' => $cambios, 'supuesto' => $escenario['justificacion']];
            }

            return [$casos, []];
        }
        $ejes = [$this->valores($d['ejes'][0])];
        if ($d['modo'] === 'matriz') {
            $ejes[] = $this->valores($d['ejes'][1]);
        }
        if (count($ejes[0]) * count($ejes[1] ?? [null]) > self::MAX_CASOS) {
            throw new DomainException('La matriz admite como máximo 100 simulaciones.');
        }
        $casos = [];
        foreach ($ejes[0] as $i => $v) {
            foreach ($ejes[1] ?? [null] as $j => $w) {
                $cambios = [$d['ejes'][0] + ['valor' => $v]];
                if ($w !== null) {
                    $cambios[] = $d['ejes'][1] + ['valor' => $w];
                }
                $casos[] = ['nombre' => $w === null ? $v : "$v × $w", 'cambios' => $cambios, 'supuesto' => null, 'x' => $i, 'y' => $j];
            }
        }

        return [$casos, $ejes];
    }

    private function valores(array $eje): array
    {
        if (! empty($eje['valores'])) {
            $partes = preg_split('/[\s,;]+/', trim($eje['valores']), flags: PREG_SPLIT_NO_EMPTY);
            $valores = array_values(array_unique(array_map(
                fn ($v) => (string) $this->variables->numero($v)->strippedOfTrailingZeros(), $partes,
            )));
        } else {
            $min = $this->variables->numero($eje['min'] ?? null);
            $max = $this->variables->numero($eje['max'] ?? null);
            $cantidad = (int) ($eje['cantidad'] ?? 0);
            $paso = isset($eje['paso']) ? $this->variables->numero($eje['paso']) : null;
            if ($max->isLessThan($min)) {
                throw new DomainException('Intervalo inválido. El máximo debe ser igual o mayor que el mínimo.');
            }
            if ($paso && ! $paso->isPositive()) {
                throw new DomainException('El incremento debe ser mayor que cero.');
            }
            if (! $paso && $cantidad < 2) {
                throw new DomainException('Indica un incremento positivo o al menos dos valores distribuidos en el intervalo.');
            }
            $valores = [];
            for ($i = 0; $i <= self::MAX_CASOS; $i++) {
                if (! $paso && $i >= $cantidad) {
                    break;
                }
                $v = $paso ? $min->plus($paso->multipliedBy($i))
                    : $min->plus($max->minus($min)->multipliedBy($i)->dividedBy($cantidad - 1, 12, \Brick\Math\RoundingMode::HalfUp));
                if ($v->isGreaterThan($max)) {
                    break;
                }
                $valores[] = (string) $v->strippedOfTrailingZeros();
                if ($paso && $cantidad > 0 && count($valores) >= $cantidad) {
                    break;
                }
            }
        }
        if ($valores === [] || count($valores) > self::MAX_CASOS) {
            throw new DomainException('Cada eje debe tener de 1 a 100 valores.');
        }
        usort($valores, fn ($a, $b) => BigDecimal::of($a)->compareTo($b));

        return $valores;
    }

    private function presupuesto(array $base, array $d, int $cantidad, array $casos): void
    {
        $h = $base['escenario']['horizonte_anios'];
        foreach ($casos as $caso) {
            foreach ($caso['cambios'] as $c) {
                if ($c['variable'] === 'horizonte') {
                    $valor = $this->variables->numero($c['valor']);
                    $simulado = $c['modo'] === 'relativo'
                        ? BigDecimal::of($base['escenario']['horizonte_anios'])->multipliedBy($valor->withPointMovedLeft(2)->plus(1)) : $valor;
                    $h = max($h, min(100, $simulado->toFloat()));
                }
            }
        }
        if ($cantidad * $h * 12 * count($d['alternativas']) > 90000) {
            throw new DomainException('Reduce el número de alternativas, el horizonte o la cantidad de simulaciones.');
        }
    }

    private function resumir(array $s, array $base, array $caso): array
    {
        $r = $s['resultado'];
        $alternativas = [];
        foreach ($r['alternativas'] as $id => $a) {
            $deltas = $porcentajes = $elasticidad = [];
            foreach (['van', 'vpc', 'caue', 'rcb'] as $clave) {
                $actual = $a['indicadores'][$clave];
                $anterior = $base['alternativas'][$id]['indicadores'][$clave];
                $deltas[$clave] = $actual === null || $anterior === null ? null : (string) BigDecimal::of($actual)->minus($anterior);
                $porcentajes[$clave] = $deltas[$clave] === null || BigDecimal::of($anterior)->isZero() ? null
                    : (string) DecimalFinanciero::dividir(BigDecimal::of($deltas[$clave])->multipliedBy(100), BigDecimal::of($anterior));
                $elasticidad[$clave] = $this->relativa($anterior, $actual, $s['variables']);
            }
            $alternativas[$id] = ['indicadores' => $a['indicadores'], 'deltas' => $deltas, 'cambios_pct' => $porcentajes, 'elasticidades' => $elasticidad, 'avisos' => $a['avisos']];
        }

        $orden = array_keys($alternativas);
        usort($orden, fn ($a, $b) => BigDecimal::of($alternativas[$a]['indicadores']['vpc'])->compareTo($alternativas[$b]['indicadores']['vpc']));
        $menor = count($orden) < 2 || BigDecimal::of($alternativas[$orden[0]]['indicadores']['vpc'])->isEqualTo($alternativas[$orden[1]]['indicadores']['vpc'])
            ? null : $orden[0];

        return $caso + [
            'variables' => $s['variables'], 'laboral' => $s['laboral'], 'alternativas' => $alternativas, 'menor_vpc' => $menor,
            'lectura_costos' => $this->lecturaCostos($r, $alternativas, $menor),
            'tasa' => $r['escenario']['tasa'], 'anual' => $r['escenario']['anual'], 'horizonte' => $r['escenario']['horizonte'],
            'comparaciones' => array_map(fn ($c) => array_diff_key($c, ['periodos' => true]), $r['comparaciones']),
        ];
    }

    public function lecturaCostos(array $evaluacion, array $alternativas, ?int $menor, bool $historica = false): array
    {
        $criterio = $this->comparacion->interpretar($evaluacion);
        $candidatas = $criterio['candidatas'];
        if ($historica) {
            $candidatas = array_filter($candidatas, fn ($id) => isset($alternativas[$id])
                && ! BigDecimal::of($alternativas[$id]['indicadores']['vpc'])->isZero()
                && ! array_diff($alternativas[$id]['avisos'] ?? [], $evaluacion['alternativas'][$id]['avisos']));
        }

        $suficientes = count($candidatas) === count($alternativas) && count($alternativas) >= 2;

        return [
            'menor' => $suficientes ? $menor : null,
            'estado' => ! $suficientes ? 'Sin clasificación por costo' : ($menor === null ? 'Igual VPC' : 'Menor VPC'),
            'preliminar' => $criterio['preliminar'] || ! $suficientes,
            'excluidas' => array_values(array_diff(array_keys($alternativas), $candidatas)),
        ];
    }

    public function relativa(?string $anterior, ?string $actual, array $variables): array
    {
        $motivo = match (true) {
            count($variables) !== 1 => 'Se requiere una sola variable.',
            $anterior === null || $actual === null => 'El indicador no es aplicable.',
            BigDecimal::of($anterior)->isZero() || $variables[0]['cambio_pct'] === null => 'La base es cero.',
            BigDecimal::of($anterior)->getSign() !== BigDecimal::of($actual)->getSign() => 'El indicador cambia de signo o llega a cero.',
            BigDecimal::of($variables[0]['cambio_pct'])->abs()->isLessThan('0.0000001') => 'El cambio porcentual es nulo o demasiado pequeño.',
            default => null,
        };
        if ($motivo) {
            return ['valor' => null, 'motivo' => $motivo, 'cambio_indicador' => null];
        }
        $cambio = DecimalFinanciero::dividir(BigDecimal::of($actual)->minus($anterior)->multipliedBy(100), BigDecimal::of($anterior));

        return ['valor' => (string) DecimalFinanciero::dividir($cambio, BigDecimal::of($variables[0]['cambio_pct'])), 'motivo' => null, 'cambio_indicador' => (string) $cambio];
    }

    private function crucesMatriz(array $simulaciones, array $ejes): array
    {
        $cruces = [];
        foreach ($simulaciones as $i => $s) {
            $v = BigDecimal::of($s['comparaciones'][0]['diferencia_vpc']);
            $y = BigDecimal::of($ejes[1][$s['y']]);
            if ($v->isZero()) {
                $cruces[] = [$ejes[0][$s['x']], (string) $y];

                continue;
            }
            if ($s['y'] === 0) {
                continue;
            }
            $a = BigDecimal::of($simulaciones[$i - 1]['comparaciones'][0]['diferencia_vpc']);
            if ($a->isZero() || $a->getSign() === $v->getSign()) {
                continue;
            }
            $y0 = BigDecimal::of($ejes[1][$s['y'] - 1]);
            $estimado = $y0->plus(DecimalFinanciero::dividir($a->multipliedBy($y->minus($y0)), $a->minus($v)));
            $cruces[] = [$ejes[0][$s['x']], (string) $estimado];
        }

        return $cruces;
    }

    private function par(array $base): array
    {
        $comparacion = $base['comparaciones'][0];

        return [$base['alternativas'][$comparacion['id_a']]['nombre'], $base['alternativas'][$comparacion['id_b']]['nombre']];
    }
}
