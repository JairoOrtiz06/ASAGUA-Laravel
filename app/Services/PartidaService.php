<?php

namespace App\Services;

use App\Models\CuentaContable;
use App\Models\DetallePartida;
use App\Models\Partida;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PartidaService
{
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
                DetallePartida::create([
                    'id_partida' => $partida->getKey(),
                    'id_cuenta' => $detalle['id_cuenta'],
                    'id_usuario' => $usuario->getKey(),
                    'id_documento' => null,
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
