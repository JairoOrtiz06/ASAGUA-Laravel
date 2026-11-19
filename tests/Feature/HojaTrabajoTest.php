<?php

namespace Tests\Feature;

use App\Models\Partida;
use App\Models\User;
use App\Services\HojaTrabajoContableService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HojaTrabajoTest extends TestCase
{
    use RefreshDatabase;

    public function test_builds_all_sections_with_ordinary_movements_and_adjustments(): void
    {
        [$usuario, $ids] = $this->catalogo();
        $ordinaria = $this->partida($usuario, 1, '2026-01-10', 'Venta ordinaria');
        $ordinaria->detalles()->createMany([
            ['id_cuenta' => $ids['caja'], 'id_usuario' => $usuario->id_usuario, 'debe' => '100.00', 'haber' => '0.00'],
            ['id_cuenta' => $ids['ingreso'], 'id_usuario' => $usuario->id_usuario, 'debe' => '0.00', 'haber' => '100.00'],
        ]);
        $ajuste = $this->partida($usuario, 2, '2026-02-10', '[AJUSTE:Depreciaciones] Ajuste');
        $ajuste->detalles()->createMany([
            ['id_cuenta' => $ids['gasto'], 'id_usuario' => $usuario->id_usuario, 'debe' => '30.00', 'haber' => '0.00'],
            ['id_cuenta' => $ids['proveedor'], 'id_usuario' => $usuario->id_usuario, 'debe' => '0.00', 'haber' => '30.00'],
        ]);

        $hoja = app(HojaTrabajoContableService::class)->generar();

        $this->assertSame('100.00', $this->importe($hoja, 'Caja corriente', 'balance_comprobacion', 'debe'));
        $this->assertSame('30.00', $this->importe($hoja, 'Gastos', 'ajustes', 'debe'));
        $this->assertSame('130.00', $hoja['totales']['balance_ajustado']['debe']);
        $this->assertSame('130.00', $hoja['totales']['balance_ajustado']['haber']);
        $this->assertSame('100.00', $this->importe($hoja, 'Ingresos', 'estado_resultados', 'haber'));
        $this->assertSame('30.00', $this->importe($hoja, 'Gastos', 'estado_resultados', 'debe'));
        $this->assertSame('100.00', $this->importe($hoja, 'Caja corriente', 'balance_general', 'debe'));
        $this->assertSame('30.00', $this->importe($hoja, 'Proveedores', 'balance_general', 'haber'));
        $this->assertSame('70.00', $this->importe($hoja, 'RESULTADOS DEL EJERCICIO', 'balance_general', 'haber'));
        $this->assertTrue($hoja['balance_comprobacion_cuadra']);
        $this->assertTrue($hoja['ajustes_cuadran']);
        $this->assertTrue($hoja['balance_ajustado_cuadra']);
        $this->assertSame('70.00', $hoja['utilidad_neta']);
        $this->assertTrue($hoja['balance_general_cuadra']);
    }

    public function test_worksheet_applies_period_filters(): void
    {
        [$usuario, $ids] = $this->catalogo();
        $anterior = $this->partida($usuario, 1, '2026-01-01', 'Anterior');
        $anterior->detalles()->create([
            'id_cuenta' => $ids['caja'], 'id_usuario' => $usuario->id_usuario,
            'debe' => '40.00', 'haber' => '0.00',
        ]);
        $incluida = $this->partida($usuario, 2, '2026-02-01', 'Incluida');
        $incluida->detalles()->create([
            'id_cuenta' => $ids['caja'], 'id_usuario' => $usuario->id_usuario,
            'debe' => '25.00', 'haber' => '0.00',
        ]);

        $hoja = app(HojaTrabajoContableService::class)->generar('2026-02-01', '2026-02-28');

        $this->assertSame('25.00', $this->importe($hoja, 'Caja corriente', 'balance_comprobacion', 'debe'));
    }

    public function test_only_contador_can_consult_the_worksheet(): void
    {
        $contador = User::factory()->create(['rol' => 'Contador']);
        $auditor = User::factory()->create(['rol' => 'Auditor']);

        $this->actingAs($contador)->get(route('hoja-trabajo.index'))->assertOk();
        $this->actingAs($auditor)->get(route('hoja-trabajo.index'))->assertForbidden();
    }

    /** @param array<string, mixed> $hoja */
    private function importe(array $hoja, string $cuenta, string $seccion, string $lado): ?string
    {
        foreach ($hoja['filas'] as $fila) {
            if ($fila['cuenta'] === $cuenta) {
                return $fila[$seccion][$lado];
            }
        }

        return null;
    }

    /** @return array{0: User, 1: array{caja: int, proveedor: int, ingreso: int, gasto: int}} */
    private function catalogo(): array
    {
        $usuario = User::factory()->create(['rol' => 'Contador']);
        DB::table('tblcuentascontables')->insert([
            ['id_cuenta' => 7, 'codigo_cuenta' => '10', 'nombre_cuenta' => 'Activo corriente', 'tipo_cuenta' => 'Activo', 'nivel' => 2, 'id_cuenta_padre' => null, 'estado' => true],
            ['id_cuenta' => 8, 'codigo_cuenta' => '12', 'nombre_cuenta' => 'Activo no corriente', 'tipo_cuenta' => 'Activo', 'nivel' => 2, 'id_cuenta_padre' => null, 'estado' => true],
            ['id_cuenta' => 9, 'codigo_cuenta' => '20', 'nombre_cuenta' => 'Pasivo corriente', 'tipo_cuenta' => 'Pasivo', 'nivel' => 2, 'id_cuenta_padre' => null, 'estado' => true],
            ['id_cuenta' => 10, 'codigo_cuenta' => '30', 'nombre_cuenta' => 'Patrimonio', 'tipo_cuenta' => 'Patrimonio', 'nivel' => 2, 'id_cuenta_padre' => null, 'estado' => true],
            ['id_cuenta' => 11, 'codigo_cuenta' => '1101', 'nombre_cuenta' => 'Caja corriente', 'tipo_cuenta' => 'Activo', 'nivel' => 3, 'id_cuenta_padre' => 7, 'estado' => true],
            ['id_cuenta' => 12, 'codigo_cuenta' => '2101', 'nombre_cuenta' => 'Proveedores', 'tipo_cuenta' => 'Pasivo', 'nivel' => 3, 'id_cuenta_padre' => 9, 'estado' => true],
            ['id_cuenta' => 13, 'codigo_cuenta' => '4101', 'nombre_cuenta' => 'Ingresos', 'tipo_cuenta' => 'Ingreso', 'nivel' => 3, 'id_cuenta_padre' => null, 'estado' => true],
            ['id_cuenta' => 14, 'codigo_cuenta' => '5101', 'nombre_cuenta' => 'Gastos', 'tipo_cuenta' => 'Gasto', 'nivel' => 3, 'id_cuenta_padre' => null, 'estado' => true],
            ['id_cuenta' => 15, 'codigo_cuenta' => '3201', 'nombre_cuenta' => 'RESULTADOS DEL EJERCICIO', 'tipo_cuenta' => 'Patrimonio', 'nivel' => 3, 'id_cuenta_padre' => 10, 'estado' => true],
        ]);

        return [$usuario, ['caja' => 11, 'proveedor' => 12, 'ingreso' => 13, 'gasto' => 14]];
    }

    private function partida(User $usuario, int $numero, string $fecha, string $concepto): Partida
    {
        return Partida::create([
            'id_usuario' => $usuario->id_usuario,
            'numero_partida' => $numero,
            'fecha' => $fecha,
            'concepto' => $concepto,
        ]);
    }
}
