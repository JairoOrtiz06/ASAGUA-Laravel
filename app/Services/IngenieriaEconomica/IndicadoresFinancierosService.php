<?php

declare(strict_types=1);

namespace App\Services\IngenieriaEconomica;

use Brick\Math\BigDecimal;
use DomainException;

class IndicadoresFinancierosService
{
    public function __construct(
        private FactoresFinancierosService $factores,
        private TirService $tir,
    ) {}

    /** @param array<int, array{beneficios: BigDecimal, costos: BigDecimal}> $periodos */
    public function calcular(array $periodos, BigDecimal $tasa, BigDecimal $anual, int $horizonte, bool $incluirTir = true): array
    {
        if ($horizonte < 1 || $horizonte > ProyeccionFlujosService::MAX_ANIOS || $tasa->isLessThanOrEqualTo(-1) || $anual->isLessThanOrEqualTo(-1)) {
            throw new DomainException('La tasa y el horizonte no permiten una evaluación.');
        }

        ksort($periodos, SORT_NUMERIC);
        $ultimo = array_key_last($periodos);
        if ($periodos === [] || ! is_int(array_key_first($periodos)) || array_key_first($periodos) < 0
            || ! is_int($ultimo) || $ultimo > ProyeccionFlujosService::MAX_MESES) {
            throw new DomainException('La evaluación requiere períodos numerados dentro del horizonte admitido.');
        }

        $factor = BigDecimal::one();
        $exponente = 0;
        $descuento = DecimalFinanciero::dividir(BigDecimal::one(), $tasa->plus(1));
        $ap = $this->factores->calcular($anual, $horizonte)['A/P'];
        $beneficios = $costos = BigDecimal::zero();
        $filas = [];
        $netos = array_fill(0, $ultimo + 1, BigDecimal::zero());
        foreach ($periodos as $numero => $periodo) {
            if (! is_int($numero)) {
                throw new DomainException('La evaluación requiere períodos enteros.');
            }
            while ($exponente < $numero) {
                $factor = DecimalFinanciero::redondearBase($factor->multipliedBy($descuento));
                $exponente++;
            }

            $neto = $periodo['beneficios']->minus($periodo['costos']);
            $vpBeneficios = $periodo['beneficios']->multipliedBy($factor);
            $vpCostos = $periodo['costos']->multipliedBy($factor);
            $beneficios = $beneficios->plus($vpBeneficios);
            $costos = $costos->plus($vpCostos);
            $netos[$numero] = $neto;
            $filas[$numero] = [
                'beneficios' => (string) $periodo['beneficios'], 'costos' => (string) $periodo['costos'],
                'neto' => (string) $neto, 'factor' => (string) $factor, 'vp' => (string) $neto->multipliedBy($factor),
                'vp_beneficios' => (string) $vpBeneficios, 'vp_costos' => (string) $vpCostos,
            ];
        }

        return [
            'van' => (string) $beneficios->minus($costos),
            'vpc' => (string) $costos,
            'vp_beneficios' => (string) $beneficios,
            'caue' => (string) $costos->multipliedBy($ap),
            'factor_ap' => (string) $ap,
            'rcb' => $costos->isZero() || $beneficios->isZero() ? null : (string) DecimalFinanciero::dividir($beneficios, $costos),
            'tir' => $incluirTir ? $this->tir->resolver($netos) : null,
            'periodos' => $filas,
        ];
    }
}
