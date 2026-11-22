<?php

namespace Tests\Feature;

use App\Models\CuentaContable;
use App\Models\DocumentoFuente;
use App\Models\Partida;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

    public function test_partida_can_use_an_existing_document_for_one_detail(): void
    {
        $usuario = User::factory()->create(['rol' => 'Contador']);
        [$debe, $haber] = $this->cuentasActivas();
        $documento = DocumentoFuente::create([
            'nombre_documento' => 'factura-existente.pdf',
            'tipo_documento' => 'PDF',
            'archivo' => '%PDF-existente',
        ]);

        $response = $this->actingAs($usuario)->post(route('partidas.store'), [
            'fecha' => '2026-01-15',
            'concepto' => 'Partida con documento existente',
            'detalles' => [
                [
                    'id_cuenta' => $debe->id_cuenta,
                    'modo_documento' => 'existente',
                    'id_documento' => $documento->id_documento,
                    'debe' => '100.00', 'haber' => '0.00',
                ],
                [
                    'id_cuenta' => $haber->id_cuenta,
                    'modo_documento' => 'ninguno',
                    'debe' => '0.00', 'haber' => '100.00',
                ],
            ],
        ]);

        $partida = Partida::query()->firstOrFail();
        $response->assertRedirect(route('partidas.show', $partida));
        $this->assertDatabaseHas('tbldetalles_partida', [
            'id_partida' => $partida->id_partida,
            'id_cuenta' => $debe->id_cuenta,
            'id_documento' => $documento->id_documento,
        ]);
        $this->assertDatabaseHas('tbldetalles_partida', [
            'id_partida' => $partida->id_partida,
            'id_cuenta' => $haber->id_cuenta,
            'id_documento' => null,
        ]);
    }

    public function test_partida_can_create_different_documents_for_different_details(): void
    {
        $usuario = User::factory()->create(['rol' => 'Contador']);
        [$debe, $haber] = $this->cuentasActivas();

        $this->actingAs($usuario)->post(route('partidas.store'), [
            'fecha' => '2026-01-15',
            'concepto' => 'Partida con documentos nuevos',
            'detalles' => [
                [
                    'id_cuenta' => $debe->id_cuenta,
                    'modo_documento' => 'nuevo',
                    'documento_nuevo' => [
                        'nombre_documento' => 'factura-1.pdf',
                        'tipo_documento' => 'PDF',
                        'fecha_creacion' => '2026-01-15T10:00',
                        'descripcion' => 'Factura del detalle Debe',
                        'archivo' => UploadedFile::fake()->createWithContent('factura-1.pdf', '%PDF-1'),
                    ],
                    'debe' => '100.00', 'haber' => '0.00',
                ],
                [
                    'id_cuenta' => $haber->id_cuenta,
                    'modo_documento' => 'nuevo',
                    'documento_nuevo' => [
                        'nombre_documento' => 'recibo-2.pdf',
                        'tipo_documento' => 'PDF',
                        'fecha_creacion' => '2026-01-15T10:05',
                        'descripcion' => 'Recibo del detalle Haber',
                        'archivo' => UploadedFile::fake()->createWithContent('recibo-2.pdf', '%PDF-2'),
                    ],
                    'debe' => '0.00', 'haber' => '100.00',
                ],
            ],
        ])->assertRedirect();

        $partida = Partida::query()->firstOrFail();
        $detalles = $partida->detalles()->orderBy('id_detalle')->get();
        $this->assertDatabaseCount('tbldocumentos_fuente', 2);
        $this->assertNotNull($detalles[0]->id_documento);
        $this->assertNotNull($detalles[1]->id_documento);
        $this->assertNotSame($detalles[0]->id_documento, $detalles[1]->id_documento);
        $this->assertDatabaseHas('tbldocumentos_fuente', ['nombre_documento' => 'factura-1.pdf']);
        $this->assertDatabaseHas('tbldocumentos_fuente', ['nombre_documento' => 'recibo-2.pdf']);
    }

    public function test_invalid_partida_does_not_leave_new_documents_orphaned(): void
    {
        $usuario = User::factory()->create(['rol' => 'Contador']);
        [$debe, $haber] = $this->cuentasActivas();
        $inexistente = 999999;

        $this->actingAs($usuario)->post(route('partidas.store'), [
            'fecha' => '2026-01-15',
            'concepto' => 'Partida inválida',
            'detalles' => [
                [
                    'id_cuenta' => $debe->id_cuenta,
                    'modo_documento' => 'nuevo',
                    'documento_nuevo' => [
                        'nombre_documento' => 'no-debe-guardarse.pdf',
                        'tipo_documento' => 'PDF',
                        'archivo' => UploadedFile::fake()->createWithContent('no-debe-guardarse.pdf', '%PDF'),
                    ],
                    'debe' => '100.00', 'haber' => '0.00',
                ],
                [
                    'id_cuenta' => $inexistente,
                    'modo_documento' => 'ninguno',
                    'debe' => '0.00', 'haber' => '100.00',
                ],
            ],
        ])->assertSessionHasErrors('detalles.1.id_cuenta');

        $this->assertDatabaseCount('tblpartidas', 0);
        $this->assertDatabaseCount('tbldetalles_partida', 0);
        $this->assertDatabaseCount('tbldocumentos_fuente', 0);
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

    public function test_partida_form_lists_only_active_auxiliary_accounts(): void
    {
        $usuario = User::factory()->create(['rol' => 'Contador']);
        CuentaContable::create([
            'codigo_cuenta' => '1101', 'nombre_cuenta' => 'Cuenta agrupadora',
            'tipo_cuenta' => 'Activo', 'nivel' => 3, 'estado' => true,
        ]);
        $auxiliar = CuentaContable::create([
            'codigo_cuenta' => '110101', 'nombre_cuenta' => 'Caja General',
            'tipo_cuenta' => 'Activo', 'nivel' => 4, 'estado' => true,
        ]);
        CuentaContable::create([
            'codigo_cuenta' => '110102', 'nombre_cuenta' => 'Caja Inactiva',
            'tipo_cuenta' => 'Activo', 'nivel' => 4, 'estado' => false,
        ]);

        $this->actingAs($usuario)
            ->get(route('partidas.create'))
            ->assertOk()
            ->assertSee($auxiliar->codigo_cuenta.' — '.$auxiliar->nombre_cuenta)
            ->assertDontSee('Cuenta agrupadora')
            ->assertDontSee('Caja Inactiva');
    }

    public function test_grouping_account_cannot_be_registered_as_a_partida_detail(): void
    {
        $usuario = User::factory()->create(['rol' => 'Contador']);
        $agrupadora = CuentaContable::create([
            'codigo_cuenta' => '1101', 'nombre_cuenta' => 'Cuenta agrupadora',
            'tipo_cuenta' => 'Activo', 'nivel' => 3, 'estado' => true,
        ]);
        $auxiliar = CuentaContable::create([
            'codigo_cuenta' => '210101', 'nombre_cuenta' => 'Proveedor auxiliar',
            'tipo_cuenta' => 'Pasivo', 'nivel' => 4, 'estado' => true,
        ]);

        $this->actingAs($usuario)
            ->post(route('partidas.store'), [
                'fecha' => '2026-01-15',
                'concepto' => 'Intento con cuenta agrupadora',
                'detalles' => [
                    ['id_cuenta' => $agrupadora->id_cuenta, 'debe' => '100.00', 'haber' => '0.00'],
                    ['id_cuenta' => $auxiliar->id_cuenta, 'debe' => '0.00', 'haber' => '100.00'],
                ],
            ])
            ->assertSessionHasErrors('detalles.0.id_cuenta');

        $this->assertDatabaseCount('tblpartidas', 0);
        $this->assertDatabaseCount('tbldetalles_partida', 0);
    }

    public function test_inactive_account_failure_leaves_no_partial_records(): void
    {
        $usuario = User::factory()->create(['rol' => 'Contador']);
        [$debe, $haber] = $this->cuentasActivas();
        $inactiva = CuentaContable::create([
            'codigo_cuenta' => '9999', 'nombre_cuenta' => 'Cuenta inactiva',
            'tipo_cuenta' => 'Activo', 'nivel' => 4, 'estado' => false,
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
                'tipo_cuenta' => 'Activo', 'nivel' => 4, 'estado' => true,
            ]),
            CuentaContable::create([
                'codigo_cuenta' => '2101', 'nombre_cuenta' => 'Proveedores',
                'tipo_cuenta' => 'Pasivo', 'nivel' => 4, 'estado' => true,
            ]),
        ];
    }
}
