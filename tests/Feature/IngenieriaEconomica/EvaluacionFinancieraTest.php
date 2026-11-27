<?php

namespace Tests\Feature\IngenieriaEconomica;

use App\Models\IngenieriaEconomica\Alternativa;
use App\Models\IngenieriaEconomica\Concepto;
use App\Models\IngenieriaEconomica\Evaluacion;
use App\Models\IngenieriaEconomica\Proyecto;
use App\Models\User;
use App\Services\IngenieriaEconomica\DecimalFinanciero;
use App\Services\IngenieriaEconomica\EvaluacionService;
use Brick\Math\BigDecimal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EvaluacionFinancieraTest extends TestCase
{
    use RefreshDatabase;

    private const RUTA = 'ingenieria-economica.proyectos.evaluaciones.';

    public function test_asagua_separa_caja_valoracion_y_comunes_y_excluye_historicos(): void
    {
        [$proyecto, $escenario, $a, $b, $usuario] = $this->proyecto();
        $soporte = $this->concepto($proyecto, $a, ['nombre' => 'Soporte mensual ACAE', 'monto' => '33.90']);
        $soporte->fuentes()->create([
            'tipo' => 'factura', 'referencia' => 'Factura de soporte', 'fecha' => '2026-11-24',
            'importe_utilizado' => '33.90', 'justificacion' => 'Cuota mensual con IVA.',
        ]);
        $this->concepto($proyecto, $b, [
            'nombre' => 'Tiempo administrativo adicional', 'monto' => '71.4285', 'naturaleza_economica' => 'costo_oportunidad',
            'estado_dato' => 'estimado', 'observaciones' => 'Estimación de horas adicionales. Falta verificar el tiempo real.',
        ]);
        $this->concepto($proyecto, null, ['nombre' => 'Internet compartido', 'monto' => '25', 'clasificacion_decision' => 'comun']);
        $this->concepto($proyecto, $a, [
            'nombre' => 'Compra anterior', 'monto' => '1695', 'tipo_concepto' => 'inversion_inicial',
            'periodicidad' => 'unico', 'fecha_real' => '2025-12-01', 'clasificacion_decision' => 'hundido',
        ]);
        $this->concepto($proyecto, $a, [
            'nombre' => 'Mantenimiento observado', 'monto' => '135.60', 'periodicidad' => 'unico', 'fecha_real' => '2026-05-13',
        ]);
        $datos = $this->datos($escenario->id, [$a->id, $b->id], $b->id);
        $servicio = app(EvaluacionService::class);
        $caja = $servicio->preparar($proyecto, $escenario, $datos);
        $econ = $servicio->preparar($proyecto, $escenario, array_replace($datos, ['perspectiva' => 'economica']));

        $this->assertSame('0.010037562539', $econ['escenario']['anual']);
        $this->assertSame('0.000832639852', DecimalFinanciero::mostrar(BigDecimal::of($econ['escenario']['tasa'])));
        $this->assertCount(61, $econ['alternativas'][$a->id]['periodos']);
        $this->assertSame('3445.78', $this->dinero($caja['alternativas'][$a->id]['indicadores']['vpc']));
        $this->assertSame('1462.55', $this->dinero($caja['alternativas'][$b->id]['indicadores']['vpc']));
        $this->assertSame('5641.27', $this->dinero($econ['alternativas'][$b->id]['indicadores']['vpc']));
        $this->assertSame('1162.46', $this->dinero($econ['alternativas'][$b->id]['indicadores']['caue']));
        $this->assertSame('2195.50', $this->dinero($econ['comparaciones'][0]['van']));
        $this->assertSame('-1983.22', $this->dinero($caja['comparaciones'][0]['van']));
        $periodo = $econ['comparaciones'][0]['periodos'][1];
        $this->assertSame('-33.90', $this->dinero($periodo['a']));
        $this->assertSame('-71.43', $this->dinero($periodo['b']));
        $this->assertSame('-25.00', $this->dinero($periodo['comun_a']));
        $this->assertSame('-25.00', $this->dinero($periodo['comun_b']));
        $this->assertSame(['Costo hundido para esta decisión', 'Anterior a la fecha base'], array_column($econ['alternativas'][$a->id]['excluidos'], 'motivo'));
        $this->assertNull($econ['alternativas'][$b->id]['indicadores']['rcb']);
        $this->assertSame('insuficiente', $econ['alternativas'][$b->id]['indicadores']['tir']['estado']);
        $this->assertStringContainsString('viabilidad técnica está pendiente', $econ['comparaciones'][0]['criterio']);
        $this->assertSame('Factura de soporte', $econ['origenes']['c'.$soporte->id]['fuentes'][0]['referencia']);

        $this->actingAs($usuario)->get(route(self::RUTA.'index', $proyecto))->assertOk()->assertSee('Evaluación financiera');
        $this->post(route(self::RUTA.'preview', $proyecto), array_replace($datos, ['perspectiva' => 'economica']))
            ->assertOk()->assertSee('Memoria de Valor actual neto')->assertSee('No aplicable con los datos actuales')
            ->assertSee('TIR no calculable para estos flujos')->assertSee('Comparación incremental')
            ->assertSee('2195.50')->assertSee('Factura de soporte');
        $this->assertDatabaseCount('ie_evaluaciones', 0);
    }

    public function test_ordena_costos_con_respaldo_y_excluye_inviables_o_sin_datos(): void
    {
        [$p, $e, $a, $b, $usuario] = $this->proyecto();
        $e->update(['estado_tasa' => 'validada']);
        $b->update(['viabilidad_tecnica' => 'viable']);
        $this->concepto($p, $a, ['monto' => '33.90']);
        $cero = $this->concepto($p, $b, ['monto' => '0']);
        $fuente = $cero->fuentes()->create([
            'tipo' => 'cotizacion', 'referencia' => 'Servicio sin cargo', 'fecha' => '2026-11-24',
            'importe_utilizado' => '0', 'justificacion' => 'El proveedor no cobra el servicio.',
        ]);
        $datos = $this->datos($e->id, [$a->id, $b->id], $b->id) + ['equivalencia' => true];
        $this->actingAs($usuario);
        $url = route(self::RUTA.'preview', $p);
        $this->post($url, $datos)->assertOk()->assertSee('Viable y comparable')
            ->assertSee('Costo cero documentado')->assertSee('Menor VPC bajo los supuestos registrados: Hacienda')
            ->assertDontSee('Comparación preliminar');

        $this->post($url, array_replace($datos, ['equivalencia' => false]))->assertOk()
            ->assertSee('Comparabilidad pendiente de confirmar')->assertSee('Comparación preliminar');
        $e->update(['estado_tasa' => 'provisional']);
        $this->post($url, $datos)->assertOk()->assertSee('Viable y comparable')
            ->assertSee('Comparación preliminar')->assertSee('La TMAR del escenario no está validada');
        $e->update(['estado_tasa' => 'validada']);

        $b->update(['viabilidad_tecnica' => 'no_viable']);
        $this->post($url, $datos)->assertOk()->assertSee('se excluye la opción de la recomendación')
            ->assertSee('Costo cero documentado')->assertDontSee('Menor VPC bajo los supuestos registrados: Hacienda');

        $b->update(['viabilidad_tecnica' => 'viable']);
        $fuente->delete();
        $this->post($url, $datos)->assertOk()->assertSee('Información económica insuficiente')
            ->assertSee('Comparación preliminar')->assertDontSee('Costo cero documentado');
        $cero->delete();
        $this->post($url, $datos)->assertOk()->assertSee('Sin movimientos incluidos en esta perspectiva')
            ->assertDontSee('Menor VPC bajo los supuestos registrados: Hacienda');
    }

    public function test_caso_asagua_muestra_menor_costo_en_comparacion_preliminar(): void
    {
        [$p, $e, $a, $b, $usuario] = $this->proyecto();
        $this->concepto($p, $a, ['monto' => '33.90']);
        $this->concepto($p, $b, ['monto' => '71.428571', 'naturaleza_economica' => 'costo_oportunidad',
            'estado_dato' => 'estimado', 'observaciones' => 'Valor estimado del tiempo administrativo.']);
        $datos = array_replace($this->datos($e->id, [$a->id, $b->id]), ['periodicidad' => 'anual', 'perspectiva' => 'economica']);

        $this->actingAs($usuario)->post(route(self::RUTA.'preview', $p), $datos)->assertOk()
            ->assertSee('Menor VPC bajo los supuestos registrados: ACAE')->assertSee('1974.16')
            ->assertSee('4159.62')->assertSee('406.80 USD/año')->assertSee('857.14 USD/año')
            ->assertSee('Comparación preliminar')->assertSee('La viabilidad técnica está pendiente')
            ->assertSee('No aplicable con los datos actuales');

        $resultado = app(EvaluacionService::class)->preparar($p, $e, $datos);
        $evaluacion = $p->evaluaciones()->make([
            'perspectiva' => 'economica', 'moneda' => 'USD', 'version_motor' => $resultado['version'], 'instantanea' => $resultado,
        ]);
        $evaluacion->escenario()->associate($e);
        $evaluacion->usuario()->associate($usuario);
        $evaluacion->save();
        $this->post(route('ingenieria-economica.proyectos.informes.preview', $p), [
            'evaluacion_id' => $evaluacion->id, 'estado' => 'preliminar',
        ])->assertOk()->assertSee('Menor VPC bajo los supuestos registrados: ACAE')
            ->assertSee('Comparación preliminar')->assertSee('2185.47 USD')->assertSee('450.34 USD/año');
    }

    public function test_guardado_inmutable_firma_obsoleta_y_autorizacion_por_proyecto(): void
    {
        [$proyecto, $escenario, $a, $b, $usuario] = $this->proyecto();
        [$ajeno, $escenarioAjeno, , , $otro] = $this->proyecto();
        $concepto = $this->concepto($proyecto, $a, ['monto' => '33.90']);
        $datos = $this->datos($escenario->id, [$a->id]);
        $this->get(route(self::RUTA.'index', $proyecto))->assertRedirectToRoute('login');
        $this->actingAs(User::factory()->create(['rol' => 'Auditor']))->post(route(self::RUTA.'preview', $proyecto), $datos)->assertForbidden();
        $this->actingAs($otro)->get(route(self::RUTA.'index', $proyecto))->assertForbidden();
        $this->post(route(self::RUTA.'preview', $proyecto), $datos)->assertForbidden();

        $this->actingAs($usuario)->post(route(self::RUTA.'preview', $proyecto), array_replace($datos, [
            'escenario_id' => $escenarioAjeno->id, 'referencia_id' => $b->id, 'periodicidad' => 'semanal',
        ]))->assertSessionHasErrors(['escenario_id', 'referencia_id', 'periodicidad']);
        $this->post(route(self::RUTA.'store', $proyecto), $datos)->assertSessionHasErrors('firma');
        $vista = $this->post(route(self::RUTA.'preview', $proyecto), $datos)->assertOk();
        $firma = $vista->viewData('firma');

        $this->post(route(self::RUTA.'store', $proyecto), $datos + ['firma' => $firma])->assertRedirect()->assertSessionHasNoErrors();
        $primera = Evaluacion::query()->sole();
        $instantanea = $primera->instantanea;
        $this->assertSame($usuario->id_usuario, $primera->id_usuario);
        $this->assertSame('1983.22', $this->dinero($instantanea['alternativas'][$a->id]['indicadores']['vpc']));

        $concepto->update(['monto' => '40']);
        $this->post(route(self::RUTA.'store', $proyecto), $datos + ['firma' => $firma])->assertSessionHasErrors('evaluacion');
        $this->get(route(self::RUTA.'show', [$proyecto, $primera]))->assertOk()->assertSee('1983.22');
        $this->assertSame($instantanea, $primera->fresh()->instantanea);

        $nueva = $this->post(route(self::RUTA.'preview', $proyecto), $datos)->assertOk();
        $this->post(route(self::RUTA.'store', $proyecto), $datos + ['firma' => $nueva->viewData('firma')])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('ie_evaluaciones', 2);
        $this->assertSame($instantanea, $primera->fresh()->instantanea);

        $this->actingAs($otro)->get(route(self::RUTA.'show', [$proyecto, $primera]))->assertForbidden();
        $this->get(route(self::RUTA.'show', [$ajeno, $primera]))->assertNotFound();
    }

    public function test_comparacion_conserva_tir_incremental_y_separa_inviabilidad(): void
    {
        [$proyecto, $escenario, $a, $b] = $this->proyecto();
        $escenario->update(['horizonte_anios' => 1, 'tasa_efectiva_anual' => '0.10']);
        $b->update(['viabilidad_tecnica' => 'no_viable']);
        $this->concepto($proyecto, $a, ['monto' => '100', 'tipo_concepto' => 'inversion_inicial', 'periodicidad' => 'unico']);
        $this->concepto($proyecto, $a, ['monto' => '110', 'tipo_concepto' => 'valor_rescate', 'periodicidad' => 'unico', 'naturaleza_economica' => 'ingreso_real']);
        $this->concepto($proyecto, $b, ['monto' => '0', 'tipo_concepto' => 'inversion_inicial', 'periodicidad' => 'unico']);
        $datos = array_replace($this->datos($escenario->id, [$a->id, $b->id], $b->id), ['periodicidad' => 'anual', 'equivalencia' => true]);

        $comparacion = app(EvaluacionService::class)->preparar($proyecto, $escenario, $datos)['comparaciones'][0];

        $this->assertSame('0.00', $this->dinero($comparacion['van']));
        $this->assertSame('unica', $comparacion['tir']['estado']);
        $this->assertEqualsWithDelta(0.1, (float) $comparacion['tir']['raices'][0]['tasa'], 1e-12);
        $this->assertStringContainsString('técnicamente inviable', $comparacion['criterio']);
    }

    public function test_evaluacion_sin_tmar_se_rechaza_y_cero_es_valido(): void
    {
        [$proyecto, $escenario, $a, , $usuario] = $this->proyecto();
        $escenario->update(['tasa_efectiva_anual' => null, 'estado_tasa' => 'pendiente']);
        $datos = $this->datos($escenario->id, [$a->id]);
        $this->actingAs($usuario)->post(route(self::RUTA.'preview', $proyecto), $datos)->assertSessionHasErrors('evaluacion');

        $escenario->update(['tasa_efectiva_anual' => '0']);
        $this->concepto($proyecto, $a, ['monto' => '33.90']);
        $this->post(route(self::RUTA.'preview', $proyecto), $datos)->assertOk()->assertSee('2034.00')->assertSee('406.80');
    }

    private function proyecto(): array
    {
        $usuario = User::factory()->create();
        $proyecto = new Proyecto([
            'nombre' => 'Facturación Electrónica', 'tipo_decision' => 'reemplazo', 'fecha_base' => '2026-11-24',
            'horizonte_anios' => 5, 'estado' => 'en_preparacion',
        ]);
        $proyecto->usuario()->associate($usuario);
        $proyecto->save();
        $escenario = $proyecto->escenarios()->create([
            'nombre' => 'Base', 'horizonte_anios' => 5, 'periodicidad' => 'anual', 'tasa_original' => '0.0025',
            'modalidad_tasa' => 'efectiva', 'periodicidad_tasa' => 'trimestral', 'tasa_efectiva_anual' => '0.010037562539',
            'estado_tasa' => 'provisional', 'fuente_tasa' => 'Referencia trimestral por confirmar',
        ]);
        $a = $proyecto->alternativas()->create(['nombre' => 'ACAE', 'estado_implementacion' => 'implementada', 'viabilidad_tecnica' => 'viable']);
        $b = $proyecto->alternativas()->create(['nombre' => 'Hacienda', 'estado_implementacion' => 'no_implementada', 'viabilidad_tecnica' => 'pendiente']);

        return [$proyecto, $escenario, $a, $b, $usuario];
    }

    private function concepto(Proyecto $proyecto, ?Alternativa $alternativa, array $datos): Concepto
    {
        $concepto = $proyecto->conceptos()->make($datos + [
            'nombre' => 'Costo', 'tipo_concepto' => 'costo_operativo', 'moneda' => 'USD',
            'periodicidad' => 'mensual', 'momento_pago' => 'fin',
            'clasificacion_decision' => 'diferencial', 'naturaleza_economica' => 'desembolso_real', 'estado_dato' => 'confirmado',
        ]);
        $concepto->alternativa()->associate($alternativa);
        $concepto->save();

        return $concepto;
    }

    private function datos(int $escenario, array $alternativas, ?int $referencia = null): array
    {
        return [
            'escenario_id' => $escenario, 'alternativas' => $alternativas, 'referencia_id' => $referencia,
            'moneda' => 'USD', 'periodicidad' => 'mensual', 'perspectiva' => 'caja',
        ];
    }

    private function dinero(string $valor): string
    {
        return DecimalFinanciero::dinero(BigDecimal::of($valor));
    }
}
