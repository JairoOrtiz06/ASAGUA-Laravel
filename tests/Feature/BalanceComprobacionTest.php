<?php

namespace Tests\Feature;

use App\Models\CuentaContable;
use App\Models\Partida;
use App\Models\User;
use App\Services\LibroContableService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BalanceComprobacionTest extends TestCase
{
    use RefreshDatabase;

    public function test_balance_groups_accounts_and_calculates_debtor_and_creditor_balances(): void
    {
        [$usuario, $caja, $proveedores, $ingresos] = $this->baseContable();
        $partida = $this->partida($usuario, 1, '2026-01-10', 'Partida ordinaria');
        $partida->detalles()->createMany([
            ['id_cuenta' => $caja->id_cuenta, 'id_usuario' => $usuario->id_usuario, 'descripcion' => 'Caja', 'debe' => '150.00', 'haber' => '50.00'],
            ['id_cuenta' => $proveedores->id_cuenta, 'id_usuario' => $usuario->id_usuario, 'descripcion' => 'Proveedor', 'debe' => '20.00', 'haber' => '100.00'],
        ]);

        $balance = app(LibroContableService::class)->balanceComprobacion();
        $filas = collect($balance['cuentas'])->keyBy('codigo');

        $this->assertCount(2, $filas);
        $this->assertSame('150.00', $filas['1101']['debe']);
        $this->assertSame('50.00', $filas['1101']['haber']);
        $this->assertSame('100.00', $filas['1101']['saldo_deudor']);
        $this->assertSame('0.00', $filas['1101']['saldo_acreedor']);
        // Python uses saldo = debe - haber and classifies a debtor balance
        // only when that difference is positive: 20 - 100 = -80.
        $this->assertSame('0.00', $filas['2101']['saldo_deudor']);
        $this->assertSame('80.00', $filas['2101']['saldo_acreedor']);
    }

    public function test_balance_totals_include_ordinary_and_adjustment_movements(): void
    {
        [$usuario, $caja, $proveedores] = $this->baseContable();
        $ordinaria = $this->partida($usuario, 1, '2026-01-10', 'Ordinaria');
        $ordinaria->detalles()->create([
            'id_cuenta' => $caja->id_cuenta, 'id_usuario' => $usuario->id_usuario,
            'descripcion' => 'Compra', 'debe' => '100.00', 'haber' => '0.00',
        ]);
        $ajuste = $this->partida($usuario, 2, '2026-02-10', '[AJUSTE:Depreciaciones] Cierre');
        $ajuste->detalles()->create([
            'id_cuenta' => $proveedores->id_cuenta, 'id_usuario' => $usuario->id_usuario,
            'descripcion' => 'Ajuste', 'debe' => '0.00', 'haber' => '100.00',
            'fecha_creacion' => '2026-02-10',
        ]);

        $response = $this->actingAs($usuario)->get(route('balances.comprobacion'));
        $response->assertOk()->assertSee('Ordinaria')->assertSee('100.00');

        $balance = app(LibroContableService::class)->balanceComprobacion();
        $this->assertSame('100.00', $balance['total_debe']);
        $this->assertSame('100.00', $balance['total_haber']);
        $this->assertSame('100.00', $balance['total_deudor']);
        $this->assertSame('100.00', $balance['total_acreedor']);
        $this->assertDatabaseCount('tblpartidas', 2);
        $this->assertDatabaseCount('tbldetalles_partida', 2);
    }

    public function test_balance_respects_date_filters_without_altering_records(): void
    {
        [$usuario, $caja] = $this->baseContable();
        $enero = $this->partida($usuario, 1, '2026-01-10', 'Enero');
        $enero->detalles()->create([
            'id_cuenta' => $caja->id_cuenta, 'id_usuario' => $usuario->id_usuario,
            'debe' => '25.00', 'haber' => '0.00',
        ]);
        $febrero = $this->partida($usuario, 2, '2026-02-10', 'Febrero');
        $febrero->detalles()->create([
            'id_cuenta' => $caja->id_cuenta, 'id_usuario' => $usuario->id_usuario,
            'debe' => '75.00', 'haber' => '0.00',
        ]);

        $balance = app(LibroContableService::class)->balanceComprobacion('2026-02-01', '2026-02-28');

        $this->assertSame('75.00', $balance['total_debe']);
        $this->assertDatabaseHas('tblpartidas', ['id_partida' => $enero->id_partida, 'concepto' => 'Enero']);
        $this->assertDatabaseHas('tblpartidas', ['id_partida' => $febrero->id_partida, 'concepto' => 'Febrero']);
        $this->assertDatabaseCount('tbldetalles_partida', 2);
    }

    public function test_contador_and_auditor_can_consult_balance_but_admin_cannot(): void
    {
        foreach (['Contador', 'Auditor'] as $rol) {
            $this->actingAs(User::factory()->create(['rol' => $rol]))
                ->get(route('balances.comprobacion'))->assertOk();
        }

        $this->actingAs(User::factory()->create(['rol' => 'Admin']))
            ->get(route('balances.comprobacion'))->assertForbidden();
    }

    /** @return array{0: User, 1: CuentaContable, 2: CuentaContable, 3: CuentaContable} */
    private function baseContable(): array
    {
        $usuario = User::factory()->create(['rol' => 'Contador']);
        return [
            $usuario,
            CuentaContable::create([
                'codigo_cuenta' => '1101', 'nombre_cuenta' => 'Caja',
                'tipo_cuenta' => 'Activo', 'nivel' => 3, 'estado' => true,
            ]),
            CuentaContable::create([
                'codigo_cuenta' => '2101', 'nombre_cuenta' => 'Proveedores',
                'tipo_cuenta' => 'Pasivo', 'nivel' => 3, 'estado' => true,
            ]),
            CuentaContable::create([
                'codigo_cuenta' => '4101', 'nombre_cuenta' => 'Ingresos',
                'tipo_cuenta' => 'Ingreso', 'nivel' => 3, 'estado' => true,
            ]),
        ];
    }

    private function partida(User $usuario, int $numero, string $fecha, string $concepto): Partida
    {
        return Partida::create([
            'id_usuario' => $usuario->id_usuario, 'numero_partida' => $numero,
            'fecha' => $fecha, 'concepto' => $concepto,
        ]);
    }
}
