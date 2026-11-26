<?php

namespace App\Models\IngenieriaEconomica;

use App\Models\DetallePartida;
use App\Models\DocumentoFuente;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FuenteActivo extends Model
{
    public const USOS = [
        'adquisicion' => 'Adquisición', 'instalacion' => 'Instalación', 'mantenimiento' => 'Mantenimiento',
        'reparacion' => 'Reparación', 'depreciacion' => 'Depreciación registrada', 'amortizacion' => 'Amortización registrada',
        'vida_util' => 'Vida útil', 'residual' => 'Valor residual estimado',
        'mercado' => 'Valor de mercado', 'libros' => 'Valor en libros', 'rescate' => 'Valor de rescate',
        'retiro' => 'Venta, rescate o retiro', 'fiscal' => 'Documentación fiscal', 'otro' => 'Otro',
    ];

    protected $table = 'ie_fuentes_activos';

    protected $fillable = ['uso', 'tipo', 'referencia', 'fecha', 'importe_observado', 'id_documento', 'id_detalle', 'justificacion'];

    protected function casts(): array
    {
        return ['fecha' => 'immutable_date', 'importe_observado' => 'decimal:6'];
    }

    public function activo(): BelongsTo
    {
        return $this->belongsTo(Activo::class);
    }

    public function documento(): BelongsTo
    {
        return $this->belongsTo(DocumentoFuente::class, 'id_documento');
    }

    public function detalle(): BelongsTo
    {
        return $this->belongsTo(DetallePartida::class, 'id_detalle');
    }
}
