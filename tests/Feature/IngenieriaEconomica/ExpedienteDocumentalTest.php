<?php

namespace Tests\Feature\IngenieriaEconomica;

use App\Models\CuentaContable;
use App\Models\DetallePartida;
use App\Models\DocumentoFuente;
use App\Models\IngenieriaEconomica\EvidenciaDocumental;
use App\Models\IngenieriaEconomica\InformeEconomico;
use App\Models\IngenieriaEconomica\Proyecto;
use App\Models\Partida;
use App\Models\User;
use App\Services\IngenieriaEconomica\EvaluacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExpedienteDocumentalTest extends TestCase
{
    use RefreshDatabase;

    private const R = 'ingenieria-economica.proyectos.';

    public function test_archivos_privados_tipo_tamano_y_acceso_por_proyecto(): void
    {
        Storage::fake('ingenieria_privada');
        [$p, $u] = $this->proyecto();
        [$ajeno, $otro] = $this->proyecto();
        $this->get(route(self::R.'evidencias.index',$p))->assertRedirectToRoute('login');
        $this->actingAs(User::factory()->create(['rol'=>'Auditor']))->get(route(self::R.'evidencias.index',$p))->assertForbidden();
        $this->actingAs($u)->get(route(self::R.'evidencias.create',$p))->assertOk();
        $this->post(route(self::R.'evidencias.store',$p),$this->datos() + [
            'archivo'=>UploadedFile::fake()->createWithContent('cotizacion.pdf','archivo de texto'),
        ])->assertSessionHasErrors('archivo');
        config(['ingenieria_documental.max_kib'=>1]);
        $this->post(route(self::R.'evidencias.store',$p),$this->datos() + [
            'archivo'=>UploadedFile::fake()->createWithContent('limite.pdf', "%PDF-1.4\n".str_repeat('a',2200)),
        ])->assertSessionHasErrors('archivo');
        config(['ingenieria_documental.max_kib'=>10240]);
        $e = $this->evidencia($p);
        $this->assertSame('existente',$e->estado);
        $this->assertSame($u->id_usuario,$e->id_usuario);
        Storage::disk('ingenieria_privada')->assertExists('evidencias/'.$e->archivo);
        $this->get(route(self::R.'evidencias.show',[$p,$e]))->assertOk()->assertDontSee($e->archivo);
        $descarga = route(self::R.'evidencias.download',[$p,$e]);
        $this->get($descarga)->assertDownload('evidencia-'.$e->id.'.pdf')->assertHeader('X-Content-Type-Options','nosniff');
        $this->actingAs($otro)->get($descarga)->assertForbidden();
        $this->get(route(self::R.'evidencias.download',[$ajeno,$e]))->assertNotFound();
        $this->actingAs($u);
        Storage::disk('ingenieria_privada')->put('evidencias/'.$e->archivo,'manipulado');
        $this->get($descarga)->assertConflict();
    }

    public function test_referencias_contables_e_historial_no_modifican_asientos(): void
    {
        Storage::fake('ingenieria_privada');
        [$p,$u] = $this->proyecto();
        [$ajeno] = $this->proyecto();
        $cuenta = CuentaContable::create(['codigo_cuenta'=>'1101','nombre_cuenta'=>'Equipo','tipo_cuenta'=>'Activo','nivel'=>4,'estado'=>true]);
        $partida = Partida::create(['id_usuario'=>$u->id_usuario,'numero_partida'=>1,'fecha'=>'2026-01-01','concepto'=>'Movimiento original']);
        $documento = DocumentoFuente::create(['nombre_documento'=>'Comprobante original','tipo_documento'=>'factura']);
        $detalle = DetallePartida::create(['id_partida'=>$partida->id_partida,'id_cuenta'=>$cuenta->id_cuenta,
            'id_documento'=>$documento->id_documento,'debe'=>'1000','haber'=>'0']);
        $contables = [$cuenta,$partida,$documento,$detalle];
        $antes = array_map(fn ($m)=>$m->fresh()->getAttributes(),$contables);
        $this->actingAs($u);
        $e = $this->evidencia($p);
        $url = route(self::R.'evidencias.vincular',[$p,$e]);
        $this->get(route(self::R.'evidencias.show',[$p,$e,'tipo'=>'detalle','q'=>'Equipo']))->assertOk()
            ->assertSee('Detalle de partida 1')->assertSee('Comprobante original')->assertSee('1000.00');
        $this->post($url,['tipo_vinculo'=>'proyecto','destino_id'=>$ajeno->id,'nota'=>'Cruce inválido'])->assertSessionHasErrors('destino_id');
        $this->post(route(self::R.'evidencias.estado',[$p,$e]),['estado'=>'validado','nota'=>'Cargado'])->assertSessionHasErrors('estado');
        foreach (['cuenta'=>$cuenta,'partida'=>$partida,'detalle'=>$detalle,'documento'=>$documento] as $tipo=>$m) {
            $this->post($url,['tipo_vinculo'=>$tipo,'destino_id'=>$m->getKey(),'conciliacion'=>'conciliado','nota'=>'Referencia verificada'])
                ->assertRedirect()->assertSessionHasNoErrors();
        }
        $this->aprobar($p,$e);
        $this->get(route(self::R.'referencias',$p))->assertOk()->assertSee('Comprobante original')->assertSee('1101')
            ->assertSee('Movimiento original')->assertSee('Debe')->assertSee('1000.00')->assertSee('Conciliado documentalmente');
        $this->assertSame('estimado',$e->fresh()->calidad);
        $this->assertSame(7,$e->historial()->count());
        $this->post($url,['tipo_vinculo'=>'cuenta','destino_id'=>$cuenta->id_cuenta,'conciliacion'=>'na','nota'=>'No se concilia el catálogo'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->get(route(self::R.'referencias',$p))->assertOk()->assertSee('No aplicable');
        $this->assertSame($antes,array_map(fn ($m)=>$m->fresh()->getAttributes(),$contables));
        $this->assertDatabaseCount('ie_conceptos',2);
        $this->assertDatabaseCount('ie_flujos',0);
    }

    public function test_preliminar_pdf_multipagina_y_version_historica_inmutable(): void
    {
        Storage::fake('ingenieria_privada');
        [$p,$u,$esc,$alt,$c] = $this->proyecto();
        [, $otro] = $this->proyecto();
        $evaluacion = $this->evaluacion($p,$u,$esc,$alt);
        $this->actingAs($u)->get(route(self::R.'validacion',[$p,'evaluacion_id'=>$evaluacion->id]))
            ->assertOk()->assertSee('Validación documental de TMAR')->assertSee('Viabilidad')->assertSee('estimado')->assertSee('No aplicable');
        $this->get(route(self::R.'informes.index',[$p,'evaluacion_id'=>$evaluacion->id]))->assertOk()->assertSee('Resultados económicos');
        $d = ['evaluacion_id'=>$evaluacion->id,'estado'=>'validado'];
        $preview = $this->post(route(self::R.'informes.preview',$p),$d)->assertOk()->assertSee('emisión como validado está bloqueada');
        $this->post(route(self::R.'informes.store',$p),$d + ['firma'=>$preview->viewData('firma')])->assertSessionHasErrors('informe');
        $this->assertDatabaseCount('ie_informes',0);

        $d = ['evaluacion_id'=>$evaluacion->id,'estado'=>'preliminar','estudiantes'=>'Equipo autorizado',
            'comentarios'=>str_repeat("Comentario documentado para una memoria extensa.\n",145)];
        $preview = $this->post(route(self::R.'informes.preview',$p),$d)->assertOk()
            ->assertSee('No aplicable con los datos actuales')->assertSee('TIR no calculable')->assertSee('24/11/2026')->assertSee('2034.00');
        $this->assertDatabaseCount('ie_informes',0);
        $this->post(route(self::R.'informes.store',$p),$d + ['firma'=>$preview->viewData('firma')])->assertRedirect()->assertSessionHasNoErrors();
        $version = InformeEconomico::query()->sole();
        $pdf = Storage::disk('ingenieria_privada')->get('informes/'.$version->folio.'.pdf');
        $this->assertStringStartsWith('%PDF-',$pdf);
        $this->assertGreaterThan(3,preg_match_all('/\/Type\s*\/Page\b/',$pdf));
        $this->assertSame($version->sha256,hash('sha256',$pdf));
        $url = route(self::R.'informes.download',[$p,$version]);
        $this->assertSame($pdf,$this->get($url)->assertDownload()->streamedContent());
        $instantanea = $version->instantanea;
        $c->update(['monto'=>'40']);
        $esc->update(['nombre'=>'Escenario renombrado']);
        $alt->update(['nombre'=>'Alternativa renombrada']);
        $this->get(route(self::R.'informes.index',[$p,'evaluacion_id'=>$evaluacion->id]))
            ->assertOk()->assertSee('Escenario anual')->assertSee('ACAE académico')
            ->assertDontSee('Escenario renombrado')->assertDontSee('Alternativa renombrada');
        $this->get(route(self::R.'informes.show',[$p,$version]))->assertOk()
            ->assertSee('2034.00')->assertSee('Comparación preliminar')->assertSee('ACAE académico');
        $this->assertSame($instantanea,$version->fresh()->instantanea);
        $this->assertSame($pdf,$this->get($url)->assertOk()->streamedContent());
        $this->actingAs($otro)->get($url)->assertForbidden();
    }

    public function test_validado_requiere_respaldos_y_evaluacion_vigente(): void
    {
        Storage::fake('ingenieria_privada');
        [$p,$u,$esc,$alt,$c] = $this->proyecto();
        $esc->update(['estado_tasa'=>'validada']);
        $alt->update(['viabilidad_tecnica'=>'viable']);
        $c->update(['estado_dato'=>'confirmado']);
        $this->actingAs($u);
        foreach (['escenario'=>$esc,'alternativa'=>$alt,'concepto'=>$c] as $tipo=>$destino) {
            $e = $this->evidencia($p,'documentado','2026-11-24');
            $this->post(route(self::R.'evidencias.vincular',[$p,$e]),[
                'tipo_vinculo'=>$tipo,'destino_id'=>$destino->id,'nota'=>'Respaldo pertinente registrado.',
            ])->assertRedirect()->assertSessionHasNoErrors();
            $this->aprobar($p,$e);
        }
        $eval = $this->evaluacion($p,$u,$esc,$alt);
        $d = ['evaluacion_id'=>$eval->id,'estado'=>'validado'];
        $vista = $this->post(route(self::R.'informes.preview',$p),$d)->assertOk()->assertSee('Guardar versión y PDF');
        $this->post(route(self::R.'informes.store',$p),$d + ['firma'=>$vista->viewData('firma')])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('validado',InformeEconomico::query()->sole()->estado);
        $c->update(['monto'=>'40']);
        $this->post(route(self::R.'informes.preview',$p),$d)->assertOk()->assertSee('emisión como validado está bloqueada')->assertSee('insumos vigentes');
    }

    public function test_selector_busca_y_confirma_registros_autorizados_con_paginacion(): void
    {
        Storage::fake('ingenieria_privada');
        [$p,$u,,, $concepto] = $this->proyecto();
        [$ajeno,$otro,,,$conceptoAjeno] = $this->proyecto();
        $conceptoAjeno->update(['nombre'=>'Costo de otro proyecto']);
        $activo = $p->activos()->create([
            'nombre'=>'Servidor autorizado','categoria'=>'otro','clasificacion_contable'=>'activo_tangible',
            'costo_adquisicion'=>'100','costos_capitalizables'=>'0','valor_residual'=>'0','vida_util_anios'=>5,'moneda'=>'USD','porcentaje_uso'=>'100','estado_activo'=>'previsto','origen_dato'=>'estimado',
        ]);
        for ($i = 1; $i <= 13; $i++) {
            $p->alternativas()->create(['nombre'=>'Opción local '.$i,'estado_implementacion'=>'no_implementada','viabilidad_tecnica'=>'pendiente']);
        }

        $this->actingAs($u);
        $e = $this->evidencia($p);
        $url = route(self::R.'evidencias.show',[$p,$e]);
        $this->get($url.'?tipo=concepto&q=soporte')->assertOk()->assertSee($concepto->nombre)->assertDontSee($conceptoAjeno->nombre);
        $this->get($url.'?tipo=activo&q=servidor')->assertOk()->assertSee('Servidor autorizado');
        $this->get($url.'?tipo=alternativa&q=local')->assertOk()->assertSee('Siguiente')->assertSee('Opción local 13');
        $this->get($url.'?tipo=alternativa&q=local&pagina=2')->assertOk()->assertSee('Anterior')->assertSee('Opción local 1');
        $this->get($url.'?tipo=concepto&registro='.$concepto->id)->assertOk()->assertSee('Confirmar vínculo');
        $this->get($url.'?tipo=concepto&registro='.$conceptoAjeno->id)->assertNotFound();
        foreach ([$conceptoAjeno->id,999999] as $id) {
            $this->post(route(self::R.'evidencias.vincular',[$p,$e]),['tipo_vinculo'=>'concepto','destino_id'=>$id,'nota'=>'Inválido'])
                ->assertSessionHasErrors('destino_id');
        }
        foreach (['concepto'=>$concepto,'activo'=>$activo,'alternativa'=>$concepto->alternativa] as $tipo=>$registro) {
            $this->post(route(self::R.'evidencias.vincular',[$p,$e]),['tipo_vinculo'=>$tipo,'destino_id'=>$registro->id,'nota'=>'Respaldo comprobable'])
                ->assertRedirect()->assertSessionHasNoErrors();
        }
        $this->get(route(self::R.'evidencias.index',[$p,'tipo'=>'concepto','registro'=>$concepto->id]))->assertOk()->assertSee('Vinculada');
        $this->actingAs($otro)->get($url.'?tipo=concepto')->assertForbidden();
        $this->get(route(self::R.'evidencias.show',[$ajeno,$e,'tipo'=>'cuenta']))->assertNotFound();
    }

    public function test_respaldo_sin_adjunto_explica_validacion_y_conserva_citas_guardadas(): void
    {
        Storage::fake('ingenieria_privada');
        [$p,$u,$esc,$alt,$c] = $this->proyecto();
        $fuente = $c->fuentes()->create(['tipo'=>'factura','referencia'=>'Factura histórica de soporte',
            'fecha'=>'2026-01-01','justificacion'=>'Origen histórico preservado.','importe_utilizado'=>'33.90']);
        $eval = $this->evaluacion($p,$u,$esc,$alt);
        $instantanea = $eval->fresh()->getRawOriginal('instantanea');
        $this->actingAs($u)->get(route(self::R.'evidencias.index',[$p,'evaluacion_fuentes'=>$eval->id]))
            ->assertOk()->assertSee('Factura histórica de soporte')->assertSee('Falta vincular evidencia central');
        $this->post(route(self::R.'evidencias.store',$p), ['calidad'=>'documentado'] + $this->datos())->assertRedirect();
        $doc = $p->evidenciasDocumentales()->sole();
        $vista = route(self::R.'evidencias.show',[$p,$doc]);
        $estado = route(self::R.'evidencias.estado',[$p,$doc]);
        $this->get($vista)->assertOk()->assertSee('Falta archivo adjunto')->assertSee('Adjuntar respaldo');
        $this->post($estado,['estado'=>'validado','nota'=>'Prueba'])->assertSessionHasErrors('estado');
        $this->post($estado,['estado'=>'revisado','nota'=>'Se revisó su procedencia.'])->assertRedirect()->assertSessionHasNoErrors();
        $this->post($estado,['estado'=>'validado','nota'=>'Prueba'])->assertSessionHasErrors('estado');
        $adjuntar = route(self::R.'evidencias.adjuntar',[$p,$doc]);
        $archivo = fn () => UploadedFile::fake()->createWithContent('adjunto.pdf',"%PDF-1.4
%%EOF
");
        $this->post($adjuntar,['archivo'=>$archivo()])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('existente',$doc->fresh()->estado);
        $this->post($adjuntar,['archivo'=>$archivo()])->assertSessionHasErrors('archivo');
        $this->post(route(self::R.'evidencias.vincular',[$p,$doc]),['tipo_vinculo'=>'concepto','destino_id'=>$c->id,'nota'=>'Documento aplicable'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $validacion = route(self::R.'validacion',[$p,'evaluacion_id'=>$eval->id]);
        $this->get($validacion)->assertOk()->assertSee('Vincular o revisar evidencia')->assertSee('Falta revisar contenido');
        $this->aprobar($p,$doc);
        $this->get($validacion)->assertOk()->assertSee('Evidencia validada')->assertSee('Avisos informativos');
        $this->assertSame($instantanea,$eval->fresh()->getRawOriginal('instantanea'));
        $this->assertSame('Factura histórica de soporte',$fuente->fresh()->referencia);
    }

    public function test_cotizacion_historica_validada_no_acredita_vigencia_de_tmar(): void
    {
        Storage::fake('ingenieria_privada');
        [$p,$u,$esc] = $this->proyecto();
        $esc->update(['estado_tasa'=>'validada']);
        $this->actingAs($u);
        $doc = $this->evidencia($p,'documentado');
        $this->post(route(self::R.'evidencias.vincular',[$p,$doc]),['tipo_vinculo'=>'escenario','destino_id'=>$esc->id,'nota'=>'Cotización archivada'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->aprobar($p,$doc);
        $this->get(route(self::R.'validacion',$p))->assertOk()->assertSee('Falta documentar vigencia')
            ->assertSee('Bloquea informe validado');
        $this->assertSame('validado',$doc->fresh()->estado);
    }

    public function test_eliminaciones_seguras_y_desvinculacion_conservan_historial(): void
    {
        Storage::fake('ingenieria_privada');
        [$p,$u,$esc,$alt,$concepto] = $this->proyecto();
        [$ajeno,$otro] = $this->proyecto();
        $this->actingAs($u);
        $escenario = $p->escenarios()->create(['nombre'=>'Desechable','horizonte_anios'=>1,'periodicidad'=>'anual']);
        $this->delete(route(self::R.'escenarios.destroy',[$p,$escenario]))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertModelMissing($escenario);
        $esc->forceFill(['es_principal'=>true])->save();
        $ruta = route(self::R.'escenarios.destroy',[$p,$esc]);
        $this->delete($ruta)->assertSessionHasErrors('escenario');
        $esc->forceFill(['es_principal'=>false])->save();
        $soloSensibilidad = $p->escenarios()->create(['nombre'=>'Sensibilidad','horizonte_anios'=>1,'periodicidad'=>'anual','tasa_efectiva_anual'=>'0']);
        $sensibilidad = $p->analisisSensibilidad()->make(['nombre'=>'Histórica','perspectiva'=>'caja','moneda'=>'USD',
            'estado'=>'provisional','version_motor'=>'6.0.0','instantanea'=>[
                'base'=>app(EvaluacionService::class)->preparar($p,$soloSensibilidad,[
                    'moneda'=>'USD','perspectiva'=>'caja','periodicidad'=>'anual','alternativas'=>[$alt->id],
                ]),
            ]]);
        $sensibilidad->escenario_id = $soloSensibilidad->id;
        $sensibilidad->id_usuario = $u->id_usuario;
        $sensibilidad->save();
        $this->delete(route(self::R.'escenarios.destroy',[$p,$soloSensibilidad]))->assertSessionHasErrors('escenario');
        $evaluacion = $this->evaluacion($p,$u,$esc,$alt);
        $original = $evaluacion->fresh()->getRawOriginal('instantanea');
        $this->delete($ruta)->assertSessionHasErrors('escenario');
        $this->delete(route(self::R.'conceptos.destroy',[$p,$concepto]))->assertSessionHasErrors('concepto');

        $fuente = $concepto->fuentes()->create(['tipo'=>'factura','referencia'=>'Cita','fecha'=>'2026-01-01',
            'importe_utilizado'=>'1','justificacion'=>'Prueba']);
        $this->delete(route(self::R.'conceptos.fuentes.destroy',[$p,$concepto,$fuente]))->assertSessionHasErrors('fuente');
        $this->assertModelExists($fuente);
        $activo = $p->activos()->create(['nombre'=>'Equipo','categoria'=>'otro','clasificacion_contable'=>'activo_tangible',
            'costo_adquisicion'=>'1','costos_capitalizables'=>'0','valor_residual'=>'0','vida_util_anios'=>1,
            'moneda'=>'USD','porcentaje_uso'=>'100','estado_activo'=>'previsto','origen_dato'=>'estimado']);
        $cita = $activo->fuentes()->create(['uso'=>'adquisicion','tipo'=>'factura','referencia'=>'Cita sin utilizar',
            'fecha'=>'2026-01-01','justificacion'=>'Prueba']);
        $this->delete(route(self::R.'activos.destroy',[$p,$activo]))->assertSessionHasErrors('activo');
        $this->delete(route(self::R.'activos.fuentes.destroy',[$p,$activo,$cita]))->assertRedirect()->assertSessionHasNoErrors();
        $this->delete(route(self::R.'activos.destroy',[$p,$activo]))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertModelMissing($activo);

        $borrador = $this->evidencia($p);
        $this->delete(route(self::R.'evidencias.destroy',[$p,$borrador]))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertModelMissing($borrador);
        Storage::disk('ingenieria_privada')->assertMissing('evidencias/'.$borrador->archivo);
        $doc = $this->evidencia($p,'documentado');
        $this->post(route(self::R.'evidencias.vincular',[$p,$doc]),[
            'tipo_vinculo'=>'concepto','destino_id'=>$concepto->id,'nota'=>'Respaldo inicial',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->aprobar($p,$doc);
        $vinculo = $doc->vinculos()->sole();
        $desvincular = route(self::R.'evidencias.desvincular',[$p,$doc,$vinculo]);
        $this->delete($desvincular)->assertSessionHasErrors('nota');
        $this->actingAs($otro)->delete($desvincular,['nota'=>'Prueba'])->assertForbidden();
        $this->delete(route(self::R.'evidencias.desvincular',[$ajeno,$doc,$vinculo]),['nota'=>'Prueba'])->assertNotFound();
        $this->actingAs($u)->delete($desvincular,['nota'=>'Enlace incorrecto'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertModelMissing($vinculo);
        $this->assertSame('validado',$doc->fresh()->estado);
        $this->assertSame('Enlace incorrecto',$doc->historial()->where('accion','desvinculo')->sole()->datos['nota']);
        Storage::disk('ingenieria_privada')->assertExists('evidencias/'.$doc->archivo);
        $this->delete(route(self::R.'evidencias.destroy',[$p,$doc]))->assertSessionHasErrors('evidencia');
        $this->assertSame($original,$evaluacion->fresh()->getRawOriginal('instantanea'));
    }

    public function test_eliminar_evidencia_exige_token_csrf_en_sesion_web(): void
    {
        [$p,$u] = $this->proyecto();
        Storage::fake('ingenieria_privada');
        $this->actingAs($u);
        $doc = $this->evidencia($p);
        $entorno = $this->app['env'];
        try {
            $this->app['env'] = 'local';
            $this->delete(route(self::R.'evidencias.destroy',[$p,$doc]))->assertStatus(419);
        } finally {
            $this->app['env'] = $entorno;
        }
        $this->assertModelExists($doc);
    }

    public function test_alternativa_referenciada_en_instantanea_o_por_evidencia_se_conserva(): void
    {
        [$p, $u, $esc, $alternativa] = $this->proyecto();
        Storage::fake('ingenieria_privada');
        $this->actingAs($u);
        $historica = $p->alternativas()->create(['nombre'=>'Histórica','estado_implementacion'=>'no_implementada','viabilidad_tecnica'=>'pendiente']);
        $evaluacion = $this->evaluacion($p, $u, $esc, $historica);
        $original = $evaluacion->fresh()->getRawOriginal('instantanea');
        $this->delete(route(self::R.'alternativas.destroy', [$p,$historica]))->assertSessionHasErrors('alternativa');
        $doc = $this->evidencia($p);
        $libre = $p->alternativas()->create(['nombre'=>'Temporal','estado_implementacion'=>'no_implementada','viabilidad_tecnica'=>'pendiente']);
        $ruta = route(self::R.'alternativas.destroy', [$p,$libre]);
        $this->post(route(self::R.'evidencias.vincular',[$p,$doc]),[
            'tipo_vinculo'=>'alternativa','destino_id'=>$libre->id,'nota'=>'Enlace temporal',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->delete($ruta)->assertSessionHasErrors('alternativa');
        $this->delete(route(self::R.'evidencias.desvincular',[$p,$doc,$doc->vinculos()->sole()]),['nota'=>'Enlace equivocado'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->delete($ruta)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertModelMissing($libre);
        $this->assertModelExists($historica);
        $sinDependencias = $p->alternativas()->create(['nombre'=>'Resguardo','estado_implementacion'=>'no_implementada','viabilidad_tecnica'=>'pendiente']);
        $versionDesconocida = $p->evaluaciones()->make([
            'perspectiva'=>'caja','moneda'=>'USD','version_motor'=>'desconocido','instantanea'=>['formato_anterior'=>true],
        ]);
        $versionDesconocida->escenario()->associate($esc);
        $versionDesconocida->usuario()->associate($u);
        $versionDesconocida->save();
        $this->delete(route(self::R.'alternativas.destroy',[$p,$sinDependencias]))->assertSessionHasErrors('alternativa');
        $this->assertModelExists($sinDependencias);
        $this->assertSame($original, $evaluacion->fresh()->getRawOriginal('instantanea'));
    }

    private function proyecto(): array
    {
        $u = User::factory()->create();
        $p = new Proyecto(['nombre'=>'Facturación académica','descripcion'=>'Caso académico para verificar el informe.','tipo_decision'=>'reemplazo',
            'fecha_base'=>'2026-11-24','horizonte_anios'=>5,'estado'=>'en_preparacion']);
        $p->usuario()->associate($u);
        $p->save();
        $esc = $p->escenarios()->create(['nombre'=>'Escenario anual','horizonte_anios'=>5,'periodicidad'=>'anual','tasa_original'=>'0.0025',
            'tasa_efectiva_anual'=>'0.010037562539','modalidad_tasa'=>'efectiva','periodicidad_tasa'=>'trimestral',
            'estado_tasa'=>'provisional','fuente_tasa'=>'Fuente declarada de prueba']);
        $alt = $p->alternativas()->create(['nombre'=>'ACAE académico','estado_implementacion'=>'implementada','viabilidad_tecnica'=>'pendiente']);
        $c = $p->conceptos()->make(['nombre'=>'Soporte mensual','monto'=>'33.90','moneda'=>'USD','periodicidad'=>'mensual','momento_pago'=>'fin',
            'tipo_concepto'=>'costo_operativo','clasificacion_decision'=>'diferencial','naturaleza_economica'=>'desembolso_real','estado_dato'=>'estimado']);
        $c->alternativa()->associate($alt);
        $c->save();

        return [$p,$u,$esc,$alt,$c];
    }

    private function evaluacion($p,$u,$esc,$a)
    {
        $r = app(EvaluacionService::class)->preparar($p,$esc,['periodicidad'=>'mensual','moneda'=>'USD','perspectiva'=>'caja','alternativas'=>[$a->id]]);
        $ev = $p->evaluaciones()->make(['moneda'=>'USD','perspectiva'=>'caja','version_motor'=>$r['version'],'instantanea'=>$r]);
        $ev->escenario()->associate($esc);
        $ev->usuario()->associate($u);
        $ev->save();

        return $ev;
    }

    private function datos(): array
    {
        return ['titulo'=>'Evidencia de prueba','tipo'=>'cotizacion','procedencia'=>'Proveedor de prueba','fecha_documento'=>'2026-01-01','calidad'=>'estimado'];
    }

    private function evidencia(Proyecto $p, string $calidad = 'estimado', string $fecha = '2026-01-01'): EvidenciaDocumental
    {
        $this->post(route(self::R.'evidencias.store',$p),['calidad'=>$calidad, 'fecha_documento'=>$fecha] + $this->datos() + [
            'archivo'=>UploadedFile::fake()->createWithContent('prueba.pdf',"%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF\n"),
        ])->assertRedirect()->assertSessionHasNoErrors();

        return $p->evidenciasDocumentales()->latest('id')->firstOrFail();
    }

    private function aprobar($p,$e): void
    {
        $ruta = route(self::R.'evidencias.estado',[$p,$e]);
        $this->post($ruta,['estado'=>'revisado','nota'=>'Revisión de procedencia.'])->assertRedirect()->assertSessionHasNoErrors();
        $this->post($ruta,['estado'=>'validado','nota'=>'Respaldo confirmado.'])->assertRedirect()->assertSessionHasNoErrors();
    }
}
