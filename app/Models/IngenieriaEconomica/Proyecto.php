<?php

namespace App\Models\IngenieriaEconomica;

use App\Models\User;
use App\Policies\IngenieriaEconomica\ProyectoPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[UsePolicy(ProyectoPolicy::class)]
class Proyecto extends Model
{
    public const TIPOS_DECISION = ['historica' => 'Histórica', 'prospectiva' => 'Prospectiva', 'reemplazo' => 'Reemplazo'];

    public const ESTADOS = ['borrador' => 'Borrador', 'en_preparacion' => 'En preparación', 'evaluado' => 'Evaluado'];

    protected $table = 'ie_proyectos';

    protected $fillable = ['nombre', 'descripcion', 'organizacion', 'tipo_decision', 'fecha_base', 'horizonte_anios', 'estado'];

    protected function casts(): array
    {
        return ['fecha_base' => 'date', 'horizonte_anios' => 'integer'];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario', 'id_usuario');
    }

    public function alternativas(): HasMany
    {
        return $this->hasMany(Alternativa::class);
    }

    public function escenarios(): HasMany
    {
        return $this->hasMany(Escenario::class);
    }

    public function escenarioPrincipal(): HasOne
    {
        return $this->hasOne(Escenario::class)->where('es_principal', true);
    }

    public function conceptos(): HasMany
    {
        return $this->hasMany(Concepto::class);
    }

    public function evaluaciones(): HasMany
    {
        return $this->hasMany(Evaluacion::class);
    }

    public function activos(): HasMany
    {
        return $this->hasMany(Activo::class);
    }

    public function analisisSensibilidad(): HasMany
    {
        return $this->hasMany(AnalisisSensibilidad::class);
    }

    public function evidenciasDocumentales(): HasMany
    {
        return $this->hasMany(EvidenciaDocumental::class);
    }

    public function informesEconomicos(): HasMany
    {
        return $this->hasMany(InformeEconomico::class);
    }
}
