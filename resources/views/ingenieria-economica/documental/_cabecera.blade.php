@use('App\Services\IngenieriaEconomica\ContextoEvaluacion')

<div class="topbar">
    <div><h2>{{ $titulo }}</h2><p class="muted">{{ $nombreProyecto ?? $proyecto->nombre }}</p></div>
    <div class="actions">
        <a class="button button-regresar" href="{{ $volverUrl ?? ContextoEvaluacion::enlace('show',$proyecto) }}"><span class="button-regresar-icono" aria-hidden="true">←</span> {{ $volverTexto ?? 'Proyecto' }}</a>
        @unless (request()->routeIs('ingenieria-economica.proyectos.evidencias.*'))
            <a class="button secondary" href="{{ ContextoEvaluacion::enlace('evidencias.index', [$proyecto, 'origen' => match (true) {
                request()->routeIs('ingenieria-economica.proyectos.informes.*') => 'informes',
                request()->routeIs('ingenieria-economica.proyectos.validacion') => 'validacion',
                request()->routeIs('ingenieria-economica.proyectos.referencias') => 'referencias',
                default => null,
            }]) }}">Expediente</a>
        @endunless
        @if (($volverUrl ?? null) !== ContextoEvaluacion::enlace('validacion', $proyecto))
            <a class="button secondary" href="{{ ContextoEvaluacion::enlace('validacion',$proyecto) }}">Validación</a>
        @endif
        @if (($volverUrl ?? null) !== ContextoEvaluacion::enlace('informes.index', $proyecto))
            <a class="button secondary" href="{{ ContextoEvaluacion::enlace('informes.index',$proyecto) }}">Informes</a>
        @endif
    </div>
</div>
