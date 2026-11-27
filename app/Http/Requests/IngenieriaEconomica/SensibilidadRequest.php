<?php

namespace App\Http\Requests\IngenieriaEconomica;

use App\Models\IngenieriaEconomica\Evaluacion;
use App\Services\IngenieriaEconomica\ProyeccionFlujosService;
use App\Services\IngenieriaEconomica\SensibilidadService;
use App\Services\IngenieriaEconomica\VariablesSensibilidadService;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class SensibilidadRequest extends ConfiguracionRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('proyecto'));
    }

    protected function prepareForValidation(): void
    {
        $json = $this->input('parametros');
        if (! is_string($json) || strlen($json) > 48000) {
            return;
        }
        $datos = json_decode($json, true);
        if (is_array($datos)) {
            $this->merge(Arr::only($datos, [
                'nombre', 'descripcion', 'supuestos', 'estado', 'escenario_id', 'evaluacion_id', 'alternativas',
                'referencia_id', 'perspectiva', 'moneda', 'periodicidad', 'indicadores', 'modo', 'criterio',
                'ejes', 'escenarios', 'oportunidad',
            ]));
        }
    }

    public function rules(): array
    {
        $p = $this->route('proyecto');
        $variable = ['string', 'regex:/^(tmar|horizonte|(monto|crecimiento|horas|salario|migracion):[1-9][0-9]{0,18})$/D'];
        $modo = Rule::in(array_keys(VariablesSensibilidadService::MODOS));
        $numero = ['numeric', 'decimal:0,12'];
        $reglas = [
            'nombre' => ['required', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string', 'max:3000'],
            'supuestos' => ['required', 'string', 'max:5000'],
            'estado' => ['required', Rule::in(['provisional', 'validado'])],
            'escenario_id' => ['required', 'integer', Rule::exists('ie_escenarios', 'id')->where('proyecto_id', $p->id)],
            'evaluacion_id' => ['nullable', 'integer', Rule::exists('ie_evaluaciones', 'id')->where('proyecto_id', $p->id)],
            'alternativas' => ['required', 'array', 'min:1', 'max:6'],
            'alternativas.*' => ['required', 'integer', 'distinct', Rule::exists('ie_alternativas', 'id')->where('proyecto_id', $p->id)],
            'referencia_id' => ['nullable', 'integer', Rule::in(array_filter((array) $this->input('alternativas', []), 'is_scalar'))],
            'perspectiva' => ['required', Rule::in(array_keys(Evaluacion::PERSPECTIVAS))],
            'moneda' => ['required', 'regex:/^[A-Z]{3}$/D'],
            'periodicidad' => ['required', Rule::in(array_keys(ProyeccionFlujosService::AGRUPACIONES))],
            'indicadores' => ['required', 'array', 'min:1', 'max:5'],
            'indicadores.*' => ['required', 'distinct', Rule::in(array_keys(SensibilidadService::INDICADORES))],
            'modo' => ['required', Rule::in(array_keys(SensibilidadService::MODOS))],
            'criterio' => ['nullable', Rule::in(['vpc', 'caue'])],
            'oportunidad' => ['nullable', 'array', 'max:30'],
            'oportunidad.*' => ['array:salario,jornada,dias,horas'],
            'ejes' => ['exclude_if:modo,escenarios', 'required', 'array:0,1'],
            'escenarios' => ['exclude_unless:modo,escenarios', 'required', 'array:optimista,pesimista'],
            'firma' => [$this->routeIs('*.sensibilidad.store') ? 'required' : 'nullable', 'string', 'regex:/^[a-f0-9]{64}$/D'],
            'memoria' => ['nullable', 'integer', 'between:0,119'],
        ];
        foreach (['salario', 'jornada', 'dias', 'horas'] as $campo) {
            $reglas['oportunidad.*.'.$campo] = ['nullable', ...$numero];
        }
        foreach ([0, 1] as $i) {
            $excluir = $i === 0 ? 'exclude_if:modo,escenarios' : 'exclude_unless:modo,matriz';
            $reglas["ejes.$i"] = [$excluir, 'required', 'array:variable,modo,valores,min,max,paso,cantidad'];
            $reglas["ejes.$i.variable"] = [$excluir, 'required', ...$variable];
            $reglas["ejes.$i.modo"] = [$excluir, 'required', $modo];
            $reglas["ejes.$i.valores"] = [$excluir, 'nullable', 'string', 'max:2500'];
            foreach (['min','max','paso'] as $campo) {
                $reglas["ejes.$i.$campo"] = [$excluir, 'nullable', ...$numero];
            }
            $reglas["ejes.$i.cantidad"] = [$excluir, 'nullable', 'integer', 'between:2,100'];
        }
        foreach (['optimista', 'pesimista'] as $tipo) {
            $prefijo = "escenarios.$tipo";
            $excluir = 'exclude_unless:modo,escenarios';
            $reglas[$prefijo] = [$excluir, 'required', 'array:justificacion,cambios'];
            $reglas[$prefijo.'.justificacion'] = [$excluir, 'required', 'string', 'max:2000'];
            $reglas[$prefijo.'.cambios'] = [$excluir, 'required', 'array', 'max:6'];
            $reglas[$prefijo.'.cambios.*'] = [$excluir, 'array:variable,modo,valor'];
            $reglas[$prefijo.'.cambios.*.variable'] = [$excluir, 'nullable', ...$variable];
            $reglas[$prefijo.'.cambios.*.modo'] = [$excluir, 'nullable', $modo];
            $reglas[$prefijo.'.cambios.*.valor'] = [$excluir, 'nullable', ...$numero];
        }

        return $reglas;
    }

    protected function getRedirectUrl(): string
    {
        return route('ingenieria-economica.proyectos.sensibilidad.create', ['proyecto' => $this->route('proyecto')]
            + array_filter(array_intersect_key($this->all(), ['escenario_id' => true, 'evaluacion_id' => true, 'moneda' => true]), 'is_scalar'));
    }
}
