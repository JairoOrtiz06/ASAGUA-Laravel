<?php

namespace App\Http\Requests\IngenieriaEconomica;

use App\Models\IngenieriaEconomica\Concepto;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

abstract class MovimientoEconomicoRequest extends ConfiguracionRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('proyecto'));
    }

    protected function reglasMovimiento(array $clasificaciones): array
    {
        return [
            'nombre' => ['required', 'string', 'max:150'],
            'tipo_concepto' => ['required', Rule::in(array_keys(Concepto::TIPOS))],
            'direccion_otro' => ['exclude_unless:tipo_concepto,otro', 'required', Rule::in(array_keys(Concepto::DIRECCIONES))],
            'monto' => ['required', 'numeric', 'decimal:0,6', 'gte:0', 'lt:100000000000000'],
            'moneda' => ['required', 'regex:/^[A-Z]{3}$/D'],
            'clasificacion_decision' => ['required', Rule::in($clasificaciones)],
            'naturaleza_economica' => ['required', Rule::in(array_keys(Concepto::NATURALEZAS))],
            'estado_dato' => ['required', Rule::in(array_keys(Concepto::ESTADOS))],
        ];
    }

    protected function validarNaturaleza(Validator $validator, array $datos): void
    {
        $direccion = Concepto::direccion($datos['tipo_concepto'], $datos['direccion_otro'] ?? null);
        $exigida = match ($datos['naturaleza_economica']) {
            'desembolso_real', 'costo_oportunidad' => 'salida',
            'ingreso_real', 'ahorro_estimado' => 'entrada',
            default => null,
        };

        if ($exigida !== null && $exigida !== $direccion) {
            $validator->errors()->add('naturaleza_economica', 'La naturaleza económica debe ser compatible con la dirección del concepto.');
        }
    }

    public function messages(): array
    {
        return [
            'moneda.regex' => 'Usa un código de moneda de tres letras, como USD.',
            'monto.decimal' => 'El monto admite hasta seis decimales.',
            'monto.gte' => 'El monto debe ser mayor o igual a cero.',
        ] + parent::messages();
    }
}
