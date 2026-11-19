<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->rol === 'Admin';
    }

    public function rules(): array
    {
        $user = $this->route('user');
        $id = $user?->getKey();

        return [
            'nombre' => ['required', 'string'],
            'apellido' => ['required', 'string'],
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('tblusuarios', 'email')->ignore($id, 'id_usuario'),
            ],
            'usuario' => [
                'required', 'string', 'max:100',
                Rule::unique('tblusuarios', 'usuario')->ignore($id, 'id_usuario'),
            ],
            'password' => [$user ? 'nullable' : 'required', 'string'],
            'rol' => ['required', Rule::in(['Admin', 'Contador', 'Auditor'])],
        ];
    }
}
