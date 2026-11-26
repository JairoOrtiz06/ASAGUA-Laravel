<?php

namespace App\Http\Requests\IngenieriaEconomica;

use App\Models\DetallePartida;
use App\Models\DocumentoFuente;
use App\Models\IngenieriaEconomica\FuenteConcepto;
use Illuminate\Validation\Rule;

class FuenteConceptoRequest extends ConfiguracionRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('proyecto'));
    }

    public function rules(): array
    {
        $importe = ['numeric', 'decimal:0,6', 'gte:0', 'lt:100000000000000'];

        return [
            'tipo' => ['required', Rule::in(array_keys(FuenteConcepto::TIPOS))],
            'referencia' => ['required', 'string', 'max:255'],
            'id_documento' => ['nullable', 'integer', Rule::exists(DocumentoFuente::class, 'id_documento')],
            'id_detalle' => ['nullable', 'integer', Rule::exists(DetallePartida::class, 'id_detalle')],
            'fecha' => ['required', 'date_format:Y-m-d'],
            'importe_observado' => ['nullable', ...$importe],
            'importe_utilizado' => ['required', ...$importe],
            'justificacion' => ['required', 'string', 'max:5000'],
        ];
    }
}
