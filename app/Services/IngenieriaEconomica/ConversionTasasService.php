<?php

declare(strict_types=1);

namespace App\Services\IngenieriaEconomica;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DomainException;

class ConversionTasasService
{
    public const PERIODOS = ['mensual' => 'Mensual', 'trimestral' => 'Trimestral', 'semestral' => 'Semestral', 'anual' => 'Anual'];

    public const MODALIDADES = ['efectiva' => 'Efectiva', 'nominal' => 'Nominal anual'];

    private const FRECUENCIAS = ['mensual' => 12, 'trimestral' => 4, 'semestral' => 2, 'anual' => 1];

    public function frecuencia(string $periodicidad, string $modalidad = 'efectiva'): int
    {
        if (! isset(self::FRECUENCIAS[$periodicidad], self::MODALIDADES[$modalidad])) {
            throw new DomainException('La modalidad o periodicidad indicada no es compatible.');
        }

        if ($modalidad === 'nominal' && $periodicidad === 'anual') {
            throw new DomainException('Selecciona capitalización mensual, trimestral o semestral para la tasa nominal anual.');
        }

        return self::FRECUENCIAS[$periodicidad];
    }

    public function efectivaPeriodica(string $tasa, string $modalidad, string $periodicidad): BigDecimal
    {
        $frecuencia = $this->frecuencia($periodicidad, $modalidad);
        $valor = BigDecimal::of($tasa);
        $limite = $modalidad === 'nominal' ? -$frecuencia : -1;

        if ($valor->isLessThanOrEqualTo($limite)) {
            throw new DomainException('La tasa efectiva del período debe ser mayor que −100 %. Para una nominal, verifica j/m.');
        }

        return $modalidad === 'nominal'
            ? DecimalFinanciero::dividir($valor, BigDecimal::of($frecuencia))
            : $valor;
    }

    public function convertir(
        string $tasa,
        string $modalidad,
        string $origen,
        string $destino,
        string $modalidadDestino = 'efectiva',
    ): BigDecimal {
        $mOrigen = $this->frecuencia($origen, $modalidad);
        $mDestino = $this->frecuencia($destino, $modalidadDestino);
        $periodica = $this->efectivaPeriodica($tasa, $modalidad, $origen);

        if ($periodica->isZero()) {
            return BigDecimal::zero();
        }

        if ($mOrigen === $mDestino) {
            return $modalidad === $modalidadDestino
                ? BigDecimal::of($tasa)
                : ($modalidadDestino === 'nominal' ? $periodica->multipliedBy($mDestino) : $periodica);
        }

        // Las frecuencias disponibles dividen 12. Simplificar la razón deja raíces de orden 2 o 3.
        $numerador = $mOrigen;
        $denominador = $mDestino;
        foreach ([2, 3] as $factor) {
            while ($numerador % $factor === 0 && $denominador % $factor === 0) {
                $numerador = intdiv($numerador, $factor);
                $denominador = intdiv($denominador, $factor);
            }
        }

        $base = $periodica->plus(1);
        while ($denominador % 2 === 0) {
            $base = $base->sqrt(DecimalFinanciero::ESCALA, RoundingMode::HalfEven);
            $denominador = intdiv($denominador, 2);
        }
        if ($denominador === 3) {
            $base = $this->raizCubica($base);
        }

        $efectiva = DecimalFinanciero::redondearBase($base->power($numerador))->minus(1);
        if ($modalidadDestino === 'efectiva') {
            return $efectiva;
        }

        return $efectiva->multipliedBy($mDestino);
    }

    private function raizCubica(BigDecimal $valor): BigDecimal
    {
        $inferior = BigDecimal::zero();
        $superior = BigDecimal::max($valor, 1);
        do {
            $medio = $inferior->plus($superior)->dividedBy(2, DecimalFinanciero::ESCALA, RoundingMode::Down);
            if ($medio->isEqualTo($inferior)) {
                return $medio;
            }
            $comparacion = $medio->power(3)->compareTo($valor);
            if ($comparacion === 0) {
                return $medio;
            }
            if ($comparacion > 0) {
                $superior = $medio;
            } else {
                $inferior = $medio;
            }
        } while (true);
    }
}
