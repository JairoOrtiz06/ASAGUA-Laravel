<?php

namespace App\Models\IngenieriaEconomica;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Evaluacion extends Model
{
    public const UPDATED_AT = null;

    public const PERSPECTIVAS = ['caja' => 'Financiera de caja', 'economica' => 'Económica ampliada'];

    public const PERSPECTIVA_FISCAL = 'fiscal';

    public const PERSPECTIVAS_PRESENTACION = self::PERSPECTIVAS + [self::PERSPECTIVA_FISCAL => 'Financiera después de impuestos'];

    protected $table = 'ie_evaluaciones';

    protected $fillable = ['perspectiva', 'moneda', 'version_motor', 'estado', 'instantanea'];

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

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario');
    }
}
