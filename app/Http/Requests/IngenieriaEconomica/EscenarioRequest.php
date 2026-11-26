<?php

namespace App\Http\Requests\IngenieriaEconomica;

use App\Models\IngenieriaEconomica\Escenario;
use App\Services\IngenieriaEconomica\ConversionTasasService;
use App\Services\IngenieriaEconomica\DecimalFinanciero;
use App\Services\IngenieriaEconomica\ProyeccionFlujosService;
use Brick\Math\RoundingMode;
use DomainException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class EscenarioRequest extends ConfiguracionRequest
{
    private ?string $tasaAnual = null;

    private ?int $capitalizaciones = null;

    protected function prepareForValidation(): void
    {
        if (! $this->exists('tasa_porcentaje')) {
            return;
        }

        $this->merge(['tasa_original' => DecimalFinanciero::fraccionDePorcentaje($this->input('tasa_porcentaje'))]);
    }

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('proyecto'));
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:150'],
            'fecha_base' => ['nullable', 'date_format:Y-m-d'],
            'descripcion' => ['nullable', 'string', 'max:5000'],
            'horizonte_anios' => ['required', 'integer', 'between:1,'.ProyeccionFlujosService::MAX_ANIOS],
            'periodicidad' => ['required', Rule::in(array_keys(Escenario::PERIODOS))],
            'tasa_original' => ['nullable', 'required_with:periodicidad_tasa,modalidad_tasa', 'required_if:estado_tasa,provisional,validada', 'numeric', 'decimal:0,12', 'gt:-12', 'lt:100000000'],
            'periodicidad_tasa' => ['nullable', 'required_with:tasa_original', Rule::in(array_keys(Escenario::PERIODOS_TASA))],
            'modalidad_tasa' => ['nullable', 'required_with:tasa_original', Rule::in(array_keys(Escenario::MODALIDADES))],
            'fuente_tasa' => ['nullable', 'required_if:estado_tasa,validada', 'string', 'max:2000'],
            'estado_tasa' => ['required', Rule::in(array_keys(Escenario::ESTADOS_TASA))],
            ...($this->routeIs('*.escenarios.previsualizar') ? [
                'periodicidad_descuento' => ['nullable', Rule::in(array_keys(Escenario::PERIODOS))],
            ] : []),
        ];
    }

    public function after(ConversionTasasService $tasas): array
    {
        return [
            function (Validator $validator) use ($tasas): void {
                if ($validator->errors()->any()) {
                    return;
                }

                $datos = $validator->validated();
                if (! isset($datos['tasa_original'])) {
                    return;
                }

                try {
                    $this->capitalizaciones = $tasas->frecuencia($datos['periodicidad_tasa'], $datos['modalidad_tasa']);
                    $tasa = $tasas->convertir(
                        (string) $datos['tasa_original'],
                        $datos['modalidad_tasa'],
                        $datos['periodicidad_tasa'],
                        'anual',
                    )->toScale(12, RoundingMode::HalfUp);
                } catch (DomainException $error) {
                    $validator->errors()->add('tasa_original', $error->getMessage());

                    return;
                }

                if ($tasa->isLessThanOrEqualTo(-1) || $tasa->isGreaterThanOrEqualTo(100000000)) {
                    $validator->errors()->add('tasa_original', 'La equivalencia anual queda fuera del rango que admite el escenario.');

                    return;
                }

                $this->tasaAnual = (string) $tasa;
            },
        ];
    }

    public function datosEscenario(): array
    {
        return $this->safe()->except('periodicidad_descuento') + [
            'fecha_base' => null,
            'tasa_original' => null,
            'periodicidad_tasa' => null,
            'modalidad_tasa' => null,
            'fuente_tasa' => null,
            'tasa_efectiva_anual' => $this->tasaAnual,
            'capitalizaciones_anuales' => $this->capitalizaciones,
        ];
    }

    public function attributes(): array
    {
        return [
            'nombre' => 'nombre',
            'descripcion' => 'descripción',
            'horizonte_anios' => 'horizonte en años',
            'periodicidad' => 'periodicidad del escenario',
            'tasa_original' => 'tasa original',
            'periodicidad_tasa' => 'periodicidad de la tasa',
            'modalidad_tasa' => 'modalidad de la tasa',
            'fuente_tasa' => 'fuente de la tasa',
            'estado_tasa' => 'estado de la tasa',
        ];
    }
}
