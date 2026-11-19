<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DocumentoFuenteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->rol, ['Contador', 'Auditor'], true);
    }

    public function rules(): array
    {
        return [
            'nombre_documento' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
            'tipo_documento' => ['required', 'string', 'max:50'],
            'fecha_creacion' => ['nullable', 'date'],
            'archivo' => ['nullable', 'file', 'max:39062'],
            'id_detalle' => [
                'nullable', 'integer',
                Rule::exists('tbldetalles_partida', 'id_detalle'),
            ],
        ];
    }
}
