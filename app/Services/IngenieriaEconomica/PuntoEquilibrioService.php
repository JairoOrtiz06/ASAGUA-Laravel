<?php

declare(strict_types=1);

namespace App\Services\IngenieriaEconomica;

use Brick\Math\BigDecimal;

class PuntoEquilibrioService
{
    public const TOLERANCIA = '0.000001';

    public const MUESTRAS = 16;

    public const MAX_CONSULTAS = 120;

    public function buscar(BigDecimal $minimo, BigDecimal $maximo, callable $evaluar): array
    {
        $paso = DecimalFinanciero::dividir($maximo->minus($minimo), BigDecimal::of(self::MUESTRAS));
        $puntos = $cruces = [];
        $consultas = 0;
        for ($i = 0; $i <= self::MUESTRAS; $i++) {
            $x = $i === self::MUESTRAS ? $maximo : $minimo->plus($paso->multipliedBy($i));
            $puntos[] = [$x, $evaluar($x)];
            $consultas++;
        }

        $formula = 'Δ(x) = costo presente o anual de A menos el de B. Se busca Δ(x) = 0.';
        $resultado = [
            'intervalo' => [(string) $minimo, (string) $maximo], 'tolerancia' => self::TOLERANCIA,
            'formula' => $formula, 'cruces' => [], 'muestras' => array_map(fn ($p) => array_map('strval', $p), $puntos),
        ];
        if (count(array_filter($puntos, fn ($p) => ! $p[1]->isZero())) === 0) {
            return $resultado + ['estado' => 'Indiferencia en todos los puntos muestreados.'];
        }

        $incompleto = false;
        foreach ($puntos as $i => $derecha) {
            if ($derecha[1]->isZero()) {
                $cruces[] = $this->cruce($derecha, $puntos[max(0, $i - 1)], $puntos[min(self::MUESTRAS, $i + 1)]);
            }
            if ($i === 0 || $derecha[1]->isZero() || $puntos[$i - 1][1]->isZero()
                || $derecha[1]->getSign() === $puntos[$i - 1][1]->getSign()) {
                continue;
            }
            $izquierda = $puntos[$i - 1];
            for ($j = 0; $j < 96 && $consultas < self::MAX_CONSULTAS; $j++) {
                $medio = DecimalFinanciero::dividir($izquierda[0]->plus($derecha[0]), BigDecimal::of(2));
                $delta = $evaluar($medio);
                $consultas++;
                if ($delta->isZero() || ($derecha[0]->minus($izquierda[0])->isLessThanOrEqualTo(self::TOLERANCIA)
                    && $delta->abs()->isLessThanOrEqualTo(self::TOLERANCIA))) {
                    $cruces[] = $this->cruce([$medio, $delta], $izquierda, $derecha);

                    continue 2;
                }
                if ($delta->getSign() === $izquierda[1]->getSign()) {
                    $izquierda = [$medio, $delta];
                } else {
                    $derecha = [$medio, $delta];
                }
            }
            $incompleto = true;
        }

        $resultado['cruces'] = $cruces;

        return $resultado + ['estado' => match (true) {
            $incompleto => 'Búsqueda parcial. Algún intervalo no alcanzó la tolerancia.',
            count($cruces) > 1 => 'Se detectaron múltiples cruces.',
            count($cruces) === 1 => 'Se detectó un punto de equilibrio.',
            default => 'No se detectó equilibrio en el intervalo analizado.',
        }];
    }

    private function cruce(array $punto, array $izquierda, array $derecha): array
    {
        return [
            'valor' => (string) $punto[0], 'residual' => (string) $punto[1],
            'izquierda' => array_map('strval', $izquierda), 'derecha' => array_map('strval', $derecha),
        ];
    }
}
