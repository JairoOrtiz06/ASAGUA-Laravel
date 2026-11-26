<?php

namespace App\Services\IngenieriaEconomica;

use App\Models\IngenieriaEconomica\Escenario;
use App\Models\IngenieriaEconomica\Proyecto;
use Illuminate\Support\Facades\DB;

class EscenarioPrincipalService
{
    public function asignar(Proyecto $proyecto, Escenario $escenario): void
    {
        DB::transaction(function () use ($proyecto, $escenario): void {
            Proyecto::query()->lockForUpdate()->findOrFail($proyecto->getKey());

            $principal = $proyecto->escenarios()->findOrFail($escenario->getKey());

            $proyecto->escenarios()->where('es_principal', true)
                ->whereKeyNot($principal->getKey())
                ->update(['es_principal' => false]);

            $principal->es_principal = true;
            $principal->save();
        }, attempts: 3);
    }
}
