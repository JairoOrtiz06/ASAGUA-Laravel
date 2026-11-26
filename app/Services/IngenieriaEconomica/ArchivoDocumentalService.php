<?php

namespace App\Services\IngenieriaEconomica;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ArchivoDocumentalService
{
    private const EXTENSIONES = ['application/pdf' => 'pdf', 'image/png' => 'png', 'image/jpeg' => 'jpg'];

    public function disco(): FilesystemAdapter
    {
        return Storage::disk('ingenieria_privada');
    }

    public function subir(UploadedFile $archivo): array
    {
        $mime = $archivo->getMimeType();
        $ruta = $archivo->getRealPath();
        $valido = isset(self::EXTENSIONES[$mime]) && $ruta
            && ($mime === 'application/pdf'
                ? file_get_contents($ruta, false, null, 0, 5) === '%PDF-'
                : (@getimagesize($ruta)['mime'] ?? null) === $mime);
        if (! $valido) {
            throw ValidationException::withMessages(['archivo' => 'Adjunta un PDF, PNG o JPEG con contenido válido.']);
        }

        $nombre = Str::uuid().'.'.self::EXTENSIONES[$mime];
        $hash = hash_file('sha256', $ruta);
        $this->disco()->putFileAs('evidencias', $archivo, $nombre);

        return ['archivo' => $nombre, 'mime' => $mime, 'sha256' => $hash];
    }

    public function evidenciaDisponible(?string $nombre, ?string $hash): bool
    {
        if (! $nombre || ! $hash || ! preg_match('/^[a-f0-9-]{36}\.(pdf|png|jpg)$/D', $nombre)) {
            return false;
        }

        $ruta = 'evidencias/'.$nombre;
        try {
            $checksum = $this->disco()->checksum($ruta, ['checksum_algo' => 'sha256']);

            return is_string($checksum) && hash_equals($hash, $checksum);
        } catch (\League\Flysystem\FilesystemException) {
            return false;
        }
    }

    public function descargarEvidencia(string $nombre, string $mime, string $hash, int $id): StreamedResponse
    {
        abort_unless(isset(self::EXTENSIONES[$mime]) && preg_match('/^[a-f0-9-]{36}\.(pdf|png|jpg)$/D', $nombre), 404);

        return $this->descargar('evidencias/'.$nombre, 'evidencia-'.$id.'.'.self::EXTENSIONES[$mime], $mime, $hash);
    }

    public function descargarInforme(string $folio, string $hash): StreamedResponse
    {
        abort_unless(Str::isUuid($folio), 404);

        return $this->descargar('informes/'.$folio.'.pdf', 'informe-'.$folio.'.pdf', 'application/pdf', $hash);
    }

    private function descargar(string $ruta, string $nombre, string $mime, string $hash): StreamedResponse
    {
        $disco = $this->disco();
        abort_unless($disco->exists($ruta), 404);
        // Se verifica y se transmite el mismo descriptor para evitar carreras entre lecturas.
        $flujo = $disco->readStream($ruta);
        abort_unless(is_resource($flujo), 404);
        $contexto = hash_init('sha256');
        hash_update_stream($contexto, $flujo);
        if (! hash_equals($hash, hash_final($contexto)) || ! rewind($flujo)) {
            fclose($flujo);
            abort(409, 'El archivo no coincide con la versión registrada.');
        }

        return response()->streamDownload(function () use ($flujo): void {
            try {
                fpassthru($flujo);
            } finally {
                fclose($flujo);
            }
        }, $nombre, [
            'Content-Type' => $mime, 'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store', 'Content-Security-Policy' => "sandbox; default-src 'none'",
        ]);
    }
}
