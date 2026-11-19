<?php

namespace Tests\Feature;

use App\Models\CuentaContable;
use App\Models\Partida;
use App\Models\User;
use App\Services\AjusteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AjusteTest extends TestCase
{
    use RefreshDatabase;

    public function test_contador_can_register_a_balanced_adjustment_with_type_marker(): void
    {
        $usuario = User::factory()->create(['rol' => 'Contador']);
        [$debe, $haber] = $this->cuentasActivas();

        $response = $this->actingAs($usuario)->post(route('ajustes.store'), $this->datos(
            $debe->id_cuenta,
            $haber->id_cuenta,
        ));

        $partida = Partida::query()->firstOrFail();
        $response->assertRedirect(route('ajustes.show', $partida));
        $this->assertSame('[AJUSTE:Depreciaciones] Ajuste de cierre', $partida->concepto);
        $this->assertSame('Depreciaciones', $partida->tipo_ajuste);
        $this->assertTrue($partida->esAjuste());
        $this->assertDatabaseCount('tbldetalles_partida', 2);
        $this->assertDatabaseHas('tbldetalles_partida', [
            'id_partida' => $partida->id_partida,
            'id_cuenta' => $debe->id_cuenta,
            'descripcion' => 'Depreciación del período',
            'debe' => '100.00',
            'haber' => '0.00',
            'fecha_creacion' => '2026-06-30 00:00:00',
        ]);
    }

    public function test_unbalanced_adjustment_is_rejected(): void
    {
        $usuario = User::factory()->create(['rol' => 'Contador']);
        [$debe, $haber] = $this->cuentasActivas();
        $datos = $this->datos($debe->id_cuenta, $haber->id_cuenta);
        $datos['detalles'][1]['haber'] = '99.99';

        $this->actingAs($usuario)->post(route('ajustes.store'), $datos)
            ->assertSessionHasErrors('detalles');

        $this->assertDatabaseCount('tblpartidas', 0);
        $this->assertDatabaseCount('tbldetalles_partida', 0);
    }

    public function test_adjustment_rejects_date_outside_accounting_period(): void
    {
        $usuario = User::factory()->create(['rol' => 'Contador']);
        [$debe, $haber] = $this->cuentasActivas();
        $datos = $this->datos($debe->id_cuenta, $haber->id_cuenta);
        $datos['fecha'] = '2025-12-31';

        $this->actingAs($usuario)->post(route('ajustes.store'), $datos)
            ->assertSessionHasErrors('fecha');
        $this->assertDatabaseCount('tblpartidas', 0);
    }

    public function test_adjustment_rejects_inactive_account_before_invalid_detail_amount(): void
    {
        $usuario = User::factory()->create(['rol' => 'Contador']);
        [$debe, $haber] = $this->cuentasActivas();
        $inactiva = CuentaContable::create([
            'codigo_cuenta' => '9999', 'nombre_cuenta' => 'Cuenta inactiva',
            'tipo_cuenta' => 'Activo', 'nivel' => 3, 'estado' => false,
        ]);
        $datos = $this->datos($debe->id_cuenta, $haber->id_cuenta);
        $datos['detalles'][1]['id_cuenta'] = $inactiva->id_cuenta;
        $datos['detalles'][] = [
            'id_cuenta' => $haber->id_cuenta,
            'descripcion' => 'Detalle inválido',
            'debe' => '0.00',
            'haber' => '0.00',
        ];

        $this->actingAs($usuario)->post(route('ajustes.store'), $datos)
            ->assertSessionHasErrors('detalles.1.id_cuenta');
        $this->assertDatabaseCount('tblpartidas', 0);
        $this->assertDatabaseCount('tbldetalles_partida', 0);
    }

    public function test_only_contador_can_register_adjustments(): void
    {
        [$debe, $haber] = $this->cuentasActivas();

        $this->actingAs(User::factory()->create(['rol' => 'Auditor']))
            ->get(route('ajustes.create'))
            ->assertForbidden();

        $this->actingAs(User::factory()->create(['rol' => 'Admin']))
            ->post(route('ajustes.store'), $this->datos($debe->id_cuenta, $haber->id_cuenta))
            ->assertForbidden();
    }

    public function test_adjustments_are_separated_from_ordinary_partidas(): void
    {
        $usuario = User::factory()->create(['rol' => 'Contador']);
        [$debe, $haber] = $this->cuentasActivas();
        $ajuste = $this->actingAs($usuario)->post(route('ajustes.store'), $this->datos(
            $debe->id_cuenta,
            $haber->id_cuenta,
        ))->getSession()->get('status');

        $this->assertNotNull($ajuste);
        $this->assertSame(1, Partida::query()->ajustes()->count());

        $ordinaria = Partida::create([
            'id_usuario' => $usuario->id_usuario, 'numero_partida' => 2,
            'fecha' => '2026-06-30', 'concepto' => 'Partida ordinaria',
        ]);

        $this->assertFalse($ordinaria->esAjuste());
        $this->assertSame(1, Partida::query()->ajustes()->count());
    }

    /** @return array<string, mixed> */
    private function datos(int $idDebe, int $idHaber): array
    {
        return [
            'periodo_inicio' => '2026-01-01',
            'periodo_fin' => '2026-12-31',
            'fecha' => '2026-06-30',
            'tipo' => AjusteService::TIPOS_AJUSTE[0],
            'concepto' => 'Ajuste de cierre',
            'detalles' => [
                ['id_cuenta' => $idDebe, 'descripcion' => 'Depreciación del período', 'debe' => '100.00', 'haber' => '0.00'],
                ['id_cuenta' => $idHaber, 'descripcion' => 'Depreciación acumulada', 'debe' => '0.00', 'haber' => '100.00'],
            ],
        ];
    }

    /** @return array{0: CuentaContable, 1: CuentaContable} */
    private function cuentasActivas(): array
    {
        return [
            CuentaContable::create([
                'codigo_cuenta' => '6101', 'nombre_cuenta' => 'Gasto depreciación',
                'tipo_cuenta' => 'Gasto', 'nivel' => 3, 'estado' => true,
            ]),
            CuentaContable::create([
                'codigo_cuenta' => '1701', 'nombre_cuenta' => 'Depreciación acumulada',
                'tipo_cuenta' => 'Activo', 'nivel' => 3, 'estado' => true,
            ]),
        ];
    }
}
