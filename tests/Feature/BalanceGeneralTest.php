<?php

namespace Tests\Feature;

use App\Models\Partida;
use App\Models\User;
use App\Services\LibroContableService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BalanceGeneralTest extends TestCase
{
    use RefreshDatabase;

    public function test_balance_classifies_assets_liabilities_and_equity_and_adds_profit(): void
    {
        [$usuario, $ids] = $this->catalogoJerarquico();
        $ordinaria = $this->partida($usuario, 1, '2026-01-10', 'Ordinaria');
        $ordinaria->detalles()->createMany([
            ['id_cuenta' => $ids['caja'], 'id_usuario' => $usuario->id_usuario, 'debe' => '100.00', 'haber' => '0.00'],
            ['id_cuenta' => $ids['ingreso'], 'id_usuario' => $usuario->id_usuario, 'debe' => '0.00', 'haber' => '100.00'],
        ]);
        $ajuste = $this->partida($usuario, 2, '2026-02-10', '[AJUSTE:Depreciaciones] Ajuste');
        $ajuste->detalles()->createMany([
            ['id_cuenta' => $ids['gasto'], 'id_usuario' => $usuario->id_usuario, 'debe' => '30.00', 'haber' => '0.00', 'fecha_creacion' => '2026-02-10'],
            ['id_cuenta' => $ids['proveedor'], 'id_usuario' => $usuario->id_usuario, 'debe' => '0.00', 'haber' => '30.00', 'fecha_creacion' => '2026-02-10'],
        ]);

        $balance = app(LibroContableService::class)->balanceGeneral();

        $this->assertSame('100.00', $this->saldo($balance['AC_CORRIENTE'], 'Caja corriente'));
        $this->assertSame('30.00', $this->saldo($balance['PAS_CORRIENTE'], 'Proveedores corrientes'));
        $this->assertSame('70.00', $this->saldo($balance['PATRIMONIO'], 'RESULTADOS DEL EJERCICIO'));
        $this->assertSame('100.00', $balance['total_activos']);
        $this->assertSame('30.00', $balance['total_pasivos']);
        $this->assertSame('70.00', $balance['total_patrimonio']);
        $this->assertSame('100.00', $balance['total_pasivos_patrimonio']);
        $this->assertTrue($balance['ecuacion_ok']);
    }

    public function test_non_current_liabilities_are_excluded_from_original_total(): void
    {
        [$usuario, $ids] = $this->catalogoJerarquico();
        $partida = $this->partida($usuario, 1, '2026-03-01', 'Pasivo no corriente');
        $partida->detalles()->createMany([
            ['id_cuenta' => $ids['caja'], 'id_usuario' => $usuario->id_usuario, 'debe' => '100.00', 'haber' => '0.00'],
            ['id_cuenta' => $ids['pasivoNoCorriente'], 'id_usuario' => $usuario->id_usuario, 'debe' => '0.00', 'haber' => '50.00'],
        ]);

        $balance = app(LibroContableService::class)->balanceGeneral();

        $this->assertSame([], $balance['PAS_NO_CORRIENTE']);
        $this->assertSame('0.00', $balance['total_pasivos']);
        $this->assertSame('100.00', $balance['total_activos']);
    }

    public function test_balance_applies_the_available_date_filters(): void
    {
        [$usuario, $ids] = $this->catalogoJerarquico();
        $anterior = $this->partida($usuario, 1, '2026-01-01', 'Anterior');
        $anterior->detalles()->createMany([
            ['id_cuenta' => $ids['caja'], 'id_usuario' => $usuario->id_usuario, 'debe' => '40.00', 'haber' => '0.00'],
            ['id_cuenta' => $ids['ingreso'], 'id_usuario' => $usuario->id_usuario, 'debe' => '0.00', 'haber' => '40.00'],
        ]);
        $posterior = $this->partida($usuario, 2, '2026-02-01', 'Posterior');
        $posterior->detalles()->createMany([
            ['id_cuenta' => $ids['caja'], 'id_usuario' => $usuario->id_usuario, 'debe' => '25.00', 'haber' => '0.00'],
            ['id_cuenta' => $ids['ingreso'], 'id_usuario' => $usuario->id_usuario, 'debe' => '0.00', 'haber' => '25.00'],
        ]);

        $balance = app(LibroContableService::class)->balanceGeneral('2026-02-01', '2026-02-28');

        $this->assertSame('25.00', $this->saldo($balance['AC_CORRIENTE'], 'Caja corriente'));
        $this->assertSame('25.00', $this->saldo($balance['PATRIMONIO'], 'RESULTADOS DEL EJERCICIO'));
    }

    public function test_balance_route_includes_ordinary_adjustment_and_non_current_section(): void
    {
        [$usuario, $ids] = $this->catalogoJerarquico();
        $partida = $this->partida($usuario, 1, '2026-04-01', 'Ordinaria');
        $partida->detalles()->create([
            'id_cuenta' => $ids['caja'], 'id_usuario' => $usuario->id_usuario,
            'debe' => '25.00', 'haber' => '0.00',
        ]);
        $ajuste = $this->partida($usuario, 2, '2026-04-02', '[AJUSTE:Depreciaciones] Ajuste');
        $ajuste->detalles()->create([
            'id_cuenta' => $ids['pasivoNoCorriente'], 'id_usuario' => $usuario->id_usuario,
            'debe' => '0.00', 'haber' => '25.00', 'fecha_creacion' => '2026-04-02',
        ]);

        $this->actingAs($usuario)->get(route('balances.general'))
            ->assertOk()
            ->assertSee('Activo corriente')
            ->assertSee('Pasivo no corriente')
            ->assertSee('25.00');

        $this->assertDatabaseCount('tblpartidas', 2);
        $this->assertDatabaseCount('tbldetalles_partida', 2);
    }

    public function test_contador_and_auditor_can_consult_general_balance_but_admin_cannot(): void
    {
        foreach (['Contador', 'Auditor'] as $rol) {
            $this->actingAs(User::factory()->create(['rol' => $rol]))
                ->get(route('balances.general'))->assertOk();
        }

        $this->actingAs(User::factory()->create(['rol' => 'Admin']))
            ->get(route('balances.general'))->assertForbidden();
    }

    /** @return array{0: User, 1: array<string, int>} */
    private function catalogoJerarquico(): array
    {
        $usuario = User::factory()->create(['rol' => 'Contador']);
        DB::table('tblcuentascontables')->insert([
            ['id_cuenta' => 7, 'codigo_cuenta' => '10', 'nombre_cuenta' => 'Activo corriente', 'tipo_cuenta' => 'Activo', 'nivel' => 2, 'id_cuenta_padre' => null, 'estado' => true],
            ['id_cuenta' => 8, 'codigo_cuenta' => '12', 'nombre_cuenta' => 'Activo no corriente', 'tipo_cuenta' => 'Activo', 'nivel' => 2, 'id_cuenta_padre' => null, 'estado' => true],
            ['id_cuenta' => 9, 'codigo_cuenta' => '20', 'nombre_cuenta' => 'Pasivo corriente', 'tipo_cuenta' => 'Pasivo', 'nivel' => 2, 'id_cuenta_padre' => null, 'estado' => true],
            ['id_cuenta' => 10, 'codigo_cuenta' => '30', 'nombre_cuenta' => 'Patrimonio', 'tipo_cuenta' => 'Patrimonio', 'nivel' => 2, 'id_cuenta_padre' => null, 'estado' => true],
            ['id_cuenta' => 11, 'codigo_cuenta' => '1101', 'nombre_cuenta' => 'Caja corriente', 'tipo_cuenta' => 'Activo', 'nivel' => 3, 'id_cuenta_padre' => 7, 'estado' => true],
            ['id_cuenta' => 12, 'codigo_cuenta' => '1201', 'nombre_cuenta' => 'Terreno', 'tipo_cuenta' => 'Activo', 'nivel' => 3, 'id_cuenta_padre' => 8, 'estado' => true],
            ['id_cuenta' => 13, 'codigo_cuenta' => '2101', 'nombre_cuenta' => 'Proveedores corrientes', 'tipo_cuenta' => 'Pasivo', 'nivel' => 3, 'id_cuenta_padre' => 9, 'estado' => true],
            ['id_cuenta' => 14, 'codigo_cuenta' => '3101', 'nombre_cuenta' => 'Capital', 'tipo_cuenta' => 'Patrimonio', 'nivel' => 3, 'id_cuenta_padre' => 10, 'estado' => true],
            ['id_cuenta' => 15, 'codigo_cuenta' => '3201', 'nombre_cuenta' => 'RESULTADOS DEL EJERCICIO', 'tipo_cuenta' => 'Patrimonio', 'nivel' => 3, 'id_cuenta_padre' => 10, 'estado' => true],
            ['id_cuenta' => 16, 'codigo_cuenta' => '4101', 'nombre_cuenta' => 'Ingresos', 'tipo_cuenta' => 'Ingreso', 'nivel' => 3, 'id_cuenta_padre' => null, 'estado' => true],
            ['id_cuenta' => 17, 'codigo_cuenta' => '5101', 'nombre_cuenta' => 'Gastos', 'tipo_cuenta' => 'Gasto', 'nivel' => 3, 'id_cuenta_padre' => null, 'estado' => true],
            ['id_cuenta' => 18, 'codigo_cuenta' => '22', 'nombre_cuenta' => 'Pasivo no corriente', 'tipo_cuenta' => 'Pasivo', 'nivel' => 2, 'id_cuenta_padre' => null, 'estado' => true],
            ['id_cuenta' => 19, 'codigo_cuenta' => '2201', 'nombre_cuenta' => 'Préstamo largo plazo', 'tipo_cuenta' => 'Pasivo', 'nivel' => 3, 'id_cuenta_padre' => 18, 'estado' => true],
        ]);

        return [$usuario, [
            'caja' => 11, 'proveedor' => 13, 'ingreso' => 16,
            'gasto' => 17, 'pasivoNoCorriente' => 19,
        ]];
    }

    private function partida(User $usuario, int $numero, string $fecha, string $concepto): Partida
    {
        return Partida::create([
            'id_usuario' => $usuario->id_usuario, 'numero_partida' => $numero,
            'fecha' => $fecha, 'concepto' => $concepto,
        ]);
    }

    /** @param array<int, array{cuenta: string, saldo: string}> $filas */
    private function saldo(array $filas, string $cuenta): ?string
    {
        foreach ($filas as $fila) {
            if ($fila['cuenta'] === $cuenta) {
                return $fila['saldo'];
            }
        }
        return null;
    }
}
