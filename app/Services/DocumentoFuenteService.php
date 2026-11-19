<?php

namespace App\Services;

use App\Models\DetallePartida;
use App\Models\DocumentoFuente;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DocumentoFuenteService
{
    public function registrar(array $data, ?UploadedFile $archivo = null): DocumentoFuente
    {
        if ($archivo && $archivo->getSize() > 40_000_000) {
            throw ValidationException::withMessages([
                'archivo' => 'El documento no puede superar 40 MB.',
            ]);
        }

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

            $documento = DocumentoFuente::create([
                'nombre_documento' => $data['nombre_documento'],
                'descripcion' => $data['descripcion'] ?? null,
                'tipo_documento' => $data['tipo_documento'],
                'fecha_creacion' => $data['fecha_creacion'] ?? null,
                'archivo' => $archivo?->get(),
            ]);

            if ($detalle) {
                $detalle->update(['id_documento' => $documento->getKey()]);
            }

            return $documento->load('detalles.partida');
        });
    }
}
