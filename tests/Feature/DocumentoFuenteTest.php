<?php

namespace Tests\Feature;

use App\Models\CuentaContable;
use App\Models\DetallePartida;
use App\Models\DocumentoFuente;
use App\Models\Partida;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class DocumentoFuenteTest extends TestCase
{
    use RefreshDatabase;

    public function test_contador_can_register_and_download_a_document(): void
    {
        $usuario = User::factory()->create(['rol' => 'Contador']);
        $archivo = UploadedFile::fake()->createWithContent('factura.pdf', '%PDF-asagua');

        $response = $this->actingAs($usuario)->post(route('documentos.store'), [
            'nombre_documento' => 'factura.pdf',
            'descripcion' => 'Factura de prueba',
            'tipo_documento' => 'PDF',
            'fecha_creacion' => '2026-06-30T10:30',
            'archivo' => $archivo,
        ]);

        $documento = DocumentoFuente::query()->firstOrFail();
        $response->assertRedirect(route('documentos.show', $documento));
        $this->assertDatabaseHas('tbldocumentos_fuente', [
            'id_documento' => $documento->id_documento,
            'nombre_documento' => 'factura.pdf',
            'tipo_documento' => 'PDF',
        ]);

        $this->actingAs($usuario)->get(route('documentos.index'))
            ->assertOk()->assertSee('factura.pdf')->assertSee('Factura de prueba');
        $this->actingAs($usuario)->get(route('documentos.download', $documento))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf')
            ->assertSee('%PDF-asagua');
    }

    public function test_document_can_be_associated_with_a_detail(): void
    {
        [$partida, $detalle] = $this->detalleSinDocumento();
        $usuario = User::factory()->create(['rol' => 'Contador']);

        $response = $this->actingAs($usuario)->post(route('documentos.store'), [
            'nombre_documento' => 'recibo.pdf',
            'tipo_documento' => 'PDF',
            'id_detalle' => $detalle->id_detalle,
            'archivo' => UploadedFile::fake()->createWithContent('recibo.pdf', '%PDF-recibo'),
        ]);

        $documento = DocumentoFuente::query()->firstOrFail();
        $response->assertRedirect(route('documentos.show', $documento));
        $this->assertDatabaseHas('tbldetalles_partida', [
            'id_detalle' => $detalle->id_detalle,
            'id_partida' => $partida->id_partida,
            'id_documento' => $documento->id_documento,
        ]);
        $this->assertCount(1, $documento->fresh()->detalles);
    }

    public function test_auditor_can_consult_and_download_but_cannot_register(): void
    {
        $documento = DocumentoFuente::create([
            'nombre_documento' => 'informe.pdf', 'descripcion' => 'Informe',
            'tipo_documento' => 'PDF', 'archivo' => '%PDF-informe',
        ]);
        $auditor = User::factory()->create(['rol' => 'Auditor']);

        $this->actingAs($auditor)->get(route('documentos.index'))->assertOk();
        $this->actingAs($auditor)->get(route('documentos.download', $documento))
            ->assertOk()->assertSee('%PDF-informe');
        $this->actingAs($auditor)->get(route('documentos.create'))->assertForbidden();
    }

    public function test_second_document_cannot_overwrite_an_existing_detail_association(): void
    {
        [, $detalle] = $this->detalleSinDocumento();
        $usuario = User::factory()->create(['rol' => 'Contador']);
        $existente = DocumentoFuente::create([
            'nombre_documento' => 'original.pdf', 'tipo_documento' => 'PDF',
            'archivo' => '%PDF-original',
        ]);
        $detalle->update(['id_documento' => $existente->id_documento]);

        $this->actingAs($usuario)->post(route('documentos.store'), [
            'nombre_documento' => 'nuevo.pdf', 'tipo_documento' => 'PDF',
            'id_detalle' => $detalle->id_detalle,
            'archivo' => UploadedFile::fake()->createWithContent('nuevo.pdf', '%PDF-nuevo'),
        ])->assertSessionHasErrors('id_detalle');

        $this->assertDatabaseCount('tbldocumentos_fuente', 1);
        $this->assertDatabaseHas('tbldetalles_partida', [
            'id_detalle' => $detalle->id_detalle,
            'id_documento' => $existente->id_documento,
        ]);
    }

    public function test_invalid_association_does_not_leave_a_document_record(): void
    {
        $usuario = User::factory()->create(['rol' => 'Contador']);

        $this->actingAs($usuario)->post(route('documentos.store'), [
            'nombre_documento' => 'huerfano.pdf', 'tipo_documento' => 'PDF',
            'id_detalle' => 999999,
            'archivo' => UploadedFile::fake()->createWithContent('huerfano.pdf', '%PDF-huerfano'),
        ])->assertSessionHasErrors('id_detalle');

        $this->assertDatabaseCount('tbldocumentos_fuente', 0);
    }

    /** @return array{0: Partida, 1: DetallePartida} */
    private function detalleSinDocumento(): array
    {
        $usuario = User::factory()->create(['rol' => 'Contador']);
        $cuenta = CuentaContable::create([
            'codigo_cuenta' => '1101', 'nombre_cuenta' => 'Caja',
            'tipo_cuenta' => 'Activo', 'nivel' => 3, 'estado' => true,
        ]);
        $partida = Partida::create([
            'id_usuario' => $usuario->id_usuario, 'numero_partida' => 1,
            'fecha' => '2026-06-30', 'concepto' => 'Partida documental',
        ]);
        $detalle = $partida->detalles()->create([
            'id_cuenta' => $cuenta->id_cuenta,
            'id_usuario' => $usuario->id_usuario,
            'descripcion' => 'Detalle documental',
            'debe' => '10.00', 'haber' => '0.00',
        ]);

        return [$partida, $detalle];
    }
}
