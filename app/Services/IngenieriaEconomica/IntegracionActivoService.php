<?php

declare(strict_types=1);

namespace App\Services\IngenieriaEconomica;

use App\Models\IngenieriaEconomica\Activo;
use App\Models\IngenieriaEconomica\Concepto;
use App\Models\IngenieriaEconomica\Escenario;
use App\Models\IngenieriaEconomica\Proyecto;
use Brick\Math\BigDecimal;
use DomainException;
use Illuminate\Support\Facades\DB;

class IntegracionActivoService
{
    public const OPERACIONES = [
        'adquisicion' => 'Adquisición', 'instalacion' => 'Instalación', 'inversion_adicional' => 'Inversión adicional',
        'venta' => 'Venta', 'rescate' => 'Rescate monetario', 'reemplazo' => 'Costo de reemplazo',
    ];

    public function __construct(private ProyeccionFlujosService $proyeccion) {}

    public function incorporar(Proyecto $proyecto, Activo $activo, array $d): Concepto
    {
        if (! in_array($d['confirmar'] ?? null, ['yes', 'on', 1, '1', true, 'true'], true)) {
            throw new DomainException('Confirma explícitamente el movimiento de efectivo.');
        }

        return DB::transaction(function () use ($proyecto, $activo, $d): Concepto {
            $proyecto = Proyecto::query()->lockForUpdate()->findOrFail($proyecto->id);
            $activo = $proyecto->activos()->lockForUpdate()->findOrFail($activo->id);
            $escenario = $proyecto->escenarios()->findOrFail($d['escenario_id']);
            $fuente = $activo->fuentes()->findOrFail($d['fuente_id']);
            if ($activo->moneda !== $d['moneda']) {
                throw new DomainException('La moneda de la evaluación y la del activo deben coincidir.');
            }
            if (! in_array($activo->clasificacion_contable, ['activo_tangible', 'activo_intangible'], true)) {
                throw new DomainException('Revisa la clasificación del activo antes de incorporarlo a los flujos.');
            }
            if (in_array($fuente->uso, ['depreciacion', 'amortizacion', 'fiscal'], true)) {
                throw new DomainException('Una referencia de depreciación, amortización o tratamiento fiscal no acredita un movimiento de efectivo.');
            }

            $operacion = $d['operacion'];
            $usos = match ($operacion) {
                'adquisicion' => ['adquisicion', 'otro'],
                'instalacion' => ['instalacion', 'otro'],
                'venta', 'rescate' => ['retiro', 'otro'],
                default => ['adquisicion', 'instalacion', 'otro'],
            };
            if (! in_array($fuente->uso, $usos, true)) {
                throw new DomainException('La evidencia seleccionada debe respaldar esta operación.');
            }

            $entrada = in_array($operacion, ['venta', 'rescate'], true);
            $tipo = match ($operacion) {
                'adquisicion' => 'inversion_inicial',
                'venta' => 'ingreso',
                'rescate' => 'valor_rescate',
                default => 'inversion_adicional',
            };
            $naturaleza = $entrada ? 'ingreso_real' : 'desembolso_real';
            $tipoCompatible = $entrada ? ['ingreso', 'valor_rescate'] : ['inversion_inicial', 'inversion_adicional'];
            $candidatos = $proyecto->conceptos()->where('alternativa_id', $activo->alternativa_id)
                ->where('moneda', $activo->moneda)->where('periodicidad', 'unico')
                ->where('naturaleza_economica', $naturaleza)->whereIn('tipo_concepto', $tipoCompatible)
                ->where('monto', (string) BigDecimal::of($d['monto']))->lockForUpdate()->get()
                ->filter(fn (Concepto $item) => $this->fecha($item, $proyecto, $escenario) === $d['fecha']);

            if (! empty($d['concepto_id'])) {
                $concepto = $candidatos->firstWhere('id', (int) $d['concepto_id']);
                if (! $concepto || ($concepto->activo_id !== null && ((int) $concepto->activo_id !== (int) $activo->id || $concepto->activo_operacion !== $operacion))) {
                    throw new DomainException('El concepto elegido debe coincidir en fecha, importe, moneda, ámbito y naturaleza, y estar libre o vinculado a esta operación.');
                }
            } else {
                if ($candidato = $candidatos->first(fn (Concepto $item) => (int) $item->activo_id === (int) $activo->id && $item->activo_operacion === $operacion)) {
                    return $candidato;
                }
                if ($candidatos->isNotEmpty()) {
                    throw new DomainException('Ya existe un concepto equivalente. Selecciona el concepto existente para vincularlo.');
                }
                $concepto = $proyecto->conceptos()->make([
                    'nombre' => mb_substr(self::OPERACIONES[$operacion].' · '.$activo->nombre, 0, 150),
                    'tipo_concepto' => $tipo, 'monto' => $d['monto'], 'moneda' => $activo->moneda,
                    'periodicidad' => 'unico', 'momento_pago' => 'inicio',
                    'fecha_real' => $d['observado'] ? $d['fecha'] : null,
                    'fecha_programada' => $d['observado'] ? null : $d['fecha'],
                    'clasificacion_decision' => $activo->alternativa_id === null ? 'comun' : 'diferencial',
                    'naturaleza_economica' => $naturaleza, 'estado_dato' => $d['estado_dato'],
                    'observaciones' => $d['justificacion'],
                ]);
                $concepto->alternativa_id = $activo->alternativa_id;
            }

            if ($operacion === 'adquisicion' && $activo->conceptos()->where('activo_operacion', 'adquisicion')
                ->when($concepto->exists, fn ($q) => $q->whereKeyNot($concepto->id))->exists()) {
                throw new DomainException('El activo ya tiene una adquisición vinculada.');
            }

            $concepto->activo()->associate($activo);
            $concepto->activo_operacion = $operacion;
            $concepto->save();
            $concepto->fuentes()->firstOrCreate([
                'tipo' => $fuente->tipo, 'referencia' => $fuente->referencia, 'fecha' => $fuente->fecha,
                'id_documento' => $fuente->id_documento, 'id_detalle' => $fuente->id_detalle,
            ], [
                'importe_observado' => $fuente->importe_observado, 'importe_utilizado' => $concepto->monto,
                'justificacion' => mb_substr($d['justificacion']."\n".$fuente->justificacion, 0, 5000),
            ]);

            return $concepto;
        }, attempts: 3);
    }

    private function fecha(Concepto $concepto, Proyecto $proyecto, Escenario $escenario): string
    {
        $base = ($escenario->fecha_base ?? $proyecto->fecha_base)->toImmutable();

        return $this->proyeccion->fechaUnica($concepto, $base, $escenario->horizonte_anios * 12)->toDateString();
    }
}
