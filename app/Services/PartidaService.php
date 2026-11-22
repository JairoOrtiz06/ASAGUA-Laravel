<?php

namespace App\Services;

use App\Models\CuentaContable;
use App\Models\DetallePartida;
use App\Models\DocumentoFuente;
use App\Models\Partida;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PartidaService
{
    public function __construct(
        private readonly DocumentoFuenteService $documentoFuenteService,
    ) {
    }

    /**
     * Persists a complete journal entry as one PostgreSQL transaction.
     * Monetary values are normalized to integer cents; no float is used.
     */
    public function registrar(User $usuario, array $data): Partida
    {
        return DB::transaction(function () use ($usuario, $data): Partida {
            // Account validity is checked before reading or normalizing any
            // monetary value, so an invalid account always reports the account
            // problem first and cannot be masked by an invalid amount later on.
            $detallesEntrada = $this->validarCuentasActivas($data['detalles'] ?? []);
            $detallesEntrada = $this->validarDocumentos($detallesEntrada);
            $detalles = $this->normalizarDetalles($detallesEntrada);
            $this->validarEquilibrio($detalles);

            if (DB::connection()->getDriverName() === 'pgsql') {
                // The Python flow serializes adjustment numbering. Apply the
                // same protection to ordinary entries in the web application.
                DB::statement('LOCK TABLE tblpartidas IN SHARE ROW EXCLUSIVE MODE');
            }

            $numero = ((int) (DB::table('tblpartidas')->max('numero_partida') ?? 0)) + 1;
            $partida = Partida::create([
                'id_usuario' => $usuario->getKey(),
                'numero_partida' => $numero,
                'fecha' => $data['fecha'],
                'concepto' => trim((string) ($data['concepto'] ?? '')) ?: 'Asiento contable',
            ]);

            foreach ($detalles as $detalle) {
                $idDocumento = $detalle['id_documento'] ?? null;
                if (($detalle['modo_documento'] ?? 'ninguno') === 'nuevo') {
                    $documento = $this->documentoFuenteService->crear(
                        $detalle['documento_nuevo'],
                        $detalle['documento_nuevo']['archivo'] ?? null,
                    );
                    $idDocumento = $documento->getKey();
                }

                DetallePartida::create([
                    'id_partida' => $partida->getKey(),
                    'id_cuenta' => $detalle['id_cuenta'],
                    'id_usuario' => $usuario->getKey(),
                    'id_documento' => $idDocumento,
                    'descripcion' => $detalle['descripcion'],
                    'debe' => $detalle['debe'],
                    'haber' => $detalle['haber'],
                    // Ordinary entries omit this field; adjustments provide
                    // the adjustment date, as in the Python flow.
                    'fecha_creacion' => $detalle['fecha_creacion'] ?? null,
                ]);
            }

            return $partida->load(['usuario', 'detalles.cuenta']);
        });
    }

    private function normalizarDetalles(array $detalles): array
    {
        if ($detalles === []) {
            throw ValidationException::withMessages([
                'detalles' => 'Agregue al menos un detalle a la partida.',
            ]);
        }

        $normalizados = [];
        foreach ($detalles as $index => $detalle) {
            $debe = $this->centavos($detalle['debe'] ?? null, "detalles.{$index}.debe");
            $haber = $this->centavos($detalle['haber'] ?? null, "detalles.{$index}.haber");

            if ($debe === 0 && $haber === 0) {
                throw ValidationException::withMessages([
                    "detalles.{$index}" => 'Cada detalle debe tener un importe en Debe o Haber.',
                ]);
            }

            if ($debe > 0 && $haber > 0) {
                throw ValidationException::withMessages([
                    "detalles.{$index}" => 'Un detalle no puede tener Debe y Haber a la vez.',
                ]);
            }

            $normalizados[] = [
                'id_cuenta' => (int) ($detalle['id_cuenta'] ?? 0),
                'modo_documento' => $detalle['modo_documento'] ?? 'ninguno',
                'id_documento' => $detalle['id_documento'] ?? null,
                'documento_nuevo' => $detalle['documento_nuevo'] ?? null,
                'descripcion' => trim((string) ($detalle['descripcion'] ?? '')),
                'debe_centavos' => $debe,
                'haber_centavos' => $haber,
                'debe' => $this->formatear($debe),
                'haber' => $this->formatear($haber),
                'fecha_creacion' => $detalle['fecha_creacion'] ?? null,
            ];
        }

        return $normalizados;
    }

    private function validarDocumentos(array $detalles): array
    {
        $ids = array_values(array_unique(array_filter(array_map(
            static fn (array $detalle): int => (int) ($detalle['id_documento'] ?? 0),
            $detalles,
        ))));
        $documentos = DocumentoFuente::query()
            ->whereIn('id_documento', $ids)
            ->get()
            ->keyBy('id_documento');

        foreach ($detalles as $index => $detalle) {
            $modo = (string) ($detalle['modo_documento'] ?? 'ninguno');
            $idDocumento = $detalle['id_documento'] ?? null;

            if ($modo === 'existente') {
                if (! $idDocumento || ! $documentos->has((int) $idDocumento)) {
                    throw ValidationException::withMessages([
                        "detalles.{$index}.id_documento" => 'El documento fuente seleccionado no existe.',
                    ]);
                }
            }

            if ($modo !== 'existente' && $idDocumento) {
                throw ValidationException::withMessages([
                    "detalles.{$index}.id_documento" => 'El documento seleccionado no coincide con el modo indicado.',
                ]);
            }

            if ($modo === 'nuevo') {
                $nuevo = (array) ($detalle['documento_nuevo'] ?? []);
                if (trim((string) ($nuevo['nombre_documento'] ?? '')) === ''
                    || trim((string) ($nuevo['tipo_documento'] ?? '')) === '') {
                    throw ValidationException::withMessages([
                        "detalles.{$index}.documento_nuevo" => 'El nombre y tipo del documento son obligatorios.',
                    ]);
                }
                $detalles[$index]['documento_nuevo'] = $nuevo;
            } elseif (collect((array) ($detalle['documento_nuevo'] ?? []))->contains(
                static fn (mixed $valor): bool => $valor !== null && $valor !== ''
            )) {
                throw ValidationException::withMessages([
                    "detalles.{$index}.documento_nuevo" => 'Los datos de documento nuevo requieren seleccionar ese modo.',
                ]);
            }

            $detalles[$index]['id_documento'] = $idDocumento ? (int) $idDocumento : null;
        }

        return $detalles;
    }

    private function validarCuentasActivas(array $detalles): array
    {
        if ($detalles === []) {
            return $detalles;
        }

        $ids = array_values(array_unique(array_map(
            static fn (array $detalle): int => (int) ($detalle['id_cuenta'] ?? 0),
            $detalles,
        )));
        $cuentas = CuentaContable::query()
            ->whereIn('id_cuenta', $ids)
            ->where('estado', true)
            ->get()
            ->keyBy('id_cuenta');

        foreach ($detalles as $index => $detalle) {
            $idCuenta = (int) ($detalle['id_cuenta'] ?? 0);
            $cuenta = $cuentas->get($idCuenta);
            if (! $cuenta) {
                throw ValidationException::withMessages([
                    "detalles.{$index}.id_cuenta" => 'La cuenta no existe o está deshabilitada.',
                ]);
            }

            if ((int) $cuenta->nivel !== 4) {
                throw ValidationException::withMessages([
                    "detalles.{$index}.id_cuenta" => 'La cuenta debe ser de nivel 4 (auxiliar).',
                ]);
            }

            if (trim((string) ($detalle['descripcion'] ?? '')) === '') {
                $detalles[$index]['descripcion'] = $cuenta->nombre_cuenta;
            }
            $detalles[$index]['id_cuenta'] = $idCuenta;
        }

        return $detalles;
    }

    private function validarEquilibrio(array $detalles): void
    {
        $debe = array_sum(array_column($detalles, 'debe_centavos'));
        $haber = array_sum(array_column($detalles, 'haber_centavos'));

        if ($debe === 0 || $haber === 0 || $debe !== $haber) {
            throw ValidationException::withMessages([
                'detalles' => 'El total del Debe debe ser igual al total del Haber y ambos deben ser mayores que cero.',
            ]);
        }
    }

    private function centavos(mixed $value, string $field): int
    {
        $value = trim((string) $value);
        if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $value)) {
            throw ValidationException::withMessages([
                $field => 'El importe debe ser un número no negativo con hasta dos decimales.',
            ]);
        }

        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $fraction = str_pad($fraction, 2, '0');
        return ((int) $whole * 100) + (int) $fraction;
    }

    private function formatear(int $centavos): string
    {
        return intdiv($centavos, 100).'.'.str_pad((string) ($centavos % 100), 2, '0', STR_PAD_LEFT);
    }
}
