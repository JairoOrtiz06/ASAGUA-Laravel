<?php

namespace App\Models\IngenieriaEconomica;

use Illuminate\Database\Eloquent\Model;

class InformeEconomico extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'ie_informes';

    protected $fillable = ['evaluacion_id', 'id_usuario', 'estado', 'folio', 'sha256', 'instantanea'];

    protected function casts(): array
    {
        return ['instantanea' => 'array', 'created_at' => 'immutable_datetime'];
    }
}
