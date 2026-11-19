<?php

namespace Tests\Feature;

use App\Models\Partida;
use App\Models\User;
use App\Services\LibroContableService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReportePdfTest extends TestCase
{
    use RefreshDatabase;

    private int $siguienteNumero = 1;

    public function test_contador_can_view_the_daily_book_as_pdf(): void
    {
        [$usuario, $ids] = $this->catalogo();
        $partida = Partida::create([
            'id_usuario' => $usuario->id_usuario,
            'numero_partida' => 1,
            'fecha' => '2026-01-10',
            'concepto' => 'Compra ordinaria',
        ]);
        $partida->detalles()->create([
            'id_cuenta' => $ids['caja'],
            'id_usuario' => $usuario->id_usuario,
            'debe' => '100.00',
            'haber' => '0.00',
        ]);

        $response = $this->actingAs($usuario)->get(route('reportes.libro-diario.ver'));

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'inline; filename="libro-diario.pdf"');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_auditor_can_download_the_daily_book_with_date_filters(): void
    {
        [$usuario, $ids] = $this->catalogo('Auditor');
        $partida = Partida::create([
            'id_usuario' => $usuario->id_usuario,
            'numero_partida' => 1,
            'fecha' => '2026-02-10',
            'concepto' => '[AJUSTE:Depreciaciones] Ajuste',
        ]);
        $partida->detalles()->create([
            'id_cuenta' => $ids['caja'],
            'id_usuario' => $usuario->id_usuario,
            'debe' => '0.00',
            'haber' => '25.00',
        ]);

        $response = $this->actingAs($usuario)->get(route('reportes.libro-diario.descargar', [
            'fecha_inicio' => '2026-02-01',
            'fecha_fin' => '2026-02-28',
        ]));

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'attachment; filename="libro-diario.pdf"');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_admin_cannot_access_daily_book_pdf(): void
    {
        $this->actingAs(User::factory()->create(['rol' => 'Admin']))
            ->get(route('reportes.libro-diario.ver'))
            ->assertForbidden();
    }

    public function test_contador_can_view_major_pdf_for_selected_account(): void
    {
        [$usuario, $ids] = $this->catalogo();
        $this->registrarMovimiento($usuario, $ids['caja'], '2026-01-10', 'Compra', '50.00', '0.00');

        $mayor = app(LibroContableService::class)->mayor($ids['caja']);
        $this->assertSame('50.00', $mayor['total_debe']);

        $this->actingAs($usuario)->get(route('reportes.libro-mayor.ver', [
            'cuenta_id' => $ids['caja'],
        ]))->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'inline; filename="libro-mayor.pdf"');
    }

    public function test_auditor_can_download_trial_balance_pdf_with_date_filter(): void
    {
        [$usuario, $ids] = $this->catalogo('Auditor');
        $this->registrarMovimiento($usuario, $ids['caja'], '2026-01-10', 'Anterior', '40.00', '0.00');
        $this->registrarMovimiento($usuario, $ids['caja'], '2026-02-10', 'Incluida', '25.00', '0.00');

        $balance = app(LibroContableService::class)->balanceComprobacion('2026-02-01', '2026-02-28');
        $this->assertSame('25.00', $balance['total_debe']);

        $this->actingAs($usuario)->get(route('reportes.balance-comprobacion.descargar', [
            'fecha_inicio' => '2026-02-01',
            'fecha_fin' => '2026-02-28',
        ]))->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'attachment; filename="balance-comprobacion.pdf"');
    }

    public function test_contador_can_view_general_balance_pdf(): void
    {
        [$usuario, $ids] = $this->catalogo();
        $partida = $this->partida($usuario, 1, '2026-03-01', 'Ordinaria');
        $partida->detalles()->createMany([
            ['id_cuenta' => $ids['caja'], 'id_usuario' => $usuario->id_usuario, 'debe' => '100.00', 'haber' => '0.00'],
            ['id_cuenta' => $ids['ingreso'], 'id_usuario' => $usuario->id_usuario, 'debe' => '0.00', 'haber' => '100.00'],
        ]);

        $balance = app(LibroContableService::class)->balanceGeneral();
        $this->assertSame('100.00', $balance['total_activos']);

        $this->actingAs($usuario)->get(route('reportes.balance-general.ver'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'inline; filename="balance-general.pdf"');
    }

    public function test_auditor_can_download_income_statement_pdf_with_adjustment(): void
    {
        [$usuario, $ids] = $this->catalogo('Auditor');
        $partida = $this->partida($usuario, 1, '2026-04-01', '[AJUSTE:Depreciaciones] Ajuste');
        $partida->detalles()->createMany([
            ['id_cuenta' => $ids['ingreso'], 'id_usuario' => $usuario->id_usuario, 'debe' => '0.00', 'haber' => '80.00'],
            ['id_cuenta' => $ids['gasto'], 'id_usuario' => $usuario->id_usuario, 'debe' => '30.00', 'haber' => '0.00'],
        ]);

        $estado = app(LibroContableService::class)->estadoResultados();
        $this->assertSame('50.00', $estado['utilidad_neta']);

        $this->actingAs($usuario)->get(route('reportes.estado-resultados.descargar', [
            'fecha_inicio' => '2026-04-01',
            'fecha_fin' => '2026-04-30',
        ]))->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'attachment; filename="estado-resultados.pdf"');
    }

    /** @return array{0: User, 1: array{caja: int, ingreso: int, gasto: int}} */
    private function catalogo(string $rol = 'Contador'): array
    {
        $usuario = User::factory()->create(['rol' => $rol]);
        DB::table('tblcuentascontables')->insert([
            ['id_cuenta' => 7, 'codigo_cuenta' => '10', 'nombre_cuenta' => 'Activo corriente', 'tipo_cuenta' => 'Activo', 'nivel' => 2, 'id_cuenta_padre' => null, 'estado' => true],
            ['id_cuenta' => 8, 'codigo_cuenta' => '12', 'nombre_cuenta' => 'Activo no corriente', 'tipo_cuenta' => 'Activo', 'nivel' => 2, 'id_cuenta_padre' => null, 'estado' => true],
            ['id_cuenta' => 9, 'codigo_cuenta' => '20', 'nombre_cuenta' => 'Pasivo corriente', 'tipo_cuenta' => 'Pasivo', 'nivel' => 2, 'id_cuenta_padre' => null, 'estado' => true],
            ['id_cuenta' => 10, 'codigo_cuenta' => '30', 'nombre_cuenta' => 'Patrimonio', 'tipo_cuenta' => 'Patrimonio', 'nivel' => 2, 'id_cuenta_padre' => null, 'estado' => true],
            ['id_cuenta' => 11, 'codigo_cuenta' => '1101', 'nombre_cuenta' => 'Caja', 'tipo_cuenta' => 'Activo', 'nivel' => 3, 'id_cuenta_padre' => 7, 'estado' => true],
            ['id_cuenta' => 12, 'codigo_cuenta' => '1201', 'nombre_cuenta' => 'Terreno', 'tipo_cuenta' => 'Activo', 'nivel' => 3, 'id_cuenta_padre' => 8, 'estado' => true],
            ['id_cuenta' => 13, 'codigo_cuenta' => '2101', 'nombre_cuenta' => 'Proveedores', 'tipo_cuenta' => 'Pasivo', 'nivel' => 3, 'id_cuenta_padre' => 9, 'estado' => true],
            ['id_cuenta' => 14, 'codigo_cuenta' => '3101', 'nombre_cuenta' => 'Capital', 'tipo_cuenta' => 'Patrimonio', 'nivel' => 3, 'id_cuenta_padre' => 10, 'estado' => true],
            ['id_cuenta' => 15, 'codigo_cuenta' => '3201', 'nombre_cuenta' => 'RESULTADOS DEL EJERCICIO', 'tipo_cuenta' => 'Patrimonio', 'nivel' => 3, 'id_cuenta_padre' => 10, 'estado' => true],
            ['id_cuenta' => 16, 'codigo_cuenta' => '4101', 'nombre_cuenta' => 'Ingresos', 'tipo_cuenta' => 'Ingreso', 'nivel' => 3, 'id_cuenta_padre' => null, 'estado' => true],
            ['id_cuenta' => 17, 'codigo_cuenta' => '5101', 'nombre_cuenta' => 'Gastos', 'tipo_cuenta' => 'Gasto', 'nivel' => 3, 'id_cuenta_padre' => null, 'estado' => true],
        ]);

        return [$usuario, ['caja' => 11, 'ingreso' => 16, 'gasto' => 17]];
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

    private function registrarMovimiento(User $usuario, int $cuentaId, string $fecha, string $concepto, string $debe, string $haber): void
    {
        $partida = $this->partida($usuario, $this->siguienteNumero++, $fecha, $concepto);
        $partida->detalles()->create([
            'id_cuenta' => $cuentaId,
            'id_usuario' => $usuario->id_usuario,
            'debe' => $debe,
            'haber' => $haber,
        ]);
    }
}
