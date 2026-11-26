<?php

declare(strict_types=1);

namespace App\Services\IngenieriaEconomica;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final class DecimalFinanciero
{
    public const ESCALA = 40;

    public const MAX_PERIODOS = 600;

    public static function dividir(BigDecimal $numerador, BigDecimal $denominador): BigDecimal
    {
        if ($numerador->isZero()) {
            return BigDecimal::zero();
        }

        // Conserva precisión relativa también cuando el cociente se acerca a cero.
        $escala = self::ESCALA + max(0, self::orden($denominador) - self::orden($numerador));

        return $numerador->dividedBy($denominador, $escala, RoundingMode::HalfEven);
    }

    public static function redondearBase(BigDecimal $base): BigDecimal
    {
        // Retiene los dígitos alrededor de cero incluso si la tasa está cerca de −1.
        $escala = self::ESCALA + max(0, 1 - self::orden($base));

        return $base->toScale($escala, RoundingMode::HalfEven)->strippedOfTrailingZeros();
    }

    public static function mostrar(BigDecimal $valor, int $escala = 12): string
    {
        $redondeado = $valor->toScale($escala, RoundingMode::HalfUp)->strippedOfTrailingZeros();
        $orden = self::orden($valor) - 1;
        if (! $valor->isZero() && ($redondeado->isZero() || $orden >= 24)) {
            $mantisa = $valor->withPointMovedLeft($orden)->toScale($escala, RoundingMode::HalfUp)->strippedOfTrailingZeros();

            return $mantisa.'e'.$orden;
        }

        return (string) $redondeado;
    }

    public static function fraccionDePorcentaje(mixed $porcentaje): ?string
    {
        if ($porcentaje === null || $porcentaje === '') {
            return null;
        }

        $texto = is_scalar($porcentaje) ? str_replace(',', '.', trim((string) $porcentaje)) : '';

        return preg_match('/^-?[0-9]{1,10}(?:\.[0-9]{1,10})?$/D', $texto) === 1
            ? (string) BigDecimal::of($texto)->withPointMovedLeft(2)
            : 'porcentaje inválido';
    }

    public static function porcentaje(string $fraccion): string
    {
        return (string) BigDecimal::of($fraccion)->withPointMovedRight(2)->strippedOfTrailingZeros();
    }

    public static function dinero(BigDecimal $valor): string
    {
        return (string) $valor->toScale(2, RoundingMode::HalfUp);
    }

    private static function orden(BigDecimal $valor): int
    {
        return strlen((string) $valor->abs()->getUnscaledValue()) - $valor->getScale();
    }
}
