<?php

namespace App\Http\Requests\IngenieriaEconomica;

use App\Models\IngenieriaEconomica\Proyecto;
use Illuminate\Validation\Rule;

class ProyectoRequest extends ConfiguracionRequest
{
    public function authorize(): bool
    {
        $proyecto = $this->route('proyecto');

        return $proyecto
            ? $this->user()->can('update', $proyecto)
            : $this->user()->can('create', Proyecto::class);
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string', 'max:5000'],
            'organizacion' => ['nullable', 'string', 'max:150'],
            'tipo_decision' => ['required', Rule::in(array_keys(Proyecto::TIPOS_DECISION))],
            'fecha_base' => ['required', 'date_format:Y-m-d'],
            'horizonte_anios' => ['required', 'integer', 'between:1,32767'],
            'estado' => ['required', Rule::in(array_keys(Proyecto::ESTADOS))],
        ];
    }

    public function attributes(): array
    {
        return [
            'nombre' => 'nombre',
            'descripcion' => 'descripción',
            'organizacion' => 'organización',
            'tipo_decision' => 'tipo de decisión',
            'fecha_base' => 'fecha base',
            'horizonte_anios' => 'horizonte en años',
            'estado' => 'estado',
        ];
    }
}
