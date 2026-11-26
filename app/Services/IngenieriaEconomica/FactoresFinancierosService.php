<?php

declare(strict_types=1);

namespace App\Services\IngenieriaEconomica;

use Brick\Math\BigDecimal;
use DomainException;

class FactoresFinancierosService
{
    public const FORMULAS = [
        'F/P' => '(1+i)^n',
        'P/F' => '1/(1+i)^n',
        'P/A' => '((1+i)^n − 1)/(i(1+i)^n)',
        'A/P' => 'i(1+i)^n/((1+i)^n − 1)',
        'F/A' => '((1+i)^n − 1)/i',
        'A/F' => 'i/((1+i)^n − 1)',
    ];

    public const LIMITES_TASA_CERO = ['F/P' => '1', 'P/F' => '1', 'P/A' => 'n', 'A/P' => '1/n', 'F/A' => 'n', 'A/F' => '1/n'];

    public const NOMBRES = [
        'F/P' => 'Cantidad compuesta de un capital',
        'P/F' => 'Valor presente de un capital',
        'P/A' => 'Valor presente de una anualidad',
        'A/P' => 'Recuperación de capital',
        'F/A' => 'Cantidad compuesta de una anualidad',
        'A/F' => 'Fondo de amortización',
    ];

    public function crecimiento(BigDecimal $tasa, int $periodos): BigDecimal
    {
        if ($tasa->isLessThanOrEqualTo(-1) || $periodos < 0 || $periodos > DecimalFinanciero::MAX_PERIODOS) {
            throw new DomainException('Usa una tasa efectiva superior a −100 % y un número de períodos dentro del rango admitido.');
        }

        return $tasa->plus(1)->strippedOfTrailingZeros()->power($periodos);
    }

    /** @return array<string, BigDecimal> */
    public function calcular(BigDecimal $tasa, int $periodos): array
    {
        if ($periodos < 1) {
            throw new DomainException('Los factores de anualidad requieren al menos un período.');
        }

        $crecimiento = $this->crecimiento($tasa, $periodos);
        $futuro = $tasa->isZero()
            ? BigDecimal::of($periodos)
            : DecimalFinanciero::dividir($crecimiento->minus(1), $tasa);
        $presente = DecimalFinanciero::dividir($futuro, $crecimiento);

        return [
            'F/P' => $crecimiento,
            'P/F' => DecimalFinanciero::dividir(BigDecimal::one(), $crecimiento),
            'P/A' => $presente,
            'A/P' => DecimalFinanciero::dividir(BigDecimal::one(), $presente),
            'F/A' => $futuro,
            'A/F' => DecimalFinanciero::dividir(BigDecimal::one(), $futuro),
        ];
    }
}
