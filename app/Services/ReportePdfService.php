<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;

class ReportePdfService
{
    public function __construct(private readonly LibroContableService $libros)
    {
    }

    public function libroDiario(?string $fechaInicio = null, ?string $fechaFin = null): string
    {
        $movimientos = $this->libros->diario($fechaInicio, $fechaFin);
        $totalDebe = $this->sumarImportes($movimientos->pluck('debe')->all());
        $totalHaber = $this->sumarImportes($movimientos->pluck('haber')->all());
        $subtitulo = 'Registro de todas las partidas contables';

        if ($fechaInicio !== null || $fechaFin !== null) {
            $subtitulo = sprintf(
                'Partidas del %s al %s',
                $fechaInicio ?: 'inicio',
                $fechaFin ?: 'hoy',
            );
        }

        return Pdf::loadView('pdf.libro-diario', compact(
            'movimientos', 'totalDebe', 'totalHaber', 'subtitulo',
        ))
            ->setPaper('letter')
            ->output();
    }

    public function libroMayor(int $cuentaId, ?string $fechaInicio = null, ?string $fechaFin = null): string
    {
        $mayor = $this->libros->mayor($cuentaId, $fechaInicio, $fechaFin);

        return Pdf::loadView('pdf.libro-mayor', [
            'mayor' => $mayor,
            'subtitulo' => $this->subtitulo('Movimientos agrupados por cuenta contable', $fechaInicio, $fechaFin),
        ])
            ->setPaper('letter', 'landscape')
            ->output();
    }

    public function balanceComprobacion(?string $fechaInicio = null, ?string $fechaFin = null): string
    {
        $balance = $this->libros->balanceComprobacion($fechaInicio, $fechaFin);

        return Pdf::loadView('pdf.balance-comprobacion', [
            'balance' => $balance,
            'subtitulo' => $this->subtitulo('Saldos deudores y acreedores por cuenta', $fechaInicio, $fechaFin),
        ])
            ->setPaper('letter')
            ->output();
    }

    public function balanceGeneral(?string $fechaInicio = null, ?string $fechaFin = null): string
    {
        $balance = $this->libros->balanceGeneral($fechaInicio, $fechaFin);
        $balance['diferencia'] = $this->formatearImporte(
            $this->importeCentavos($balance['total_activos'])
                - $this->importeCentavos($balance['total_pasivos_patrimonio']),
        );

        return Pdf::loadView('pdf.balance-general', [
            'balance' => $balance,
            'subtitulo' => $fechaFin === null ? 'Situación financiera de la entidad' : 'Situación financiera al '.$fechaFin,
        ])
            ->setPaper('letter')
            ->output();
    }

    public function estadoResultados(?string $fechaInicio = null, ?string $fechaFin = null): string
    {
        $estado = $this->libros->estadoResultados($fechaInicio, $fechaFin);

        return Pdf::loadView('pdf.estado-resultados', [
            'estado' => $estado,
            'subtitulo' => $this->subtitulo('Ingresos, gastos y resultado neto del ejercicio', $fechaInicio, $fechaFin),
        ])
            ->setPaper('letter')
            ->output();
    }

    /** @param array<int, string> $importes */
    private function sumarImportes(array $importes): string
    {
        $total = 0;
        foreach ($importes as $importe) {
            $total += $this->importeCentavos($importe);
        }

        return $this->formatearImporte($total);
    }

    private function importeCentavos(string $importe): int
    {
        $valor = trim($importe);
        $negativo = str_starts_with($valor, '-');
        $valor = ltrim($valor, '+-');
        [$entero, $decimal] = array_pad(explode('.', $valor, 2), 2, '');
        $centavos = ((int) $entero * 100) + (int) str_pad(substr($decimal, 0, 2), 2, '0');

        return $negativo ? -$centavos : $centavos;
    }

    private function formatearImporte(int $centavos): string
    {
        $signo = $centavos < 0 ? '-' : '';
        $absoluto = abs($centavos);

        return $signo.intdiv($absoluto, 100).'.'.str_pad((string) ($absoluto % 100), 2, '0', STR_PAD_LEFT);
    }

    private function subtitulo(string $base, ?string $fechaInicio, ?string $fechaFin): string
    {
        if ($fechaInicio === null && $fechaFin === null) {
            return $base;
        }

        return sprintf('%s — período del %s al %s', $base, $fechaInicio ?: 'inicio', $fechaFin ?: 'hoy');
    }
}
