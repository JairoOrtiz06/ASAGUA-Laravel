<?php

namespace App\Services;

use App\Models\DetallePartida;
use App\Models\DocumentoFuente;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DocumentoFuenteService
{
    /**
     * Creates a document in the caller's transaction.
     */
    public function crear(array $data, ?UploadedFile $archivo = null): DocumentoFuente
    {
        $this->validarTamano($archivo);

        return DocumentoFuente::create([
            'nombre_documento' => $data['nombre_documento'],
            'descripcion' => $data['descripcion'] ?? null,
            'tipo_documento' => $data['tipo_documento'],
            'fecha_creacion' => $data['fecha_creacion'] ?? null,
            'archivo' => $archivo?->get(),
        ]);
    }

    public function registrar(array $data, ?UploadedFile $archivo = null): DocumentoFuente
    {
        $this->validarTamano($archivo);

        return DB::transaction(function () use ($data, $archivo): DocumentoFuente {
            $detalle = null;
            if (! empty($data['id_detalle'])) {
                $detalle = DetallePartida::query()->lockForUpdate()->findOrFail($data['id_detalle']);

                if ($detalle->id_documento !== null) {
                    throw ValidationException::withMessages([
                        'id_detalle' => 'El detalle ya tiene un documento fuente asociado.',
                    ]);
                }
            }

            $documento = $this->crear($data, $archivo);

            if ($detalle) {
                $detalle->update(['id_documento' => $documento->getKey()]);
            }

            return $documento->load('detalles.partida');
        });
    }

    private function validarTamano(?UploadedFile $archivo): void
    {
        if ($archivo && $archivo->getSize() > 40_000_000) {
            throw ValidationException::withMessages([
                'archivo' => 'El documento no puede superar 40 MB.',
            ]);
        }
    }
}
