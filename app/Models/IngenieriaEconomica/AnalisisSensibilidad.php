<?php

namespace App\Models\IngenieriaEconomica;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalisisSensibilidad extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'ie_analisis_sensibilidad';

    protected $fillable = ['nombre', 'descripcion', 'perspectiva', 'estado', 'version_motor', 'instantanea'];

    protected function casts(): array
    {
        return ['instantanea' => 'array', 'created_at' => 'immutable_datetime'];
    }

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class);
    }

    public function escenario(): BelongsTo
    {
        return $this->belongsTo(Escenario::class);
    }

    public function evaluacion(): BelongsTo
    {
        return $this->belongsTo(Evaluacion::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario');
    }
}
