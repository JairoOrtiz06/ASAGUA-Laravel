<?php

namespace App\Models\IngenieriaEconomica;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Concepto extends Model
{
    public const TIPOS = [
        'inversion_inicial' => 'Inversión inicial', 'ingreso' => 'Ingreso', 'ahorro' => 'Ahorro',
        'costo_operativo' => 'Costo operativo', 'mantenimiento' => 'Mantenimiento',
        'inversion_adicional' => 'Inversión adicional', 'valor_rescate' => 'Valor de rescate', 'otro' => 'Otro',
    ];

    public const PERIODICIDADES = [
        'unico' => 'Único', 'mensual' => 'Mensual', 'trimestral' => 'Trimestral', 'semestral' => 'Semestral', 'anual' => 'Anual',
    ];

    public const MOMENTOS = ['inicio' => 'Inicio', 'fin' => 'Fin'];

    public const DIRECCIONES = ['entrada' => 'Entrada', 'salida' => 'Salida'];

    public const CLASIFICACIONES = [
        'diferencial' => 'Diferencial', 'comun' => 'Común', 'hundido' => 'Hundido',
        'pendiente_clasificacion' => 'Pendiente de clasificación',
    ];

    public const NATURALEZAS = [
        'desembolso_real' => 'Desembolso de efectivo', 'ingreso_real' => 'Ingreso de efectivo',
        'costo_oportunidad' => 'Costo de oportunidad', 'ahorro_estimado' => 'Ahorro estimado',
        'no_monetario' => 'No monetario',
    ];

    public const ESTADOS = ['confirmado' => 'Confirmado', 'estimado' => 'Estimado', 'provisional' => 'Provisional'];

    protected $table = 'ie_conceptos';

    protected $fillable = [
        'nombre', 'descripcion', 'tipo_concepto', 'direccion_otro', 'monto', 'moneda', 'periodicidad',
        'momento_pago', 'periodo_inicio', 'periodo_fin', 'fecha_real', 'fecha_programada', 'numero_ocurrencias',
        'porcentaje_crecimiento', 'clasificacion_decision', 'naturaleza_economica', 'estado_dato', 'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:6', 'fecha_real' => 'immutable_date', 'fecha_programada' => 'immutable_date', 'periodo_inicio' => 'integer',
            'periodo_fin' => 'integer', 'numero_ocurrencias' => 'integer', 'porcentaje_crecimiento' => 'decimal:12',
        ];
    }

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class);
    }

    public function alternativa(): BelongsTo
    {
        return $this->belongsTo(Alternativa::class);
    }

    public function activo(): BelongsTo
    {
        return $this->belongsTo(Activo::class);
    }

    public function fuentes(): HasMany
    {
        return $this->hasMany(FuenteConcepto::class);
    }

    public static function direccion(string $tipo, ?string $direccionOtro): string
    {
        return match ($tipo) {
            'ingreso', 'ahorro', 'valor_rescate' => 'entrada',
            'otro' => $direccionOtro ?? throw new \DomainException('Selecciona la dirección para el tipo Otro.'),
            default => 'salida',
        };
    }
}
