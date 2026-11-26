<?php

namespace App\Http\Requests\IngenieriaEconomica;

use App\Models\IngenieriaEconomica\Concepto;
use App\Services\IngenieriaEconomica\IntegracionActivoService;
use Illuminate\Validation\Rule;

class FlujoActivoRequest extends ConfiguracionRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('proyecto'));
    }

    public function rules(): array
    {
        $proyecto = $this->route('proyecto');

        return [
            'escenario_id' => ['required', 'integer', Rule::exists('ie_escenarios', 'id')->where('proyecto_id', $proyecto->id)],
            'fuente_id' => ['required', 'integer', Rule::exists('ie_fuentes_activos', 'id')->where('activo_id', $this->route('activo')->id)],
            'concepto_id' => ['nullable', 'integer', Rule::exists('ie_conceptos', 'id')->where('proyecto_id', $proyecto->id)],
            'operacion' => ['required', Rule::in(array_keys(IntegracionActivoService::OPERACIONES))],
            'fecha' => ['required', 'date_format:Y-m-d', ...($this->boolean('observado') ? ['before_or_equal:today'] : [])],
            'observado' => ['required', 'boolean'],
            'monto' => ['required', 'numeric', 'decimal:0,6', 'gte:0', 'lt:100000000000000'],
            'moneda' => ['required', 'regex:/^[A-Z]{3}$/D'],
            'estado_dato' => ['required', Rule::in(array_keys(Concepto::ESTADOS))],
            'justificacion' => ['required', 'string', 'max:5000'],
            'confirmar' => ['accepted'],
        ];
    }
}
