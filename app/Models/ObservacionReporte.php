<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ObservacionReporte extends Model
{
    use HasFactory;

    protected $table = 'observaciones_reportes';
    protected $primaryKey = 'id_observacion';
    public $timestamps = false;

    protected $fillable = [
        'id_reporte', 'id_auditor', 'id_contador', 'comentario',
        'prioridad', 'fecha_observacion', 'estado', 'fecha_vista',
        'respuesta_contador', 'fecha_respuesta',
    ];

    protected function casts(): array
    {
        return [
            'fecha_observacion' => 'datetime',
            'fecha_vista' => 'datetime',
            'fecha_respuesta' => 'datetime',
        ];
    }

    public function reporte(): BelongsTo
    {
        return $this->belongsTo(Reporte::class, 'id_reporte', 'id');
    }

    public function auditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_auditor', 'id_usuario');
    }

    public function contador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_contador', 'id_usuario');
    }
}
