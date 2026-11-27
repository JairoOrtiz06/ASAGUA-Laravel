<?php

namespace App\Http\Requests\IngenieriaEconomica;

use App\Models\IngenieriaEconomica\Concepto;
use App\Services\IngenieriaEconomica\ProyeccionFlujosService;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ConceptoRequest extends MovimientoEconomicoRequest
{
    public function rules(): array
    {
        $max = ProyeccionFlujosService::MAX_MESES;

        return $this->reglasMovimiento(array_keys(Concepto::CLASIFICACIONES)) + [
            'alternativa_id' => $this->route('concepto') ? ['prohibited'] : [
                'nullable', 'integer', Rule::exists('ie_alternativas', 'id')->where('proyecto_id', $this->route('proyecto')->id),
            ],
            'descripcion' => ['nullable', 'string', 'max:5000'],
            'observaciones' => ['nullable', 'string', 'max:5000'],
            'periodicidad' => ['required', Rule::in(array_keys(Concepto::PERIODICIDADES))],
            'momento_pago' => ['required', Rule::in(array_keys(Concepto::MOMENTOS))],
            'periodo_inicio' => ['nullable', 'integer', "between:0,$max"],
            'periodo_fin' => ['nullable', 'integer', "between:0,$max"],
            'fecha_programada' => ['nullable', 'date_format:Y-m-d'],
            'fecha_real' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'numero_ocurrencias' => ['nullable', 'integer', "between:1,$max"],
            'porcentaje_crecimiento' => ['nullable', 'numeric', 'decimal:0,12', 'between:-1,1'],
        ] + ($this->routeIs('*.conceptos.previsualizar*') ? [
            'escenario_calendario' => ['required', 'integer', Rule::exists('ie_escenarios', 'id')->where('proyecto_id', $this->route('proyecto')->id)],
        ] : []);
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->any()) {
                return;
            }

            $datos = $validator->validated();
            $this->validarNaturaleza($validator, $datos);
            $alternativa = $this->route('concepto')?->alternativa_id ?? ($datos['alternativa_id'] ?? null);
            if (($alternativa === null) !== ($datos['clasificacion_decision'] === 'comun')) {
                $validator->errors()->add('clasificacion_decision', 'Los conceptos comunes se registran una vez en el proyecto. Los demás pertenecen a una alternativa.');
            }

            $unico = $datos['periodicidad'] === 'unico';
            $inicio = $datos['periodo_inicio'] ?? null;
            $fin = $datos['periodo_fin'] ?? null;
            $fechaReal = $datos['fecha_real'] ?? null;
            $programada = $datos['fecha_programada'] ?? null;
            if ($programada !== null && (! $unico || $fechaReal !== null || $inicio !== null)) {
                $validator->errors()->add('fecha_programada', 'La fecha programada es exclusiva de un movimiento único sin fecha observada ni mes inicial.');
            }
            if (! $unico && $fechaReal !== null) {
                $validator->errors()->add('fecha_real', 'Una fecha observada corresponde a un movimiento único.');
            }
            if ($unico) {
                foreach (['periodo_fin', 'numero_ocurrencias', 'porcentaje_crecimiento'] as $campo) {
                    if (isset($datos[$campo])) {
                        $validator->errors()->add($campo, 'Este dato corresponde a una recurrencia.');
                    }
                }
                if ($fechaReal !== null && $inicio !== null) {
                    $validator->errors()->add('periodo_inicio', 'Usa la fecha observada o el mes programado.');
                }
                if ($inicio === null && $fechaReal === null && $programada === null && ! in_array($datos['tipo_concepto'], ['inversion_inicial', 'valor_rescate'], true)) {
                    $validator->errors()->add('periodo_inicio', 'Indica el mes programado o la fecha observada.');
                }
            }

            if (in_array($datos['tipo_concepto'], ['inversion_inicial', 'valor_rescate'], true) && ! $unico) {
                $validator->errors()->add('periodicidad', 'La inversión inicial y el rescate requieren periodicidad única.');
            }
            if ($fin !== null && $fin < ($inicio ?? 0)) {
                $validator->errors()->add('periodo_fin', 'El último mes de pago debe ser igual o posterior al mes de inicio.');
            }
        }];
    }

    public function datosConcepto(): array
    {
        return $this->safe()->except(['alternativa_id', 'escenario_calendario']) + [
            'direccion_otro' => null, 'fecha_real' => null, 'fecha_programada' => null, 'periodo_inicio' => null, 'periodo_fin' => null,
            'numero_ocurrencias' => null, 'porcentaje_crecimiento' => null,
        ];
    }
}
