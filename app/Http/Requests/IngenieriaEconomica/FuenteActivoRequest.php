<?php

namespace App\Http\Requests\IngenieriaEconomica;

use App\Models\IngenieriaEconomica\FuenteActivo;
use Illuminate\Validation\Rule;

class FuenteActivoRequest extends FuenteConceptoRequest
{
    public function rules(): array
    {
        $reglas = parent::rules();
        unset($reglas['importe_utilizado']);
        $reglas['uso'] = ['required', Rule::in(array_keys(FuenteActivo::USOS))];

        return $reglas;
    }
}
