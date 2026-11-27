<?php

namespace App\Models\IngenieriaEconomica;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EvidenciaDocumental extends Model
{
    public const TIPOS = [
        'cotizacion' => 'Cotización', 'factura' => 'Factura', 'comprobante' => 'Comprobante de pago',
        'contrato' => 'Contrato', 'estado_cuenta' => 'Estado de cuenta', 'cuestionario' => 'Respuesta de cuestionario',
        'bancario' => 'Documento bancario', 'operaciones' => 'Reporte de operaciones', 'tecnico' => 'Documento técnico',
        'otro' => 'Otro documento justificativo',
    ];

    public const ESTADOS = ['pendiente' => 'Pendiente de verificación', 'existente' => 'Existente', 'revisado' => 'Revisado', 'validado' => 'Validado'];

    public const CALIDADES = ['documentado' => 'Dato documentado', 'declarado' => 'Dato declarado por la empresa',
        'estimado' => 'Dato estimado', 'supuesto' => 'Supuesto académico'];

    protected $table = 'ie_documentos_economicos';

    protected $fillable = ['titulo', 'tipo', 'procedencia', 'fecha_documento', 'calidad', 'observaciones'];

    protected $hidden = ['archivo'];

    protected function casts(): array
    {
        return ['fecha_documento' => 'immutable_date'];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario');
    }

    public function vinculos(): HasMany
    {
        return $this->hasMany(VinculoDocumental::class, 'evidencia_id');
    }

    public function historial(): HasMany
    {
        return $this->hasMany(EventoDocumental::class, 'evidencia_id');
    }
}
