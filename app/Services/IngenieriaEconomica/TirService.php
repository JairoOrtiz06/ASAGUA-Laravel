<?php

declare(strict_types=1);

namespace App\Services\IngenieriaEconomica;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

class TirService
{
    public const MINIMA = '-0.99';
    public const MAXIMA = '10';
    public const TOLERANCIA = '0.000000000001';
    public const MAX_ITERACIONES = 100;

    private const MUESTRAS = 360;

    private array $cache = [];

    /** @param list<BigDecimal> $flujos */
    public function resolver(array $flujos, int $maxIteraciones = self::MAX_ITERACIONES): array
    {
        $clave = hash('sha256', $maxIteraciones.':'.implode(';', array_map(
            fn (BigDecimal $flujo) => (string) $flujo->strippedOfTrailingZeros(), $flujos,
        )));
        if (count($this->cache) >= 128) {
            $this->cache = [];
        }

        return $this->cache[$clave] ??= $this->buscar($flujos, $maxIteraciones);
    }

    /** @param list<BigDecimal> $flujos */
    private function buscar(array $flujos, int $maxIteraciones): array
    {
        $signos = array_values(array_filter(array_map(fn (BigDecimal $monto) => $monto->getSign(), $flujos)));
        $variaciones = 0;
        for ($i = 1; $i < count($signos); $i++) {
            $variaciones += (int) ($signos[$i] !== $signos[$i - 1]);
        }
        $resultado = [
            'estado' => 'insuficiente', 'raices' => [], 'variaciones' => $variaciones,
            'dominio' => [self::MINIMA, self::MAXIMA], 'tolerancia' => self::TOLERANCIA,
            'max_iteraciones' => $maxIteraciones, 'muestras' => self::MUESTRAS,
        ];
        if ($variaciones === 0) {
            return $resultado;
        }

        $originales = $flujos;

        // Las potencias de x que solo anteceden al primer movimiento no cambian las raíces positivas.
        while ($flujos[0]->isZero()) {
            array_shift($flujos);
        }
        while ($flujos[array_key_last($flujos)]->isZero()) {
            array_pop($flujos);
        }
        $escala = BigDecimal::max(...array_map(fn (BigDecimal $monto) => $monto->abs(), $flujos));
        $coeficientes = array_map(fn (BigDecimal $monto) => DecimalFinanciero::dividir($monto, $escala), $flujos);
        $aproximados = array_map(fn (BigDecimal $coef) => $coef->toFloat(), $coeficientes);
        $minimo = DecimalFinanciero::dividir(BigDecimal::one(), BigDecimal::of(self::MAXIMA)->plus(1));
        $maximo = DecimalFinanciero::dividir(BigDecimal::one(), BigDecimal::of(self::MINIMA)->plus(1));
        $puntos = $this->malla($minimo->toFloat(), $maximo->toFloat());
        $estacionarios = $variaciones > 1 ? $this->estacionarios($aproximados, [$minimo->toFloat(), ...$puntos, $maximo->toFloat()]) : [];
        $intervalo = array_merge($puntos, $estacionarios);
        sort($intervalo, SORT_NUMERIC);

        $raices = [];
        $noConverge = false;
        $anterior = $minimo;
        $signoAnterior = $this->evaluarDecimal($coeficientes, $anterior)->getSign();
        if ($signoAnterior === 0) {
            $raices[] = $this->raiz($minimo, $minimo, 0, 'límite');
        }

        // La malla localiza candidatos. Las raíces se refinan con bisección decimal.
        foreach ($intervalo as $x) {
            $actual = BigDecimal::of(json_encode($x, JSON_THROW_ON_ERROR));
            if ($actual->isLessThanOrEqualTo($minimo) || $actual->isGreaterThanOrEqualTo($maximo) || $actual->isEqualTo($anterior)) {
                continue;
            }
            $signo = $this->evaluarAproximado($aproximados, $x) <=> 0.0;
            if ($signo === 0 || $x === 1.0 || in_array($x, $estacionarios, true) || $signo !== $signoAnterior) {
                $signo = $this->evaluarDecimal($coeficientes, $actual)->getSign();
            }

            $this->examinar($coeficientes, $anterior, $actual, $signoAnterior, $signo, $maxIteraciones, $raices, $noConverge);
            $anterior = $actual;
            $signoAnterior = $signo;
        }
        $this->examinar(
            $coeficientes, $anterior, $maximo, $signoAnterior,
            $this->evaluarDecimal($coeficientes, $maximo)->getSign(), $maxIteraciones, $raices, $noConverge,
        );

        foreach ($estacionarios as $x) {
            $actual = BigDecimal::of(json_encode($x, JSON_THROW_ON_ERROR));
            if ($this->evaluarDecimal($coeficientes, $actual)->abs()->isLessThan('0.000000000000000000000001')) {
                $raices[] = $this->raiz($actual, $actual, 0, 'posible tangencia');
            }
        }
        usort($raices, fn (array $a, array $b) => BigDecimal::of($a['tasa'])->compareTo($b['tasa']));
        foreach ($raices as $raiz) {
            $previa = $resultado['raices'][array_key_last($resultado['raices'])]['tasa'] ?? null;
            if ($previa === null || BigDecimal::of($raiz['tasa'])->minus($previa)->abs()->isGreaterThan(self::TOLERANCIA)) {
                $x = DecimalFinanciero::dividir(BigDecimal::one(), BigDecimal::of($raiz['tasa'])->plus(1));
                $residual = BigDecimal::zero();
                foreach (array_reverse($originales) as $flujo) {
                    $residual = DecimalFinanciero::redondearBase($residual->multipliedBy($x)->plus($flujo));
                }
                $raiz['residual'] = (string) $residual;
                $resultado['raices'][] = $raiz;
            }
        }

        $cantidad = count($resultado['raices']);
        $resultado['estado'] = match (true) {
            $noConverge => 'no_convergencia',
            $cantidad === 0 => 'sin_raiz',
            $cantidad > 1 || $variaciones > 1 => 'posibles_multiples',
            default => 'unica',
        };

        return $resultado;
    }

