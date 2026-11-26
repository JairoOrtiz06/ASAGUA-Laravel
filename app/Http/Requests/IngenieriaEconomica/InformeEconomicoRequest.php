<?php

namespace App\Http\Requests\IngenieriaEconomica;

use Illuminate\Validation\Rule;

class InformeEconomicoRequest extends ConfiguracionRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view',$this->route('proyecto'));
    }

    public function rules(): array
    {
        $p = $this->route('proyecto');
        $escritura = ! $this->isMethod('GET');

        return [
            'evaluacion_id' => [$escritura?'required':'nullable','integer',Rule::exists('ie_evaluaciones','id')->where('proyecto_id',$p->id)],
            'sensibilidad_id' => ['nullable','integer',Rule::exists('ie_analisis_sensibilidad','id')->where('proyecto_id',$p->id)],
            'calculos' => ['nullable','array','list','max:8'],
            'calculos.*' => ['required','integer','distinct','min:1'],
            'estado' => [$escritura?'required':'nullable',Rule::in(['preliminar','validado'])],
            'estudiantes' => ['nullable','string','max:700'],
            'docentes' => ['nullable','string','max:700'],
            'comentarios' => ['nullable','string','max:10000'],
            'firma' => [$this->routeIs('*.informes.store')?'required':'nullable','string','regex:/^[a-f0-9]{64}$/D'],
        ];
    }
}
