<?php

namespace App\Services\IngenieriaEconomica;

use App\Models\IngenieriaEconomica\Escenario;
use App\Models\IngenieriaEconomica\Evaluacion;
use App\Models\IngenieriaEconomica\Proyecto;
use App\Models\User;
use DomainException;

class GuardarEvaluacionService
{
    public function firma(array $instantanea, User $usuario): string
    {
        return hash_hmac('sha256', json_encode([$usuario->getKey(), $instantanea], JSON_THROW_ON_ERROR), config('app.key'));
    }

    public function guardar(Proyecto $proyecto, Escenario $escenario, User $usuario, array $instantanea, string $firma): Evaluacion
    {
        if (! hash_equals($this->firma($instantanea, $usuario), $firma)) {
            throw new DomainException('Cambió la configuración o algún dato desde la vista previa. Genera otra evaluación antes de guardar.');
        }

        $evaluacion = $proyecto->evaluaciones()->make([
            'perspectiva' => $instantanea['perspectiva'] === Evaluacion::PERSPECTIVA_FISCAL ? 'caja' : $instantanea['perspectiva'], 'moneda' => $instantanea['moneda'],
            'version_motor' => $instantanea['version'], 'estado' => 'guardada', 'instantanea' => $instantanea,
        ]);
        $evaluacion->escenario()->associate($escenario);
        $evaluacion->usuario()->associate($usuario);
        $evaluacion->save();

        return $evaluacion;
    }
}
