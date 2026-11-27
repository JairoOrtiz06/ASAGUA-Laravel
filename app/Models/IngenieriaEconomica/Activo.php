<?php

namespace App\Models\IngenieriaEconomica;

use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Activo extends Model
{
    public const CATEGORIAS = [
        'equipo_informatico' => 'Equipo informático', 'maquinaria' => 'Maquinaria', 'mobiliario' => 'Mobiliario',
        'vehiculo' => 'Vehículo', 'software' => 'Software', 'licencia' => 'Licencia', 'otro' => 'Otro',
    ];

    public const CLASIFICACIONES = [
        'activo_tangible' => 'Activo tangible', 'activo_intangible' => 'Activo intangible',
        'gasto' => 'Gasto', 'pendiente_revision' => 'Pendiente de revisión',
    ];

    public const ESTADOS = ['previsto' => 'Previsto', 'en_uso' => 'En uso', 'fuera_de_uso' => 'Fuera de uso', 'retirado' => 'Retirado'];

    protected $table = 'ie_activos';

    protected $fillable = [
        'alternativa_id', 'nombre', 'descripcion', 'categoria', 'clasificacion_contable', 'fecha_adquisicion', 'fecha_disponible_uso',
        'costo_adquisicion', 'costos_capitalizables', 'valor_residual', 'vida_util_anios', 'vida_indefinida', 'moneda', 'porcentaje_uso',
        'estado_activo', 'origen_dato', 'observaciones', 'justificacion_residual', 'depreciacion_fiscal', 'amortizacion_fiscal', 'efecto_tributario',
    ];

    protected function casts(): array
    {
        return [
            'fecha_adquisicion' => 'immutable_date', 'fecha_disponible_uso' => 'immutable_date',
            'costo_adquisicion' => 'decimal:6', 'costos_capitalizables' => 'decimal:6', 'valor_residual' => 'decimal:6',
            'porcentaje_uso' => 'decimal:4', 'vida_util_anios' => 'integer', 'vida_indefinida' => 'boolean',
        ];
    }

    public function baseCapitalizada(): BigDecimal
    {
        return BigDecimal::of($this->costo_adquisicion)->plus($this->costos_capitalizables);
    }

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class);
    }

    public function alternativa(): BelongsTo
    {
        return $this->belongsTo(Alternativa::class);
    }

    public function fuentes(): HasMany
    {
        return $this->hasMany(FuenteActivo::class);
    }

    public function calculos(): HasMany
    {
        return $this->hasMany(CalculoActivo::class);
    }

    public function conceptos(): HasMany
    {
        return $this->hasMany(Concepto::class);
    }
}
