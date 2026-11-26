<?php

namespace App\Http\Requests\IngenieriaEconomica;

use App\Models\IngenieriaEconomica\Concepto;
use Illuminate\Validation\Rule;

class ConsultaConceptosRequest extends ConfiguracionRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view', $this->route('proyecto'));
    }

    public function rules(): array
    {
        return [
            'alternativa_id' => ['nullable', 'integer', Rule::exists('ie_alternativas', 'id')->where('proyecto_id', $this->route('proyecto')->id)],
            'tipo_concepto' => ['nullable', Rule::in(array_keys(Concepto::TIPOS))],
            'estado_dato' => ['nullable', Rule::in(array_keys(Concepto::ESTADOS))],
        ];
    }
}
