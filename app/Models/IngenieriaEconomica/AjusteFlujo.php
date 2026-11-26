<?php

namespace App\Models\IngenieriaEconomica;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AjusteFlujo extends Model
{
    protected $table = 'ie_flujos';

    protected $fillable = [
        'nombre', 'fecha_flujo', 'tipo_concepto', 'direccion_otro', 'monto', 'moneda',
        'clasificacion_decision', 'naturaleza_economica', 'estado_dato', 'observado', 'justificacion',
    ];

    protected function casts(): array
    {
        return ['fecha_flujo' => 'immutable_date', 'monto' => 'decimal:6', 'observado' => 'boolean'];
    }

    public function alternativa(): BelongsTo
    {
        return $this->belongsTo(Alternativa::class);
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
