<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LibroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->rol, ['Contador', 'Auditor'], true);
    }

    public function rules(): array
    {
        return [
            'fecha_inicio' => ['nullable', 'date'],
            'fecha_fin' => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
            'cuenta_id' => [
                'nullable', 'integer', Rule::exists('tblcuentascontables', 'id_cuenta'),
            ],
        ];
    }
}
