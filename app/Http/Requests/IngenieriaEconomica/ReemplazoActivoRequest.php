<?php

namespace App\Http\Requests\IngenieriaEconomica;

use Illuminate\Validation\Rule;

class ReemplazoActivoRequest extends ConfiguracionRequest
{
    public const COLUMNAS = [
        'reposiciones' => ['anio'=>'Año de reposición','compra'=>'Costo de compra','rescate'=>'Rescate del sustituido',
            'retiro'=>'Retiro del sustituido','vida'=>'Vida del nuevo (años)','operacion'=>'Operación anual del nuevo',
            'mantenimiento'=>'Mantenimiento anual del nuevo','nota'=>'Fuente y justificación'],
        'candidatos' => ['anio'=>'Edad candidata (años)','rescate'=>'Rescate a esa edad','retiro'=>'Retiro a esa edad','nota'=>'Fuente y justificación'],
    ];

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('proyecto'));
    }

    public function rules(): array
    {
        $proyecto = $this->route('proyecto');
        $importe = ['required', 'numeric', 'decimal:0,6', 'gte:0', 'lt:100000000000000'];
        $reglas = [
            'escenario_id' => ['required', 'integer', Rule::exists('ie_escenarios', 'id')->where('proyecto_id', $proyecto->id)],
            'nuevo_id' => ['nullable', 'integer', 'not_in:'.$this->route('activo')->id, Rule::exists('ie_activos', 'id')->where('proyecto_id', $proyecto->id)],
            'politica' => ['required', Rule::in(['sin_reposicion', 'reposiciones_documentadas'])],
            'equivalencia' => ['sometimes', 'boolean'],
            'justificacion' => ['required', 'string', 'max:5000'],
            'considerar_retiro' => ['sometimes', 'boolean'],
            'valoracion_fecha' => ['nullable', 'date_format:Y-m-d'],
            'valoracion_fuente' => ['nullable', 'string', 'max:1000'],
            'ceros_documentados' => ['sometimes', 'boolean'],
        ];

        $reglas['valor_libros_actual'] = ['nullable', ...array_slice($importe, 1)];
        foreach (['mercado_actual', 'venta_actual', 'retiro_ahora', 'precio', 'instalacion', 'puesta_marcha'] as $campo) {
            $reglas[$campo] = $importe;
        }
        foreach (['conservar', 'reemplazar'] as $opcion) {
            foreach (['operacion', 'mantenimiento', 'rescate', 'retiro'] as $campo) {
                $reglas["$opcion.$campo"] = $importe;
            }
            $reglas["$opcion"] = ['required', 'array:operacion,mantenimiento,rescate,retiro,vida,reposiciones,candidatos'];
            $reglas["$opcion.vida"] = ['required', 'integer', 'between:1,100'];
            $reglas["$opcion.reposiciones"] = ['nullable', 'string', 'max:16000'];
            $reglas["$opcion.candidatos"] = ['nullable', 'string', 'max:8000'];
        }

        foreach (['conservar', 'reemplazar'] as $opcion) {
            $reglas["filas.$opcion"] = ['nullable', 'array:reposiciones,candidatos'];
            foreach (self::COLUMNAS as $tipo => $columnas) {
                $ruta = "filas.$opcion.$tipo";
                $reglas[$ruta] = ['nullable', 'array', 'max:100'];
                $reglas["$ruta.*"] = ['required', 'array:'.implode(',', array_keys($columnas))];
                foreach ($columnas as $campo => $etiqueta) {
                    $reglas["$ruta.*.$campo"] = match ($campo) {
                        'anio', 'vida' => ['required', 'integer', 'between:1,100'],
                        'nota' => ['required', 'string', 'max:140', 'regex:/^[^;\r\n]+$/u'],
                        default => $importe,
                    };
                }
            }
        }

        return ['filas' => ['nullable', 'array:conservar,reemplazar']] + $reglas;
    }

    public function messages(): array
    {
        return [
            'filas.*.*.*.*.required' => 'Completa este campo de la fila o elimina la fila si no corresponde.',
            'filas.*.*.*.nota.regex' => 'Indica la fuente y justificación en una sola línea, sin punto y coma.',
            'filas.*.*.*.*.decimal' => 'El importe admite hasta seis decimales.',
        ] + parent::messages();
    }

    public function after(): array
    {
        return [function (\Illuminate\Validation\Validator $validator): void {
            if ($validator->errors()->any()) {
                return;
            }
            foreach ($this->input('filas', []) ?? [] as $opcion => $tipos) {
                foreach ($tipos ?? [] as $tipo => $filas) {
                    $anios = [];
                    foreach ($filas ?? [] as $indice => $fila) {
                        $anio = (int) $fila['anio'];
                        if (isset($anios[$anio])) {
                            $validator->errors()->add("filas.$opcion.$tipo.$indice.anio", 'La edad o año debe aparecer una sola vez en esta tabla.');
                        }
                        $anios[$anio] = true;
                    }
                }
            }
        }];
    }

    public function datosCalculo(): array
    {
        $datos = $this->safe()->except('filas');
        foreach ($this->validated('filas') ?? [] as $opcion => $tipos) {
            foreach ($tipos ?? [] as $tipo => $filas) {
                $datos[$opcion][$tipo] = implode("\n", array_map(function (array $fila) use ($tipo): string {
                    return implode(';', array_map(fn ($campo) => $fila[$campo], array_keys(self::COLUMNAS[$tipo])));
                }, $filas ?? []));
            }
        }

        return $datos;
    }
}
