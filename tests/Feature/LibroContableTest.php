<?php

namespace Tests\Feature;

use App\Models\CuentaContable;
use App\Models\DetallePartida;
use App\Models\Partida;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LibroContableTest extends TestCase
{
    use RefreshDatabase;

    public function test_diario_includes_ordinary_and_adjustment_movements_in_chronological_order(): void
    {
        [$usuario, $debe, $haber] = $this->baseContable();
        $enero = $this->crearPartida($usuario, 1, '2026-01-10', 'Compra ordinaria');
        $enero->detalles()->create([
            'id_cuenta' => $debe->id_cuenta, 'id_usuario' => $usuario->id_usuario,
            'descripcion' => 'Compra', 'debe' => '100.00', 'haber' => '0.00',
        ]);
        $febrero = $this->crearPartida($usuario, 2, '2026-02-10', '[AJUSTE:Depreciaciones] Cierre');
        $febrero->detalles()->create([
            'id_cuenta' => $haber->id_cuenta, 'id_usuario' => $usuario->id_usuario,
            'descripcion' => 'Ajuste', 'debe' => '0.00', 'haber' => '100.00',
            'fecha_creacion' => '2026-02-10',
        ]);

        $this->actingAs($usuario)->get(route('libros.diario'))
            ->assertOk()
            ->assertSee('Compra ordinaria')
            ->assertSee('Ajuste: Depreciaciones');

        $movimientos = app(\App\Services\LibroContableService::class)->diario();
        $this->assertSame(2, $movimientos->count());
        $this->assertSame('2026-01-10', $movimientos[0]['fecha']);
        $this->assertSame('2026-02-10', $movimientos[1]['fecha']);
        $this->assertTrue($movimientos[1]['es_ajuste']);
    }

    public function test_mayor_filters_movements_by_account_and_calculates_debit_balance(): void
    {
        [$usuario, $debe, $haber] = $this->baseContable();
        $partida = $this->crearPartida($usuario, 3, '2026-03-01', 'Movimiento');
        $partida->detalles()->createMany([
            ['id_cuenta' => $debe->id_cuenta, 'id_usuario' => $usuario->id_usuario, 'descripcion' => 'Cargo', 'debe' => '150.00', 'haber' => '20.00'],
            ['id_cuenta' => $haber->id_cuenta, 'id_usuario' => $usuario->id_usuario, 'descripcion' => 'Abono', 'debe' => '10.00', 'haber' => '200.00'],
        ]);

        $mayor = app(\App\Services\LibroContableService::class)->mayor($debe->id_cuenta);

        $this->assertSame($debe->id_cuenta, $mayor['cuenta']->id_cuenta);
        $this->assertCount(1, $mayor['movimientos']);
        $this->assertSame('150.00', $mayor['total_debe']);
        $this->assertSame('20.00', $mayor['total_haber']);
        $this->assertSame('130.00', $mayor['saldo']);
        $this->assertSame('Deudor', $mayor['naturaleza']);
        $this->assertSame(3, $mayor['movimientos'][0]['numero_partida']);
    }

    public function test_mayor_calculates_creditor_balance_and_preserves_adjustment_classification(): void
    {
        [$usuario, $debe, $haber] = $this->baseContable();
        $partida = $this->crearPartida($usuario, 4, '2026-04-01', '[AJUSTE:Ingresos diferidos] Periodificación');
        $partida->detalles()->create([
            'id_cuenta' => $haber->id_cuenta, 'id_usuario' => $usuario->id_usuario,
            'descripcion' => 'Ingreso diferido', 'debe' => '25.00', 'haber' => '100.00',
            'fecha_creacion' => '2026-04-01',
        ]);

        $mayor = app(\App\Services\LibroContableService::class)->mayor($haber->id_cuenta);

        $this->assertSame('75.00', $mayor['saldo']);
        $this->assertSame('Acreedor', $mayor['naturaleza']);
        $this->assertTrue($mayor['movimientos'][0]['es_ajuste']);
        $this->assertSame('Ingresos diferidos', $mayor['movimientos'][0]['tipo_ajuste']);
    }

    public function test_roles_can_consult_books_but_admin_cannot(): void
    {
        foreach (['Contador', 'Auditor'] as $rol) {
            $this->actingAs(User::factory()->create(['rol' => $rol]))
                ->get(route('libros.diario'))->assertOk();
        }

        $this->actingAs(User::factory()->create(['rol' => 'Admin']))
            ->get(route('libros.diario'))->assertForbidden();
    }

    /** @return array{0: User, 1: CuentaContable, 2: CuentaContable} */
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
        ];
    }

    private function crearPartida(User $usuario, int $numero, string $fecha, string $concepto): Partida
    {
        return Partida::create([
            'id_usuario' => $usuario->id_usuario,
            'numero_partida' => $numero,
            'fecha' => $fecha,
            'concepto' => $concepto,
        ]);
    }
}
