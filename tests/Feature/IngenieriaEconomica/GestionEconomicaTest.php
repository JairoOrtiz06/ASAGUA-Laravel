<?php

namespace Tests\Feature\IngenieriaEconomica;

use App\Models\IngenieriaEconomica\Alternativa;
use App\Models\IngenieriaEconomica\Escenario;
use App\Models\IngenieriaEconomica\Proyecto;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GestionEconomicaTest extends TestCase
{
    use RefreshDatabase;

    private const RUTA = 'ingenieria-economica.proyectos.';

    private const PROYECTO = [
        'nombre' => 'Evaluación económica de facturación electrónica',
        'organizacion' => 'ASAGUA',
        'tipo_decision' => 'prospectiva',
        'fecha_base' => '2026-11-24',
        'horizonte_anios' => 5,
        'estado' => 'borrador',
    ];

    private const ALTERNATIVA = [
        'nombre' => 'Sistema ACAE de SISMANTEC',
        'proveedor' => 'SISMANTEC',
        'estado_implementacion' => 'no_implementada',
        'viabilidad_tecnica' => 'pendiente',
    ];

    private const ESCENARIO = [
        'nombre' => 'Referencia',
        'horizonte_anios' => 5,
        'periodicidad' => 'mensual',
        'tasa_original' => '0.0025',
        'periodicidad_tasa' => 'trimestral',
        'modalidad_tasa' => 'efectiva',
        'capitalizaciones_anuales' => 4,
        'tasa_efectiva_anual' => '0.010037562539',
        'fuente_tasa' => 'Referencia trimestral de la entidad',
        'estado_tasa' => 'provisional',
    ];

    public function test_contador_can_create_view_and_update_a_project_with_its_own_identity(): void
    {
        $contador = User::factory()->create();
        $ajeno = $this->proyecto(User::factory()->create(), ['nombre' => 'Proyecto privado']);

        $this->actingAs($contador)->get(route('ingenieria-economica.index'))
            ->assertOk()
            ->assertSee('class="sidebar"', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('Inicio de Ingeniería Económica')
            ->assertSee(route(self::RUTA.'index'), false)
            ->assertSee(route(self::RUTA.'create'), false)
            ->assertDontSee('Partidas contables')
            ->assertDontSee('class="table-wrap"', false);

        $this->get(route(self::RUTA.'index'))
            ->assertOk()
            ->assertSee('Todavía no tienes proyectos registrados.')
            ->assertDontSee($ajeno->nombre);

        $this->get(route(self::RUTA.'create'))->assertOk()->assertSee('Nuevo proyecto');
        $respuesta = $this->post(route(self::RUTA.'store'), self::PROYECTO + ['id_usuario' => $ajeno->id_usuario]);
        $proyecto = Proyecto::query()->where('nombre', self::PROYECTO['nombre'])->firstOrFail();

        $respuesta->assertRedirectToRoute(self::RUTA.'show', $proyecto);
        $this->assertTrue($proyecto->usuario->is($contador));
        $this->get(route(self::RUTA.'show', $proyecto))
            ->assertOk()->assertSee('ASAGUA')->assertSee('24/11/2026')
            ->assertSee('Proyectar y comparar flujos');
        $this->get(route(self::RUTA.'index'))
            ->assertOk()
            ->assertSee('class="table-wrap"', false)
            ->assertSee($proyecto->nombre)
            ->assertSee('Ver proyecto');

        $this->get(route(self::RUTA.'edit', $proyecto))
            ->assertOk()->assertSee('Editar proyecto')->assertSee('Guardar cambios');
        $this->patch(route(self::RUTA.'update', $proyecto), array_replace(self::PROYECTO, ['estado' => 'en_preparacion']))
            ->assertRedirectToRoute(self::RUTA.'show', $proyecto);
        $this->assertSame('en_preparacion', $proyecto->fresh()->estado);
    }

    public function test_horizontes_validos_se_guardan_al_crear_y_editar_proyectos(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get(route(self::RUTA.'create'))->assertOk()
            ->assertSee('min="1" max="100"', false)->assertSee('Indica entre 1 y 100 años.');

        foreach ([1, 5, 100] as $horizonte) {
            $datos = array_replace(self::PROYECTO, ['nombre' => "Proyecto de $horizonte años", 'horizonte_anios' => $horizonte]);
            $respuesta = $this->post(route(self::RUTA.'store'), $datos)->assertSessionHasNoErrors();
            $proyecto = Proyecto::query()->where('nombre', $datos['nombre'])->sole();
            $respuesta->assertRedirectToRoute(self::RUTA.'show', $proyecto);
            $this->assertSame($horizonte, $proyecto->horizonte_anios);
        }

        $this->get(route(self::RUTA.'edit', $proyecto))->assertOk()->assertSee('min="1" max="100"', false);
        foreach ([1, 5, 100] as $horizonte) {
            $this->patch(route(self::RUTA.'update', $proyecto), array_replace($datos, ['horizonte_anios' => $horizonte]))
                ->assertRedirectToRoute(self::RUTA.'show', $proyecto)->assertSessionHasNoErrors();
            $this->assertSame($horizonte, $proyecto->fresh()->horizonte_anios);
        }
    }

    public function test_horizontes_invalidos_se_rechazan_al_crear_y_editar_proyectos(): void
    {
        $contador = User::factory()->create();
        $proyecto = $this->proyecto($contador);
        $this->actingAs($contador);
        foreach ([0, 101, 32767, -1, 'cinco'] as $horizonte) {
            $datos = array_replace(self::PROYECTO, ['horizonte_anios' => $horizonte]);
            $mensaje = $horizonte === 'cinco'
                ? 'El campo horizonte en años debe ser un número entero.'
                : 'El campo horizonte en años debe estar entre 1 y 100.';
            $this->post(route(self::RUTA.'store'), $datos)
                ->assertSessionHasErrors(['horizonte_anios' => $mensaje]);
            $this->patch(route(self::RUTA.'update', $proyecto), $datos)
                ->assertSessionHasErrors(['horizonte_anios' => $mensaje]);
        }

        $this->assertDatabaseCount('ie_proyectos', 1);
        $this->assertSame(5, $proyecto->fresh()->horizonte_anios);
    }

    public function test_proyecto_historico_requiere_elegir_horizonte_para_nuevo_escenario(): void
    {
        $contador = User::factory()->create();
        $proyecto = $this->proyecto($contador, ['horizonte_anios' => 32767]);
        $this->actingAs($contador);

        $this->get(route(self::RUTA.'show', $proyecto))->assertOk()->assertSee('32767');
        $this->get(route(self::RUTA.'edit', $proyecto))->assertOk()
            ->assertSee('El horizonte guardado está fuera del rango operativo')
            ->assertSee('name="horizonte_anios" type="number" value="32767"', false);
        $this->get(route(self::RUTA.'escenarios.create', $proyecto))->assertOk()
            ->assertSee('El horizonte del proyecto está fuera del rango operativo')
            ->assertSee('name="horizonte_anios" type="number" value=""', false);

        $ruta = route(self::RUTA.'escenarios.store', $proyecto);
        foreach ([null, 32767] as $horizonte) {
            $this->post($ruta, array_replace(self::ESCENARIO, ['horizonte_anios' => $horizonte]))
                ->assertSessionHasErrors('horizonte_anios');
        }
        $this->assertDatabaseCount('ie_escenarios', 0);
        $this->post($ruta, self::ESCENARIO)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(5, $proyecto->escenarios()->sole()->horizonte_anios);
        $this->assertSame(32767, $proyecto->fresh()->horizonte_anios);

        $this->actingAs(User::factory()->create());
        $this->get(route(self::RUTA.'escenarios.create', $proyecto))->assertForbidden();
        $this->post($ruta, self::ESCENARIO)->assertForbidden();
        $this->patch(route(self::RUTA.'update', $proyecto), self::PROYECTO)->assertForbidden();
        $this->assertSame(32767, $proyecto->fresh()->horizonte_anios);
    }

    public function test_owner_can_manage_alternatives_and_implemented_records_are_preserved(): void
    {
        $contador = User::factory()->create();
        $proyecto = $this->proyecto($contador);
        $otro = $this->proyecto($contador);

        $this->actingAs($contador)->get(route(self::RUTA.'alternativas.create', $proyecto))->assertOk();
        $this->post(route(self::RUTA.'alternativas.store', $proyecto), self::ALTERNATIVA + ['proyecto_id' => $otro->id])
            ->assertRedirect()->assertSessionHasNoErrors();

        $alternativa = $proyecto->alternativas()->sole();
        $this->assertTrue($alternativa->proyecto->is($proyecto));
        $this->get(route(self::RUTA.'alternativas.index', $proyecto))->assertRedirect();
        $this->get(route(self::RUTA.'show', $proyecto))->assertSee($alternativa->nombre)
            ->assertSeeInOrder([
                route(self::RUTA.'alternativas.edit', [$proyecto, $alternativa]),
                route(self::RUTA.'alternativas.destroy', [$proyecto, $alternativa]),
                route(self::RUTA.'conceptos.index', [$proyecto, 'alternativa_id' => $alternativa->id]),
            ], false);
        $this->get(route(self::RUTA.'alternativas.edit', [$proyecto, $alternativa]))->assertOk();
        $this->patch(route(self::RUTA.'alternativas.update', [$proyecto, $alternativa]), array_replace(
            self::ALTERNATIVA,
            ['estado_implementacion' => 'implementada', 'fecha_implementacion' => '2026-11-01'],
        ))->assertRedirect()->assertSessionHasNoErrors();

        $this->delete(route(self::RUTA.'alternativas.destroy', [$proyecto, $alternativa]))
            ->assertSessionHasErrors('alternativa');
        $this->assertModelExists($alternativa);

        $borrador = $proyecto->alternativas()->create(self::ALTERNATIVA + ['fecha_implementacion' => '2026-11-24']);
        $this->delete(route(self::RUTA.'alternativas.destroy', [$proyecto, $borrador]))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertModelMissing($borrador);
    }

    public function test_owner_can_store_fractional_rates_edit_scenarios_and_change_the_principal(): void
    {
        $contador = User::factory()->create();
        $proyecto = $this->proyecto($contador);

        $formulario = $this->actingAs($contador)->get(route(self::RUTA.'escenarios.create', $proyecto))
            ->assertOk()->assertSee('name="horizonte_anios" type="number" value="5"', false);
        $datos = array_replace(self::ESCENARIO, ['horizonte_anios' => $formulario->viewData('escenario')->horizonte_anios]);
        $this->post(route(self::RUTA.'escenarios.store', $proyecto), $datos + ['es_principal' => true])
            ->assertRedirect()->assertSessionHasNoErrors();

        $primero = $proyecto->escenarios()->sole();
        $this->assertSame($proyecto->horizonte_anios, $primero->horizonte_anios);
        $this->assertTrue($primero->proyecto->is($proyecto));
        $this->assertFalse($primero->es_principal);
        $this->assertSame('0.002500000000', $primero->tasa_original);
        $this->assertSame('0.010037562539', $primero->tasa_efectiva_anual);
        $this->get(route(self::RUTA.'escenarios.edit', [$proyecto, $primero]))->assertOk()->assertSee('value="0.25"', false);

        $this->patch(route(self::RUTA.'escenarios.update', [$proyecto, $primero]), array_replace(
            self::ESCENARIO,
            ['tasa_efectiva_anual' => null, 'horizonte_anios' => 6],
        ))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(6, $primero->fresh()->horizonte_anios);
        $this->assertSame('0.010037562539', $primero->fresh()->tasa_efectiva_anual);
        $this->assertSame(4, $primero->fresh()->capitalizaciones_anuales);
        $this->assertSame('Referencia trimestral de la entidad', $primero->fresh()->fuente_tasa);

        $this->patch(route(self::RUTA.'escenarios.update', [$proyecto, $primero]), array_replace(
            self::ESCENARIO,
            ['tasa_original' => '0.12', 'modalidad_tasa' => 'nominal', 'tasa_efectiva_anual' => '0.01',
                'capitalizaciones_anuales' => 99, 'estado_tasa' => 'validada'],
        ))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('0.125508810000', $primero->fresh()->tasa_efectiva_anual);
        $this->assertSame(4, $primero->fresh()->capitalizaciones_anuales);
        $this->assertSame('validada', $primero->fresh()->estado_tasa);

        $this->post(route(self::RUTA.'escenarios.store', $proyecto), [
            'nombre' => 'Pendiente', 'horizonte_anios' => 5, 'periodicidad' => 'anual', 'estado_tasa' => 'pendiente',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $segundo = $proyecto->escenarios()->where('nombre', 'Pendiente')->sole();

        foreach ([$primero, $segundo, $segundo] as $principal) {
            $this->patch(route(self::RUTA.'escenarios.principal', [$proyecto, $principal]))->assertRedirect();
            $this->assertTrue($proyecto->fresh()->escenarioPrincipal->is($principal));
        }

        $this->assertFalse($primero->fresh()->es_principal);
        $this->get(route(self::RUTA.'show', $proyecto))->assertOk()->assertSee('Principal')->assertSee('12.550881 %');
    }

    public function test_porcentaje_visible_y_vista_previa_con_descuento_mensual(): void
    {
        $contador = User::factory()->create();
        $proyecto = $this->proyecto($contador);
        $datos = array_replace(self::ESCENARIO, ['tasa_porcentaje' => '0,25', 'periodicidad_descuento' => 'mensual']);
        $this->actingAs($contador)->postJson(route(self::RUTA.'escenarios.previsualizar', $proyecto), $datos)
            ->assertOk()->assertJsonPath('resumen.1', 'TMAR efectiva anual: 1.0037562539 %.')
            ->assertJsonPath('resumen.2', 'Tasa efectiva para descuento mensual: 0.083263985179 %.');
        $this->assertDatabaseCount('ie_escenarios', 0);
        $this->post(route(self::RUTA.'escenarios.store', $proyecto), $datos)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('0.002500000000', $proyecto->escenarios()->sole()->tasa_original);
        $this->get(route(self::RUTA.'show', $proyecto))->assertOk()->assertSee('Estado administrativo')
            ->assertSee('Preparación documental')->assertSee('1.0037562539 %');
    }

    public function test_horizonte_operativo_y_edicion_de_escenario_antiguo(): void
    {
        $contador = User::factory()->create();
        $proyecto = $this->proyecto($contador);
        $this->actingAs($contador);
        $ruta = route(self::RUTA.'escenarios.store', $proyecto);
        foreach ([1, 5, 100] as $horizonte) {
            $this->post($ruta, array_replace(self::ESCENARIO, ['horizonte_anios' => $horizonte]))
                ->assertRedirect()->assertSessionHasNoErrors();
        }
        foreach ([101, 32767, 0, -1, 'cinco'] as $horizonte) {
            $this->post($ruta, array_replace(self::ESCENARIO, ['horizonte_anios' => $horizonte]))
                ->assertSessionHasErrors('horizonte_anios');
        }
        $antiguo = $proyecto->escenarios()->create(array_replace(self::ESCENARIO, ['horizonte_anios' => 101]));
        $editar = route(self::RUTA.'escenarios.edit', [$proyecto, $antiguo]);
        $actualizar = route(self::RUTA.'escenarios.update', [$proyecto, $antiguo]);
        $this->get($editar)->assertOk()->assertSee('El horizonte guardado supera el límite operativo');
        $this->patch($actualizar, array_replace(self::ESCENARIO, ['horizonte_anios' => 101]))
            ->assertSessionHasErrors('horizonte_anios');
        $this->assertSame(101, $antiguo->fresh()->horizonte_anios);
        $this->patch($actualizar, self::ESCENARIO)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(5, $antiguo->fresh()->horizonte_anios);
    }

    public function test_invalid_project_alternative_and_rate_payloads_are_rejected(): void
    {
        $contador = User::factory()->create();
        $proyecto = $this->proyecto($contador);

        $this->actingAs($contador)->post(route(self::RUTA.'store'), [
            'tipo_decision' => 'desconocida', 'fecha_base' => '2026-02-30', 'horizonte_anios' => 0,
        ])->assertSessionHasErrors(['nombre', 'tipo_decision', 'fecha_base', 'horizonte_anios', 'estado']);

        $this->post(route(self::RUTA.'alternativas.store', $proyecto), [
            'nombre' => '', 'estado_implementacion' => 'desconocido', 'viabilidad_tecnica' => 'desconocida',
        ])->assertSessionHasErrors(['nombre', 'estado_implementacion', 'viabilidad_tecnica']);

        $this->post(route(self::RUTA.'escenarios.store', $proyecto), array_replace(self::ESCENARIO, [
            'tasa_original' => '-1', 'modalidad_tasa' => 'nominal', 'periodicidad_tasa' => null,
            'estado_tasa' => 'validada', 'fuente_tasa' => null,
        ]))->assertSessionHasErrors(['periodicidad_tasa', 'fuente_tasa']);

        $this->post(route(self::RUTA.'escenarios.store', $proyecto), array_replace(self::ESCENARIO, [
            'tasa_original' => '0.1', 'modalidad_tasa' => 'nominal', 'periodicidad_tasa' => 'anual',
        ]))->assertSessionHasErrors('tasa_original');

        $this->post(route(self::RUTA.'escenarios.store', $proyecto), array_replace(self::ESCENARIO, [
            'tasa_original' => '99', 'periodicidad_tasa' => 'mensual',
        ]))->assertSessionHasErrors('tasa_original');

        $this->assertDatabaseCount('ie_proyectos', 1);
        $this->assertDatabaseCount('ie_alternativas', 0);
        $this->assertDatabaseCount('ie_escenarios', 0);
    }

    public function test_access_requires_a_contador_and_the_project_owner(): void
    {
        $propietario = User::factory()->create();
        $proyecto = $this->proyecto($propietario);

        $this->get(route('ingenieria-economica.index'))->assertRedirectToRoute('login');

        foreach (['Admin', 'Auditor'] as $rol) {
            $this->actingAs(User::factory()->create(['rol' => $rol]));
            $this->get(route('ingenieria-economica.index'))->assertForbidden();
            $this->post(route(self::RUTA.'store'), self::PROYECTO)->assertForbidden();
        }

        $this->actingAs(User::factory()->create());
        $this->get(route(self::RUTA.'show', $proyecto))->assertForbidden();
        $this->get(route(self::RUTA.'edit', $proyecto))->assertForbidden();
        $this->patch(route(self::RUTA.'update', $proyecto), self::PROYECTO)->assertForbidden();
        $this->post(route(self::RUTA.'alternativas.store', $proyecto), self::ALTERNATIVA)->assertForbidden();
        $this->post(route(self::RUTA.'escenarios.store', $proyecto), self::ESCENARIO)->assertForbidden();

        $escenario = $proyecto->escenarios()->create(self::ESCENARIO);
        $this->get(route(self::RUTA.'escenarios.edit', [$proyecto, $escenario]))->assertForbidden();
        $this->patch(route(self::RUTA.'escenarios.update', [$proyecto, $escenario]), self::ESCENARIO)->assertForbidden();
    }

    public function test_child_bindings_reject_cross_project_edits_deletion_and_principal_assignment(): void
    {
        $contador = User::factory()->create();
        $primero = $this->proyecto($contador);
        $segundo = $this->proyecto($contador);
        $alternativa = $segundo->alternativas()->create(self::ALTERNATIVA);
        $escenario = $segundo->escenarios()->create(self::ESCENARIO);

        $this->actingAs($contador);
        $this->get(route(self::RUTA.'alternativas.edit', [$primero, $alternativa]))->assertNotFound();
        $this->patch(route(self::RUTA.'alternativas.update', [$primero, $alternativa]), self::ALTERNATIVA)->assertNotFound();
        $this->delete(route(self::RUTA.'alternativas.destroy', [$primero, $alternativa]))->assertNotFound();
        $this->get(route(self::RUTA.'escenarios.edit', [$primero, $escenario]))->assertNotFound();
        $this->patch(route(self::RUTA.'escenarios.update', [$primero, $escenario]), self::ESCENARIO)->assertNotFound();
        $this->patch(route(self::RUTA.'escenarios.principal', [$primero, $escenario]))->assertNotFound();

        $this->assertModelExists($alternativa);
        $this->assertFalse($escenario->fresh()->es_principal);
    }

    public function test_navegacion_contextual_conserva_proyecto_y_permisos(): void
    {
        $contador = User::factory()->create();
        $proyecto = $this->proyecto($contador);
        $raiz = route(self::RUTA.'show', $proyecto);
        $this->actingAs($contador);

        $secciones = ['show', 'alternativas.create', 'escenarios.create', 'conceptos.index', 'flujos.index',
            'evaluaciones.index', 'activos.index', 'sensibilidad.index', 'evidencias.index', 'referencias',
            'validacion', 'informes.index'];
        foreach ($secciones as $seccion) {
            $this->get(route(self::RUTA.$seccion, $proyecto))->assertOk()
                ->assertSee('Saltar al contenido')
                ->assertSee('aria-label="Ubicación del proyecto"', false)
                ->assertSee($raiz)
                ->assertSee($proyecto->nombre);
        }

        $contenido = $this->get(route(self::RUTA.'conceptos.index', $proyecto))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/aria-current="page"\s*>Conceptos</', $contenido);
        $this->actingAs(User::factory()->create())->get(route(self::RUTA.'informes.index', $proyecto))->assertForbidden();
    }

    public function test_eliminar_proyecto_desde_listado_respeta_propietario_y_dependencias(): void
    {
        $contador = User::factory()->create();
        $vacio = $this->proyecto($contador);
        $vinculado = $this->proyecto($contador);
        $alternativa = $vinculado->alternativas()->create(self::ALTERNATIVA);
        $ajeno = $this->proyecto(User::factory()->create());

        $this->actingAs($contador)->get(route(self::RUTA.'index'))->assertOk()
            ->assertSeeInOrder(['Ver proyecto', route(self::RUTA.'destroy', $vacio), 'Eliminar'], false);
        $this->delete(route(self::RUTA.'destroy', $ajeno))->assertForbidden();
        $this->delete(route(self::RUTA.'destroy', $vinculado))->assertSessionHasErrors('proyecto');
        $this->assertModelExists($vinculado);
        $this->assertModelExists($alternativa);
        $this->delete(route(self::RUTA.'destroy', $vacio))->assertRedirectToRoute(self::RUTA.'index');
        $this->assertModelMissing($vacio);
    }

    public function test_database_enforces_foreign_keys_and_one_principal_per_project(): void
    {
        $proyecto = $this->proyecto(User::factory()->create());
        $escenario = $proyecto->escenarios()->create(self::ESCENARIO);
        $escenario->es_principal = true;
        $escenario->saveOrFail();
        $segundo = $proyecto->escenarios()->create(self::ESCENARIO);
        $segundo->es_principal = true;

        $this->assertIntegrityViolation(fn () => $segundo->save(), 'ie_escenarios');
        $this->assertIntegrityViolation(fn () => Proyecto::query()->whereKey($proyecto->id)->delete(), 'ie_proyectos');
        $this->assertIntegrityViolation(fn () => DB::table('ie_alternativas')->insert(self::ALTERNATIVA + [
            'proyecto_id' => $proyecto->id + 100,
        ]), 'ie_alternativas');
        $this->assertSame(1, Escenario::query()->where('es_principal', true)->count());
        $this->assertSame(0, Alternativa::query()->count());
    }

    private function proyecto(User $contador, array $cambios = []): Proyecto
    {
        $proyecto = new Proyecto(array_replace(self::PROYECTO, $cambios));
        $proyecto->usuario()->associate($contador);
        $proyecto->saveOrFail();

        return $proyecto;
    }

    private function assertIntegrityViolation(callable $operation, string $table): void
    {
        try {
            DB::transaction($operation);
            $this->fail("Se esperaba una restricción de integridad en {$table}.");
        } catch (QueryException $exception) {
            $this->assertStringStartsWith('23', (string) $exception->getCode());
            $this->assertStringContainsString($table, $exception->getSql());
        }
    }
}
