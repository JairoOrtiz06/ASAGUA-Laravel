@extends('layouts.ingenieria-economica')

@use('App\Services\IngenieriaEconomica\ContextoEvaluacion')

@php
    use App\Models\IngenieriaEconomica\Evaluacion;
    use App\Services\IngenieriaEconomica\ProyeccionFlujosService;

    $contexto = ContextoEvaluacion::actual();
    $seleccion = $contexto?->datos ?? [];
    $seleccionadas = array_map('strval', array_filter((array) old('alternativas', $seleccion['alternativas'] ?? []), 'is_scalar'));
@endphp

@section('economia-content')
@if ($avisoPreferencia = request()->attributes->get('aviso_preferencia_evaluacion'))
    <p class="economia-aviso" role="status">{{ $avisoPreferencia }}</p>
@endif

<div class="topbar">
    <div>
        <h2>Evaluación financiera</h2>
        <p class="muted">{{ $proyecto->nombre }}</p>
    </div>
    <div class="actions">
        <a class="button button-regresar" href="{{ ContextoEvaluacion::enlace('show', $proyecto) }}"><span class="button-regresar-icono" aria-hidden="true">←</span> Proyecto</a>
        <a class="button button-regresar" href="{{ route('ingenieria-economica.proyectos.index') }}"><span class="button-regresar-icono" aria-hidden="true">←</span> Cambiar proyecto</a>
    </div>
</div>

@if ($escenarios->isEmpty() || $alternativas->isEmpty())
    <p id="seleccion-evaluacion" class="muted">Registra un escenario con TMAR y al menos una alternativa para evaluar.</p>
@else
    @if (! $contexto)
        <p class="muted">Los valores sugeridos todavía no están aplicados al recorrido.</p>
    @elseif ($contexto->historica)
        <p class="economia-aviso">Los selectores parten de la versión #{{ $contexto->historica->id }}. Aplicar la selección inicia trabajo con datos actuales. La versión guardada sigue disponible.</p>
    @endif
    <form class="panel-link" id="seleccion-evaluacion" method="POST" action="{{ ContextoEvaluacion::enlace('evaluaciones.preview', $proyecto) }}">
        @csrf
        <div class="form-grid">
            @include('ingenieria-economica._campo', [
                'campo' => 'escenario_id', 'etiqueta' => 'Escenario', 'tipo' => 'select', 'obligatorio' => true,
                'valor' => $seleccion['escenario_id'] ?? $escenarios->first()->id,
                'opciones' => $escenarios->mapWithKeys(fn ($item) => [$item->id => $item->nombre.' · '.$item->horizonte_anios.' años · '.$item->periodicidad.' · TEA '.($item->tasa_efectiva_anual === null ? 'pendiente' : \App\Services\IngenieriaEconomica\DecimalFinanciero::porcentaje($item->tasa_efectiva_anual).' %')]),
            ])
            @include('ingenieria-economica._campo', [
                'campo' => 'perspectiva', 'etiqueta' => 'Perspectiva', 'tipo' => 'select',
                'opciones' => $evidenciasFiscales->isEmpty() && ($seleccion['perspectiva'] ?? null) !== 'fiscal' ? Evaluacion::PERSPECTIVAS : Evaluacion::PERSPECTIVAS_PRESENTACION, 'valor' => $seleccion['perspectiva'] ?? 'caja', 'obligatorio' => true,
            ])
            @include('ingenieria-economica._campo', [
                'campo' => 'periodicidad', 'etiqueta' => 'Periodicidad del descuento', 'tipo' => 'select',
                'opciones' => ProyeccionFlujosService::AGRUPACIONES, 'valor' => $seleccion['periodicidad'] ?? 'escenario', 'obligatorio' => true,
                'ayuda' => 'Cada flujo se descuenta al cierre del período elegido. Usa mensual para valorar pagos mensuales en sus cierres mensuales.',
            ])
            @include('ingenieria-economica._campo', ['campo' => 'moneda', 'etiqueta' => 'Moneda', 'valor' => $seleccion['moneda'] ?? 'USD', 'maximo' => 3, 'obligatorio' => true])
            <fieldset class="field-full">
                <legend>Alternativas a evaluar</legend>
                @foreach ($alternativas as $alternativa)
                    <label class="flujo-opcion">
                        <input type="checkbox" name="alternativas[]" value="{{ $alternativa->id }}" @checked(in_array((string) $alternativa->id, $seleccionadas, true))>
                        {{ $alternativa->nombre }}
                    </label>
                @endforeach
            </fieldset>
            @include('ingenieria-economica._campo', [
                'campo' => 'referencia_id', 'etiqueta' => 'Alternativa de referencia (B)', 'tipo' => 'select',
                'opciones' => $alternativas->pluck('nombre', 'id'), 'valor' => $seleccion['referencia_id'] ?? null,
                'ayuda' => 'Para la comparación A − B. Si queda vacía, se toma la primera alternativa seleccionada.',
            ])
            @include('ingenieria-economica._campo', [
                'campo' => 'equivalencia', 'etiqueta' => 'Equivalencia técnica y de servicio', 'tipo' => 'select',
                'opciones' => [0 => 'Pendiente de confirmar', 1 => 'Confirmada para el horizonte común'], 'valor' => 0,
                'ayuda' => 'Confirma condiciones técnicas, normativas y operativas. Las vidas útiles y reemplazos deben quedar sustentados.',
            ])
            @include('ingenieria-economica._campo', [
                'campo' => 'supuestos', 'etiqueta' => 'Supuestos y restricciones', 'tipo' => 'textarea',
                'ayuda' => 'Documenta cómo se cubre el horizonte, los supuestos de vida útil y cualquier condición pendiente.',
            ])
        </div>
        <div id="evaluacion-fiscal" tabindex="-1">
            @include('ingenieria-economica.evaluaciones._configuracion-fiscal')
            <div class="actions section">
                <a class="button secondary" href="{{ ContextoEvaluacion::enlace('activos.index', $proyecto) }}"
                    target="_blank" rel="noopener">Depreciación y otros cálculos de activos <span class="proyecto-solo-lector">(abre en una pestaña nueva)</span></a>
            </div>
        </div>
        <div class="actions section">
            <button class="button secondary" type="submit" formaction="{{ route('ingenieria-economica.proyectos.evaluaciones.seleccion', $proyecto) }}">Aplicar selección al recorrido</button>
            <button class="button" type="submit">Generar evaluación</button>
        </div>
    </form>
