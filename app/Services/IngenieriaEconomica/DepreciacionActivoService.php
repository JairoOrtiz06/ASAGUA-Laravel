<?php

declare(strict_types=1);

namespace App\Services\IngenieriaEconomica;

use App\Models\IngenieriaEconomica\Activo;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use DomainException;

class DepreciacionActivoService
{
    public const VERSION = '5.0.0';

    public const CONVENCION = 'Años de servicio desde la disponibilidad. Si hay corte, incluye ese día y prorratea por días calendario de ese año de servicio.';

    public function calcular(Activo $activo, array $opciones = []): array
    {
        $tangible = $activo->clasificacion_contable === 'activo_tangible';
        if (! $tangible && $activo->clasificacion_contable !== 'activo_intangible') {
            throw new DomainException('La clasificación actual no permite depreciación ni amortización.');
        }

        $ajuste = (bool) ($opciones['ajuste_final'] ?? false);
        $justificacion = trim($opciones['justificacion_ajuste'] ?? '');
        if ($ajuste && (! $tangible || $justificacion === '')) {
            throw new DomainException('El ajuste final se aplica al doble saldo decreciente y requiere justificación.');
        }

        $base = $activo->baseCapitalizada();
        $residual = BigDecimal::of($activo->valor_residual);
        $vida = $activo->vida_util_anios;
        $inicio = $activo->fecha_disponible_uso;
        $corte = empty($opciones['fecha_corte']) ? null : CarbonImmutable::parse($opciones['fecha_corte'])->addDay();
        $informacion = [
            'activo' => $activo->only(['id', 'nombre', 'clasificacion_contable', 'moneda', 'porcentaje_uso', 'vida_indefinida',
                'justificacion_residual', 'depreciacion_fiscal', 'amortizacion_fiscal', 'efecto_tributario']),
            'inicio' => $inicio?->toDateString(), 'corte' => $opciones['fecha_corte'] ?? null,
            'base' => (string) $base, 'residual' => (string) $residual, 'vida' => $vida,
            'convencion' => self::CONVENCION, 'ajuste_final' => $ajuste, 'justificacion_ajuste' => $ajuste ? $justificacion : null,
            'version' => self::VERSION, 'precision' => '40 decimales de trabajo. Presentación monetaria a dos decimales.',
            'estado' => 'calculado', 'metodos' => [],
        ];

        if (! $tangible && $activo->vida_indefinida) {
            $informacion['estado'] = 'Vida útil indefinida. No se programa amortización.';
            $informacion['saldo_sin_amortizacion'] = (string) $base;
            $informacion['amortizacion_acumulada'] = '0';

            return $informacion;
        }
        if (! $inicio) {
            throw new DomainException('Registra la fecha disponible para uso antes de calcular.');
        }
        if ($vida === null || $vida < 1 || $vida > 100 || $residual->isNegative() || $residual->isGreaterThan($base)) {
            throw new DomainException('La vida útil o la base de cálculo no son válidas.');
        }

        $informacion['metodos']['lineal'] = $this->tabla($base, $residual, $vida, $inicio, $corte, false, false);
        if ($tangible) {
            $informacion['metodos']['doble_saldo'] = $this->tabla($base, $residual, $vida, $inicio, $corte, true, $ajuste);
        }

        return $informacion;
    }

    private function tabla(BigDecimal $base, BigDecimal $residual, int $vida, CarbonImmutable $inicio, ?CarbonImmutable $corte, bool $doble, bool $ajuste): array
    {
        $saldo = $base;
        $lineal = DecimalFinanciero::dividir($base->minus($residual), BigDecimal::of($vida));
        $tasa = DecimalFinanciero::dividir(BigDecimal::of(2), BigDecimal::of($vida));
        $filas = [];
        $agotado = false;

        for ($anio = 1; $anio <= $vida; $anio++) {
            $desde = $inicio->addYearsNoOverflow($anio - 1);
            $aniversario = $inicio->addYearsNoOverflow($anio);
            if ($corte && $corte->lte($desde)) {
                break;
            }
            $hasta = $corte && $corte->lt($aniversario) ? $corte : $aniversario;
            $dias = (int) $desde->diffInDays($hasta);
            $diasAnio = (int) $desde->diffInDays($aniversario);
            $restante = $saldo->minus($residual);
            $ordinario = $doble ? BigDecimal::min($saldo->multipliedBy($tasa), $restante) : BigDecimal::min($lineal, $restante);
            $fraccion = DecimalFinanciero::dividir(BigDecimal::of($dias), BigDecimal::of($diasAnio));
            $importe = $ordinario->multipliedBy($fraccion);
            $ajusteAplicado = BigDecimal::zero();
            if ($anio === $vida && $hasta->eq($aniversario)) {
                $agotado = true;
                if ($doble && $ajuste) {
                    $ajusteAplicado = $restante->minus($importe);
                    $importe = $restante;
                } elseif (! $doble) {
                    // Absorbe solo el residuo de precisión decimal de la división lineal.
                    $importe = $restante;
                }
            }

            $final = $saldo->minus($importe);
            $filas[] = [
                'anio' => $anio, 'desde' => $desde->toDateString(), 'hasta' => $hasta->subDay()->toDateString(),
                'dias' => $dias, 'dias_anio' => $diasAnio, 'saldo_inicial' => (string) $saldo,
                'ordinario_anual' => (string) $ordinario, 'ajuste' => (string) $ajusteAplicado,
                'importe' => (string) $importe, 'acumulado' => (string) $base->minus($final),
                'saldo_final' => (string) $final, 'residual' => (string) $residual,
            ];
            $saldo = $final;
        }

        return [
            'formula' => $doble ? 'Dₜ = MIN(VL inicial × (2/n), VL inicial − VR)' : 'D = (C − VR) / n',
            'tasa' => $doble ? (string) $tasa : null, 'anual_lineal' => $doble ? null : (string) $lineal,
            'filas' => $filas, 'saldo' => (string) $saldo, 'acumulado' => (string) $base->minus($saldo),
            'pendiente' => (string) $saldo->minus($residual), 'fin_vida' => $agotado,
        ];
    }
}
