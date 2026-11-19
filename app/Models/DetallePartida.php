<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetallePartida extends Model
{
    use HasFactory;

    protected $table = 'tbldetalles_partida';
    protected $primaryKey = 'id_detalle';
    public $timestamps = false;

    protected $fillable = [
        'id_partida', 'id_cuenta', 'id_usuario', 'id_documento',
        'descripcion', 'debe', 'haber', 'fecha_creacion',
    ];

    protected function casts(): array
    {
        return [
            'debe' => 'decimal:2',
            'haber' => 'decimal:2',
            'fecha_creacion' => 'datetime',
        ];
    }

    public function partida(): BelongsTo
    {
        return $this->belongsTo(Partida::class, 'id_partida', 'id_partida');
    }

    public function cuenta(): BelongsTo
    {
        return $this->belongsTo(CuentaContable::class, 'id_cuenta', 'id_cuenta');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario', 'id_usuario');
    }

    public function documento(): BelongsTo
    {
        return $this->belongsTo(DocumentoFuente::class, 'id_documento', 'id_documento');
    }
}
