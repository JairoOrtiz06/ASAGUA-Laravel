<?php

namespace App\Services\IngenieriaEconomica;

use App\Models\IngenieriaEconomica\Proyecto;
use App\Models\User;

class PreferenciaEvaluacion
{
    private const TABLA = 'ie_preferencias_evaluacion';

    public function guardar(User $usuario, Proyecto $proyecto, ContextoEvaluacion $contexto): void
    {
        $proyecto->getConnection()->table(self::TABLA)->upsert([
            'proyecto_id' => $proyecto->id,
            'id_usuario' => $usuario->getKey(),
            'seleccion' => json_encode(ContextoEvaluacion::entrada($contexto->datos)['contexto'], JSON_THROW_ON_ERROR),
        ], ['proyecto_id', 'id_usuario'], ['seleccion']);
    }

    public function recuperar(User $usuario, Proyecto $proyecto): ?array
    {
        $seleccion = $proyecto->getConnection()->table(self::TABLA)
            ->where('proyecto_id', $proyecto->id)
            ->where('id_usuario', $usuario->getKey())
            ->value('seleccion');

        return $seleccion === null ? null : (array) json_decode($seleccion, true, flags: JSON_THROW_ON_ERROR);
    }
}
