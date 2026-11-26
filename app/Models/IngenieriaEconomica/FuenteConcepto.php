<?php

namespace App\Models\IngenieriaEconomica;

use App\Models\DetallePartida;
use App\Models\DocumentoFuente;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FuenteConcepto extends Model
{
    public const TIPOS = [
        'factura' => 'Factura', 'cotizacion' => 'Cotización', 'comprobante' => 'Comprobante',
        'entrevista' => 'Entrevista', 'registro_contable' => 'Registro contable', 'estimacion' => 'Estimación justificada',
    ];

    protected $table = 'ie_fuentes';

    protected $fillable = [
        'tipo', 'referencia', 'id_documento', 'id_detalle', 'fecha',
        'importe_observado', 'importe_utilizado', 'justificacion',
    ];

    protected function casts(): array
    {
        return ['fecha' => 'immutable_date', 'importe_observado' => 'decimal:6', 'importe_utilizado' => 'decimal:6'];
    }

    public function concepto(): BelongsTo
    {
        return $this->belongsTo(Concepto::class);
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
