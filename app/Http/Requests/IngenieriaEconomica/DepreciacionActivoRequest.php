<?php

namespace App\Http\Requests\IngenieriaEconomica;

class DepreciacionActivoRequest extends ConfiguracionRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('proyecto'));
    }

    public function rules(): array
    {
        return [
            'fecha_corte' => ['nullable', 'date_format:Y-m-d'],
            'ajuste_final' => ['sometimes', 'boolean'],
            'justificacion_ajuste' => ['nullable', 'required_if:ajuste_final,1', 'string', 'max:5000'],
        ];
    }
}
