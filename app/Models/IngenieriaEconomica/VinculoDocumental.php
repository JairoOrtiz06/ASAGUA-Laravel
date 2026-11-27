<?php

namespace App\Models\IngenieriaEconomica;

use Illuminate\Database\Eloquent\Model;

class VinculoDocumental extends Model
{
    public const TIPOS = [
        'proyecto' => 'Proyecto', 'alternativa' => 'Alternativa', 'escenario' => 'Escenario',
        'concepto' => 'Concepto', 'activo' => 'Activo', 'evaluacion' => 'Evaluación',
        'sensibilidad' => 'Análisis de sensibilidad', 'informe' => 'Informe',
        'cuenta' => 'Cuenta contable', 'partida' => 'Partida contable', 'detalle' => 'Detalle contable', 'documento' => 'Documento contable',
    ];

    public const CONCILIACIONES = ['pendiente' => 'Pendiente de conciliación', 'conciliado' => 'Conciliado documentalmente', 'diferencia' => 'Diferencia detectada', 'na' => 'No aplicable'];

    protected $table = 'ie_vinculos_documentales';

    protected $fillable = ['tipo', 'destino_id', 'etiqueta', 'conciliacion', 'nota'];
}
