<?php

namespace App\Http\Requests;

use App\Services\AjusteService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AjusteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->rol === 'Contador';
    }

    public function rules(): array
    {
        return [
            'periodo_inicio' => ['required', 'date'],
            'periodo_fin' => ['required', 'date', 'after_or_equal:periodo_inicio'],
            'fecha' => ['required', 'date'],
            'tipo' => ['required', 'string', Rule::in(AjusteService::TIPOS_AJUSTE)],
            'concepto' => ['required', 'string'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.id_cuenta' => [
                'required', 'integer',
                Rule::exists('tblcuentascontables', 'id_cuenta'),
            ],
            'detalles.*.descripcion' => ['nullable', 'string'],
            'detalles.*.debe' => ['required', 'regex:/^\d+(?:\.\d{1,2})?$/'],
            'detalles.*.haber' => ['required', 'regex:/^\d+(?:\.\d{1,2})?$/'],
        ];
    }
}
