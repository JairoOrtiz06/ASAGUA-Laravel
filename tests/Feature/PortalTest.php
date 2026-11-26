<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_contador_can_access_the_main_portal(): void
    {
        $contador = User::factory()->create(['rol' => 'Contador', 'nombre' => 'Elena', 'apellido' => 'López']);

        $this->actingAs($contador)
            ->get(route('portal.contador'))
            ->assertOk()
            ->assertSee('Bienvenido, Elena López.')
            ->assertSee($contador->usuario)
            ->assertSee('Sistemas Contables')
            ->assertSee('Ingeniería Económica')
            ->assertSee(route('panel.contador'), false)
            ->assertSee(route('ingenieria-economica.index'), false);
    }

    public function test_contador_can_access_engineering_economics_and_return_to_portal(): void
    {
        $contador = User::factory()->create(['rol' => 'Contador']);

        $this->actingAs($contador)
            ->get(route('ingenieria-economica.index'))
            ->assertOk()
            ->assertSee('Ingeniería Económica')
            ->assertSee(route('portal.contador'), false)
            ->assertSee('aria-current="page"', false)
            ->assertSee(route('ingenieria-economica.proyectos.index'), false)
            ->assertSee(route('ingenieria-economica.proyectos.create'), false)
            ->assertSee(route('ingenieria-economica.herramientas.index'), false);

        foreach (['proyectos.index', 'proyectos.create', 'herramientas.index'] as $destino) {
            $this->get(route('ingenieria-economica.'.$destino))->assertOk();
        }
        $this->post(route('logout'))->assertRedirectToRoute('login');
        $this->assertGuest();
        $this->get(route('ingenieria-economica.index'))->assertRedirectToRoute('login');
    }

    public function test_non_contador_roles_cannot_access_contador_portal_or_engineering_economics(): void
    {
        foreach (['Admin', 'Auditor'] as $role) {
            $user = User::factory()->create(['rol' => $role]);

            $this->actingAs($user)
                ->get(route('portal.contador'))
                ->assertForbidden();

            $this->actingAs($user)
                ->get(route('ingenieria-economica.index'))
                ->assertForbidden();
        }
    }

    public function test_contador_can_reach_the_accounting_panel_from_the_portal(): void
    {
        $contador = User::factory()->create(['rol' => 'Contador']);

        $this->actingAs($contador)
            ->get(route('panel.contador'))
            ->assertOk()
            ->assertSee('Portal principal')
            ->assertSee('Partidas contables')
            ->assertDontSee('Ingeniería Económica')
            ->assertDontSee(route('ingenieria-economica.index'), false);
    }
}
