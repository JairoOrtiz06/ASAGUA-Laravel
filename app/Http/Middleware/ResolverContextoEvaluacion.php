<?php

namespace App\Http\Middleware;

use App\Models\IngenieriaEconomica\Proyecto;
use App\Services\IngenieriaEconomica\ContextoEvaluacion;
use App\Services\IngenieriaEconomica\PreferenciaEvaluacion;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class ResolverContextoEvaluacion
{
    public function __construct(private PreferenciaEvaluacion $preferencia) {}

    public function handle(Request $request, Closure $next): Response
    {
        $proyecto = $request->route('proyecto');
        if ($proyecto instanceof Proyecto && $request->query->has('contexto')) {
            Gate::authorize('view', $proyecto);
            $entrada = $request->query()['contexto'];
            abort_unless(is_array($entrada), 422, 'La selección de evaluación es inválida.');

            try {
                $contexto = ContextoEvaluacion::resolver($proyecto, $entrada);
                $request->attributes->set(ContextoEvaluacion::class, $contexto);
            } catch (ValidationException $error) {
                if (! $request->isMethod('GET') || $request->expectsJson()) {
                    throw $error;
                }

                return redirect()->route(ContextoEvaluacion::RUTA.'evaluaciones.index', $proyecto)
                    ->withErrors($error->errors());
            }
        } elseif ($proyecto instanceof Proyecto && $request->isMethod('GET')
            && $request->routeIs(ContextoEvaluacion::RUTA.'show', ContextoEvaluacion::RUTA.'evaluaciones.index')) {
            Gate::authorize('view', $proyecto);
            $seleccion = $this->preferencia->recuperar($request->user(), $proyecto);

            if ($seleccion !== null) {
                try {
                    $contexto = ContextoEvaluacion::resolver($proyecto, ContextoEvaluacion::entrada($seleccion)['contexto']);
                } catch (ValidationException $error) {
                    $campos = [
                        'escenario_id' => 'escenario',
                        'alternativas' => 'alternativas',
                        'referencia_id' => 'alternativa de referencia',
                        'moneda' => 'moneda',
                        'periodicidad' => 'periodicidad',
                        'perspectiva' => 'perspectiva',
                    ];
                    $invalidos = array_unique(array_map(fn ($campo) => $campos[explode('.', $campo)[0]], array_keys($error->errors())));
                    $request->attributes->set('aviso_preferencia_evaluacion',
                        'Actualiza los siguientes datos de la selección guardada: '.implode(', ', $invalidos).'.');

                    return $next($request);
                }

                if ($request->session()->has('errors')) {
                    $request->attributes->set(ContextoEvaluacion::class, $contexto);

                    return $next($request);
                }

                return redirect()->route($request->route()->getName(), [
                    $proyecto, ...$contexto->consulta(), 'page' => $request->integer('page') ?: null,
                ])->header('Cache-Control', 'private, no-store');
            }
        }

        return $next($request);
    }
}
