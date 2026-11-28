<?php

namespace Tests\Feature\IngenieriaEconomica;

use App\Models\CuentaContable;
use App\Models\DetallePartida;
use App\Models\DocumentoFuente;
use App\Models\IngenieriaEconomica\Activo;
use App\Models\IngenieriaEconomica\CalculoActivo;
use App\Models\IngenieriaEconomica\Proyecto;
use App\Models\Partida;
use App\Models\User;
use App\Services\IngenieriaEconomica\DecimalFinanciero;
use App\Services\IngenieriaEconomica\ProyeccionFlujosService;
use Brick\Math\BigDecimal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivosTest extends TestCase
{
    use RefreshDatabase;

    private const R = 'ingenieria-economica.proyectos.activos.';

    private const ACTIVO = [
        'nombre' => 'Equipo académico', 'categoria' => 'equipo_informatico', 'clasificacion_contable' => 'activo_tangible',
        'fecha_adquisicion' => '2026-01-01', 'fecha_disponible_uso' => '2026-01-02',
        'costo_adquisicion' => '1000', 'costos_capitalizables' => '0', 'valor_residual' => '100',
        'vida_util_anios' => 5, 'moneda' => 'USD', 'porcentaje_uso' => '100', 'estado_activo' => 'en_uso',
        'origen_dato' => 'confirmado',
    ];

    public function test_registro_edicion_validacion_historial_y_acceso_por_proyecto(): void
    {
        [$p, $a, , $u] = $this->proyecto();
        [$ajeno, $aAjena, , $otro] = $this->proyecto();
        $this->get(route(self::R.'index', $p))->assertRedirectToRoute('login');
        $this->actingAs(User::factory()->create(['rol' => 'Auditor']))->get(route(self::R.'index', $p))->assertForbidden();
        $this->actingAs($u)->get(route(self::R.'create', $p))->assertOk()->assertSee('Registrar activo');

        $this->post(route(self::R.'store', $p), array_replace(self::ACTIVO, [
            'costo_adquisicion' => '-1', 'vida_util_anios' => 0, 'porcentaje_uso' => 101, 'alternativa_id' => $aAjena->id,
        ]))->assertSessionHasErrors(['costo_adquisicion', 'vida_util_anios', 'porcentaje_uso', 'alternativa_id']);
        $this->post(route(self::R.'store', $p), array_replace(self::ACTIVO, ['valor_residual' => '1000.001']))
            ->assertSessionHasErrors('valor_residual');

        $this->post(route(self::R.'store', $p), self::ACTIVO + ['alternativa_id' => $a->id])->assertRedirect()->assertSessionHasNoErrors();
        $activo = Activo::query()->sole();
        $this->get(route(self::R.'index', $p))->assertOk()->assertSee($activo->nombre);
        $this->get(route(self::R.'show', [$p, $activo]))->assertOk()->assertSee('Comparar métodos de depreciación');
        $this->get(route(self::R.'edit', [$p, $activo]))->assertOk();
        $this->get(route(self::R.'depreciacion', [$p, $activo]))->assertOk();
        $this->post(route(self::R.'depreciacion.store', [$p, $activo]), ['ajuste_final' => 1])
            ->assertSessionHasErrors('justificacion_ajuste');

        $this->post(route(self::R.'depreciacion.store', [$p, $activo]))->assertRedirect()->assertSessionHasNoErrors();
        $calculo = CalculoActivo::query()->sole();
        $original = $calculo->instantanea;
        $this->get(route(self::R.'calculos.show', [$p, $activo, $calculo]))->assertOk()->assertSee('180.00')->assertSee('29.60');
        $this->get(route(self::R.'calculos.csv', [$p, $activo, $calculo]))->assertDownload('calculo-activo-'.$calculo->id.'.csv');

        $this->patch(route(self::R.'update', [$p, $activo]), array_replace(self::ACTIVO, [
            'alternativa_id' => $a->id, 'costo_adquisicion' => '1200',
        ]))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('1200.000000', $activo->fresh()->costo_adquisicion);
        $this->post(route(self::R.'depreciacion.store', [$p, $activo]))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($original, $calculo->fresh()->instantanea);
        $this->assertDatabaseCount('ie_calculos_activos', 2);
        $this->assertDatabaseCount('ie_conceptos', 0);
        $this->assertDatabaseCount('ie_flujos', 0);

        $this->actingAs($otro)->get(route(self::R.'show', [$p, $activo]))->assertForbidden();
        $this->get(route(self::R.'show', [$ajeno, $activo]))->assertNotFound();
        $this->patch(route(self::R.'update', [$p, $activo]), self::ACTIVO)->assertForbidden();
    }

    public function test_validacion_e_informe_detectan_un_calculo_de_activo_desactualizado(): void
    {
        [$proyecto, $alternativa, $escenario, $usuario] = $this->proyecto();
        $ruta = 'ingenieria-economica.proyectos.';
        $this->actingAs($usuario)->post(route(self::R.'store', $proyecto), self::ACTIVO + ['alternativa_id' => $alternativa->id])
            ->assertSessionHasNoErrors();
        $activo = $proyecto->activos()->sole();
        $this->post(route(self::R.'depreciacion.store', [$proyecto, $activo]))->assertSessionHasNoErrors();
        $calculo = $activo->calculos()->sole();
        $datos = ['escenario_id' => $escenario->id, 'alternativas' => [$alternativa->id],
            'moneda' => 'USD', 'periodicidad' => 'mensual', 'perspectiva' => 'caja'];
        $previa = $this->post(route($ruta.'evaluaciones.preview', $proyecto), $datos)->assertOk();
        $this->post(route($ruta.'evaluaciones.store', $proyecto), $datos + ['firma' => $previa->viewData('firma')])
            ->assertSessionHasNoErrors();
        $evaluacion = $proyecto->evaluaciones()->sole();
        $alcance = [$proyecto, 'contexto' => ['evaluacion_id' => $evaluacion->id, 'calculos' => [$calculo->id]]];

        $this->get(route($ruta.'validacion', $alcance))->assertOk()->assertSee('siguen disponibles y coinciden');
        $activo->update(['vida_util_anios' => 6]);
        foreach (['validacion', 'informes.index'] as $pantalla) {
            $this->get(route($ruta.$pantalla, $alcance))->assertOk()
                ->assertSee('Los datos actuales del activo difieren de los capturados');
        }
        $this->assertSame(5, $calculo->fresh()->instantanea['vida']);
    }

    public function test_reemplazo_anual_con_oportunidad_y_cadena_documentada(): void
    {
        [$p, $a, $escenario, $u] = $this->proyecto();
        $activo = $p->activos()->create(self::ACTIVO + ['alternativa_id' => $a->id]);
        $datos = [
            'escenario_id' => $escenario->id, 'politica' => 'sin_reposicion', 'justificacion' => 'Supuesto académico de valor terminal y servicio.',
            'mercado_actual' => '550', 'venta_actual' => '500', 'retiro_ahora' => '0',
            'precio' => '1000', 'instalacion' => '50', 'puesta_marcha' => '50', 'valor_libros_actual' => '300',
            'conservar' => ['vida' => 3, 'operacion' => '200', 'mantenimiento' => '50', 'rescate' => '100', 'retiro' => '20',
                'candidatos' => "1;200;20;Cotización año uno\n3;100;20;Cotización año tres"],
            'reemplazar' => ['vida' => 5, 'operacion' => '50', 'mantenimiento' => '20', 'rescate' => '200', 'retiro' => '20'],
        ];
        $this->actingAs($u)->get(route(self::R.'reemplazo', [$p, $activo]))->assertOk()->assertSee('Política del horizonte común');
        $this->post(route(self::R.'reemplazo.store', [$p, $activo]), $datos)->assertRedirect()->assertSessionHasNoErrors();
        $guardado = CalculoActivo::query()->sole();
        $r = $guardado->instantanea;
        $this->assertSame('500.00', $this->dinero($r['oportunidad_conservar']));
        $this->assertSame('0.00', $this->dinero($r['conservar']['periodos'][0]['costos']));
        $this->assertSame('500.00', $this->dinero($r['reemplazar']['periodos'][0]['beneficios']));
        $this->assertSame('600.00', $this->dinero($r['inversion_adicional']));
        $this->assertSame('670.00', $this->dinero($r['conservar']['vpc_neto']));
        $this->assertSame('630.00', $this->dinero($r['reemplazar']['vpc_neto']));
        $this->assertSame('210.00', $this->dinero($r['reemplazar']['caue_neto']));
        $this->assertSame('40.00', $this->dinero($r['van_incremental']));
        $this->assertSame(3, $r['vidas_economicas']['conservar']['menor_caue']);
        $this->assertArrayHasKey(36, $r['incremental']);
        $this->get(route(self::R.'calculos.show', [$p, $activo, $guardado]))->assertOk()->assertSee('CAUE neto')->assertSee('Flujo incremental');

        $datos['conservar']['vida'] = 2;
        unset($datos['conservar']['candidatos']);
        $this->post(route(self::R.'reemplazo.store', [$p, $activo]), $datos)->assertSessionHasErrors('calculo');
        $datos['politica'] = 'reposiciones_documentadas';
        $datos['conservar']['reposiciones'] = '2;800;90;20;4;100;20;Oferta y cobertura de servicio';
        $this->post(route(self::R.'reemplazo.store', [$p, $activo]), $datos)->assertRedirect()->assertSessionHasNoErrors();
        $r2 = CalculoActivo::query()->latest('id')->firstOrFail()->instantanea;
        $this->assertSame('1070.00', $this->dinero($r2['conservar']['periodos'][24]['costos']));
        $this->assertSame('1270.00', $this->dinero($r2['conservar']['vpc_neto']));
        $this->assertSame($r, $guardado->fresh()->instantanea);
        $this->assertDatabaseCount('ie_conceptos', 0);
    }

    public function test_filas_editables_retiro_y_cadena_preservan_versiones(): void
    {
        [$p, $a, $escenario, $u] = $this->proyecto();
        $activo = $p->activos()->create(self::ACTIVO + ['alternativa_id'=>$a->id]);
        $url = route(self::R.'reemplazo.store', [$p,$activo]);
        $this->actingAs($u)->get(route(self::R.'reemplazo', [$p,$activo]))->assertOk()
            ->assertSee('Agregar fila')->assertSee('Eliminar fila')->assertSee('Edad candidata');
        $datos = [
            'escenario_id'=>$escenario->id,'politica'=>'reposiciones_documentadas','considerar_retiro'=>1,
            'justificacion'=>'Mercado, vidas y servicio documentados.','mercado_actual'=>'550','venta_actual'=>'500','retiro_ahora'=>'40',
            'precio'=>'900','instalacion'=>'0','puesta_marcha'=>'0',
            'valoracion_fecha'=>'2026-01-01','valoracion_fuente'=>'Cotización fechada y registro de libros',
            'conservar'=>['vida'=>1,'operacion'=>'100','mantenimiento'=>'50','rescate'=>'200','retiro'=>'10'],
            'reemplazar'=>['vida'=>3,'operacion'=>'50','mantenimiento'=>'30','rescate'=>'250','retiro'=>'15'],
        ];
        $fila = ['anio'=>1,'vida'=>1,'operacion'=>'80','mantenimiento'=>'40','compra'=>'350','rescate'=>'150','retiro'=>'10','nota'=>'Oferta de reposición'];
        $datos['filas']['conservar']['reposiciones'] = [$fila,$fila];
        $this->from(route(self::R.'reemplazo', [$p,$activo]))->post($url,$datos)
            ->assertSessionHasErrors('filas.conservar.reposiciones.1.anio');
        $datos['filas']['conservar']['reposiciones'][1]['anio'] = 2;
        $incompleto = $datos;
        unset($incompleto['filas']['conservar']['reposiciones'][1]['nota']);
        $this->post($url,$incompleto)->assertSessionHasErrors('filas.conservar.reposiciones.1.nota');
        $this->assertDatabaseCount('ie_calculos_activos', 0);
        $datos['filas']['conservar']['candidatos'] = [
            ['anio'=>1,'rescate'=>'200','retiro'=>'10','nota'=>'Oferta de rescate'],
        ];
        $this->post($url,$datos)->assertRedirect()->assertSessionHasNoErrors();
        $version = CalculoActivo::query()->sole();
        $esperado = $version->getRawOriginal('instantanea');
        $this->assertSame('460.00',$this->dinero($version->instantanea['retiro_inmediato']));
        $this->assertCount(2,explode("\n",$version->instantanea['parametros']['conservar']['reposiciones']));
        $this->get(route(self::R.'calculos.show', [$p,$activo,$version]))->assertOk()
            ->assertSee('Retirar hoy sin reemplazar')->assertSee('460.00 USD')->assertSee('Se mantienen constantes');
        $activo->update(['nombre'=>'Equipo renombrado']);
        $this->assertSame($esperado,$version->fresh()->getRawOriginal('instantanea'));
        $this->get(route(self::R.'show', [$p,$activo]))->assertOk()->assertSee('550.00 USD')->assertSee('200.00 USD');
    }

    public function test_vincula_adquisicion_existente_evidencias_y_creacion_explicita_sin_duplicar(): void
    {
        [$p, $a, $e, $u] = $this->proyecto();
        $activo = $p->activos()->create(self::ACTIVO + ['alternativa_id' => $a->id]);
        $cuenta = CuentaContable::create(['codigo_cuenta' => '1101', 'nombre_cuenta' => 'Equipo', 'tipo_cuenta' => 'Activo', 'nivel' => 4, 'estado' => true]);
        $partida = Partida::create(['id_usuario' => $u->id_usuario, 'numero_partida' => 1, 'fecha' => '2026-01-01', 'concepto' => 'Compra']);
        $documento = DocumentoFuente::create(['nombre_documento' => 'Comprobante original', 'tipo_documento' => 'factura']);
        $detalle = DetallePartida::create(['id_partida' => $partida->id_partida, 'id_cuenta' => $cuenta->id_cuenta,
            'id_documento' => $documento->id_documento, 'debe' => '1000', 'haber' => '0']);
        $contables = [$cuenta, $partida, $documento, $detalle];
        $antes = array_map(fn ($item) => $item->fresh()->getAttributes(), $contables);
        $this->actingAs($u)->get(route(self::R.'fuentes.create', [$p, $activo, 'q'=>'Comprobante']))->assertOk()
            ->assertSee('Comprobante original')->assertDontSee('ID de documento contable');
        $this->post(route(self::R.'fuentes.store', [$p, $activo]), [
            'tipo' => 'comprobante', 'uso' => 'adquisicion', 'fecha' => '2026-01-01', 'referencia' => 'Comprobante de pago',
            'id_detalle' => $detalle->id_detalle, 'id_documento' => $documento->id_documento,
            'importe_observado' => '1000', 'justificacion' => 'Pago confirmado con comprobante.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $fuente = $activo->fuentes()->sole();
        $this->get(route(self::R.'show', [$p, $activo]))->assertOk()->assertSee('Comprobante original')->assertSee('1101');
        $this->get(route(self::R.'flujo.create', [$p, $activo]))->assertOk()->assertSee('Confirmar movimiento');

        $concepto = $p->conceptos()->make([
            'nombre' => 'Compra ya programada', 'periodicidad' => 'unico', 'momento_pago' => 'inicio',
            'tipo_concepto' => 'inversion_inicial', 'moneda' => 'USD', 'monto' => '1000',
            'clasificacion_decision' => 'diferencial', 'naturaleza_economica' => 'desembolso_real', 'estado_dato' => 'confirmado',
        ]);
        $concepto->alternativa()->associate($a);
        $concepto->save();
        $ruta = route(self::R.'flujo.store', [$p, $activo]);
        $datos = ['escenario_id' => $e->id, 'fuente_id' => $fuente->id, 'operacion' => 'adquisicion', 'fecha' => '2026-01-01',
            'observado' => 0, 'monto' => '1000', 'moneda' => 'USD', 'estado_dato' => 'confirmado',
            'justificacion' => 'Registro económico comprobado.'];
        $this->post($ruta, $datos)->assertSessionHasErrors('confirmar');
        $this->post($ruta, $datos + ['confirmar' => 1])->assertSessionHasErrors('movimiento');
        $this->post($ruta, $datos + ['confirmar' => 1, 'concepto_id' => $concepto->id])->assertRedirect()->assertSessionHasNoErrors();
        $this->post($ruta, $datos + ['confirmar' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($activo->id, $concepto->fresh()->activo_id);
        $this->assertDatabaseCount('ie_conceptos', 1);
        $this->assertDatabaseCount('ie_fuentes', 1);

        $cotizacion = $activo->fuentes()->create([
            'uso' => 'retiro', 'tipo' => 'cotizacion', 'referencia' => 'Oferta de compra del activo',
            'fecha' => '2026-11-24', 'justificacion' => 'Valor de venta proyectado para la fecha programada.',
        ]);
        $venta = array_replace($datos, ['fuente_id' => $cotizacion->id, 'operacion' => 'venta', 'fecha' => '2027-04-12', 'monto' => '500', 'confirmar' => 1]);
        $this->post($ruta, $venta)->assertRedirect()->assertSessionHasNoErrors();
        $this->post($ruta, $venta)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('ie_conceptos', 2);
        $generado = app(ProyeccionFlujosService::class)->generar($p, $e, [$a->id], 'USD', 'mensual')['resultado'][$a->id];
        $observado = collect($generado['periodos'])->pluck('movimientos')->collapse()->firstWhere('tipo', 'ingreso');
        $this->assertSame('2027-04-12', $observado['fecha']->toDateString());
        $this->assertFalse($observado['observado']);

        $contable = $activo->fuentes()->create($fuente->only(['tipo', 'referencia', 'fecha', 'justificacion']) + ['uso' => 'depreciacion']);
        $this->post($ruta, array_replace($venta, ['fuente_id' => $contable->id]))->assertSessionHasErrors('movimiento');
        $this->assertSame($antes, array_map(fn ($item) => $item->fresh()->getAttributes(), $contables));
    }

    public function test_intangible_indefinido_se_guarda_y_renderiza_sin_cuotas(): void
    {
        [$p, , , $u] = $this->proyecto();
        $datos = array_replace(self::ACTIVO, [
            'categoria' => 'licencia', 'clasificacion_contable' => 'activo_intangible',
            'vida_indefinida' => 1, 'vida_util_anios' => null, 'valor_residual' => '0',
        ]);

        $this->actingAs($u)->post(route(self::R.'store', $p), $datos)->assertRedirect()->assertSessionHasNoErrors();
        $activo = $p->activos()->sole();
        $this->post(route(self::R.'depreciacion.store', [$p, $activo]))->assertRedirect()->assertSessionHasNoErrors();
        $calculo = $activo->calculos()->sole();
        $this->assertSame('amortizacion_lineal', $calculo->metodo);
        $this->assertSame([], $calculo->instantanea['metodos']);
        $this->get(route(self::R.'calculos.show', [$p, $activo, $calculo]))
            ->assertOk()->assertSee('No se programa amortización')->assertSee('Valor en libros calculado');
        $this->assertDatabaseCount('ie_conceptos', 0);
    }

    public function test_activo_compartido_genera_un_solo_concepto_comun(): void
    {
        [$p, $a, $e, $u] = $this->proyecto();
        $b = $p->alternativas()->create(['nombre' => 'Otra', 'estado_implementacion' => 'no_implementada', 'viabilidad_tecnica' => 'viable']);
        $activo = $p->activos()->create(self::ACTIVO);
        $fuente = $activo->fuentes()->create(['uso' => 'adquisicion', 'tipo' => 'cotizacion',
            'referencia' => 'Cotización compartida', 'fecha' => '2026-01-01', 'justificacion' => 'Un solo activo para el proyecto.']);

        $this->actingAs($u)->post(route(self::R.'flujo.store', [$p, $activo]), [
            'escenario_id' => $e->id, 'fuente_id' => $fuente->id, 'operacion' => 'adquisicion',
            'fecha' => '2027-01-01', 'observado' => 0, 'monto' => '1000', 'moneda' => 'USD',
            'estado_dato' => 'estimado', 'justificacion' => 'Desembolso compartido y único.', 'confirmar' => 1,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $concepto = $p->conceptos()->sole();
        $this->assertNull($concepto->alternativa_id);
        $this->assertSame('comun', $concepto->clasificacion_decision);
        $flujos = app(ProyeccionFlujosService::class)->generar($p, $e, [$a->id, $b->id], 'USD', 'anual')['resultado'];
        foreach ($flujos as $flujo) {
            $this->assertSame('1000.00', DecimalFinanciero::dinero($flujo['totales']['salidas']));
            $this->assertCount(1, $flujo['periodos'][1]['movimientos']);
        }
    }

    private function proyecto(): array
    {
        $u = User::factory()->create();
        $p = new Proyecto(['nombre' => 'Proyecto académico', 'tipo_decision' => 'reemplazo', 'fecha_base' => '2026-01-01',
            'horizonte_anios' => 3, 'estado' => 'en_preparacion']);
        $p->usuario()->associate($u);
        $p->save();
        $a = $p->alternativas()->create(['nombre' => 'Opción', 'estado_implementacion' => 'implementada', 'viabilidad_tecnica' => 'viable']);
        $e = $p->escenarios()->create(['nombre' => 'Base', 'horizonte_anios' => 3, 'periodicidad' => 'mensual',
            'tasa_efectiva_anual' => '0', 'estado_tasa' => 'provisional']);

        return [$p, $a, $e, $u];
    }

    private function dinero(string $valor): string
    {
        return DecimalFinanciero::dinero(BigDecimal::of($valor));
    }
}
