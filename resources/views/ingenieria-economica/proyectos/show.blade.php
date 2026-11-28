@extends('layouts.ingenieria-economica')

@php
    use Illuminate\Support\Str;
    use App\Models\IngenieriaEconomica\Evaluacion;
    use App\Models\IngenieriaEconomica\Escenario;
    use App\Services\IngenieriaEconomica\ContextoEvaluacion;

    $descripcionBreve = Str::squish($proyecto->descripcion ?? '');
@endphp

@section('economia-content')
@if ($avisoPreferencia = request()->attributes->get('aviso_preferencia_evaluacion'))
    <p class="economia-aviso" role="status">{{ $avisoPreferencia }}</p>
@endif

<div class="topbar proyecto-encabezado" id="resumen-proyecto" tabindex="-1">
    <h1 class="proyecto-titulo">{{ $proyecto->nombre }}</h1>
    <div class="actions">
        <a class="button button-regresar" href="{{ route('ingenieria-economica.proyectos.index') }}"><span class="button-regresar-icono" aria-hidden="true">←</span> Volver a mis proyectos</a>
        <a class="button" href="{{ ContextoEvaluacion::enlace('edit', $proyecto) }}">Editar proyecto</a>
    </div>
</div>

<section aria-label="Datos del proyecto">
    <dl class="summary proyecto-contexto">
        <div><dt>Organización</dt><dd>{{ $proyecto->organizacion ?? 'Sin registrar' }}</dd></div>
        <div><dt>Tipo de decisión</dt><dd>{{ $proyecto::TIPOS_DECISION[$proyecto->tipo_decision] }}</dd></div>
        <div><dt>Fecha base</dt><dd>{{ $proyecto->fecha_base->format('d/m/Y') }}</dd></div>
        <div><dt>Horizonte</dt><dd>{{ $proyecto->horizonte_anios }} años</dd></div>
        <div><dt>Estado administrativo</dt><dd>{{ $proyecto::ESTADOS[$proyecto->estado] }}</dd></div>
        <div><dt>Alternativas registradas</dt><dd>{{ $proyecto->alternativas_count }}</dd></div>
        <div><dt>Escenario principal</dt><dd>{{ $proyecto->escenarioPrincipal?->nombre ?? 'Sin escenario principal' }}</dd></div>
        <div><dt>Preparación documental</dt><dd>{{ isset($progreso['alcance']) ? ($progreso['alcance']['calidad']['puede_validar'] ? 'Sin bloqueos críticos para la selección' : count($progreso['alcance']['calidad']['bloqueos']).' requisitos críticos por revisar') : 'Sin revisión de una evaluación guardada seleccionada' }}</dd></div>
    </dl>
    @if (Str::length($descripcionBreve) > 110)
        <details class="proyecto-descripcion">
            <summary>Descripción del proyecto <span class="proyecto-descripcion-breve">{{ Str::limit($descripcionBreve, 110, preserveWords: true) }}</span></summary>
            <p class="description">{{ $proyecto->descripcion }}</p>
        </details>
    @elseif ($descripcionBreve !== '')
        <p class="proyecto-descripcion">{{ $descripcionBreve }}</p>
    @endif
</section>

<section class="section panel-link" aria-labelledby="evaluacion-seleccionada">
    <div class="topbar">
        <h2 id="evaluacion-seleccionada">Evaluación seleccionada</h2>
        <a class="button secondary" href="{{ ContextoEvaluacion::enlace('evaluaciones.index', $proyecto) }}#seleccion-evaluacion">Cambiar selección</a>
    </div>
    @if ($contexto)
        <dl class="summary proyecto-contexto">
            <div><dt>Escenario</dt><dd>{{ $contexto->historica?->instantanea['escenario']['nombre'] ?? $contexto->escenario?->nombre }}</dd></div>
            <div><dt>Alternativas</dt><dd>{{ implode(', ', $progreso['nombres']) }}</dd></div>
            <div><dt>Moneda y periodicidad</dt><dd>{{ $contexto->datos['moneda'] }} · {{ Escenario::PERIODOS[$contexto->periodicidadEfectiva()] }}</dd></div>
            <div><dt>Perspectiva</dt><dd>{{ Evaluacion::PERSPECTIVAS_PRESENTACION[$contexto->datos['perspectiva']] }}</dd></div>
            @if (count($contexto->datos['alternativas']) > 1)
                <div><dt>Alternativa de referencia</dt><dd>{{ $progreso['nombres'][$contexto->datos['referencia_id']] ?? 'Sin referencia' }}</dd></div>
            @endif
            @if ($progreso['evaluacion'])
                @php($calidadSeleccion = $progreso['alcance']['calidad'] ?? null)
                <div><dt>Estado de la evaluación</dt><dd>
                    Guardada
                    @if ($calidadSeleccion['vigencia']['vigente'] ?? false) · Vigente @endif
                    @if ($calidadSeleccion && ! $calidadSeleccion['puede_validar']) · Preliminar para esta decisión @endif
                    @if ($calidadSeleccion['puede_validar'] ?? false) · Documentalmente validable @endif
                </dd></div>
                <div><dt>Versión</dt><dd><a href="{{ ContextoEvaluacion::enlace('evaluaciones.show', [$proyecto, $progreso['evaluacion']]) }}">Evaluación #{{ $progreso['evaluacion']->id }}</a>{{ $contexto->historica ? ' · consulta de instantánea histórica' : '' }}</dd></div>
            @endif
        </dl>
    @else
        <p>Elige escenario, alternativas, moneda y perspectiva en Evaluación. Los estados se actualizarán al aplicar la selección.</p>
    @endif
</section>

@include('ingenieria-economica.proyectos._recorrido')
@endsection
