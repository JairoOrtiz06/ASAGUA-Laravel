@extends('layouts.ingenieria-economica')

@use('App\Services\IngenieriaEconomica\ContextoEvaluacion')

@php
    use App\Models\IngenieriaEconomica\Escenario;
    use App\Services\IngenieriaEconomica\DecimalFinanciero;
    use App\Services\IngenieriaEconomica\ProyeccionFlujosService;
@endphp

@section('economia-content')
<div class="topbar">
    <div>
        <h2>Flujos de efectivo</h2>
        <p class="muted">{{ $proyecto->nombre }}</p>
    </div>
    <div class="actions">
        <a class="button button-regresar" href="{{ ContextoEvaluacion::enlace('show', $proyecto) }}"><span class="button-regresar-icono" aria-hidden="true">←</span> Proyecto</a>
        <a class="button button-regresar" href="{{ route('ingenieria-economica.proyectos.index') }}"><span class="button-regresar-icono" aria-hidden="true">←</span> Cambiar proyecto</a>
        @if (! $contexto?->historica && $escenario && $alternativas->isNotEmpty())
            <a class="button" href="{{ ContextoEvaluacion::enlace('ajustes-flujo.create', [
                'proyecto' => $proyecto, 'escenario_id' => $escenario->id,
                'alternativas' => $seleccionadas, 'moneda' => $moneda,
            ]) }}">Agregar ajuste manual</a>
        @endif
    </div>
</div>

@if ($contexto?->historica)
    <p>Los flujos de la evaluación #{{ $contexto->historica->id }} se conservan en su memoria. Una nueva proyección usaría los datos actuales.</p>
    <a class="button" href="{{ ContextoEvaluacion::enlace('evaluaciones.show', [$proyecto, $contexto->historica]) }}">Ver memoria de la evaluación</a>
@elseif ($escenarios->isEmpty() || $alternativas->isEmpty())
    <p class="muted">Crea al menos una alternativa y un escenario en el proyecto para generar flujos.</p>
@else
    <form class="panel-link" method="GET" action="{{ ContextoEvaluacion::enlace('flujos.index', $proyecto) }}">
        @include('ingenieria-economica._contexto-campos')
        <div class="form-grid">
            @include('ingenieria-economica._campo', [
                'campo' => 'escenario_id', 'etiqueta' => 'Escenario', 'tipo' => 'select', 'opciones' => $escenarios->pluck('nombre', 'id'),
                'valor' => $escenario?->id, 'obligatorio' => true,
            ])
            @include('ingenieria-economica._campo', [
                'campo' => 'agrupacion', 'etiqueta' => 'Agrupar por', 'tipo' => 'select', 'opciones' => ProyeccionFlujosService::AGRUPACIONES,
                'valor' => $agrupacion, 'obligatorio' => true,
            ])
            @include('ingenieria-economica._campo', ['campo' => 'moneda', 'etiqueta' => 'Moneda', 'valor' => $moneda, 'obligatorio' => true, 'maximo' => 3])
            @include('ingenieria-economica._campo', [
                'campo' => 'desde', 'etiqueta' => 'Mostrar desde el período', 'tipo' => 'number',
                'minimo' => 0, 'maximoNumerico' => 1200, 'valor' => $filtros['desde'] ?? null,
            ])
            @include('ingenieria-economica._campo', [
                'campo' => 'hasta', 'etiqueta' => 'Mostrar hasta el período', 'tipo' => 'number',
                'minimo' => 0, 'maximoNumerico' => 1200, 'valor' => $filtros['hasta'] ?? null,
            ])
            <fieldset class="field-full">
                <legend>Alternativas</legend>
                <p class="muted">Selecciona de 1 a 10 alternativas para consultar o comparar.</p>
                @foreach ($alternativas as $alternativa)
                    <label class="flujo-opcion">
                        <input type="checkbox" name="alternativas[]" value="{{ $alternativa->id }}" @checked(in_array($alternativa->id, $seleccionadas, true))>
                        {{ $alternativa->nombre }}
                    </label>
                @endforeach
            </fieldset>
        </div>
        <div class="actions"><button class="button" type="submit">Generar flujos</button></div>
    </form>
@endif

@if ($aviso)
    <p class="errors">{{ $aviso }}</p>
@endif

@if ($proyeccion)
    <p><a class="button secondary" href="{{ route('ingenieria-economica.proyectos.evaluaciones.index', [$proyecto, ...$paraEvaluar]) }}#seleccion-evaluacion">Usar esta selección en evaluación</a></p>
    <p class="section muted">Base {{ $proyeccion['base']->format('d/m/Y') }} · Cierre {{ $proyeccion['fin']->format('d/m/Y') }} ·
        {{ $moneda }} · Período original {{ strtolower(Escenario::PERIODOS[$escenario->periodicidad]) }}.
        Los saldos son nominales acumulados sin descuento. Las valoraciones económicas se muestran aparte de la caja.</p>

    @if (count($proyeccion['resultado']) > 1)
        @include('ingenieria-economica.flujos._comparacion', ['resultados' => $proyeccion['resultado']])
    @endif

    @foreach ($proyeccion['resultado'] as $resultado)
        @include('ingenieria-economica.flujos._alternativa')
    @endforeach
@endif
@endsection
