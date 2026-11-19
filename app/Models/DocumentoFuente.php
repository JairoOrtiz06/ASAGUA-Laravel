<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentoFuente extends Model
{
    use HasFactory;

    protected $table = 'tbldocumentos_fuente';
    protected $primaryKey = 'id_documento';
    public $timestamps = false;

    protected $fillable = [
        'nombre_documento', 'descripcion', 'tipo_documento',
        'fecha_creacion', 'archivo',
    ];

    protected $hidden = ['archivo'];

    protected function casts(): array
    {
        return ['fecha_creacion' => 'datetime'];
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetallePartida::class, 'id_documento', 'id_documento');
    }
}
