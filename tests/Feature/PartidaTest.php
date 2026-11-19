<?php

namespace Tests\Feature;

use App\Models\CuentaContable;
use App\Models\Partida;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartidaTest extends TestCase
{
    use RefreshDatabase;

    public function test_contador_can_register_a_balanced_partida_with_details(): void
    {
        $usuario = User::factory()->create(['rol' => 'Contador']);
        [$debe, $haber] = $this->cuentasActivas();

        $response = $this->actingAs($usuario)->post(route('partidas.store'), [
            'fecha' => '2026-01-15',
            'concepto' => 'Pago de servicio',
            'detalles' => [
                ['id_cuenta' => $debe->id_cuenta, 'descripcion' => '', 'debe' => '100.00', 'haber' => '0.00'],
                ['id_cuenta' => $haber->id_cuenta, 'descripcion' => '', 'debe' => '0.00', 'haber' => '100.00'],
            ],
        ]);

        $partida = Partida::query()->firstOrFail();
        $response->assertRedirect(route('partidas.show', $partida));
        $this->assertDatabaseHas('tblpartidas', [
            'id_partida' => $partida->id_partida,
            'numero_partida' => 1,
            'concepto' => 'Pago de servicio',
        ]);
        $this->assertDatabaseCount('tbldetalles_partida', 2);
        $this->assertDatabaseHas('tbldetalles_partida', [
            'id_partida' => $partida->id_partida,
            'id_cuenta' => $debe->id_cuenta,
            'descripcion' => $debe->nombre_cuenta,
            'debe' => '100.00',
            'haber' => '0.00',
        ]);
    }

    public function test_unbalanced_partida_is_rejected_without_records(): void
    {
        $usuario = User::factory()->create(['rol' => 'Contador']);
        [$debe, $haber] = $this->cuentasActivas();

        $this->actingAs($usuario)->post(route('partidas.store'), [
            'fecha' => '2026-01-15',
            'detalles' => [
                ['id_cuenta' => $debe->id_cuenta, 'debe' => '100.00', 'haber' => '0.00'],
                ['id_cuenta' => $haber->id_cuenta, 'debe' => '0.00', 'haber' => '99.99'],
            ],
        ])->assertSessionHasErrors('detalles');

        $this->assertDatabaseCount('tblpartidas', 0);
        $this->assertDatabaseCount('tbldetalles_partida', 0);
    }

    public function test_partida_without_details_is_rejected(): void
    {
        $usuario = User::factory()->create(['rol' => 'Contador']);

        $this->actingAs($usuario)->post(route('partidas.store'), [
            'fecha' => '2026-01-15',
            'detalles' => [],
        ])->assertSessionHasErrors('detalles');

        $this->assertDatabaseCount('tblpartidas', 0);
        $this->assertDatabaseCount('tbldetalles_partida', 0);
    }

    public function test_nonexistent_account_is_rejected(): void
    {
        $usuario = User::factory()->create(['rol' => 'Contador']);

        $this->actingAs($usuario)->post(route('partidas.store'), [
            'fecha' => '2026-01-15',
            'detalles' => [
                ['id_cuenta' => 999999, 'debe' => '100.00', 'haber' => '0.00'],
            ],
        ])->assertSessionHasErrors('detalles.0.id_cuenta');

        $this->assertDatabaseCount('tblpartidas', 0);
        $this->assertDatabaseCount('tbldetalles_partida', 0);
    }

    public function test_inactive_account_failure_leaves_no_partial_records(): void
    {
        $usuario = User::factory()->create(['rol' => 'Contador']);
        [$debe, $haber] = $this->cuentasActivas();
        $inactiva = CuentaContable::create([
            'codigo_cuenta' => '9999', 'nombre_cuenta' => 'Cuenta inactiva',
            'tipo_cuenta' => 'Activo', 'nivel' => 3, 'estado' => false,
        ]);

        $this->actingAs($usuario)->post(route('partidas.store'), [
            'fecha' => '2026-01-15',
            'detalles' => [
                ['id_cuenta' => $debe->id_cuenta, 'debe' => '100.00', 'haber' => '0.00'],
                ['id_cuenta' => $inactiva->id_cuenta, 'debe' => '0.00', 'haber' => '100.00'],
                ['id_cuenta' => $haber->id_cuenta, 'debe' => '0.00', 'haber' => '0.00'],
            ],
        ])->assertSessionHasErrors('detalles.1.id_cuenta');

        $this->assertDatabaseCount('tblpartidas', 0);
        $this->assertDatabaseCount('tbldetalles_partida', 0);
    }

    public function test_contador_can_consult_partida_and_details(): void
    {
        $usuario = User::factory()->create(['rol' => 'Contador']);
        [$debe, $haber] = $this->cuentasActivas();
        $partida = Partida::create([
            'id_usuario' => $usuario->id_usuario, 'numero_partida' => 7,
            'fecha' => '2026-01-15', 'concepto' => 'Consulta',
        ]);
        $partida->detalles()->createMany([
            ['id_cuenta' => $debe->id_cuenta, 'id_usuario' => $usuario->id_usuario, 'debe' => '20.00', 'haber' => '0.00'],
            ['id_cuenta' => $haber->id_cuenta, 'id_usuario' => $usuario->id_usuario, 'debe' => '0.00', 'haber' => '20.00'],
        ]);

        $this->actingAs($usuario)->get(route('partidas.show', $partida))
            ->assertOk()
            ->assertSee('Consulta')
            ->assertSee($debe->nombre_cuenta)
            ->assertSee('20.00');
    }

    /** @return array{0: CuentaContable, 1: CuentaContable} */
    private function cuentasActivas(): array
    {
        return [
            CuentaContable::create([
                'codigo_cuenta' => '1101', 'nombre_cuenta' => 'Caja',
                'tipo_cuenta' => 'Activo', 'nivel' => 3, 'estado' => true,
            ]),
            CuentaContable::create([
                'codigo_cuenta' => '2101', 'nombre_cuenta' => 'Proveedores',
                'tipo_cuenta' => 'Pasivo', 'nivel' => 3, 'estado' => true,
            ]),
        ];
    }
}
