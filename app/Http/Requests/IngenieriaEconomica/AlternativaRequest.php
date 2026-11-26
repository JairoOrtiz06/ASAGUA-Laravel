<?php

namespace App\Http\Requests\IngenieriaEconomica;

use App\Models\IngenieriaEconomica\Alternativa;
use Illuminate\Validation\Rule;

class AlternativaRequest extends ConfiguracionRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('proyecto'));
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:150'],
            'proveedor' => ['nullable', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string', 'max:5000'],
            'estado_implementacion' => ['required', Rule::in(array_keys(Alternativa::ESTADOS))],
            'fecha_implementacion' => ['nullable', 'date_format:Y-m-d'],
            'viabilidad_tecnica' => ['required', Rule::in(array_keys(Alternativa::VIABILIDADES))],
            'observaciones' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'nombre' => 'nombre',
            'proveedor' => 'proveedor',
            'descripcion' => 'descripción',
            'estado_implementacion' => 'estado de implementación',
            'fecha_implementacion' => 'fecha de implementación',
            'viabilidad_tecnica' => 'viabilidad técnica',
            'observaciones' => 'observaciones',
        ];
    }
}
