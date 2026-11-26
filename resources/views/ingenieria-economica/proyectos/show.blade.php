@extends('layouts.ingenieria-economica')

@php
    use App\Services\IngenieriaEconomica\DecimalFinanciero;
@endphp

@section('economia-content')
<div class="topbar">
    <h2>{{ $proyecto->nombre }}</h2>
    <div class="actions">
        <a class="button secondary" href="{{ route('ingenieria-economica.proyectos.index') }}">← Mis proyectos</a>
        <a class="button" href="{{ route('ingenieria-economica.proyectos.edit', $proyecto) }}">Editar proyecto</a>
    </div>
</div>

<section aria-label="Datos del proyecto">
    <dl class="panel-grid summary">
        <div class="panel-link"><dt>Organización</dt><dd>{{ $proyecto->organizacion ?? 'Sin registrar' }}</dd></div>
        <div class="panel-link"><dt>Tipo de decisión</dt><dd>{{ $proyecto::TIPOS_DECISION[$proyecto->tipo_decision] }}</dd></div>
        <div class="panel-link"><dt>Fecha base</dt><dd>{{ $proyecto->fecha_base->format('d/m/Y') }}</dd></div>
        <div class="panel-link"><dt>Horizonte en años</dt><dd>{{ $proyecto->horizonte_anios }}</dd></div>
        <div class="panel-link"><dt>Estado administrativo</dt><dd>{{ $proyecto::ESTADOS[$proyecto->estado] }}</dd></div>
        <div class="panel-link"><dt>Preparación documental</dt><dd>{{ $calidad['puede_validar'] ? 'Sin bloqueos críticos' : count($calidad['bloqueos']).' bloqueos por revisar' }}</dd></div>
        <div class="panel-link"><dt>Alternativas registradas</dt><dd>{{ $alternativas->total() }}</dd></div>
        <div class="panel-link"><dt>Escenario principal</dt><dd>{{ $proyecto->escenarioPrincipal?->nombre ?? 'Sin definir' }}</dd></div>
    </dl>
    <p class="section"><strong>Siguiente paso sugerido</strong> · <a href="{{ $siguiente[1] }}">{{ $siguiente[0] }}</a></p>
    <div class="actions section">
        <a class="button secondary" href="{{ $escenarios->total() ? '#escenarios' : route('ingenieria-economica.proyectos.escenarios.create', $proyecto) }}">Configurar escenario</a>
        <a class="button secondary" href="{{ route('ingenieria-economica.proyectos.conceptos.create', $proyecto) }}">Registrar concepto</a>
        <a class="button" href="{{ route('ingenieria-economica.proyectos.evidencias.index', $proyecto) }}">Evidencias documentales</a>
        <a class="button" href="{{ route('ingenieria-economica.proyectos.validacion', $proyecto) }}">Validación del proyecto</a>
        <a class="button" href="{{ route('ingenieria-economica.proyectos.informes.index', $proyecto) }}">Resultados económicos e informes</a>
        <a class="button" href="{{ route('ingenieria-economica.proyectos.evaluaciones.index', $proyecto) }}">Evaluación financiera</a>
        <a class="button" href="{{ route('ingenieria-economica.proyectos.sensibilidad.index', $proyecto) }}">Análisis de sensibilidad</a>
        <a class="button" href="{{ route('ingenieria-economica.proyectos.activos.index', $proyecto) }}">Activos y depreciación</a>
        <a class="button" href="{{ route('ingenieria-economica.proyectos.flujos.index', $proyecto) }}">Proyectar y comparar flujos</a>
        <a class="button secondary" href="{{ route('ingenieria-economica.proyectos.conceptos.index', $proyecto) }}">Conceptos comunes</a>
    </div>
    @if ($proyecto->descripcion)
        <p class="description">{{ $proyecto->descripcion }}</p>
    @endif
</section>

