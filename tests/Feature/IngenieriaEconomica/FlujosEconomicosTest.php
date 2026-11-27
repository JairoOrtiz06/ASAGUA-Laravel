<?php

namespace Tests\Feature\IngenieriaEconomica;

use App\Models\CuentaContable;
use App\Models\DetallePartida;
use App\Models\DocumentoFuente;
use App\Models\IngenieriaEconomica\AjusteFlujo;
use App\Models\IngenieriaEconomica\Alternativa;
use App\Models\IngenieriaEconomica\Concepto;
use App\Models\IngenieriaEconomica\Escenario;
use App\Models\IngenieriaEconomica\Proyecto;
use App\Models\Partida;
use App\Models\User;
use App\Services\IngenieriaEconomica\DecimalFinanciero;
use App\Services\IngenieriaEconomica\ProyeccionFlujosService;
use Brick\Math\BigDecimal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlujosEconomicosTest extends TestCase
{
    use RefreshDatabase;

    private const RUTA = 'ingenieria-economica.proyectos.';

    private const CONCEPTO = [
        'nombre' => 'Soporte ACAE', 'tipo_concepto' => 'costo_operativo', 'monto' => '33.90',
        'moneda' => 'USD', 'periodicidad' => 'mensual', 'momento_pago' => 'fin',
        'clasificacion_decision' => 'diferencial', 'naturaleza_economica' => 'desembolso_real', 'estado_dato' => 'confirmado',
    ];

    public function test_programacion_mensual_anual_fecha_real_inversion_rescate_y_neto(): void
    {
        [$proyecto, $alternativa, $escenario] = $this->proyecto();
        $escenario->update(['fecha_base' => '2025-12-01']);
        $this->concepto($proyecto, $alternativa);
        $this->concepto($proyecto, $alternativa, [
            'nombre' => 'Inversión inicial', 'tipo_concepto' => 'inversion_inicial', 'monto' => '1695',
            'periodicidad' => 'unico', 'momento_pago' => 'inicio',
        ]);
        $this->concepto($proyecto, $alternativa, [
            'nombre' => 'Servicio extraordinario', 'monto' => '135.60', 'periodicidad' => 'unico', 'fecha_real' => '2026-05-13',
        ]);
        $this->concepto($proyecto, $alternativa, [
            'nombre' => 'Mantenimiento anual', 'tipo_concepto' => 'mantenimiento', 'monto' => '100',
            'periodicidad' => 'anual', 'periodo_fin' => 36, 'porcentaje_crecimiento' => '0.1',
        ]);
        $this->concepto($proyecto, $alternativa, [
            'nombre' => 'Inversión del cuarto año', 'tipo_concepto' => 'inversion_adicional', 'monto' => '10',
            'periodicidad' => 'unico', 'periodo_inicio' => 36, 'momento_pago' => 'inicio',
        ]);
        $this->concepto($proyecto, $alternativa, [
            'nombre' => 'Rescate', 'tipo_concepto' => 'valor_rescate', 'monto' => '200',
            'periodicidad' => 'unico', 'naturaleza_economica' => 'ingreso_real',
        ]);

        $mensual = $this->generar($proyecto, $escenario, $alternativa, 'mensual');
        $periodos = $mensual['periodos'];
        $movimientos = collect($periodos)->pluck('movimientos')->collapse();
        $soportes = $movimientos->where('nombre', 'Soporte ACAE')->values();
        $anuales = $movimientos->where('nombre', 'Mantenimiento anual')->values();

        $this->assertCount(61, $periodos);
        $this->assertCount(60, $soportes);
        $this->assertSame('2026-01-01', $soportes[0]['fecha']->toDateString());
        $this->assertSame('2030-12-01', $soportes[59]['fecha']->toDateString());
        $this->assertSame('1695.00', DecimalFinanciero::dinero($periodos[0]['inversiones']));
        $this->assertCount(1, $movimientos->where('nombre', 'Servicio extraordinario'));
        $observado = $movimientos->firstWhere('nombre', 'Servicio extraordinario');
        $this->assertTrue($observado['observado']);
        $this->assertSame('2026-05-13', $observado['fecha']->toDateString());
        $this->assertCount(3, $anuales);
        $this->assertSame('121.00', DecimalFinanciero::dinero($anuales[2]['monto']));
        $this->assertSame('2028-12-01', $movimientos->firstWhere('nombre', 'Inversión del cuarto año')['fecha']->toDateString());
        $this->assertSame('2030-12-01', $movimientos->firstWhere('nombre', 'Rescate')['fecha']->toDateString());
        $this->assertSame('200.00', DecimalFinanciero::dinero($periodos[60]['rescates']));
        $this->assertSame('-4005.60', DecimalFinanciero::dinero($mensual['totales']['neto']));

        $anual = $this->generar($proyecto, $escenario, $alternativa, 'anual');
        $this->assertCount(6, $anual['periodos']);
        $this->assertSame('406.80', DecimalFinanciero::dinero($anual['periodos'][4]['costos']));
        $this->assertSame((string) $mensual['totales']['neto'], (string) $anual['totales']['neto']);
        $this->assertSame(60, $anual['periodos'][5]['movimientos'][array_key_last($anual['periodos'][5]['movimientos'])]['periodo_escenario']);
    }

    public function test_sesenta_pagos_vencidos_con_inicio_explicito_y_cierre_inclusivo(): void
    {
        [$proyecto, $alternativa, $escenario, $contador] = $this->proyecto();
        $escenario->update(['fecha_base' => '2026-11-24', 'periodicidad' => 'anual']);
        $programacion = ['periodo_inicio' => 1, 'periodo_fin' => 60, 'numero_ocurrencias' => 60, 'porcentaje_crecimiento' => '0'];
        $concepto = $this->concepto($proyecto, $alternativa, $programacion + ['momento_pago' => 'inicio']);
        $anterior = $this->generar($proyecto, $escenario, $alternativa, 'escenario');
        $this->assertCount(11, $anterior['periodos'][5]['movimientos']);
        $this->assertSame('2000.10', DecimalFinanciero::dinero($anterior['totales']['salidas']));

        $this->actingAs($contador)->patch(route(self::RUTA.'conceptos.update', [$proyecto, $concepto]), $programacion + self::CONCEPTO)
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('fin', $concepto->fresh()->momento_pago);
        $this->assertSame($concepto->monto, $concepto->fresh()->monto);

        $resultado = $this->generar($proyecto, $escenario, $alternativa, 'escenario');
        $periodos = $resultado['periodos'];
        $movimientos = collect($periodos)->pluck('movimientos')->collapse();
        $this->assertCount(60, $movimientos);
        $this->assertSame('2026-12-24', $movimientos->first()['fecha']->toDateString());
        $this->assertSame('2031-11-24', $movimientos->last()['fecha']->toDateString());
        $this->assertSame('2034.00', DecimalFinanciero::dinero($resultado['totales']['salidas']));
        $this->assertSame([0, 12, 12, 12, 12, 12], array_map(fn ($periodo) => count($periodo['movimientos']), $periodos));
        $this->assertSame(array_fill(0, 5, '406.80'), array_map(
            fn ($periodo) => DecimalFinanciero::dinero($periodo['salidas']), array_slice($periodos, 1),
        ));
        $this->assertSame([], $resultado['excluidos']);

        $sumaMovimientos = $movimientos->reduce(fn (BigDecimal $suma, array $item) => $suma->plus($item['monto']), BigDecimal::zero());
        $sumaPeriodos = collect($periodos)->reduce(fn (BigDecimal $suma, array $item) => $suma->plus($item['salidas']), BigDecimal::zero());
        $this->assertTrue($sumaMovimientos->isEqualTo($sumaPeriodos));
        $this->assertTrue($sumaPeriodos->isEqualTo($resultado['totales']['salidas']));
        foreach ($periodos as $indice => $periodo) {
            if ($indice > 0) {
                $this->assertTrue($periodos[$indice - 1]['hasta']->addDay()->eq($periodo['desde']));
            }
            foreach ($periodo['movimientos'] as $movimiento) {
                $this->assertTrue($movimiento['fecha']->betweenIncluded($periodo['desde'], $periodo['hasta']));
            }
        }

        $regenerado = $this->generar($proyecto, $escenario, $alternativa, 'escenario');
        $this->assertSame($movimientos->pluck('fecha')->map->toDateString()->all(),
            collect($regenerado['periodos'])->pluck('movimientos')->collapse()->pluck('fecha')->map->toDateString()->all());
        $this->assertDatabaseCount('ie_flujos', 0);
    }

    public function test_vista_previa_del_calendario_reutiliza_cierres_y_no_persiste(): void
    {
        [$p, $a, $e, $u] = $this->proyecto();
        [$ajeno, , $escenarioAjeno, $otro] = $this->proyecto();
        $e->update(['fecha_base' => '2026-11-24', 'periodicidad' => 'anual']);
        $concepto = $this->concepto($p, $a, ['monto' => '25', 'momento_pago' => 'inicio']);
        $url = route(self::RUTA.'conceptos.previsualizar', $p);
        $datos = ['escenario_calendario' => $e->id, 'alternativa_id' => $a->id, 'periodo_inicio' => 1,
            'periodo_fin' => 60, 'numero_ocurrencias' => 60] + self::CONCEPTO;
        $this->actingAs($u)->get(route(self::RUTA.'conceptos.create', $p))->assertOk()
            ->assertSee('Programación temporal')->assertSee('Clasificación económica')->assertSee('Vista previa del calendario');
        $this->postJson($url, $datos)->assertOk()->assertJsonPath('movimientos', 60)
            ->assertJsonPath('primero', '24/12/2026')->assertJsonPath('ultimo', '24/11/2031')
            ->assertJsonPath('total', '2034.00')->assertJsonPath('fuera', 0);
        $this->postJson($url, array_replace($datos, ['periodicidad' => 'trimestral']))->assertOk()
            ->assertJsonPath('movimientos', 20)->assertJsonPath('fuera', 0)->assertJsonPath('primero', '24/02/2027');
        $this->postJson($url, array_replace($datos, ['momento_pago' => 'inicio']))->assertOk()
            ->assertJsonPath('movimientos', 59)->assertJsonPath('fuera', 1);
        $this->postJson($url, array_replace($datos, ['monto' => '-1']))->assertUnprocessable()->assertJsonValidationErrors('monto');
        $this->postJson($url, array_replace($datos, ['escenario_calendario' => $escenarioAjeno->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('escenario_calendario');
        unset($datos['alternativa_id']);
        $this->postJson(route(self::RUTA.'conceptos.previsualizarEdicion', [$p, $concepto]), $datos)
            ->assertOk()->assertJsonPath('movimientos', 60);
        $this->postJson($url, ['clasificacion_decision' => 'comun'] + $datos)->assertOk()->assertJsonPath('total', '2034.00');
        $this->postJson(route(self::RUTA.'conceptos.previsualizarEdicion', [$ajeno, $concepto]), $datos)->assertNotFound();
        $this->actingAs($otro)->postJson($url, $datos)->assertForbidden();
        $this->assertSame('25.000000', $concepto->fresh()->monto);
        $this->assertDatabaseCount('ie_conceptos', 1);
        $this->assertDatabaseCount('ie_flujos', 0);
    }

    public function test_fechas_limite_y_recurrencias_trimestrales_anuales_y_anticipadas(): void
    {
        [$proyecto, $alternativa, $escenario] = $this->proyecto();
        $escenario->update(['fecha_base' => '2026-11-30', 'periodicidad' => 'trimestral', 'horizonte_anios' => 1]);
        foreach ([
            ['nombre' => 'Trimestral', 'periodicidad' => 'trimestral', 'periodo_inicio' => 1, 'periodo_fin' => 12],
            ['nombre' => 'Anual', 'periodicidad' => 'anual', 'periodo_inicio' => 1],
            ['nombre' => 'Anticipado', 'periodicidad' => 'trimestral', 'momento_pago' => 'inicio', 'periodo_inicio' => 0, 'numero_ocurrencias' => 5],
            ['nombre' => 'Cierre observado', 'periodicidad' => 'unico', 'fecha_real' => '2027-11-30'],
            ['nombre' => 'Antes de base', 'periodicidad' => 'unico', 'fecha_real' => '2026-11-29'],
            ['nombre' => 'Después del cierre', 'periodicidad' => 'unico', 'fecha_real' => '2027-12-01'],
        ] as $datos) {
            $this->concepto($proyecto, $alternativa, $datos);
        }

        $resultado = $this->generar($proyecto, $escenario, $alternativa, 'trimestral');
        $movimientos = collect($resultado['periodos'])->pluck('movimientos')->collapse();
        $fechas = fn (string $nombre) => $movimientos->where('nombre', $nombre)->pluck('fecha')->map->toDateString()->all();

        $this->assertSame(['2027-02-28', '2027-05-30', '2027-08-30', '2027-11-30'], $fechas('Trimestral'));
        $this->assertSame(['2027-11-30'], $fechas('Anual'));
        $this->assertSame(['2026-11-30', '2027-02-28', '2027-05-30', '2027-08-30'], $fechas('Anticipado'));
        $this->assertSame(['2027-11-30'], $fechas('Cierre observado'));
        $this->assertSame(4, $movimientos->firstWhere('nombre', 'Cierre observado')['periodo_escenario']);
        $this->assertSame(['Anterior a la fecha base', 'Fuera del horizonte'], array_column($resultado['excluidos'], 'motivo'));
    }

    public function test_hundidos_comunes_y_costos_de_oportunidad_se_distinguen(): void
    {
        [$proyecto, $alternativa, $escenario, $contador] = $this->proyecto();
        $otra = $proyecto->alternativas()->create($this->alternativa('Hacienda', 'pendiente'));
        $this->concepto($proyecto, null, [
            'nombre' => 'Internet', 'monto' => '25', 'clasificacion_decision' => 'comun',
        ]);
        $this->concepto($proyecto, $alternativa, [
            'nombre' => 'Compra histórica', 'monto' => '1695', 'periodicidad' => 'unico',
            'tipo_concepto' => 'inversion_inicial', 'fecha_real' => '2025-12-01', 'clasificacion_decision' => 'hundido',
        ]);
        $this->concepto($proyecto, $alternativa, [
            'nombre' => 'Otro gasto observado', 'periodicidad' => 'unico', 'fecha_real' => '2026-05-13',
        ]);
        $this->concepto($proyecto, $otra, [
            'nombre' => 'Valoración laboral', 'monto' => '25', 'naturaleza_economica' => 'costo_oportunidad',
            'numero_ocurrencias' => 1, 'estado_dato' => 'estimado',
        ]);
        $this->concepto($proyecto, $otra, [
            'nombre' => 'Pendiente por clasificar', 'clasificacion_decision' => 'pendiente_clasificacion',
            'numero_ocurrencias' => 1,
        ]);

        $resultados = app(ProyeccionFlujosService::class)->generar($proyecto, $escenario, [$alternativa->id, $otra->id], 'USD', 'anual')['resultado'];
        $ac = $resultados[$alternativa->id];
        $hc = $resultados[$otra->id];

        $this->assertSame('1500.00', DecimalFinanciero::dinero($ac['totales']['salidas']));
        $this->assertSame('1500.00', DecimalFinanciero::dinero($hc['totales']['salidas']));
        $this->assertSame('-1500.00', DecimalFinanciero::dinero($hc['totales']['comunes_netos']));
        $this->assertSame('25.00', DecimalFinanciero::dinero($hc['totales']['oportunidad']));
        $this->assertCount(2, $ac['excluidos']);
        $this->assertTrue($ac['excluidos'][0]['historico']);
        $this->assertStringContainsString('hundido', $ac['excluidos'][0]['motivo']);
        $this->assertSame('Anterior a la fecha base', $ac['excluidos'][1]['motivo']);

        $this->actingAs($contador)->get(route(self::RUTA.'flujos.index', [
            'proyecto' => $proyecto, 'escenario_id' => $escenario->id, 'alternativas' => [$alternativa->id, $otra->id],
            'agrupacion' => 'anual', 'desde' => 1, 'hasta' => 2,
        ]))->assertOk()->assertSee('Comparación preliminar')->assertSee('Costos de oportunidad')
            ->assertSee('Gráfica del flujo neto')->assertSee('está pendiente de clasificación')
            ->assertSee('La comparabilidad técnica requiere revisión')->assertDontSee('Movimientos del período 3');
    }

    public function test_ajustes_manuales_se_conservan_al_regenerar_y_otros_proyectos_no_acceden(): void
    {
        [$proyecto, $alternativa, $escenario, $contador] = $this->proyecto();
        [$ajeno, $otra, $escenarioAjeno, $otro] = $this->proyecto();
        $concepto = $this->concepto($proyecto, $alternativa);
        $datos = [
            'alternativa_id' => $alternativa->id, 'escenario_id' => $escenario->id, 'nombre' => 'Corrección',
            'fecha_flujo' => '2026-07-20', 'tipo_concepto' => 'ingreso', 'monto' => '10', 'moneda' => 'USD',
            'clasificacion_decision' => 'diferencial', 'naturaleza_economica' => 'ingreso_real', 'estado_dato' => 'confirmado',
            'observado' => '0', 'justificacion' => 'Cobro adicional documentado.',
        ];

        $this->actingAs($contador);
        $this->get(route(self::RUTA.'ajustes-flujo.create', $proyecto))->assertOk()->assertSee('Justificación del ajuste');
        $this->post(route(self::RUTA.'ajustes-flujo.store', $proyecto), $datos)->assertRedirect()->assertSessionHasNoErrors();
        $ajuste = AjusteFlujo::query()->sole();
        $this->assertSame($contador->id_usuario, $ajuste->id_usuario);
        $this->post(route(self::RUTA.'ajustes-flujo.store', $proyecto), array_replace($datos, [
            'fecha_flujo' => '2035-01-01',
        ]))->assertSessionHasErrors('fecha_flujo');

        for ($n = 0; $n < 2; $n++) {
            $actual = $this->generar($proyecto, $escenario, $alternativa, 'mensual');
            $this->assertSame('10.00', DecimalFinanciero::dinero($actual['totales']['entradas']));
            $this->assertSame(61, collect($actual['periodos'])->pluck('movimientos')->collapse()->count());
        }
        $this->assertDatabaseCount('ie_flujos', 1);

        $this->post(route(self::RUTA.'ajustes-flujo.store', $proyecto), array_replace($datos, [
            'alternativa_id' => $otra->id, 'escenario_id' => $escenarioAjeno->id,
        ]))->assertSessionHasErrors(['alternativa_id', 'escenario_id']);
        $this->get(route(self::RUTA.'flujos.index', ['proyecto' => $proyecto, 'escenario_id' => $escenarioAjeno->id]))
            ->assertSessionHasErrors('escenario_id');
        $this->delete(route(self::RUTA.'ajustes-flujo.destroy', [$ajeno, $ajuste]))->assertForbidden();
        $this->actingAs($otro)->delete(route(self::RUTA.'ajustes-flujo.destroy', [$ajeno, $ajuste]))->assertNotFound();
        $this->get(route(self::RUTA.'conceptos.edit', [$proyecto, $concepto]))->assertForbidden();
        $this->get(route(self::RUTA.'conceptos.show', [$ajeno, $concepto]))->assertNotFound();
        $this->get(route(self::RUTA.'flujos.index', $proyecto))->assertForbidden();
        $this->actingAs(User::factory()->create(['rol' => 'Auditor']))->get(route(self::RUTA.'flujos.index', $proyecto))->assertForbidden();
        $this->assertModelExists($ajuste);

        $this->actingAs($contador)->delete(route(self::RUTA.'ajustes-flujo.destroy', [$proyecto, $ajuste]))->assertRedirect();
        $this->assertModelMissing($ajuste);
    }

    public function test_crud_filtros_validacion_y_evidencias_no_escriben_en_contabilidad(): void
    {
        [$proyecto, $alternativa, , $contador] = $this->proyecto();
        $id = $alternativa->id;
        $ruta = route(self::RUTA.'conceptos.index', ['proyecto' => $proyecto, 'alternativa_id' => $id]);
        $this->actingAs($contador)
            ->get(route(self::RUTA.'conceptos.create', ['proyecto' => $proyecto, 'alternativa_id' => $id]))
            ->assertOk()->assertSee('Nuevo concepto económico');
        $this->post(route(self::RUTA.'conceptos.store', $proyecto), self::CONCEPTO + ['alternativa_id' => $id])
            ->assertRedirect()->assertSessionHasNoErrors();

        $concepto = $alternativa->conceptos()->sole();
        $this->get(route(self::RUTA.'conceptos.edit', [$proyecto, $concepto]))->assertOk();
        $this->patch(route(self::RUTA.'conceptos.update', [$proyecto, $concepto]), array_replace(self::CONCEPTO, [
            'nombre' => 'Soporte actualizado', 'estado_dato' => 'estimado',
        ]))->assertRedirect()->assertSessionHasNoErrors();
        $this->get($ruta.'&estado_dato=estimado&tipo_concepto=costo_operativo')->assertOk()->assertSee('Soporte actualizado');
        $this->get($ruta.'&estado_dato=confirmado')->assertOk()->assertDontSee('Soporte actualizado');

        $this->post(route(self::RUTA.'conceptos.store', $proyecto), array_replace(self::CONCEPTO, [
            'alternativa_id' => $id, 'monto' => '-35', 'periodicidad' => 'semanal', 'estado_dato' => 'inventado',
        ]))->assertSessionHasErrors(['monto', 'periodicidad', 'estado_dato']);
        $this->post(route(self::RUTA.'conceptos.store', $proyecto), array_replace(self::CONCEPTO, [
            'alternativa_id' => $id, 'fecha_real' => '2026-05-13', 'clasificacion_decision' => 'comun',
        ]))->assertSessionHasErrors(['fecha_real', 'clasificacion_decision']);
        $this->post(route(self::RUTA.'conceptos.store', $proyecto), array_replace(self::CONCEPTO, [
            'alternativa_id' => $id, 'periodo_inicio' => 3, 'periodo_fin' => 2,
        ]))->assertSessionHasErrors('periodo_fin');

        $cuenta = CuentaContable::create(['codigo_cuenta' => '1101', 'nombre_cuenta' => 'Caja', 'tipo_cuenta' => 'Activo', 'nivel' => 4, 'estado' => true]);
        $partida = Partida::create(['id_usuario' => $contador->id_usuario, 'numero_partida' => 1, 'fecha' => '2026-07-01', 'concepto' => 'Evidencia']);
        $documento = DocumentoFuente::create(['nombre_documento' => 'Factura original', 'tipo_documento' => 'factura']);
        $detalle = DetallePartida::create([
            'id_partida' => $partida->id_partida, 'id_cuenta' => $cuenta->id_cuenta, 'id_documento' => $documento->id_documento,
            'debe' => '100', 'haber' => '0',
        ]);
        $originales = [$cuenta->fresh()->getAttributes(), $partida->fresh()->getAttributes(),
            $documento->fresh()->getAttributes(), $detalle->fresh()->getAttributes()];
        $datos = ['tipo' => 'factura', 'referencia' => 'Factura de soporte', 'fecha' => '2026-07-01',
            'importe_observado' => '100', 'importe_utilizado' => '33.90', 'justificacion' => 'Solo el costo asociado a la alternativa.',
            'id_documento' => $documento->id_documento, 'id_detalle' => $detalle->id_detalle];
        $this->get(route(self::RUTA.'conceptos.fuentes.create', [$proyecto, $concepto]))->assertOk();
        $this->post(route(self::RUTA.'conceptos.fuentes.store', [$proyecto, $concepto]), $datos)->assertRedirect()->assertSessionHasNoErrors();
        $fuente = $concepto->fuentes()->sole();
        $this->assertTrue($fuente->detalle->is($detalle));
        $this->assertSame('33.900000', $fuente->importe_utilizado);
        $this->get(route(self::RUTA.'conceptos.fuentes.edit', [$proyecto, $concepto, $fuente]))->assertOk();
        $this->get(route(self::RUTA.'conceptos.show', [$proyecto, $concepto]))->assertOk()->assertSee('Factura original');
        $this->delete(route(self::RUTA.'conceptos.destroy', [$proyecto, $concepto]))->assertSessionHasErrors('concepto');

        $borrador = $this->concepto($proyecto, $alternativa, ['estado_dato' => 'provisional']);
        $this->delete(route(self::RUTA.'conceptos.destroy', [$proyecto, $borrador]))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertModelMissing($borrador);
        $this->assertSame($originales, [$cuenta->fresh()->getAttributes(), $partida->fresh()->getAttributes(),
            $documento->fresh()->getAttributes(), $detalle->fresh()->getAttributes()]);
        $this->assertDatabaseCount('tblpartidas', 1);
        $this->assertDatabaseCount('tbldetalles_partida', 1);
        $this->assertDatabaseCount('tbldocumentos_fuente', 1);
    }

    private function proyecto(): array
    {
        $usuario = User::factory()->create();
        $proyecto = new Proyecto([
            'nombre' => 'Facturación electrónica', 'tipo_decision' => 'prospectiva', 'fecha_base' => '2026-07-01',
            'horizonte_anios' => 5, 'estado' => 'en_preparacion',
        ]);
        $proyecto->usuario()->associate($usuario);
        $proyecto->saveOrFail();

        $alternativa = $proyecto->alternativas()->create($this->alternativa('ACAE'));
        $escenario = $proyecto->escenarios()->create(['nombre' => 'Referencia', 'horizonte_anios' => 5, 'periodicidad' => 'mensual']);

        return [$proyecto, $alternativa, $escenario, $usuario];
    }

    private function alternativa(string $nombre, string $viabilidad = 'viable'): array
    {
        return ['nombre' => $nombre, 'estado_implementacion' => 'no_implementada', 'viabilidad_tecnica' => $viabilidad];
    }

    private function concepto(Proyecto $proyecto, ?Alternativa $alternativa, array $cambios = []): Concepto
    {
        $concepto = $proyecto->conceptos()->make(array_replace(self::CONCEPTO, $cambios));
        $concepto->alternativa()->associate($alternativa);
        $concepto->saveOrFail();

        return $concepto;
    }

    private function generar(Proyecto $proyecto, Escenario $escenario, Alternativa $alternativa, string $agrupacion): array
    {
        return app(ProyeccionFlujosService::class)->generar($proyecto, $escenario, [$alternativa->id], 'USD', $agrupacion)['resultado'][$alternativa->id];
    }
}
