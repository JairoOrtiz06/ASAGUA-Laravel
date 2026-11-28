<?php

namespace App\Services\IngenieriaEconomica;

use App\Models\IngenieriaEconomica\Escenario;
use App\Models\IngenieriaEconomica\Evaluacion;
use App\Models\IngenieriaEconomica\Proyecto;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class ContextoEvaluacion
{
    public const CAMPOS = ['escenario_id', 'alternativas', 'moneda', 'periodicidad', 'perspectiva', 'referencia_id'];
    public const RUTA = 'ingenieria-economica.proyectos.';

    public function __construct(
        public readonly array $datos,
        public readonly ?Escenario $escenario,
        public readonly ?Evaluacion $historica,
        public readonly array $complementos = [],
    ) {}

    public static function reglas(Proyecto $proyecto, mixed $alternativas): array
    {
        return [
            'escenario_id' => ['required', 'integer', Rule::exists('ie_escenarios', 'id')->where('proyecto_id', $proyecto->id)],
            'alternativas' => ['required', 'array', 'list', 'min:1', 'max:10'],
            'alternativas.*' => ['required', 'integer', 'distinct', Rule::exists('ie_alternativas', 'id')->where('proyecto_id', $proyecto->id)],
            'moneda' => ['required', 'regex:/^[A-Z]{3}$/D'],
            'periodicidad' => ['required', Rule::in(array_keys(ProyeccionFlujosService::AGRUPACIONES))],
            'perspectiva' => ['required', Rule::in(array_keys(Evaluacion::PERSPECTIVAS_PRESENTACION))],
            'referencia_id' => ['nullable', 'integer', Rule::in(array_filter((array) $alternativas, 'is_scalar'))],
        ];
    }

    public static function resolver(Proyecto $proyecto, array $entrada): self
    {
        $complementos = Validator::make($entrada, [
            'sensibilidad_id' => ['nullable', 'integer', Rule::exists('ie_analisis_sensibilidad', 'id')->where('proyecto_id', $proyecto->id)],
            'calculos' => ['nullable', 'array', 'list', 'max:8'],
            'calculos.*' => ['integer', 'distinct', 'min:1', Rule::exists('ie_calculos_activos', 'id')
                ->where(fn ($q) => $q->whereIn('activo_id', $proyecto->activos()->select('id')))],
        ])->validate();

        if (array_key_exists('evaluacion_id', $entrada)) {
            abort_unless(filter_var($entrada['evaluacion_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]), 404);
            $evaluacion = $proyecto->evaluaciones()->findOrFail($entrada['evaluacion_id']);
            $datos = self::desdeInstantanea($evaluacion->instantanea);

            return new self($datos, $proyecto->escenarios()->find($datos['escenario_id']), $evaluacion, $complementos);
        }

        $datos = Validator::make($entrada, self::reglas($proyecto, $entrada['alternativas'] ?? []))->validate();
        $datos = array_replace(array_fill_keys(self::CAMPOS, null), $datos);
        $datos['alternativas'] = array_map('intval', $datos['alternativas']);
        $datos['escenario_id'] = (int) $datos['escenario_id'];
        $datos['referencia_id'] = (int) ($datos['referencia_id'] ?? $datos['alternativas'][0]);

        return new self($datos, $proyecto->escenarios()->findOrFail($datos['escenario_id']), null, $complementos);
    }

    public static function desdeInstantanea(array $resultado): array
    {
        return [
            'escenario_id' => (int) $resultado['escenario']['id'],
            'alternativas' => array_map('intval', array_keys($resultado['alternativas'])),
            'moneda' => $resultado['moneda'],
            'periodicidad' => $resultado['escenario']['periodicidad'],
            'perspectiva' => $resultado['perspectiva'],
            'referencia_id' => (int) ($resultado['referencia'] ?? array_key_first($resultado['alternativas'])),
        ];
    }

    public function periodicidadEfectiva(): string
    {
        return $this->datos['periodicidad'] === 'escenario'
            ? $this->escenario->periodicidad : $this->datos['periodicidad'];
    }

    public function coincide(Evaluacion $evaluacion): bool
    {
        $guardados = self::desdeInstantanea($evaluacion->instantanea);
        $actuales = $this->datos;
        $actuales['periodicidad'] = $this->periodicidadEfectiva();
        sort($guardados['alternativas']);
        sort($actuales['alternativas']);

        return $actuales == $guardados;
    }

    public function evaluacionCompatible(Proyecto $proyecto): ?Evaluacion
    {
        if ($this->historica) {
            return $this->historica;
        }

        return $proyecto->evaluaciones()->where('escenario_id', $this->datos['escenario_id'])
            ->where('moneda', $this->datos['moneda'])
            ->where('instantanea->perspectiva', $this->datos['perspectiva'])
            ->where('instantanea->escenario->periodicidad', $this->periodicidadEfectiva())
            ->latest('id')->cursor()->first(fn (Evaluacion $evaluacion) => $this->coincide($evaluacion));
    }

    public function consulta(): array
    {
        return ['contexto' => ($this->historica ? ['evaluacion_id' => $this->historica->id] : $this->datos) + $this->complementos];
    }

    public static function actual(): ?self
    {
        return request()->attributes->get(self::class);
    }

    public static function enlace(string $ruta, array|Proyecto $parametros): string
    {
        return route(self::RUTA.$ruta, (is_array($parametros) ? $parametros : [$parametros]) + (self::actual()?->consulta() ?? []));
    }

    public static function entrada(array $datos): array
    {
        return ['contexto' => Arr::only($datos, self::CAMPOS)];
    }
}
