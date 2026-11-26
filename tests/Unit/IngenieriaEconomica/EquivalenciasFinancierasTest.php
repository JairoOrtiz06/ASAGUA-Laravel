<?php

namespace Tests\Unit\IngenieriaEconomica;

use App\Services\IngenieriaEconomica\ConversionTasasService;
use App\Services\IngenieriaEconomica\DecimalFinanciero;
use App\Services\IngenieriaEconomica\FactoresFinancierosService;
use App\Services\IngenieriaEconomica\InteresCompuestoService;
use App\Services\IngenieriaEconomica\SeriesUniformesService;
use Brick\Math\BigDecimal;
use DomainException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EquivalenciasFinancierasTest extends TestCase
{
    public function test_conversion_trimestral_de_asagua_y_conversion_mensual(): void
    {
        $tasas = new ConversionTasasService;

        $this->assertSame('0.0100375625390625', DecimalFinanciero::mostrar($tasas->convertir('0.0025', 'efectiva', 'trimestral', 'anual'), 16));
        $this->assertSame('0.126825030132', DecimalFinanciero::mostrar($tasas->convertir('0.01', 'efectiva', 'mensual', 'anual')));
        $this->assertSame('0.01', DecimalFinanciero::mostrar($tasas->convertir('0.126825030131969720661201', 'efectiva', 'anual', 'mensual')));
    }

    public function test_conversion_nominal_y_equivalencia_en_ambos_sentidos(): void
    {
        $tasas = new ConversionTasasService;

        foreach (['mensual' => ['0.126825030132', 12], 'trimestral' => ['0.12550881', 4], 'semestral' => ['0.1236', 2]] as $frecuencia => [$anual, $periodos]) {
            $tea = $tasas->convertir('0.12', 'nominal', $frecuencia, 'anual');
            $this->assertSame($periodos, $tasas->frecuencia($frecuencia, 'nominal'));
            $this->assertSame($anual, DecimalFinanciero::mostrar($tea));
            $this->assertSame('0.12', DecimalFinanciero::mostrar($tasas->convertir((string) $tea, 'efectiva', 'anual', $frecuencia, 'nominal')));
        }

        $this->assertSame('0.1', DecimalFinanciero::mostrar($tasas->convertir('0.331', 'efectiva', 'trimestral', 'mensual')));
        $this->assertSame('0.012', DecimalFinanciero::mostrar($tasas->convertir('0.001', 'efectiva', 'mensual', 'mensual', 'nominal')));
        $this->assertSame('-0.5', DecimalFinanciero::mostrar($tasas->convertir('-2', 'nominal', 'trimestral', 'trimestral')));
        $this->assertTrue($tasas->convertir('0', 'efectiva', 'anual', 'mensual')->isZero());
    }

    public function test_interes_compuesto_presente_futuro_y_periodo_cero(): void
    {
        $motor = new InteresCompuestoService(new FactoresFinancierosService);
        $tasa = BigDecimal::of('0.1');

        $this->assertSame('1210.00', DecimalFinanciero::dinero($motor->valorFuturo(BigDecimal::of(1000), $tasa, 2)));
        $this->assertSame('1000.00', DecimalFinanciero::dinero($motor->valorPresente(BigDecimal::of(1210), $tasa, 2)));
        $this->assertSame('1210.00', DecimalFinanciero::dinero($motor->valorPresente(BigDecimal::of(1210), $tasa, 0)));
        $this->assertSame('1000.00', DecimalFinanciero::dinero($motor->valorFuturo(BigDecimal::of(1000), BigDecimal::zero(), 600)));
    }

    public function test_seis_factores_y_limites_para_tasa_cero(): void
    {
        $motor = new FactoresFinancierosService;
        $calculados = $motor->calcular(BigDecimal::of('0.1'), 2);

        $this->assertSame([
            'F/P' => '1.21', 'P/F' => '0.826446280992', 'P/A' => '1.735537190083',
            'A/P' => '0.57619047619', 'F/A' => '2.1', 'A/F' => '0.47619047619',
        ], array_map(fn ($valor) => DecimalFinanciero::mostrar($valor), $calculados));

        $this->assertSame([
            'F/P' => '1', 'P/F' => '1', 'P/A' => '4', 'A/P' => '0.25', 'F/A' => '4', 'A/F' => '0.25',
        ], array_map(fn ($valor) => DecimalFinanciero::mostrar($valor), $motor->calcular(BigDecimal::zero(), 4)));
    }

    public function test_series_con_fechas_de_pago_explicitas(): void
    {
        $series = new SeriesUniformesService(new FactoresFinancierosService);
        $tasa = BigDecimal::of('0.1');
        $pago = BigDecimal::of(100);
        $vencida = $series->primerPago('vencida');
        $anticipada = $series->primerPago('anticipada');
        $diferida = $series->primerPago('diferida', 3);

        $presente = fn (int $primero) => DecimalFinanciero::dinero($pago->multipliedBy($series->factor('presente', $tasa, 2, $primero)));
        $futuro = fn (int $primero) => DecimalFinanciero::dinero($pago->multipliedBy($series->factor('futuro', $tasa, 2, $primero)));

        $this->assertSame('173.55', $presente($vencida));
        $this->assertSame('190.91', $presente($anticipada));
        $this->assertSame('143.43', $presente($diferida));
        $this->assertSame('210.00', $futuro($vencida));
        $this->assertSame('231.00', $futuro($anticipada));
        $this->assertSame('210.00', $futuro($diferida));
        $this->assertSame('2', (string) $series->factor('presente', BigDecimal::zero(), 2, $diferida)->strippedOfTrailingZeros());
    }

    public function test_busqueda_entera_no_confunde_una_asintota_con_una_solucion(): void
    {
        $series = new SeriesUniformesService(new FactoresFinancierosService);
        $pago = BigDecimal::of(100);
        $tasa = BigDecimal::of('0.1');

        $this->assertSame(2, $series->periodos('futuro', $pago, BigDecimal::of(210), $tasa, 1));
        $this->assertSame(2, $series->periodos('futuro', $pago, BigDecimal::of(231), $tasa, 0));
        $this->assertSame(3, $series->periodos('presente', $pago, BigDecimal::of(300), BigDecimal::zero(), 3));
        $this->assertSame(2, $series->periodos('presente', $pago, BigDecimal::of(600), BigDecimal::of('-0.5'), 1));
        $this->assertNull($series->periodos('presente', $pago, BigDecimal::of(1000), $tasa, 1));
        $this->assertNull($series->periodos('futuro', $pago, BigDecimal::of(200), $tasa, 1));
    }

    #[DataProvider('tasasInvalidas')]
    public function test_rechaza_tasas_y_periodicidades_invalidas(string $tasa, string $modalidad, string $periodicidad): void
    {
        $this->expectException(DomainException::class);

        (new ConversionTasasService)->convertir($tasa, $modalidad, $periodicidad, 'anual');
    }

    public static function tasasInvalidas(): array
    {
        return [
            ['-1', 'efectiva', 'mensual'],
            ['-4', 'nominal', 'trimestral'],
            ['0.12', 'nominal', 'anual'],
            ['0.12', 'efectiva', 'semanal'],
        ];
    }

    public function test_factores_rechazan_periodo_cero(): void
    {
        $this->expectException(DomainException::class);

        (new FactoresFinancierosService)->calcular(BigDecimal::zero(), 0);
    }

    public function test_serie_diferida_rechaza_el_primer_pago_en_uno(): void
    {
        $this->expectException(DomainException::class);

        (new SeriesUniformesService(new FactoresFinancierosService))->primerPago('diferida', 1);
    }
}
