<?php

namespace App\Models\IngenieriaEconomica;

use App\Services\IngenieriaEconomica\ConversionTasasService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Escenario extends Model
{
    public const PERIODOS = ['mensual' => 'Mensual', 'trimestral' => 'Trimestral', 'anual' => 'Anual'];

    public const PERIODOS_TASA = ConversionTasasService::PERIODOS;

    public const MODALIDADES = ConversionTasasService::MODALIDADES;

    public const ESTADOS_TASA = ['pendiente' => 'Pendiente', 'provisional' => 'Provisional', 'validada' => 'Validada'];

    protected $table = 'ie_escenarios';

    protected $fillable = [
        'nombre', 'descripcion', 'fecha_base', 'horizonte_anios', 'periodicidad', 'tasa_original', 'periodicidad_tasa',
        'modalidad_tasa', 'capitalizaciones_anuales', 'tasa_efectiva_anual', 'fuente_tasa', 'estado_tasa',
    ];

    protected function casts(): array
    {
        return [
            'fecha_base' => 'immutable_date',
            'horizonte_anios' => 'integer',
            'capitalizaciones_anuales' => 'integer',
            'tasa_original' => 'decimal:12',
            'tasa_efectiva_anual' => 'decimal:12',
            'es_principal' => 'boolean',
        ];
    }

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class);
    }

    public function ajustesFlujo(): HasMany
    {
        return $this->hasMany(AjusteFlujo::class);
    }
}
