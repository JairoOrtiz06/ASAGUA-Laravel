<?php

namespace Tests\Unit\IngenieriaEconomica;

use App\Models\IngenieriaEconomica\Activo;
use App\Services\IngenieriaEconomica\DecimalFinanciero;
use App\Services\IngenieriaEconomica\DepreciacionActivoService;
use Brick\Math\BigDecimal;
use DomainException;
use Tests\TestCase;

class DepreciacionActivoTest extends TestCase
{
    public function test_linea_recta_y_doble_saldo_reproducen_los_casos_academicos(): void
    {
        $r = (new DepreciacionActivoService)->calcular($this->activo());
        $lineal = $r['metodos']['lineal'];
        $doble = $r['metodos']['doble_saldo'];

        $this->assertSame(array_fill(0, 5, '180.00'), $this->importes($lineal));
        $this->assertSame(['400.00', '240.00', '144.00', '86.40', '29.60'], $this->importes($doble));
        foreach ([$lineal, $doble] as $tabla) {
            $this->assertSame('900.00', $this->dinero($tabla['acumulado']));
            $this->assertSame('100.00', $this->dinero($tabla['saldo']));
            $this->assertTrue($tabla['fin_vida']);
        }

        $sinResidual = (new DepreciacionActivoService)->calcular($this->activo(['valor_residual' => '0']));
        $this->assertSame(array_fill(0, 5, '200.00'), $this->importes($sinResidual['metodos']['lineal']));
        $this->assertSame('1000.00', $this->dinero($sinResidual['metodos']['lineal']['acumulado']));
        $this->assertSame('0.00', $this->dinero($sinResidual['metodos']['lineal']['saldo']));
        $this->assertSame('77.76', $this->dinero($sinResidual['metodos']['doble_saldo']['pendiente']));
    }

    public function test_doble_saldo_limita_un_anio_y_documenta_ajuste_final(): void
    {
        $motor = new DepreciacionActivoService;
        $uno = $motor->calcular($this->activo(['vida_util_anios' => 1]));
        $this->assertSame(['900.00'], $this->importes($uno['metodos']['doble_saldo']));
        $this->assertSame('100.00', $this->dinero($uno['metodos']['doble_saldo']['saldo']));

        $ajustado = $motor->calcular($this->activo(['valor_residual' => '0']), [
            'ajuste_final' => true, 'justificacion_ajuste' => 'Política aprobada para el último año.',
        ]);
        $tabla = $ajustado['metodos']['doble_saldo'];
        $this->assertSame('77.76', $this->dinero($tabla['filas'][4]['ajuste']));
        $this->assertSame('0.00', $this->dinero($tabla['saldo']));
        $this->assertSame('1000.00', $this->dinero($tabla['acumulado']));

        $sinBase = $motor->calcular($this->activo(['valor_residual' => '1000']));
        $this->assertSame(array_fill(0, 5, '0.00'), $this->importes($sinBase['metodos']['doble_saldo']));
    }

    public function test_amortizacion_definida_indefinida_y_prorrateo_desde_disponibilidad(): void
    {
        $motor = new DepreciacionActivoService;
        $software = $this->activo(['clasificacion_contable' => 'activo_intangible', 'costo_adquisicion' => '1200',
            'vida_util_anios' => 4, 'valor_residual' => '0', 'fecha_disponible_uso' => '2026-07-01']);

        $r = $motor->calcular($software);
        $this->assertSame(['lineal'], array_keys($r['metodos']));
        $this->assertSame(array_fill(0, 4, '300.00'), $this->importes($r['metodos']['lineal']));
        $this->assertSame('1200.00', $this->dinero($r['metodos']['lineal']['acumulado']));
        $this->assertSame('2026-07-01', $r['metodos']['lineal']['filas'][0]['desde']);

        $parcial = $motor->calcular($software, ['fecha_corte' => '2026-12-31'])['metodos']['lineal'];
        $this->assertSame(184, $parcial['filas'][0]['dias']);
        $this->assertSame('151.23', $this->dinero($parcial['acumulado']));
        $this->assertSame([], $motor->calcular($software, ['fecha_corte' => '2026-06-30'])['metodos']['lineal']['filas']);

        $software->fill(['vida_indefinida' => true, 'vida_util_anios' => null]);
        $indefinida = $motor->calcular($software);
        $this->assertSame([], $indefinida['metodos']);
        $this->assertSame('1200.00', $this->dinero($indefinida['saldo_sin_amortizacion']));
        $this->assertSame('0.00', $this->dinero($indefinida['amortizacion_acumulada']));
        $this->assertStringContainsString('No se programa amortización', $indefinida['estado']);
    }

    public function test_gasto_no_genera_tabla(): void
    {
        $this->expectException(DomainException::class);
        (new DepreciacionActivoService)->calcular($this->activo(['clasificacion_contable' => 'gasto']));
    }

    private function activo(array $cambios = []): Activo
    {
        return new Activo($cambios + [
            'clasificacion_contable' => 'activo_tangible', 'nombre' => 'Activo académico', 'moneda' => 'USD',
            'costo_adquisicion' => '1000', 'costos_capitalizables' => '0', 'valor_residual' => '100',
            'vida_util_anios' => 5, 'fecha_disponible_uso' => '2026-01-01', 'porcentaje_uso' => '100',
        ]);
    }

    private function importes(array $tabla): array
    {
        return array_map(fn ($fila) => $this->dinero($fila['importe']), $tabla['filas']);
    }

    private function dinero(string $valor): string
    {
        return DecimalFinanciero::dinero(BigDecimal::of($valor));
    }
}
