<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CuentaContable extends Model
{
    use HasFactory;

    protected $table = 'tblcuentascontables';
    protected $primaryKey = 'id_cuenta';
    public $timestamps = false;

    protected $fillable = [
        'codigo_cuenta', 'nombre_cuenta', 'tipo_cuenta', 'nivel',
        'id_cuenta_padre', 'estado',
    ];

    protected function casts(): array
    {
        return ['nivel' => 'integer', 'estado' => 'boolean'];
    }

    public function padre(): BelongsTo
    {
        return $this->belongsTo(self::class, 'id_cuenta_padre', 'id_cuenta');
    }

    public function hijos(): HasMany
    {
        return $this->hasMany(self::class, 'id_cuenta_padre', 'id_cuenta');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetallePartida::class, 'id_cuenta', 'id_cuenta');
    }
}
