<?php

namespace Tests\Feature\IngenieriaEconomica;

use App\Models\IngenieriaEconomica\Evaluacion;
use App\Models\IngenieriaEconomica\InformeEconomico;
use App\Models\IngenieriaEconomica\Proyecto;
use App\Models\User;
use App\Services\IngenieriaEconomica\EvaluacionFiscalService;
use Brick\Math\BigDecimal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EvaluacionFiscalTest extends TestCase
{
    use RefreshDatabase;

    private const RUTA = 'ingenieria-economica.proyectos.';

    public function test_ordinario_concilia_pagos_calcula_indicadores_compara_y_conserva_pdf_e_instantanea(): void
    {
        [$p, $u, $a, $d] = $this->caso();
        $ingreso = $this->movimiento($p, $a, '1500', 'ingreso');
        $this->movimiento($p, $a, '400');
        $pago = $this->movimiento($p, $a, '30');
        $this->movimiento($p, $a, '300', 'inversion_inicial', '2026-11-24');
        $b = $p->alternativas()->create(['nombre'=>'Alternativa B','viabilidad_tecnica'=>'viable','estado_implementacion'=>'no_implementada']);
        $this->movimiento($p, $b, '400', 'ingreso');
        $this->movimiento($p, $b, '500');
        $d['alternativas'][] = $b->id;
        $d['fiscal']['tasa_venta'] = null;
        $d['fiscal']['alternativas'] = [
            $a->id => ['base'=>"0;0;0\n1;1500;400",'pagos'=>"1;c{$pago->id}"],
            $b->id => ['base'=>"0;0;0\n1;400;500"],
        ];
        $vista = $this->post(route(self::RUTA.'evaluaciones.preview', $p), $d)->assertOk()
            ->assertSee('Memoria de evaluación después de impuestos')->assertSee('Impuesto adicional')->assertSee('VAN después 580.00 USD')->assertSee('name="fiscal[tasa_venta]"', false);
        $r = $vista->viewData('resultado');
        $filas = $r['fiscal']['alternativas'][$a->id]['periodos'];
        $this->decimal('220', $filas[1]['total']);
        $this->decimal('190', $filas[1]['adicional']);
        $this->decimal('800', $r['fiscal']['alternativas'][$a->id]['antes']['van']);
        $this->decimal('580', $r['alternativas'][$a->id]['indicadores']['van']);
        $this->decimal('680', BigDecimal::of($r['comparaciones'][0]['van'])->abs());
        $this->decimal('-100', $r['fiscal']['alternativas'][$b->id]['periodos'][1]['base']);
        $this->decimal('0', $r['fiscal']['alternativas'][$b->id]['periodos'][1]['total']);
        $this->post(route(self::RUTA.'evaluaciones.store', $p), $d + ['firma'=>$vista->viewData('firma')])
            ->assertRedirect()->assertSessionHasNoErrors();
        $e = Evaluacion::query()->sole();
        $this->get(route(self::RUTA.'evaluaciones.index', $p))->assertOk()->assertSee('Financiera después de impuestos');
        $this->get(route(self::RUTA.'informes.index', [$p,'evaluacion_id'=>$e->id]))->assertOk()
            ->assertSee('Memoria de evaluación después de impuestos')->assertSee('Respaldo tributario académico');
        $params = ['evaluacion_id'=>$e->id,'estado'=>'preliminar'];
        $previo = $this->post(route(self::RUTA.'informes.preview', $p), $params)->assertOk();
        $this->post(route(self::RUTA.'informes.store', $p), $params + ['firma'=>$previo->viewData('firma')])
            ->assertRedirect()->assertSessionHasNoErrors();
        $informe = InformeEconomico::query()->sole();
        $pdf = Storage::disk('ingenieria_privada')->get('informes/'.$informe->folio.'.pdf');
        $this->assertStringStartsWith('%PDF-', $pdf);
        $instantanea = $e->instantanea;
        $ingreso->update(['monto'=>'1700']);
        $this->assertSame($instantanea, $e->fresh()->instantanea);
        $this->get(route(self::RUTA.'evaluaciones.show', [$p,$e]))->assertOk()->assertSee('VAN después 580.00 USD');
        $this->assertSame($pdf, $this->get(route(self::RUTA.'informes.download', [$p,$informe]))->assertDownload()->streamedContent());
    }

    public function test_cero_documentado_y_parametros_incompletos_no_generan_resultados(): void
    {
        [$p, , $a, $d] = $this->caso();
        $this->movimiento($p, $a, '100', 'ingreso');
        $url = route(self::RUTA.'evaluaciones.preview', $p);
        $d['fiscal']['tratamiento'] = 'no_aplicable_documentado';
        $d['fiscal']['tasa'] = '0';
        $this->post($url, $d)->assertOk()->assertSee('No aplicación documentada')->assertSee('VAN después 100.00 USD');

        unset($d['fiscal']['tasa']);
        $this->post($url, $d)->assertSessionHasErrors('fiscal.tasa');
        $d['fiscal']['tasa'] = '0.20';
        $d['fiscal']['tratamiento'] = 'ordinario';
        $this->post($url, $d)->assertSessionHasErrors('evaluacion');
        $d['fiscal']['alternativas'][$a->id] = ['base'=>"0;0;0\n1;100;0"];
        $d['fiscal']['estado'] = 'estimado';
        $this->post($url, $d)->assertSessionHasErrors('evaluacion');
        $d['fiscal']['estado'] = 'confirmado';
        $d['fiscal']['vigente_hasta'] = '2027-01-01';
        $this->post($url, $d)->assertSessionHasErrors('evaluacion');
        $this->assertDatabaseCount('ie_evaluaciones', 0);
    }

    public function test_deducciones_fiscales_distintas_y_venta_con_base_fiscal_documentada(): void
    {
        [$p, , $a, $d] = $this->caso();
        $equipo = $this->activo($p, $a, 'activo_tangible');
        $software = $this->activo($p, $a, 'activo_intangible');
        $this->movimiento($p, $a, '1000', 'ingreso');
        $venta = $this->movimiento($p, $a, '400', 'valor_rescate');
        $venta->forceFill(['activo_id'=>$equipo->id,'activo_operacion'=>'venta'])->save();
        $d['fiscal']['tasa_venta'] = '0.10';
        $d['fiscal']['alternativas'][$a->id] = [
            'base'=>"0;0;0\n1;1000;0",
            'deducciones'=>"1;{$equipo->id};manual;250\n1;{$software->id};lineal;200",
            'ventas'=>"1;{$equipo->id};300",
        ];
        $url = route(self::RUTA.'evaluaciones.preview', $p);
        $respuesta = $this->post($url, $d)->assertOk()->assertSee('Cuota contable contrastada 200.00')->assertSee('base fiscal pendiente 300.00');
        $fila = $respuesta->viewData('resultado')['fiscal']['alternativas'][$a->id]['periodos'][1];
        $this->decimal('250', $fila['dep']);
        $this->decimal('200', $fila['amort']);
        $this->decimal('550', $fila['base']);
        $this->decimal('120', $fila['total']);
        $this->decimal('1280', $fila['despues']);
        $this->assertNull($fila['activos'][$equipo->id]['cuota_contable']);

        $d['fiscal']['alternativas'][$a->id]['deducciones'] = "1;{$equipo->id};lineal;250";
        $this->post($url, $d)->assertSessionHasErrors('evaluacion');
        $d['fiscal']['alternativas'][$a->id]['deducciones'] = "1;{$equipo->id};doble_saldo;400";
        $this->post($url, $d)->assertOk();
        unset($d['fiscal']['alternativas'][$a->id]['ventas']);
        $this->post($url, $d)->assertSessionHasErrors('evaluacion');
    }

    private function caso(): array
    {
        Storage::fake('ingenieria_privada');
        $u = User::factory()->create();
        $p = new Proyecto(['nombre'=>'Proyecto académico','descripcion'=>'Caso de prueba','tipo_decision'=>'prospectiva',
            'fecha_base'=>'2026-11-24','horizonte_anios'=>1,'estado'=>'en_preparacion']);
        $p->usuario()->associate($u);
        $p->save();
        $esc = $p->escenarios()->create(['nombre'=>'Anual','periodicidad'=>'anual','horizonte_anios'=>1,
            'tasa_original'=>'0','modalidad_tasa'=>'efectiva','periodicidad_tasa'=>'anual','tasa_efectiva_anual'=>'0','estado_tasa'=>'validada']);
        $a = $p->alternativas()->create(['nombre'=>'Alternativa A','viabilidad_tecnica'=>'viable','estado_implementacion'=>'no_implementada']);
        $this->actingAs($u)->get(route(self::RUTA.'evaluaciones.index',$p))->assertOk()->assertSee(EvaluacionFiscalService::PENDIENTE);
        $this->post(route(self::RUTA.'evidencias.store',$p), ['titulo'=>'Respaldo tributario académico','tipo'=>'otro',
            'procedencia'=>'Caso ficticio','fecha_documento'=>'2026-11-24','calidad'=>'documentado',
            'archivo'=>UploadedFile::fake()->createWithContent('respaldo.pdf',"%PDF-1.4\n%%EOF\n")])->assertRedirect()->assertSessionHasNoErrors();
        $doc = $p->evidenciasDocumentales()->sole();
        foreach (['revisado','validado'] as $estado) {
            $this->post(route(self::RUTA.'evidencias.estado',[$p,$doc]), ['estado'=>$estado,'nota'=>'Supuesto del caso académico.'])
                ->assertRedirect()->assertSessionHasNoErrors();
        }

        return [$p, $u, $a, [
            'escenario_id'=>$esc->id,'alternativas'=>[$a->id],'moneda'=>'USD','perspectiva'=>'fiscal','periodicidad'=>'anual','equivalencia'=>true,
            'fiscal'=>['tratamiento'=>'ordinario','estado'=>'confirmado','tasa'=>'0.20','vigente_desde'=>'2026-11-24',
                'vigente_hasta'=>'2027-11-24','evidencia'=>$doc->id,'nota'=>'Supuestos de la hoja académica adjunta.','conciliado'=>'1'],
        ]];
    }

    private function movimiento($p, $a, string $monto, string $tipo = 'costo_operativo', string $fecha = '2027-11-24')
    {
        $concepto = $p->conceptos()->make(['nombre'=>$tipo,'tipo_concepto'=>$tipo,'monto'=>$monto,
            'moneda'=>'USD','fecha_real'=>$fecha,'periodicidad'=>'unico','momento_pago'=>'fin',
            'naturaleza_economica'=>in_array($tipo,['ingreso','valor_rescate']) ? 'ingreso_real' : 'desembolso_real',
            'clasificacion_decision'=>'diferencial','estado_dato'=>'confirmado']);
        $concepto->alternativa()->associate($a);
        $concepto->save();

        return $concepto;
    }

    private function activo($p, $a, string $tipo)
    {
        return $p->activos()->create(['alternativa_id'=>$a->id,'nombre'=>$tipo,'categoria'=>'otro','clasificacion_contable'=>$tipo,
            'fecha_adquisicion'=>'2026-11-24','fecha_disponible_uso'=>'2026-11-24','costo_adquisicion'=>'1000',
            'costos_capitalizables'=>'0','valor_residual'=>'0','vida_util_anios'=>5,'moneda'=>'USD','porcentaje_uso'=>'100',
            'estado_activo'=>'en_uso','origen_dato'=>'confirmado','depreciacion_fiscal'=>'Cuota autorizada académica.',
            'amortizacion_fiscal'=>'Coincide con la cuota lineal.','efecto_tributario'=>'Base fiscal documentada en el caso.']);
    }

    private function decimal(string $esperado, string|BigDecimal $actual): void
    {
        $this->assertTrue(BigDecimal::of($actual)->isEqualTo($esperado), (string) $actual);
    }
}
