<?php

namespace Tests\Feature\IngenieriaEconomica;

use App\Models\IngenieriaEconomica\AnalisisSensibilidad;
use App\Models\IngenieriaEconomica\Evaluacion;
use App\Models\IngenieriaEconomica\Proyecto;
use App\Models\User;
use App\Services\IngenieriaEconomica\BaseSensibilidadService;
use App\Services\IngenieriaEconomica\DecimalFinanciero;
use App\Services\IngenieriaEconomica\EvaluacionService;
use App\Services\IngenieriaEconomica\SensibilidadService;
use Brick\Math\BigDecimal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SensibilidadTest extends TestCase
{
    use RefreshDatabase;

    private const R = 'ingenieria-economica.proyectos.sensibilidad.';

    public function test_base_coincide_con_fase_cuatro_y_tmar_conserva_flujos_y_tir(): void
    {
        [$p, $e, $a, $b, $soporte] = $this->proyecto();
        $this->concepto($p, $a, ['nombre' => 'Ingreso', 'tipo_concepto'=>'ingreso', 'naturaleza_economica' => 'ingreso_real', 'monto' => '50']);
        $this->concepto($p, $a, ['nombre' => 'Inversión', 'tipo_concepto' => 'inversion_inicial', 'periodicidad' => 'unico', 'monto' => '100']);
        $d = $this->datos($e->id, [$a->id, $b->id], 'unidimensional', [['variable' => 'tmar', 'modo'=>'absoluto', 'valores'=>'0, 1.0037562539, 3, 5, 10']]);
        $r = $this->generar($p, $e, $d);
        $normal = app(EvaluacionService::class)->preparar($p, $e, $d + ['referencia_id' => $b->id]);
        $this->assertSame($normal['alternativas'], $r['base']['alternativas']);
        $this->assertCount(5, $r['simulaciones']);
        $this->assertSame('0', $r['simulaciones'][0]['tasa']);
        $this->assertSame($normal['alternativas'][$a->id]['indicadores']['tir'], $r['simulaciones'][4]['alternativas'][$a->id]['indicadores']['tir']);
        $memoria = app(SensibilidadService::class)->memoria($r, 4);
        $this->assertSame(array_column($normal['alternativas'][$a->id]['periodos'], 'neto'), array_column($memoria['alternativas'][$a->id]['periodos'], 'neto'));
        $this->assertNotSame($normal['alternativas'][$a->id]['indicadores']['vpc'], $memoria['alternativas'][$a->id]['indicadores']['vpc']);
        $this->assertSame('33.900000', $soporte->fresh()->monto);
        $this->assertSame('0.010037562539', $e->fresh()->tasa_efectiva_anual);
    }

    public function test_soporte_y_enfoques_con_tasa_cero_y_horas_criticas(): void
    {
        [$p, $e, $a, $b, $soporte, $laboral] = $this->proyecto();
        $e->update(['tasa_efectiva_anual' => '0']);
        $d = $this->datos($e->id, [$a->id, $b->id], 'unidimensional', [['variable'=>'monto:'.$soporte->id,'modo'=>'absoluto','valores'=>'25, 30, 33.90, 40, 50']]);
        $r = $this->generar($p, $e, $d);
        $this->assertSame(['1500.00', '1800.00', '2034.00', '2400.00', '3000.00'],
            array_map(fn ($s) => $this->dinero($s['alternativas'][$a->id]['indicadores']['vpc']), $r['simulaciones']));
        $this->assertSame('0.00', $this->dinero($r['base']['alternativas'][$b->id]['indicadores']['vpc']));
        $this->assertNull($r['simulaciones'][2]['alternativas'][$a->id]['elasticidades']['vpc']['valor']);

        $eq = array_replace($d, [
            'modo'=>'equilibrio', 'perspectiva'=>'economica',
            'ejes'=>[['variable'=>'horas:'.$laboral->id,'modo'=>'absoluto','min'=>'0','max'=>'2']],
            'oportunidad'=>[$laboral->id => ['salario'=>'500','jornada'=>'7','dias'=>'22','horas'=>'1']],
        ]);
        $resultado = $this->generar($p, $e, $eq);
        $this->assertCount(1, $resultado['equilibrio']['cruces']);
        $punto = $resultado['equilibrio']['cruces'][0];
        $this->assertEqualsWithDelta(0.4746, (float) $punto['valor'], 0.000001);
        $this->assertEqualsWithDelta(28.476, (float) $punto['valor'] * 60, 0.0001);
        $this->assertLessThan(0.000001, abs((float) $punto['residual']));
        $this->assertSame('4285.71', $this->dinero($resultado['base']['alternativas'][$b->id]['indicadores']['vpc']));
        $this->assertSame('71.428500', $laboral->fresh()->monto);
        $this->assertDatabaseCount('ie_conceptos', 2);

        $eq['ejes'][0]['min'] = '1';
        $this->assertSame([], $this->generar($p, $e, $eq)['equilibrio']['cruces']);
    }

    public function test_matriz_escenarios_inversion_rescate_mantenimiento_y_horizonte(): void
    {
        [$p, $e, $a, $b, $soporte, $laboral] = $this->proyecto();
        $e->update(['tasa_efectiva_anual' => '0']);
        $inversion = $this->concepto($p, $a, ['nombre'=>'Inversión','tipo_concepto'=>'inversion_inicial','periodicidad'=>'unico','monto'=>'100']);
        $rescate = $this->concepto($p, $a, ['nombre'=>'Rescate','tipo_concepto'=>'valor_rescate','naturaleza_economica'=>'ingreso_real','periodicidad'=>'unico','monto'=>'50']);
        $mantenimiento = $this->concepto($p, $a, ['nombre'=>'Mantenimiento','tipo_concepto'=>'mantenimiento','monto'=>'10','periodicidad'=>'anual']);
        $oportunidad = [$laboral->id => ['salario'=>'500','jornada'=>'7','dias'=>'22','horas'=>'1']];
        $d = $this->datos($e->id, [$a->id, $b->id], 'matriz', [
            ['variable'=>'monto:'.$soporte->id,'modo'=>'absoluto','valores'=>'25 50'],
            ['variable'=>'horas:'.$laboral->id,'modo'=>'absoluto','valores'=>'0.25 1'],
        ]) + ['oportunidad'=>$oportunidad];
        $d['perspectiva'] = 'economica';
        $m = $this->generar($p, $e, $d);
        $this->assertCount(4, $m['simulaciones']);
        $this->assertSame($b->id, $m['simulaciones'][0]['menor_vpc']);
        $this->assertSame($a->id, $m['simulaciones'][1]['menor_vpc']);
        $this->assertNotEmpty($m['cruces_matriz']);

        $d['modo'] = 'escenarios';
        $d['escenarios'] = [
            'optimista'=>['justificacion'=>'Supuestos académicos para costos bajos', 'cambios'=>[
                ['variable'=>'monto:'.$inversion->id,'modo'=>'relativo','valor'=>'-50'],
                ['variable'=>'monto:'.$rescate->id,'modo'=>'absoluto','valor'=>'100'],
                ['variable'=>'monto:'.$mantenimiento->id,'modo'=>'relativo','valor'=>'-20'],
                ['variable'=>'salario:'.$laboral->id,'modo'=>'absoluto','valor'=>'450'],
            ]],
            'pesimista'=>['justificacion'=>'Supuestos académicos para costos altos', 'cambios'=>[
                ['variable'=>'monto:'.$inversion->id,'modo'=>'relativo','valor'=>'50'],
                ['variable'=>'migracion:'.$a->id,'modo'=>'absoluto','valor'=>'250'],
                ['variable'=>'horizonte','modo'=>'absoluto','valor'=>'2'],
                ['variable'=>'crecimiento:'.$mantenimiento->id,'modo'=>'puntos','valor'=>'5'],
            ]],
        ];
        $esc = $this->generar($p, $e, $d);
        $this->assertSame('Optimista', $esc['simulaciones'][0]['nombre']);
        $this->assertSame('Pesimista', $esc['simulaciones'][1]['nombre']);
        $this->assertSame('2124.00', $this->dinero($esc['simulaciones'][0]['alternativas'][$a->id]['indicadores']['vpc']));
        $this->assertSame('3857.14', $this->dinero($esc['simulaciones'][0]['alternativas'][$b->id]['indicadores']['vpc']));
        $this->assertSame('1234.10', $this->dinero($esc['simulaciones'][1]['alternativas'][$a->id]['indicadores']['vpc']));
        $this->assertSame(2, $esc['simulaciones'][1]['horizonte']);
        $this->assertDatabaseCount('ie_conceptos', 5);
        $this->assertDatabaseCount('ie_analisis_sensibilidad', 0);
    }

    public function test_vista_previa_guardado_inmutable_base_historica_y_permisos(): void
    {
        [$p, $e, $a, $b, $soporte, , $u] = $this->proyecto();
        [$otro, $otroEscenario, , , , , $ajeno] = $this->proyecto();
        $d = $this->datos($e->id, [$a->id, $b->id], 'unidimensional', [['variable'=>'monto:'.$soporte->id,'modo'=>'absoluto','valores'=>'25 50']]);
        $d['referencia_id'] = $a->id;
        $this->get(route(self::R.'index', $p))->assertRedirectToRoute('login');
        $this->actingAs($u)->get(route(self::R.'index', $p))->assertOk();
        $this->get(route(self::R.'create', $p))->assertOk()->assertSee('Cargar variables de la base')->assertSee('Soporte');
        $this->post(route(self::R.'preview', $p), array_replace($d, ['escenario_id'=>$otroEscenario->id]))->assertSessionHasErrors('escenario_id');
        $this->post(route(self::R.'preview', $p), array_replace($d, ['ejes' => [['variable' => 'monto:999999', 'modo'=>'absoluto', 'valores'=>'5']]]))
            ->assertSessionHasErrors('sensibilidad');
        $vista = $this->post(route(self::R.'preview', $p), $d)->assertOk()->assertSee('Resultados de simulación')->assertSee('1462.55');
        $this->assertDatabaseCount('ie_analisis_sensibilidad', 0);
        $datos = $vista->viewData('r')['configuracion'];
        $guardar = ['parametros'=>json_encode($datos), 'firma'=>$vista->viewData('firma')];
        $this->post(route(self::R.'store', $p), $guardar)->assertRedirect()->assertSessionHasNoErrors();
        $s = AnalisisSensibilidad::query()->sole();
        $snapshot = $s->instantanea;
        $hist = app(EvaluacionService::class)->preparar($p, $e, $d);
        $registro = $p->evaluaciones()->make(['perspectiva'=>'caja','moneda'=>'USD','version_motor'=>$hist['version'],'instantanea'=>$hist]);
        $registro->usuario()->associate($u);
        $registro->escenario()->associate($e);
        $registro->save();
        $this->post(route('ingenieria-economica.proyectos.informes.preview', $p), [
            'evaluacion_id' => $registro->id,
            'sensibilidad_id' => $s->id,
            'estado' => 'preliminar',
        ])->assertOk()->assertSee('Análisis de sensibilidad')->assertSee('1462.55');
        $validacion = $this->get(route('ingenieria-economica.proyectos.validacion', [
            $p, 'evaluacion_id' => $registro->id, 'sensibilidad_id' => $s->id,
        ]))->assertOk();
        $this->assertNull($validacion->viewData('aviso'));

        $otraBase = $hist;
        $otraBase['referencia'] = $b->id;
        $otraVersion = $registro->replicate();
        $otraVersion->instantanea = $otraBase;
        $otraVersion->save();
        $discrepancia = [$p, 'evaluacion_id' => $otraVersion->id, 'sensibilidad_id' => $s->id];
        foreach (['validacion', 'informes.index'] as $pantalla) {
            $this->get(route('ingenieria-economica.proyectos.'.$pantalla, $discrepancia))
                ->assertOk()->assertSee('La sensibilidad debe usar la misma alternativa de referencia');
        }
        $soporte->update(['monto'=>'60']);
        $this->post(route(self::R.'store', $p), $guardar)->assertSessionHasErrors('sensibilidad');
        $this->get(route(self::R.'show', [$p, $s, 'memoria'=>0]))->assertOk()->assertSee('Memoria de cálculo');
        $this->assertSame($snapshot, $s->fresh()->instantanea);
        $r = $this->generar($p, $e, $d, $registro);
        $this->assertSame('1983.22', $this->dinero($r['base']['alternativas'][$a->id]['indicadores']['vpc']));
        unset($hist['insumos']);
        $registro->instantanea = $hist;
        $this->assertSame('1983.22', $this->dinero($this->generar($p, $e, $d, $registro)['base']['alternativas'][$a->id]['indicadores']['vpc']));
        $this->actingAs($ajeno)->get(route(self::R.'show', [$p, $s]))->assertForbidden();
        $this->get(route(self::R.'show', [$otro, $s]))->assertNotFound();
        $this->actingAs(User::factory()->create(['rol'=>'Auditor']))->get(route(self::R.'index', $p))->assertForbidden();
    }

    public function test_formularios_de_matriz_equilibrio_y_escenarios_y_modalidades_de_tasa(): void
    {
        [$p, $e, $a, $b, $soporte, $laboral, $u] = $this->proyecto();
        $d = $this->datos($e->id, [$a->id, $b->id], 'matriz', [
            ['variable' => 'monto:'.$soporte->id, 'modo' => 'absoluto', 'valores' => '25 50'],
            ['variable' => 'horas:'.$laboral->id, 'modo' => 'absoluto', 'valores' => '0.25 1'],
        ]) + ['oportunidad' => [$laboral->id => ['salario'=>'500','jornada'=>'7','dias'=>'22','horas'=>'1']]];
        $d['perspectiva'] = 'economica';
        $url = route(self::R.'preview', $p);
        $this->actingAs($u)->post($url, $d)->assertOk()->assertSee('Línea aproximada de indiferencia');
        $d['modo'] = 'equilibrio';
        $d['ejes'] = [['variable'=>'horas:'.$laboral->id,'modo'=>'absoluto','min'=>'0','max'=>'2']];
        $this->post($url, $d)->assertOk()->assertSee('28.48 min/día');
        $d['modo'] = 'escenarios';
        $d['escenarios'] = [
            'optimista'=>['justificacion'=>'Cinco por ciento relativo', 'cambios'=>[['variable'=>'tmar','modo'=>'relativo','valor'=>'5']]],
            'pesimista'=>['justificacion'=>'Cinco puntos porcentuales', 'cambios'=>[['variable'=>'tmar','modo'=>'puntos','valor'=>'5']]],
        ];
        $r = $this->post($url, $d)->assertOk()->assertSee('Optimista')->assertSee('Pesimista')->viewData('r');
        $this->assertSame('0.01053944066595', (string) BigDecimal::of($r['simulaciones'][0]['anual'])->strippedOfTrailingZeros());
        $this->assertSame('0.060037562539', (string) BigDecimal::of($r['simulaciones'][1]['anual'])->strippedOfTrailingZeros());
        $this->assertSame('La base es cero.', app(SensibilidadService::class)->relativa('0', '1', [['cambio_pct'=>'5']])['motivo']);
        $this->assertNull(app(SensibilidadService::class)->relativa('-1', '1', [['cambio_pct'=>'5']])['valor']);
    }

    public function test_matriz_conserva_cifras_y_no_clasifica_una_alternativa_inviable(): void
    {
        [$p, $e, $a, $b, $soporte, , $u] = $this->proyecto();
        $b->update(['viabilidad_tecnica'=>'no_viable']);
        $d = $this->datos($e->id, [$a->id,$b->id], 'matriz', [
            ['variable'=>'monto:'.$soporte->id,'modo'=>'absoluto','valores'=>'25 40'],
            ['variable'=>'tmar','modo'=>'absoluto','valores'=>'0 5'],
        ]);
        $url = route(self::R.'preview', $p);
        $this->actingAs($u)->post($url, $d)->assertOk()->assertSee('Sin clasificación por costo')
            ->assertSee('Comparación preliminar')->assertSee('Hacienda')->assertSee('Δ VPC');
        $b->update(['viabilidad_tecnica'=>'viable']);
        $this->post($url,$d)->assertOk()->assertSee('Sin clasificación por costo')
            ->assertSee('Revisa viabilidad o suficiencia de datos');
        $d['modo'] = 'unidimensional';
        $d['ejes'] = [['variable'=>'migracion:'.$a->id,'modo'=>'absoluto','valores'=>'15']];
        $this->post($url,$d)->assertOk()->assertSee('No aplicable (base cero)');
        $d['ejes'] = [['variable'=>'tmar','modo'=>'puntos','valores'=>'1']];
        $this->post($url,$d)->assertOk()->assertSee('Cambio absoluto 1 punto porcentual')->assertSee('Cambio relativo');
        $d['ejes'][0] = ['variable'=>'tmar','modo'=>'absoluto','min'=>'0','max'=>'5','paso'=>'0'];
        $this->post($url,$d)->assertSessionHasErrors('sensibilidad');
    }

    private function proyecto(): array
    {
        $u = User::factory()->create();
        $p = new Proyecto(['nombre'=>'Facturación de prueba', 'tipo_decision'=>'reemplazo', 'fecha_base'=>'2026-11-24', 'horizonte_anios'=>5, 'estado'=>'en_preparacion']);
        $p->usuario()->associate($u);
        $p->save();
        $e = $p->escenarios()->create(['nombre'=>'Base', 'horizonte_anios'=>5, 'periodicidad'=>'anual', 'tasa_efectiva_anual'=>'0.010037562539', 'estado_tasa'=>'provisional']);
        $a = $p->alternativas()->create(['nombre'=>'ACAE', 'estado_implementacion'=>'implementada', 'viabilidad_tecnica'=>'pendiente']);
        $b = $p->alternativas()->create(['nombre'=>'Hacienda', 'estado_implementacion'=>'no_implementada', 'viabilidad_tecnica'=>'pendiente']);
        $s = $this->concepto($p, $a, ['nombre'=>'Soporte', 'monto'=>'33.90']);
        $l = $this->concepto($p, $b, ['nombre'=>'Tiempo adicional', 'monto'=>'71.4285', 'naturaleza_economica'=>'costo_oportunidad',
            'estado_dato'=>'estimado', 'observaciones'=>'Valoración de dedicación estimada.']);

        return [$p, $e, $a, $b, $s, $l, $u];
    }

    private function concepto($p, $a, array $d)
    {
        $c = $p->conceptos()->make($d + ['tipo_concepto'=>'costo_operativo', 'moneda'=>'USD', 'periodicidad'=>'mensual',
            'momento_pago'=>'fin', 'clasificacion_decision'=>'diferencial', 'naturaleza_economica'=>'desembolso_real', 'estado_dato'=>'confirmado']);
        $c->alternativa()->associate($a);
        $c->save();

        return $c;
    }

    private function datos($e, array $ids, string $modo, array $ejes): array
    {
        return ['nombre'=>'Prueba de sensibilidad', 'supuestos'=>'Supuestos académicos documentados.', 'estado'=>'provisional',
            'escenario_id'=>$e, 'alternativas'=>$ids, 'perspectiva'=>'caja', 'moneda'=>'USD', 'periodicidad'=>'mensual',
            'indicadores'=>['van','vpc','caue','rcb','tir'], 'modo'=>$modo, 'ejes'=>$ejes];
    }

    private function generar($p, $e, array $d, ?Evaluacion $evaluacion = null): array
    {
        $base = app(BaseSensibilidadService::class)->capturar($p, $e, $evaluacion, $d['alternativas'], $d['moneda']);

        return app(SensibilidadService::class)->generar($base, $d);
    }

    private function dinero(string $v): string
    {
        return DecimalFinanciero::dinero(BigDecimal::of($v));
    }
}
