<?php

namespace App\Models\IngenieriaEconomica;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalculoActivo extends Model
{
    public const UPDATED_AT = null;

    public const METODOS = [
        'comparacion_depreciacion' => 'Comparación de depreciación',
        'amortizacion_lineal' => 'Amortización lineal',
        'reemplazo' => 'Conservación y reemplazo',
    ];

    protected $table = 'ie_calculos_activos';

    protected $fillable = ['metodo', 'version_motor', 'instantanea'];

    protected function casts(): array
    {
        return ['instantanea' => 'array', 'created_at' => 'immutable_datetime'];
    }

    public function activo(): BelongsTo
    {
        return $this->belongsTo(Activo::class);
    }

    public function escenario(): BelongsTo
    {
        return $this->belongsTo(Escenario::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario');
    }
}
