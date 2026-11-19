<?php

namespace App\Http\Controllers;

use App\Http\Requests\LibroRequest;
use App\Services\LibroContableService;
use Illuminate\View\View;

class LibroContableController extends Controller
{
    public function diario(LibroRequest $request, LibroContableService $service): View
    {
        $movimientos = $service->diario(
            $request->validated('fecha_inicio'),
            $request->validated('fecha_fin'),
        );

        return view('libros.diario', [
            'movimientos' => $movimientos,
            'totalDebe' => $this->sumar($movimientos->pluck('debe')->all()),
            'totalHaber' => $this->sumar($movimientos->pluck('haber')->all()),
        ]);
    }

    public function mayor(LibroRequest $request, LibroContableService $service): View
    {
        $cuentas = $service->cuentasParaMayor();
        $mayor = null;
        if ($request->filled('cuenta_id')) {
            $mayor = $service->mayor(
                (int) $request->validated('cuenta_id'),
                $request->validated('fecha_inicio'),
                $request->validated('fecha_fin'),
            );
        }

        return view('libros.mayor', compact('cuentas', 'mayor'));
    }

    /** @param array<int, string> $importes */
    private function sumar(array $importes): string
    {
        $total = 0;
        foreach ($importes as $importe) {
            [$entero, $decimal] = array_pad(explode('.', $importe, 2), 2, '');
            $total += ((int) $entero * 100) + (int) str_pad($decimal, 2, '0');
        }

        return intdiv($total, 100).'.'.str_pad((string) ($total % 100), 2, '0', STR_PAD_LEFT);
    }
}
