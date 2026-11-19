<?php

namespace Tests\Feature;

use App\Models\Partida;
use App\Models\User;
use App\Services\LibroContableService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EstadoResultadosTest extends TestCase
{
    use RefreshDatabase;

    public function test_groups_income_and_expense_accounts_and_includes_adjustments(): void
    {
        [$usuario, $ids] = $this->catalogo();
        $ordinaria = $this->partida($usuario, 1, '2026-01-10', 'Venta ordinaria');
        $ordinaria->detalles()->createMany([
            ['id_cuenta' => $ids['ingreso'], 'id_usuario' => $usuario->id_usuario, 'debe' => '0.00', 'haber' => '100.00'],
            ['id_cuenta' => $ids['gasto'], 'id_usuario' => $usuario->id_usuario, 'debe' => '40.00', 'haber' => '0.00'],
        ]);
        $ajuste = $this->partida($usuario, 2, '2026-02-10', '[AJUSTE:Depreciaciones] Ajuste');
        $ajuste->detalles()->create([
            'id_cuenta' => $ids['gasto'], 'id_usuario' => $usuario->id_usuario,
            'debe' => '30.00', 'haber' => '0.00',
        ]);

        $estado = app(LibroContableService::class)->estadoResultados();

        $this->assertSame('100.00', $this->saldo($estado['ingresos'], 'Ingresos'));
        $this->assertSame('70.00', $this->saldo($estado['gastos'], 'Gastos'));
        $this->assertSame('100.00', $estado['total_ingresos']);
        $this->assertSame('70.00', $estado['total_gastos']);
        $this->assertSame('30.00', $estado['utilidad_neta']);
        $this->assertTrue($estado['es_utilidad']);
    }

    public function test_loss_is_reported_as_absolute_net_amount(): void
    {
        [$usuario, $ids] = $this->catalogo();
        $partida = $this->partida($usuario, 1, '2026-03-01', 'Pérdida');
        $partida->detalles()->createMany([
            ['id_cuenta' => $ids['ingreso'], 'id_usuario' => $usuario->id_usuario, 'debe' => '0.00', 'haber' => '20.00'],
            ['id_cuenta' => $ids['gasto'], 'id_usuario' => $usuario->id_usuario, 'debe' => '35.50', 'haber' => '0.00'],
        ]);

        $estado = app(LibroContableService::class)->estadoResultados();

        $this->assertSame('15.50', $estado['utilidad_neta']);
        $this->assertFalse($estado['es_utilidad']);
    }

    public function test_state_results_applies_date_filters_and_authorization(): void
    {
        [$usuario, $ids] = $this->catalogo();
        $anterior = $this->partida($usuario, 1, '2026-01-01', 'Anterior');
        $anterior->detalles()->create([
            'id_cuenta' => $ids['ingreso'], 'id_usuario' => $usuario->id_usuario,
            'debe' => '0.00', 'haber' => '80.00',
        ]);
        $posterior = $this->partida($usuario, 2, '2026-02-01', 'Posterior');
        $posterior->detalles()->create([
            'id_cuenta' => $ids['ingreso'], 'id_usuario' => $usuario->id_usuario,
            'debe' => '0.00', 'haber' => '25.00',
        ]);

        $this->actingAs($usuario)->get(route('balances.resultados', [
            'fecha_inicio' => '2026-02-01', 'fecha_fin' => '2026-02-28',
        ]))->assertOk()->assertSee('25.00')->assertDontSee('80.00');

        $this->actingAs(User::factory()->create(['rol' => 'Admin']))
            ->get(route('balances.resultados'))->assertForbidden();
    }

    /** @return array{0: User, 1: array{ingreso: int, gasto: int}} */
    private function catalogo(): array
    {
        $usuario = User::factory()->create(['rol' => 'Contador']);
        DB::table('tblcuentascontables')->insert([
            ['id_cuenta' => 7, 'codigo_cuenta' => '10', 'nombre_cuenta' => 'Activo corriente', 'tipo_cuenta' => 'Activo', 'nivel' => 2, 'id_cuenta_padre' => null, 'estado' => true],
            ['id_cuenta' => 8, 'codigo_cuenta' => '12', 'nombre_cuenta' => 'Activo no corriente', 'tipo_cuenta' => 'Activo', 'nivel' => 2, 'id_cuenta_padre' => null, 'estado' => true],
            ['id_cuenta' => 9, 'codigo_cuenta' => '20', 'nombre_cuenta' => 'Pasivo corriente', 'tipo_cuenta' => 'Pasivo', 'nivel' => 2, 'id_cuenta_padre' => null, 'estado' => true],
            ['id_cuenta' => 10, 'codigo_cuenta' => '30', 'nombre_cuenta' => 'Patrimonio', 'tipo_cuenta' => 'Patrimonio', 'nivel' => 2, 'id_cuenta_padre' => null, 'estado' => true],
            ['id_cuenta' => 11, 'codigo_cuenta' => '4101', 'nombre_cuenta' => 'Ingresos', 'tipo_cuenta' => 'Ingreso', 'nivel' => 3, 'id_cuenta_padre' => null, 'estado' => true],
            ['id_cuenta' => 12, 'codigo_cuenta' => '5101', 'nombre_cuenta' => 'Gastos', 'tipo_cuenta' => 'Gasto', 'nivel' => 3, 'id_cuenta_padre' => null, 'estado' => true],
        ]);

        return [$usuario, ['ingreso' => 11, 'gasto' => 12]];
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
