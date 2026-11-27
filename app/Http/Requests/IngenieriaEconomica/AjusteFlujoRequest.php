<?php

namespace App\Http\Requests\IngenieriaEconomica;

use App\Models\IngenieriaEconomica\Concepto;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AjusteFlujoRequest extends MovimientoEconomicoRequest
{
    public function rules(): array
    {
        $proyecto = $this->route('proyecto');

        return $this->reglasMovimiento(array_keys(array_diff_key(Concepto::CLASIFICACIONES, ['comun' => true]))) + [
            'alternativa_id' => ['required', 'integer', Rule::exists('ie_alternativas', 'id')->where('proyecto_id', $proyecto->id)],
            'escenario_id' => ['required', 'integer', Rule::exists('ie_escenarios', 'id')->where('proyecto_id', $proyecto->id)],
            'fecha_flujo' => ['required', 'date_format:Y-m-d'],
            'observado' => ['required', 'boolean'],
            'justificacion' => ['required', 'string', 'max:5000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->any()) {
                return;
            }
            $datos = $validator->validated();
            $this->validarNaturaleza($validator, $datos);
            if ($datos['observado'] && $datos['fecha_flujo'] > today()->toDateString()) {
                $validator->errors()->add('fecha_flujo', 'Un ajuste observado no puede tener fecha futura.');

                return;
            }

            $proyecto = $this->route('proyecto');
            $escenario = $proyecto->escenarios()->findOrFail($datos['escenario_id']);
            $base = ($escenario->fecha_base ?? $proyecto->fecha_base)->toImmutable();
            $fin = $base->addYearsNoOverflow($escenario->horizonte_anios);
            if ($datos['fecha_flujo'] > $fin->toDateString() || (! $datos['observado'] && $datos['fecha_flujo'] < $base->toDateString())) {
                $validator->errors()->add('fecha_flujo', 'La fecha del ajuste queda fuera del horizonte seleccionado.');
            }
        }];
    }
}
