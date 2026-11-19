<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class HojaTrabajoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->rol === 'Contador';
    }

    public function rules(): array
    {
        return [
            'fecha_inicio' => ['nullable', 'date'],
            'fecha_fin' => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
        ];
    }
}
