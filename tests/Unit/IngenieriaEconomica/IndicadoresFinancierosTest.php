<?php

namespace Tests\Unit\IngenieriaEconomica;

use App\Services\IngenieriaEconomica\DecimalFinanciero;
use App\Services\IngenieriaEconomica\FactoresFinancierosService;
use App\Services\IngenieriaEconomica\IndicadoresFinancierosService;
use App\Services\IngenieriaEconomica\TirService;
use Brick\Math\BigDecimal;
use DomainException;
use PHPUnit\Framework\TestCase;

class IndicadoresFinancierosTest extends TestCase
{
    public function test_van_vpc_caue_rcb_y_periodo_cero(): void
    {
        $r = $this->calcular([[0, 1000], [600, 0], [600, 0]], '0.1');
        $this->assertSame('41.32', $this->dinero($r['van']));
        $this->assertSame('1000.00', $this->dinero($r['vpc']));
        $this->assertSame('576.19', $this->dinero($r['caue']));
        $this->assertSame('1.04132231405', DecimalFinanciero::mostrar(BigDecimal::of($r['rcb'])));
        $this->assertSame('1', $r['periodos'][0]['factor']);
        $this->assertSame('-1000.00', $this->dinero($r['periodos'][0]['vp']));
        $this->assertNotSame($r['rcb'], (string) DecimalFinanciero::dividir(BigDecimal::of($r['van']), BigDecimal::of($r['vpc'])));
    }

    public function test_flujos_irregulares_tasa_cero_y_relaciones_no_aplicables(): void
    {
        $r = $this->calcular([[0, 240], [60, 5], [0, 0], [90, 0]], '0');
        $this->assertSame('-95.00', $this->dinero($r['van']));
        $this->assertSame('245.00', $this->dinero($r['vpc']));
        $this->assertSame('81.67', $this->dinero($r['caue']));
        $this->assertSame('1', $r['periodos'][2]['factor']);
        $this->assertSame('0.00', $this->dinero($r['periodos'][2]['vp']));
        $this->assertNull($this->calcular([[0, 0], [0, 100]], '0.1')['rcb']);
        $this->assertNull($this->calcular([[0, 0], [100, 0]], '0.1')['rcb']);
        $this->assertSame('-100.00', $this->dinero($this->calcular([[0, 0], [0, 110]], '0.1')['van']));
    }

    public function test_saltos_temporales_mantienen_el_exponente_y_la_tir(): void
    {
        $valor = (new IndicadoresFinancierosService(new FactoresFinancierosService, new TirService))->calcular([
            0 => ['beneficios' => BigDecimal::zero(), 'costos' => BigDecimal::of(100)],
            2 => ['beneficios' => BigDecimal::of(121), 'costos' => BigDecimal::zero()],
        ], BigDecimal::of('0.1'), BigDecimal::of('0.1'), 2);

        $this->assertSame('0.00', $this->dinero($valor['van']));
        $this->assertEqualsWithDelta(0.1, (float) $valor['tir']['raices'][0]['tasa'], 1e-12);
    }

    public function test_caue_rechaza_horizonte_invalido(): void
    {
        $this->expectException(DomainException::class);
        $this->calcular([[0, 100]], '0.05');
    }

    public function test_tir_unica_exclusivamente_negativa_y_sin_raiz_en_el_dominio(): void
    {
        $motor = new TirService;
        $unica = $motor->resolver($this->flujos([-100, 0, 121]));
        $this->assertSame('unica', $unica['estado']);
        $this->assertEqualsWithDelta(0.1, (float) $unica['raices'][0]['tasa'], 1e-12);
        $this->assertLessThan(1e-9, abs((float) $unica['raices'][0]['residual']));
        $this->assertSame('insuficiente', $motor->resolver($this->flujos([-100, -10, 0]))['estado']);
        $this->assertSame('insuficiente', $motor->resolver($this->flujos([0, 100, 0]))['estado']);
        $this->assertSame('sin_raiz', $motor->resolver($this->flujos([-100, 10, -100]))['estado']);
        $this->assertSame('sin_raiz', $motor->resolver($this->flujos([-100, 1200]))['estado']);
    }

    public function test_tir_multiple_raiz_tangente_y_no_convergencia(): void
    {
        $motor = new TirService;
        $multiple = $motor->resolver($this->flujos([-100, 230, -132]));
        $this->assertSame('posibles_multiples', $multiple['estado']);
        $this->assertCount(2, $multiple['raices']);
        $this->assertEqualsWithDelta(0.1, (float) $multiple['raices'][0]['tasa'], 1e-12);
        $this->assertEqualsWithDelta(0.2, (float) $multiple['raices'][1]['tasa'], 1e-12);

        $tangente = $motor->resolver($this->flujos([-100, 220, -121]));
        $this->assertSame('posibles_multiples', $tangente['estado']);
        $this->assertEqualsWithDelta(0.1, (float) $tangente['raices'][0]['tasa'], 1e-10);
        $this->assertSame('no_convergencia', $motor->resolver($this->flujos([-100, 113]), 1)['estado']);
        $this->assertSame('unica', $motor->resolver($this->flujos([-100, 100]))['estado']);
    }

    private function calcular(array $pares, string $tasa): array
    {
        $periodos = array_map(fn ($par) => ['beneficios' => BigDecimal::of($par[0]), 'costos' => BigDecimal::of($par[1])], $pares);

        return (new IndicadoresFinancierosService(new FactoresFinancierosService, new TirService))
            ->calcular($periodos, BigDecimal::of($tasa), BigDecimal::of($tasa), count($periodos) - 1);
    }

    private function dinero(string $valor): string
    {
        return DecimalFinanciero::dinero(BigDecimal::of($valor));
    }

    private function flujos(array $montos): array
    {
        return array_map(BigDecimal::of(...), $montos);
    }
}
