<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reporte extends Model
{
    use HasFactory;

    protected $table = 'reportes';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id_usuario', 'hora', 'nombre', 'archivo',
        'tiene_observaciones', 'estado_revision',
    ];

    protected $hidden = ['archivo'];

    protected function casts(): array
    {
        return [
            'hora' => 'datetime',
            'tiene_observaciones' => 'boolean',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario', 'id_usuario');
    }

    public function observaciones(): HasMany
    {
        return $this->hasMany(ObservacionReporte::class, 'id_reporte', 'id');
    }
}
