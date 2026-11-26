<?php

namespace App\Http\Requests\IngenieriaEconomica;

use App\Models\IngenieriaEconomica\Evaluacion;
use App\Services\IngenieriaEconomica\ProyeccionFlujosService;
use Illuminate\Validation\Rule;

class EvaluacionRequest extends ConfiguracionRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('proyecto'));
    }

    public function messages(): array
    {
        $mensaje = \App\Services\IngenieriaEconomica\EvaluacionFiscalService::PENDIENTE;

        return parent::messages() + [
            'fiscal.*.required' => $mensaje.'. Completa :attribute.',
            'fiscal.conciliado.accepted' => $mensaje.'. Confirma la conciliación de los impuestos ya incluidos.',
        ];
    }

    public function rules(): array
    {
        $proyecto = $this->route('proyecto');

        $reglas = [
            'escenario_id' => ['required', 'integer', Rule::exists('ie_escenarios', 'id')->where('proyecto_id', $proyecto->id)],
            'alternativas' => ['required', 'array', 'min:1', 'max:10'],
            'alternativas.*' => ['required', 'integer', 'distinct', Rule::exists('ie_alternativas', 'id')->where('proyecto_id', $proyecto->id)],
            'referencia_id' => ['nullable', 'integer', Rule::in(array_filter((array) $this->input('alternativas', []), 'is_scalar'))],
            'moneda' => ['required', 'regex:/^[A-Z]{3}$/D'],
            'periodicidad' => ['required', Rule::in(array_keys(ProyeccionFlujosService::AGRUPACIONES))],
            'perspectiva' => ['required', Rule::in(array_keys(Evaluacion::PERSPECTIVAS_PRESENTACION))],
            'equivalencia' => ['sometimes', 'boolean'],
            'supuestos' => ['nullable', 'string', 'max:5000'],
            'fiscal' => ['exclude_unless:perspectiva,fiscal', 'required', 'array:tratamiento,tasa,tasa_venta,vigente_desde,vigente_hasta,evidencia,estado,nota,conciliado,alternativas'],
            'fiscal.tratamiento' => ['required', Rule::in(['ordinario', 'no_aplicable_documentado'])],
            'fiscal.tasa' => ['required', 'regex:/^(?:0(?:\.[0-9]{1,12})?|1(?:\.0{1,12})?)$/D'],
            'fiscal.tasa_venta' => ['nullable', 'regex:/^(?:0(?:\.[0-9]{1,12})?|1(?:\.0{1,12})?)$/D'],
            'fiscal.vigente_desde' => ['required', 'date_format:Y-m-d'],
            'fiscal.vigente_hasta' => ['required', 'date_format:Y-m-d', 'after_or_equal:fiscal.vigente_desde'],
            'fiscal.evidencia' => ['required', 'integer', Rule::exists('ie_documentos_economicos', 'id')->where('proyecto_id', $proyecto->id)],
            'fiscal.estado' => ['required', Rule::in(['pendiente', 'estimado', 'confirmado'])],
            'fiscal.nota' => ['required', 'string', 'max:3000'],
            'fiscal.conciliado' => ['accepted'],
            'fiscal.alternativas' => ['nullable', 'array', 'max:100'],
            'fiscal.alternativas.*' => ['array:base,deducciones,ventas,pagos'],
            'fiscal.alternativas.*.*' => ['nullable', 'string', 'max:12000'],
            'firma' => [$this->routeIs('*.evaluaciones.store') ? 'required' : 'nullable', 'string', 'regex:/^[a-f0-9]{64}$/D'],
        ];

        return $this->input('perspectiva') === Evaluacion::PERSPECTIVA_FISCAL
            ? $reglas
            : array_filter($reglas, fn ($campo) => ! str_starts_with($campo, 'fiscal'), ARRAY_FILTER_USE_KEY);
    }
}
