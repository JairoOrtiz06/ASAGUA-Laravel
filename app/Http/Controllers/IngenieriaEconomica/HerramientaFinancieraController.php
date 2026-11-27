<?php

namespace App\Http\Controllers\IngenieriaEconomica;

use App\Http\Controllers\Controller;
use App\Http\Requests\IngenieriaEconomica\HerramientaFinancieraRequest;
use App\Services\IngenieriaEconomica\ProcedimientoFinanciero;
use Illuminate\View\View;

class HerramientaFinancieraController extends Controller
{
    public const HERRAMIENTAS = [
        'tasas' => 'Conversión de tasas',
        'interes-compuesto' => 'Interés compuesto',
        'factores' => 'Factores de equivalencia',
        'series' => 'Series uniformes',
    ];

    public function index(): View
    {
        return view('ingenieria-economica.herramientas.index', ['herramientas' => self::HERRAMIENTAS]);
    }

    public function show(string $herramienta): View
    {
        return $this->formulario($herramienta);
    }

    public function calcular(HerramientaFinancieraRequest $request, string $herramienta, ProcedimientoFinanciero $servicio): View
    {
        $datos = $request->validated();

        return $this->formulario($herramienta, $datos, $servicio->calcular($herramienta, $datos));
    }

    private function formulario(string $herramienta, array $datos = [], ?array $resultado = null): View
    {
        return view('ingenieria-economica.herramientas.form', [
            'herramienta' => $herramienta,
            'titulo' => self::HERRAMIENTAS[$herramienta],
            'datos' => $datos,
            'resultado' => $resultado,
        ]);
    }
}