    private function examinar(array $coeficientes, BigDecimal $anterior, BigDecimal $actual, int $signoAnterior, int $signo, int $max, array &$raices, bool &$noConverge): void
    {
        if ($signo === 0) {
            $raices[] = $this->raiz($actual, $actual, 0, 'muestra');

            return;
        }
        if ($signoAnterior === 0 || $signoAnterior === $signo) {
            return;
        }
        $izquierda = $this->evaluarDecimal($coeficientes, $anterior)->getSign();
        if ($izquierda === $signo || $izquierda === 0) {
            return;
        }

        $inferior = $anterior;
        $superior = $actual;
        for ($i = 1; $i <= $max; $i++) {
            $medio = $inferior->plus($superior)->dividedBy(2, 38, RoundingMode::HalfEven);
            $signoMedio = $this->evaluarDecimal($coeficientes, $medio)->getSign();
            if ($signoMedio === 0) {
                $raices[] = $this->raiz($medio, $medio, $i, 'bisección');

                return;
            }
            if (DecimalFinanciero::dividir($superior->minus($inferior), $inferior->multipliedBy($superior))->isLessThanOrEqualTo(self::TOLERANCIA)) {
                $raices[] = $this->raiz($inferior, $superior, $i, 'bisección');

                return;
            }
            if ($signoMedio === $izquierda) {
                $inferior = $medio;
            } else {
                $superior = $medio;
            }
        }
        $noConverge = true;
    }

    private function raiz(BigDecimal $inferior, BigDecimal $superior, int $iteraciones, string $metodo): array
    {
        $tasa = fn (BigDecimal $x) => DecimalFinanciero::dividir(BigDecimal::one(), $x)->minus(1);
        $desde = $tasa($superior);
        $hasta = $tasa($inferior);

        return [
            'tasa' => (string) $desde->plus($hasta)->dividedBy(2, 18, RoundingMode::HalfEven),
            'desde' => (string) $desde, 'hasta' => (string) $hasta,
            'iteraciones' => $iteraciones, 'metodo' => $metodo,
        ];
    }

    private function malla(float $minimo, float $maximo): array
    {
        $puntos = [1.0];
        $inicio = log($minimo);
        $paso = (log($maximo) - $inicio) / self::MUESTRAS;
        for ($i = 1; $i < self::MUESTRAS; $i++) {
            $puntos[] = exp($inicio + $paso * $i);
        }
        sort($puntos, SORT_NUMERIC);

        return $puntos;
    }

    private function estacionarios(array $coeficientes, array $puntos): array
    {
        $derivada = [];
        for ($i = 1; $i < count($coeficientes); $i++) {
            $derivada[] = $i * $coeficientes[$i];
        }
        $resultado = [];
        $anterior = array_shift($puntos);
        $signoAnterior = $this->evaluarAproximado($derivada, $anterior) <=> 0.0;
        foreach ($puntos as $punto) {
            $signo = $this->evaluarAproximado($derivada, $punto) <=> 0.0;
            if ($signo === 0) {
                $resultado[] = $punto;
            } elseif ($signoAnterior !== 0 && $signo !== $signoAnterior) {
                $bajo = $anterior;
                $alto = $punto;
                for ($i = 0; $i < 52; $i++) {
                    $medio = ($bajo + $alto) / 2;
                    if (($this->evaluarAproximado($derivada, $medio) <=> 0.0) === $signoAnterior) {
                        $bajo = $medio;
                    } else {
                        $alto = $medio;
                    }
                }
                $resultado[] = ($bajo + $alto) / 2;
            }
            $anterior = $punto;
            $signoAnterior = $signo;
        }

        return $resultado;
    }

    private function evaluarAproximado(array $coeficientes, float $x): float
    {
        // Para x > 1 se elimina el factor positivo x^grado antes de evaluar.
        if ($x > 1) {
            $x = 1 / $x;
        } else {
            $coeficientes = array_reverse($coeficientes);
        }
        $valor = 0.0;
        foreach ($coeficientes as $coeficiente) {
            $valor = $valor * $x + $coeficiente;
        }

        return $valor;
    }

    private function evaluarDecimal(array $coeficientes, BigDecimal $x): BigDecimal
    {
        if ($x->isGreaterThan(1)) {
            $x = DecimalFinanciero::dividir(BigDecimal::one(), $x);
        } else {
            $coeficientes = array_reverse($coeficientes);
        }
        $valor = BigDecimal::zero();
        foreach ($coeficientes as $coeficiente) {
            $valor = DecimalFinanciero::redondearBase($valor->multipliedBy($x)->plus($coeficiente));
        }

        return $valor;
    }
}
