<?php

namespace App\Http\Controllers\IngenieriaEconomica;

use App\Http\Controllers\Controller;
use App\Http\Requests\IngenieriaEconomica\ConsultaFlujosRequest;
use App\Models\IngenieriaEconomica\Proyecto;
use App\Services\IngenieriaEconomica\ProyeccionFlujosService;
use DomainException;
use Illuminate\View\View;

class FlujoController extends Controller
{
    public function index(ConsultaFlujosRequest $request, Proyecto $proyecto, ProyeccionFlujosService $servicio): View
    {
        $filtros = $request->validated();
        $escenarios = $proyecto->escenarios()->orderByDesc('es_principal')->orderBy('nombre')->get();
        $alternativas = $proyecto->alternativas()->orderBy('nombre')->get();
        $escenario = $escenarios->find($filtros['escenario_id'] ?? $escenarios->first()?->id);
        $seleccionadas = array_map('intval', $filtros['alternativas'] ?? $alternativas->take(1)->modelKeys());
        $moneda = $filtros['moneda'] ?? 'USD';
        $agrupacion = $filtros['agrupacion'] ?? 'escenario';
        $proyeccion = $aviso = null;

        if ($escenario !== null && $seleccionadas !== []) {
            try {
                $proyeccion = $servicio->generar(
                    $proyecto, $escenario, $seleccionadas, $moneda, $agrupacion,
                    (int) ($filtros['desde'] ?? 0), isset($filtros['hasta']) ? (int) $filtros['hasta'] : null,
                );
            } catch (DomainException $error) {
                $aviso = $error->getMessage();
            }
        }

        return view('ingenieria-economica.flujos.index', compact(
            'proyecto', 'escenarios', 'alternativas', 'escenario', 'seleccionadas', 'moneda', 'agrupacion',
            'proyeccion', 'aviso', 'filtros',
        ));
    }
}
