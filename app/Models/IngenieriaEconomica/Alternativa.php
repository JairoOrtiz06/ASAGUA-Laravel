<?php

namespace App\Models\IngenieriaEconomica;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Alternativa extends Model
{
    public const ESTADOS = [
        'no_implementada' => 'Sin implementar',
        'en_proceso' => 'En proceso',
        'implementada' => 'Implementada',
        'retirada' => 'Retirada',
    ];

    public const VIABILIDADES = ['pendiente' => 'Pendiente', 'viable' => 'Viable', 'no_viable' => 'No viable'];

    protected $table = 'ie_alternativas';

    protected $fillable = ['nombre', 'proveedor', 'descripcion', 'estado_implementacion', 'fecha_implementacion', 'viabilidad_tecnica', 'observaciones'];

    protected function casts(): array
    {
        return ['fecha_implementacion' => 'date'];
    }

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class);
    }

    public function conceptos(): HasMany
    {
        return $this->hasMany(Concepto::class);
    }

    public function ajustesFlujo(): HasMany
    {
        return $this->hasMany(AjusteFlujo::class);
    }

    public function activos(): HasMany
    {
        return $this->hasMany(Activo::class);
    }
}
