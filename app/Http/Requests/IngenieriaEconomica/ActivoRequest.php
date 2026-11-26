<?php

namespace App\Http\Requests\IngenieriaEconomica;

use App\Models\IngenieriaEconomica\Activo;
use App\Models\IngenieriaEconomica\Concepto;
use Brick\Math\BigDecimal;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ActivoRequest extends ConfiguracionRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('proyecto'));
    }

    public function rules(): array
    {
        $proyecto = $this->route('proyecto');
        $importe = ['required', 'numeric', 'decimal:0,6', 'gte:0', 'lt:100000000000000'];

        return [
            'alternativa_id' => ['nullable', 'integer', Rule::exists('ie_alternativas', 'id')->where('proyecto_id', $proyecto->id)],
            'nombre' => ['required', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string', 'max:5000'],
            'categoria' => ['required', Rule::in(array_keys(Activo::CATEGORIAS))],
            'clasificacion_contable' => ['required', Rule::in(array_keys(Activo::CLASIFICACIONES))],
            'fecha_adquisicion' => ['nullable', 'date_format:Y-m-d'],
            'fecha_disponible_uso' => ['nullable', 'date_format:Y-m-d', ...($this->filled('fecha_adquisicion') ? ['after_or_equal:fecha_adquisicion'] : [])],
            'costo_adquisicion' => $importe,
            'costos_capitalizables' => $importe,
            'valor_residual' => $importe,
            'vida_util_anios' => ['nullable', 'integer', 'between:1,100'],
            'vida_indefinida' => ['sometimes', 'boolean'],
            'moneda' => ['required', 'regex:/^[A-Z]{3}$/D'],
            'porcentaje_uso' => ['required', 'numeric', 'decimal:0,4', 'between:0,100'],
            'estado_activo' => ['required', Rule::in(array_keys(Activo::ESTADOS))],
            'origen_dato' => ['required', Rule::in(array_keys(Concepto::ESTADOS))],
            'justificacion_residual' => ['nullable', 'string', 'max:5000'],
            'observaciones' => ['nullable', 'string', 'max:5000'],
            'depreciacion_fiscal' => ['nullable', 'string', 'max:5000'],
            'amortizacion_fiscal' => ['nullable', 'string', 'max:5000'],
            'efecto_tributario' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->any()) {
                return;
            }

            $d = $validator->validated();
            $residual = BigDecimal::of($d['valor_residual']);
            $capitalizable = BigDecimal::of($d['costos_capitalizables']);
            $base = BigDecimal::of($d['costo_adquisicion'])->plus($capitalizable);
            if ($residual->isGreaterThan($base)) {
                $validator->errors()->add('valor_residual', 'El valor residual no puede superar el costo capitalizado.');
            }

            $tipo = $d['clasificacion_contable'];
            $indefinida = (bool) ($d['vida_indefinida'] ?? false);
            $vida = $d['vida_util_anios'] ?? null;
            if ($indefinida && ($tipo !== 'activo_intangible' || $vida !== null)) {
                $validator->errors()->add('vida_indefinida', 'La vida indefinida corresponde a un intangible sin años definidos.');
            }
            if (in_array($tipo, ['activo_tangible', 'activo_intangible'], true) && ! $indefinida && $vida === null) {
                $validator->errors()->add('vida_util_anios', 'Indica una vida útil positiva.');
            }
            if (in_array($tipo, ['activo_tangible', 'activo_intangible'], true) && $d['estado_activo'] !== 'previsto' && ! isset($d['fecha_disponible_uso'])) {
                $validator->errors()->add('fecha_disponible_uso', 'Indica desde cuándo estuvo disponible para uso.');
            }
            if ($tipo === 'activo_intangible' && $residual->isPositive() && empty($d['justificacion_residual'])) {
                $validator->errors()->add('justificacion_residual', 'Justifica el valor residual del intangible.');
            }
            if ($tipo === 'gasto' && (! $capitalizable->isZero() || ! $residual->isZero() || $vida !== null)) {
                $validator->errors()->add('clasificacion_contable', 'Un gasto se registra sin costos capitalizables, vida útil ni valor residual.');
            }
        }];
    }
}
