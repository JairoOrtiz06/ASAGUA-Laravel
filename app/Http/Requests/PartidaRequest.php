<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PartidaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->rol === 'Contador';
    }

    public function rules(): array
    {
        return [
            'fecha' => ['required', 'date'],
            'concepto' => ['nullable', 'string'],
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