<section class="section panel-link" id="alternativas">
    <div class="topbar">
        <h3>Alternativas</h3>
        <a class="button" href="{{ route('ingenieria-economica.proyectos.alternativas.create', $proyecto) }}">Agregar alternativa</a>
    </div>
    @error('alternativa')
        <p class="errors">{{ $message }}</p>
    @enderror
    <ul class="records">
        @forelse ($alternativas as $alternativa)
            <li>
                <strong>{{ $alternativa->nombre }}</strong>
                <a href="{{ route('ingenieria-economica.proyectos.evidencias.index',[$proyecto,'tipo'=>'alternativa','registro'=>$alternativa->id]) }}">Respaldo documental</a>
                <div class="muted">
                    {{ $alternativa->proveedor ? $alternativa->proveedor . ' · ' : '' }}
                    {{ $alternativa::ESTADOS[$alternativa->estado_implementacion] }} ·
                    Viabilidad técnica: {{ $alternativa::VIABILIDADES[$alternativa->viabilidad_tecnica] }}
                    @if ($alternativa->fecha_implementacion)
                        · {{ $alternativa->fecha_implementacion->format('d/m/Y') }}
                    @endif
                </div>
                @if ($alternativa->descripcion)
                    <p class="description">{{ $alternativa->descripcion }}</p>
                @endif
                @if ($alternativa->observaciones)
                    <p class="description">{{ $alternativa->observaciones }}</p>
                @endif
                <div class="actions">
                    <a class="button" href="{{ route('ingenieria-economica.proyectos.alternativas.edit', [$proyecto, $alternativa]) }}">Editar</a>
                    <form method="POST" action="{{ route('ingenieria-economica.proyectos.alternativas.destroy', [$proyecto, $alternativa]) }}"
                        onsubmit="return confirm('¿Eliminar esta alternativa? Solo se permite si no se ha implementado y no tiene registros vinculados.')">
                        @csrf
                        @method('DELETE')
                        <button class="button warning" type="submit" aria-label="Eliminar alternativa {{ $alternativa->nombre }}">Eliminar</button>
                    </form>
                    <a class="button secondary" href="{{ route('ingenieria-economica.proyectos.conceptos.index', ['proyecto' => $proyecto, 'alternativa_id' => $alternativa->id]) }}">Conceptos</a>
                    <a class="button secondary" href="{{ route('ingenieria-economica.proyectos.flujos.index', ['proyecto' => $proyecto, 'alternativas' => [$alternativa->id]]) }}">Ver flujos</a>
                </div>
            </li>
        @empty
            <li class="muted">Todavía no hay alternativas registradas.</li>
        @endforelse
    </ul>
    @include('ingenieria-economica._paginacion', ['paginas' => $alternativas->withQueryString()->fragment('alternativas')])
</section>

<section class="section panel-link" id="escenarios">
    <div class="topbar">
        <h3>Escenarios</h3>
        <a class="button" href="{{ route('ingenieria-economica.proyectos.escenarios.create', $proyecto) }}">Configurar escenario</a>
    </div>
    <ul class="records">
        @forelse ($escenarios as $escenario)
            <li>
                <strong>{{ $escenario->nombre }}</strong>
                <a href="{{ route('ingenieria-economica.proyectos.evidencias.index',[$proyecto,'tipo'=>'escenario','registro'=>$escenario->id]) }}">Respaldo documental</a>
                @if ($escenario->es_principal)
                    <span class="muted">· Principal</span>
                @endif
                <div class="muted">
                    {{ $escenario->horizonte_anios }} años · {{ $escenario::PERIODOS[$escenario->periodicidad] }} ·
                    Tasa {{ strtolower($escenario::ESTADOS_TASA[$escenario->estado_tasa]) }}
                </div>
                @if ($escenario->tasa_original !== null)
                    <div class="muted">
                        Tasa original: {{ DecimalFinanciero::porcentaje($escenario->tasa_original) }} %
                        · {{ $escenario::MODALIDADES[$escenario->modalidad_tasa] }}
                        · {{ $escenario::PERIODOS_TASA[$escenario->periodicidad_tasa] }}
                        @if ($escenario->tasa_efectiva_anual !== null)
                            · Efectiva anual: {{ DecimalFinanciero::porcentaje($escenario->tasa_efectiva_anual) }} %
                        @endif
                    </div>
                @endif
                @if ($escenario->fuente_tasa)
                    <p class="description">Fuente: {{ $escenario->fuente_tasa }}</p>
                @endif
                <div class="actions">
                    <a class="button" href="{{ route('ingenieria-economica.proyectos.escenarios.edit', [$proyecto, $escenario]) }}">Editar</a>

        @include('ingenieria-economica._eliminar',['ruta'=>route('ingenieria-economica.proyectos.escenarios.destroy',[$proyecto,$escenario]),'nombre'=>$escenario->nombre,'accion'=>'Eliminar','conserva'=>'Los resultados guardados y las evidencias se conservan.'])
                    @unless ($escenario->es_principal)
                        <form method="POST" action="{{ route('ingenieria-economica.proyectos.escenarios.principal', [$proyecto, $escenario]) }}">
                            @csrf
                            @method('PATCH')
                            <button class="button secondary" type="submit">Definir como principal</button>
                        </form>
                    @endunless
                </div>
            </li>
        @empty
            <li class="muted">Todavía no hay escenarios configurados.</li>
        @endforelse
    </ul>
    @include('ingenieria-economica._paginacion', ['paginas' => $escenarios->withQueryString()->fragment('escenarios')])
</section>

<p class="muted">Registra los conceptos y consulta los flujos del proyecto antes de comparar alternativas.</p>
@endsection
