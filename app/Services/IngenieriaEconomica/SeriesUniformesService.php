<?php

declare(strict_types=1);

namespace App\Services\IngenieriaEconomica;

use Brick\Math\BigDecimal;
use DomainException;

class SeriesUniformesService
{
    public const TIPOS = ['vencida' => 'Vencida', 'anticipada' => 'Anticipada', 'diferida' => 'Diferida'];

    public function __construct(private FactoresFinancierosService $factores) {}

    public function primerPago(string $tipo, ?int $primerPago = null): int
    {
        return match ($tipo) {
            'vencida' => 1,
            'anticipada' => 0,
            'diferida' => $primerPago >= 2 && $primerPago <= DecimalFinanciero::MAX_PERIODOS
                ? $primerPago
                : throw new DomainException('Para una serie diferida, indica el período del primer pago desde el período 2.'),
            default => throw new DomainException('Selecciona un tipo de serie válido.'),
        };
    }

    public function factor(string $base, BigDecimal $tasa, int $periodos, int $primerPago): BigDecimal
    {
        [$numerador, $denominador] = $this->razon($base, $tasa, $periodos, $primerPago);

        return DecimalFinanciero::dividir($numerador, $denominador);
    }

    /** @return array{BigDecimal, BigDecimal} */
    private function razon(string $base, BigDecimal $tasa, int $periodos, int $primerPago): array
    {
        if (! in_array($base, ['presente', 'futuro'], true) || $periodos < 1 || $primerPago < 0 || $primerPago > DecimalFinanciero::MAX_PERIODOS) {
            throw new DomainException('Revisa la fecha de valoración, el número de pagos y el período del primer pago.');
        }

        $crecimiento = $this->factores->crecimiento($tasa, $periodos);
        if ($tasa->isZero()) {
            return [BigDecimal::of($periodos), BigDecimal::one()];
        }

        $numerador = $crecimiento->minus(1)->abs();
        $denominador = $tasa->abs();
        if ($base === 'presente') {
            $denominador = $denominador->multipliedBy($crecimiento);
            if ($primerPago > 1) {
                $denominador = $denominador->multipliedBy($this->factores->crecimiento($tasa, $primerPago - 1));
            }
        }
        if ($primerPago === 0) {
            $numerador = $numerador->multipliedBy($tasa->plus(1));
        }

        return [$numerador, $denominador];
    }

    public function periodos(string $base, BigDecimal $pago, BigDecimal $objetivo, BigDecimal $tasa, int $primerPago): ?int
    {
        if ($pago->isLessThanOrEqualTo(0) || $objetivo->isLessThanOrEqualTo(0)) {
            throw new DomainException('La anualidad y el valor objetivo deben ser positivos para calcular el número de pagos.');
        }

        // Con pagos positivos el valor crece estrictamente con n. Se busca una solución en períodos enteros.
        $inferior = 1;
        $superior = DecimalFinanciero::MAX_PERIODOS;

        while ($inferior <= $superior) {
            $medio = intdiv($inferior + $superior, 2);
            [$numerador, $denominador] = $this->razon($base, $tasa, $medio, $primerPago);
            // Comparar productos evita aceptar una asíntota por redondeo de la división.
            $comparacion = $pago->multipliedBy($numerador)->compareTo($objetivo->multipliedBy($denominador));

            if ($comparacion === 0) {
                return $medio;
            }
            if ($comparacion < 0) {
                $inferior = $medio + 1;
            } else {
                $superior = $medio - 1;
            }
        }

        return null;
    }
}
