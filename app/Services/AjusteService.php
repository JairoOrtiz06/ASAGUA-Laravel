<?php

namespace App\Services;

use App\Models\Partida;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class AjusteService
{
    public const TIPOS_AJUSTE = [
        'Depreciaciones',
        'Gastos acumulados',
        'Gastos pagados por anticipado',
        'Ingresos acumulados',
        'Ingresos diferidos',
        'Ajustes de inventario',
        'Otros ajustes justificados',
    ];

    public function __construct(private readonly PartidaService $partidaService)
    {
    }

    public function registrar(User $usuario, array $data): Partida
    {
        if ($usuario->rol !== 'Contador') {
            throw ValidationException::withMessages([
                'usuario' => 'Solo el usuario Contador puede registrar ajustes.',
            ]);
        }

        $periodoInicio = (string) $data['periodo_inicio'];
        $periodoFin = (string) $data['periodo_fin'];
        $fecha = (string) $data['fecha'];
        $tipo = trim((string) ($data['tipo'] ?? ''));
        $concepto = trim((string) ($data['concepto'] ?? ''));

        if (! in_array($tipo, self::TIPOS_AJUSTE, true)) {
            throw ValidationException::withMessages(['tipo' => 'Seleccione un tipo de ajuste válido.']);
        }

        if ($concepto === '') {
            throw ValidationException::withMessages(['concepto' => 'Escriba una descripción para el ajuste.']);
        }

        if ($periodoInicio > $periodoFin) {
            throw ValidationException::withMessages([
                'periodo_fin' => 'El inicio del período no puede ser posterior al fin.',
            ]);
        }

        if ($fecha < $periodoInicio || $fecha > $periodoFin) {
            throw ValidationException::withMessages([
                'fecha' => 'La fecha del ajuste debe estar dentro del período indicado.',
            ]);
        }

        $detalles = array_map(function (array $detalle) use ($concepto, $fecha): array {
            return [
                'id_cuenta' => $detalle['id_cuenta'],
                'descripcion' => trim((string) ($detalle['descripcion'] ?? '')) ?: $concepto,
                'debe' => $detalle['debe'],
                'haber' => $detalle['haber'],
                'fecha_creacion' => $fecha,
            ];
        }, $data['detalles'] ?? []);

        return $this->partidaService->registrar($usuario, [
            'fecha' => $fecha,
            'concepto' => '[AJUSTE:'.$tipo.'] '.$concepto,
            'detalles' => $detalles,
        ]);
    }
}
