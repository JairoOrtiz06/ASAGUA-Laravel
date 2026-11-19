<?php

namespace App\Http\Controllers;

use App\Http\Requests\LibroRequest;
use App\Services\ReportePdfService;
use Symfony\Component\HttpFoundation\Response;

class ReportePdfController extends Controller
{
    public function libroDiario(LibroRequest $request, ReportePdfService $service): Response
    {
        return $this->respuestaPdf(
            $service->libroDiario(
                $request->validated('fecha_inicio'),
                $request->validated('fecha_fin'),
            ),
            false,
        );
    }

    public function descargarLibroDiario(LibroRequest $request, ReportePdfService $service): Response
    {
        return $this->respuestaPdf(
            $service->libroDiario(
                $request->validated('fecha_inicio'),
                $request->validated('fecha_fin'),
            ),
            true,
        );
    }

    public function libroMayor(LibroRequest $request, ReportePdfService $service): Response
    {
        return $this->respuestaReporte(
            $service->libroMayor(
                $this->cuentaRequerida($request),
                $request->validated('fecha_inicio'),
                $request->validated('fecha_fin'),
            ),
            'libro-mayor.pdf',
            false,
        );
    }

    public function descargarLibroMayor(LibroRequest $request, ReportePdfService $service): Response
    {
        return $this->respuestaReporte(
            $service->libroMayor(
                $this->cuentaRequerida($request),
                $request->validated('fecha_inicio'),
                $request->validated('fecha_fin'),
            ),
            'libro-mayor.pdf',
            true,
        );
    }

    public function balanceComprobacion(LibroRequest $request, ReportePdfService $service): Response
    {
        return $this->respuestaReporte(
            $service->balanceComprobacion($request->validated('fecha_inicio'), $request->validated('fecha_fin')),
            'balance-comprobacion.pdf',
            false,
        );
    }

    public function descargarBalanceComprobacion(LibroRequest $request, ReportePdfService $service): Response
    {
        return $this->respuestaReporte(
            $service->balanceComprobacion($request->validated('fecha_inicio'), $request->validated('fecha_fin')),
            'balance-comprobacion.pdf',
            true,
        );
    }

    public function balanceGeneral(LibroRequest $request, ReportePdfService $service): Response
    {
        return $this->respuestaReporte(
            $service->balanceGeneral($request->validated('fecha_inicio'), $request->validated('fecha_fin')),
            'balance-general.pdf',
            false,
        );
    }

    public function descargarBalanceGeneral(LibroRequest $request, ReportePdfService $service): Response
    {
        return $this->respuestaReporte(
            $service->balanceGeneral($request->validated('fecha_inicio'), $request->validated('fecha_fin')),
            'balance-general.pdf',
            true,
        );
    }

    public function estadoResultados(LibroRequest $request, ReportePdfService $service): Response
    {
        return $this->respuestaReporte(
            $service->estadoResultados($request->validated('fecha_inicio'), $request->validated('fecha_fin')),
            'estado-resultados.pdf',
            false,
        );
    }

    public function descargarEstadoResultados(LibroRequest $request, ReportePdfService $service): Response
    {
        return $this->respuestaReporte(
            $service->estadoResultados($request->validated('fecha_inicio'), $request->validated('fecha_fin')),
            'estado-resultados.pdf',
            true,
        );
    }

    private function cuentaRequerida(LibroRequest $request): int
    {
        abort_unless($request->filled('cuenta_id'), 422, 'Debe seleccionar una cuenta contable.');

        return (int) $request->validated('cuenta_id');
    }

    private function respuestaPdf(string $contenido, bool $descargar): Response
    {
        return $this->respuestaReporte($contenido, 'libro-diario.pdf', $descargar);
    }

    private function respuestaReporte(string $contenido, string $nombre, bool $descargar): Response
    {
        return response($contenido, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($descargar ? 'attachment' : 'inline').'; filename="'.$nombre.'"',
        ]);
    }
}
