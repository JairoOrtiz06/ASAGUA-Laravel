<?php

namespace App\Http\Requests\IngenieriaEconomica;

use App\Services\IngenieriaEconomica\ConversionTasasService;
use App\Services\IngenieriaEconomica\DecimalFinanciero;
use App\Services\IngenieriaEconomica\FactoresFinancierosService;
use App\Services\IngenieriaEconomica\SeriesUniformesService;
use Brick\Math\BigDecimal;
use DomainException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class HerramientaFinancieraRequest extends ConfiguracionRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->exists('tasa_porcentaje')) {
            $this->merge(['tasa' => DecimalFinanciero::fraccionDePorcentaje($this->input('tasa_porcentaje'))]);
        }
    }

    public function rules(): array
    {
        $max = DecimalFinanciero::MAX_PERIODOS;
        $periodicidad = ['required', Rule::in(array_keys(ConversionTasasService::PERIODOS))];
        $modalidad = ['required', Rule::in(array_keys(ConversionTasasService::MODALIDADES))];
        $cantidad = ['numeric', 'decimal:0,12', 'between:0,1000000000000'];
        $periodos = ['integer'];

        return [
            'tasa' => ['required', 'numeric', 'decimal:0,12', 'between:-12,100'],
            'modalidad' => $modalidad,
            'periodicidad_tasa' => $periodicidad,
        ] + match ($this->route('herramienta')) {
            'tasas' => [
                'destino' => $periodicidad,
                'modalidad_destino' => $modalidad,
            ],
            'interes-compuesto' => [
                'periodicidad' => $periodicidad,
                'operacion' => ['required', Rule::in(['presente', 'futuro'])],
                'cantidad' => ['required', ...$cantidad],
                'periodos' => ['required', ...$periodos, "between:0,$max"],
            ],
            'factores' => [
                'periodicidad' => $periodicidad,
                'cantidad' => ['nullable', 'required_with:factor', ...$cantidad],
                'periodos' => ['required', ...$periodos, "between:1,$max"],
                'factor' => ['nullable', 'required_with:cantidad', Rule::in(array_keys(FactoresFinancierosService::FORMULAS))],
            ],
            'series' => [
                'periodicidad' => $periodicidad,
                'operacion' => ['required', Rule::in(['presente', 'futuro', 'anualidad', 'periodos'])],
                'tipo_serie' => ['required', Rule::in(array_keys(SeriesUniformesService::TIPOS))],
                'cantidad' => ['required', ...$cantidad],
                'periodos' => ['exclude_if:operacion,periodos', 'required', ...$periodos, "between:1,$max"],
                'primer_pago' => ['exclude_unless:tipo_serie,diferida', 'required', ...$periodos, "between:2,$max"],
                'valor_base' => ['exclude_unless:operacion,anualidad,periodos', 'required', Rule::in(['presente', 'futuro'])],
                'objetivo' => ['exclude_unless:operacion,periodos', 'required', ...$cantidad, 'gt:0'],
            ],
        };
    }

    public function after(ConversionTasasService $tasas): array
    {
        return [
            function (Validator $validator) use ($tasas): void {
                if ($validator->errors()->any()) {
                    return;
                }

                $datos = $validator->validated();
                try {
                    $tasas->efectivaPeriodica((string) $datos['tasa'], $datos['modalidad'], $datos['periodicidad_tasa']);
                } catch (DomainException $error) {
                    $validator->errors()->add('tasa', $error->getMessage());
                }

                if (isset($datos['destino'])) {
                    try {
                        $tasas->frecuencia($datos['destino'], $datos['modalidad_destino']);
                    } catch (DomainException $error) {
                        $validator->errors()->add('destino', $error->getMessage());
                    }
                }

                if (($datos['operacion'] ?? null) === 'periodos' && BigDecimal::of($datos['cantidad'])->isZero()) {
                    $validator->errors()->add('cantidad', 'La anualidad debe ser positiva para despejar el número de pagos.');
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'modalidad' => 'modalidad de origen',
            'modalidad_destino' => 'modalidad de destino',
            'periodicidad_tasa' => 'periodicidad de la tasa de origen',
            'periodicidad' => 'periodicidad del cálculo',
            'destino' => 'periodicidad de destino',
            'operacion' => 'operación',
            'periodos' => 'número de períodos',
            'tipo_serie' => 'tipo de serie',
            'primer_pago' => 'período del primer pago',
            'valor_base' => 'fecha de valoración',
            'objetivo' => 'valor objetivo',
        ];
    }
}
