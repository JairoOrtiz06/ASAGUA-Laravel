<?php

namespace App\Http\Requests\IngenieriaEconomica;

use Illuminate\Foundation\Http\FormRequest;

abstract class ConfiguracionRequest extends FormRequest
{
    public function messages(): array
    {
        return [
            'required' => 'Indica :attribute.',
            'required_if' => 'Indica :attribute para la configuración seleccionada.',
            'required_with' => 'Indica :attribute para completar la configuración de la tasa.',
            'in' => 'Selecciona una opción válida para :attribute.',
            'string' => 'El campo :attribute debe ser texto.',
            'max.string' => 'El campo :attribute admite hasta :max caracteres.',
            'date_format' => 'Indica una fecha válida para :attribute.',
            'integer' => 'El campo :attribute debe ser un número entero.',
            'between.numeric' => 'El campo :attribute debe estar entre :min y :max.',
            'decimal' => 'El campo :attribute debe ser una fracción decimal con hasta 12 decimales.',
            'gt.numeric' => 'El campo :attribute debe ser mayor que :value.',
            'lt.numeric' => 'El campo :attribute debe ser menor que :value.',
        ];
    }
}
