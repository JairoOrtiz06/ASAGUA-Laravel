<?php

declare(strict_types=1);

namespace App\Services\IngenieriaEconomica;

use Brick\Math\BigDecimal;

class InteresCompuestoService
{
    public function __construct(private FactoresFinancierosService $factores) {}

    public function valorFuturo(BigDecimal $capital, BigDecimal $tasa, int $periodos): BigDecimal
    {
        return $capital->multipliedBy($this->factores->crecimiento($tasa, $periodos));
    }

    public function valorPresente(BigDecimal $capital, BigDecimal $tasa, int $periodos): BigDecimal
    {
        return DecimalFinanciero::dividir($capital, $this->factores->crecimiento($tasa, $periodos));
    }
}
