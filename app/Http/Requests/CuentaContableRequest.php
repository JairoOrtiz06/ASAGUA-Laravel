<?php

namespace App\Http\Requests;

use App\Models\CuentaContable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CuentaContableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->rol === 'Admin';
    }

    public function rules(): array
    {
        return [
            'codigo_cuenta' => ['required', 'string', 'max:50'],
            'nombre_cuenta' => ['required', 'string'],
            'tipo_cuenta' => ['required', 'string', 'max:30'],
            'nivel' => ['required', 'integer'],
            'id_cuenta_padre' => [
                'nullable', 'integer',
                Rule::exists('tblcuentascontables', 'id_cuenta'),
            ],
            'estado' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('id_cuenta_padre') === '') {
            $this->merge(['id_cuenta_padre' => null]);
        }
    }

    public function after(): array
    {
        return [function ($validator): void {
            $account = $this->route('cuenta');
            $parentId = $this->input('id_cuenta_padre');

            if (! $account || ! $parentId) {
                return;
            }

            if ((int) $parentId === (int) $account->getKey()) {
                $validator->errors()->add('id_cuenta_padre', 'Una cuenta no puede ser su propio padre.');
                return;
            }

            $visited = [];
            $parent = CuentaContable::find($parentId);
            while ($parent) {
                if (in_array($parent->getKey(), $visited, true)) {
                    break;
                }
                $visited[] = $parent->getKey();

                if ((int) $parent->getKey() === (int) $account->getKey()) {
                    $validator->errors()->add('id_cuenta_padre', 'La jerarquía resultaría circular.');
                    break;
                }

                $parent = $parent->padre;
            }
        }];
    }
}
