<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PartidaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->rol === 'Contador';
    }

    public function rules(): array
    {
        return [
            'fecha' => ['required', 'date'],
            'concepto' => ['nullable', 'string'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.id_cuenta' => [
                'required', 'integer',
                Rule::exists('tblcuentascontables', 'id_cuenta')
                    ->where('estado', true)
                    ->where('nivel', 4),
            ],
            'detalles.*.modo_documento' => ['nullable', Rule::in(['ninguno', 'existente', 'nuevo'])],
            'detalles.*.id_documento' => [
                'nullable', 'integer',
                Rule::exists('tbldocumentos_fuente', 'id_documento'),
            ],
            'detalles.*.documento_nuevo' => ['nullable', 'array'],
            'detalles.*.documento_nuevo.nombre_documento' => ['nullable', 'string', 'max:255'],
            'detalles.*.documento_nuevo.tipo_documento' => ['nullable', 'string', 'max:50'],
            'detalles.*.documento_nuevo.fecha_creacion' => ['nullable', 'date'],
            'detalles.*.documento_nuevo.descripcion' => ['nullable', 'string'],
            'detalles.*.documento_nuevo.archivo' => ['nullable', 'file', 'max:39062'],
            'detalles.*.descripcion' => ['nullable', 'string'],
            'detalles.*.debe' => ['required', 'regex:/^\d+(?:\.\d{1,2})?$/'],
            'detalles.*.haber' => ['required', 'regex:/^\d+(?:\.\d{1,2})?$/'],
        ];
    }

    public function after(): array
    {
        return [function ($validator): void {
            foreach ((array) $this->input('detalles', []) as $index => $detalle) {
                $modo = (string) ($detalle['modo_documento'] ?? 'ninguno');
                $idDocumento = $detalle['id_documento'] ?? null;
                $nuevo = (array) ($detalle['documento_nuevo'] ?? []);
                $tieneNuevo = collect($nuevo)->contains(
                    static fn (mixed $valor): bool => $valor !== null && $valor !== ''
                );

                if ($modo === 'existente' && ! $idDocumento) {
                    $validator->errors()->add(
                        "detalles.{$index}.id_documento",
                        'Seleccione un documento fuente existente.'
                    );
                }

                if ($modo === 'nuevo') {
                    if (trim((string) ($nuevo['nombre_documento'] ?? '')) === '') {
                        $validator->errors()->add(
                            "detalles.{$index}.documento_nuevo.nombre_documento",
                            'Indique el nombre del documento fuente.'
                        );
                    }
                    if (trim((string) ($nuevo['tipo_documento'] ?? '')) === '') {
                        $validator->errors()->add(
                            "detalles.{$index}.documento_nuevo.tipo_documento",
                            'Indique el tipo del documento fuente.'
                        );
                    }
                }

                if ($modo !== 'existente' && $idDocumento) {
                    $validator->errors()->add(
                        "detalles.{$index}.id_documento",
                        'El documento seleccionado no coincide con el modo indicado.'
                    );
                }

                if ($modo !== 'nuevo' && $tieneNuevo) {
                    $validator->errors()->add(
                        "detalles.{$index}.documento_nuevo",
                        'Los datos de documento nuevo requieren seleccionar ese modo.'
                    );
                }
            }
        }];
    }
}
