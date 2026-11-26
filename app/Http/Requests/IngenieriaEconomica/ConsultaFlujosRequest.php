<?php

namespace App\Http\Requests\IngenieriaEconomica;

use App\Services\IngenieriaEconomica\ProyeccionFlujosService;
use Illuminate\Validation\Rule;

class ConsultaFlujosRequest extends ConfiguracionRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view', $this->route('proyecto'));
    }

    public function rules(): array
    {
        $proyecto = $this->route('proyecto');
        $max = ProyeccionFlujosService::MAX_MESES;

        return [
            'escenario_id' => ['nullable', 'integer', Rule::exists('ie_escenarios', 'id')->where('proyecto_id', $proyecto->id)],
            'alternativas' => ['nullable', 'array', 'min:1', 'max:10'],
            'alternativas.*' => ['required', 'integer', 'distinct', Rule::exists('ie_alternativas', 'id')->where('proyecto_id', $proyecto->id)],
            'moneda' => ['nullable', 'regex:/^[A-Z]{3}$/D'],
            'agrupacion' => ['nullable', Rule::in(array_keys(ProyeccionFlujosService::AGRUPACIONES))],
            'desde' => ['nullable', 'integer', "between:0,$max"],
            'hasta' => ['nullable', 'integer', "between:0,$max", ...($this->filled('desde') ? ['gte:desde'] : [])],
        ];
    }
}
