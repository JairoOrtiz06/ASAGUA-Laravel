<?php

namespace Tests\Feature\IngenieriaEconomica;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HerramientasFinancierasTest extends TestCase
{
    use RefreshDatabase;

    private const PREFIJO = 'ingenieria-economica.herramientas.';

    private const TASA = ['tasa' => '0.1', 'modalidad' => 'efectiva', 'periodicidad_tasa' => 'anual', 'periodicidad' => 'anual'];

    public function test_herramientas_son_exclusivas_del_contador(): void
    {
        $ruta = route(self::PREFIJO.'index');
        $this->get($ruta)->assertRedirectToRoute('login');
        $this->actingAs(User::factory()->create(['rol' => 'Auditor']))->get($ruta)->assertForbidden();
        $this->post(route(self::PREFIJO.'calcular', 'tasas'))->assertForbidden();

        $this->actingAs(User::factory()->create())->get($ruta)->assertOk()
            ->assertSee('Herramientas financieras')
            ->assertSee('Factores de equivalencia');
        $this->get(route(self::PREFIJO.'show', 'interes-compuesto'))->assertOk()->assertSee('Interés compuesto');
    }

    public function test_conversion_muestra_formula_sustitucion_y_resultado_de_asagua_sin_persistir(): void
    {
        $this->actingAs(User::factory()->create())->post(route(self::PREFIJO.'calcular', 'tasas'), [
            'tasa' => '0.0025', 'modalidad' => 'efectiva', 'periodicidad_tasa' => 'trimestral',
            'destino' => 'anual', 'modalidad_destino' => 'efectiva',
        ])->assertOk()
            ->assertSee('0.0100375625390625')
            ->assertSee('1.00375625390625 %')
            ->assertSee('(1 + 0.0025)^(4/1)')
            ->assertSee('Nuevo cálculo');

        $this->assertDatabaseCount('ie_escenarios', 0);
    }

    public function test_capital_factores_y_series_renderizan_resultados(): void
    {
        $this->actingAs(User::factory()->create());
        $this->post(route(self::PREFIJO.'calcular', 'interes-compuesto'), self::TASA + [
            'operacion' => 'futuro', 'cantidad' => '1000', 'periodos' => 2,
        ])->assertOk()->assertSee('1210.00')->assertSee('F = P(1+i)^n');

        $this->post(route(self::PREFIJO.'calcular', 'factores'), self::TASA + [
            'periodos' => 2, 'factor' => 'F/A', 'cantidad' => '100',
        ])->assertOk()->assertSee('210.00')->assertSee('A/F')->assertSee('P/F');

        $this->post(route(self::PREFIJO.'calcular', 'series'), self::TASA + [
            'operacion' => 'presente', 'tipo_serie' => 'diferida', 'periodos' => 2,
            'cantidad' => '100', 'primer_pago' => 3,
        ])->assertOk()->assertSee('143.43')->assertSee('Primer pago en t = 3')->assertSee('Último pago en t = 4');

        $this->post(route(self::PREFIJO.'calcular', 'series'), self::TASA + [
            'operacion' => 'anualidad', 'tipo_serie' => 'anticipada', 'periodos' => 2, 'valor_base' => 'futuro',
            'cantidad' => '231',
        ])->assertOk()->assertSee('100.00')->assertSee('Valuación en t = 2');

        $this->post(route(self::PREFIJO.'calcular', 'series'), self::TASA + [
            'operacion' => 'periodos', 'tipo_serie' => 'vencida', 'valor_base' => 'futuro',
            'cantidad' => '100', 'objetivo' => '210',
        ])->assertOk()->assertSee('2 pagos');

        $this->post(route(self::PREFIJO.'calcular', 'series'), self::TASA + [
            'operacion' => 'periodos', 'tipo_serie' => 'vencida', 'valor_base' => 'presente',
            'cantidad' => '100', 'objetivo' => '1000',
        ])->assertOk()->assertSee('Sin solución entera en el rango admitido');
    }

    public function test_valida_periodicidades_tasas_periodos_e_importes(): void
    {
        $this->actingAs(User::factory()->create());
        $this->post(route(self::PREFIJO.'calcular', 'tasas'), [
            'tasa' => '-1', 'modalidad' => 'efectiva', 'periodicidad_tasa' => 'trimestral',
            'destino' => 'anual', 'modalidad_destino' => 'nominal',
        ])->assertSessionHasErrors(['tasa', 'destino']);

        $this->post(route(self::PREFIJO.'calcular', 'interes-compuesto'), self::TASA + [
            'operacion' => 'no_existe', 'cantidad' => '-1000', 'periodos' => -1,
        ])->assertSessionHasErrors(['operacion', 'cantidad', 'periodos']);

        $this->post(route(self::PREFIJO.'calcular', 'series'), self::TASA + [
            'operacion' => 'periodos', 'tipo_serie' => 'vencida', 'valor_base' => 'futuro', 'cantidad' => '0', 'objetivo' => '50',
        ])->assertSessionHasErrors('cantidad');
    }
}