@endif

<section class="section panel-link">
    <h3 id="evaluaciones-guardadas" tabindex="-1">Evaluaciones guardadas</h3>
    @if ($evaluaciones->isNotEmpty())
        <p>Abre una evaluación para ver sus indicadores y la comparación incremental si incluye más de una alternativa.</p>
    @endif
    @if (($seleccion['perspectiva'] ?? null) !== 'fiscal')
        <div class="actions">
            <a class="button secondary" href="{{ ContextoEvaluacion::enlace('sensibilidad.index', $proyecto) }}">Sensibilidad y punto de equilibrio</a>
        </div>
    @endif
    <div class="table-wrap">
        <table>
            <thead><tr><th>Versión guardada</th><th>Fecha y hora</th><th>Perspectiva</th><th>Moneda</th><th>Motor</th></tr></thead>
            <tbody>
                @forelse ($evaluaciones as $guardada)
                    <tr>
                        <th scope="row"><a href="{{ ContextoEvaluacion::enlace('evaluaciones.show', [$proyecto, $guardada]) }}">Evaluación #{{ $guardada->id }}</a></th>
                        <td>{{ $guardada->created_at->format('d/m/Y H:i') }}</td>
                        <td>{{ Evaluacion::PERSPECTIVAS_PRESENTACION[$guardada->perspectiva_historica ?? $guardada->perspectiva] }}</td>
                        <td>{{ $guardada->moneda }}</td>
                        <td>{{ $guardada->version_motor }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5">Aún no hay evaluaciones guardadas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @include('ingenieria-economica._paginacion', ['paginas' => $evaluaciones])
</section>
@endsection
