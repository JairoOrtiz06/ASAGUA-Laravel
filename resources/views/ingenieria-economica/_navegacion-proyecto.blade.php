@php
    $prefijoProyecto = 'ingenieria-economica.proyectos.';
    $seccionesProyecto = [
        'alternativas'=>['Alternativas', 'show', '#alternativas'],
        'escenarios'=>['Escenarios y tasas', 'show', '#escenarios'],
        'conceptos'=>['Conceptos', 'conceptos.index', ''],
        'flujos'=>['Flujos de efectivo', 'flujos.index', ''],
        'evaluaciones'=>['Evaluación', 'evaluaciones.index', ''],
        'activos'=>['Activos y reemplazo', 'activos.index', ''],
        'sensibilidad'=>['Sensibilidad', 'sensibilidad.index', ''],
        'evidencias'=>['Evidencias', 'evidencias.index', ''],
        'validacion'=>['Validación', 'validacion', ''],
        'informes'=>['Resultados e informes', 'informes.index', ''],
    ];
    $nombreProyectoRuta = $resultado['proyecto']['nombre'] ?? $informe['evaluacion']['proyecto']['nombre'] ?? $proyecto->nombre;
@endphp
<nav aria-label="Ubicación del proyecto">
    <ol class="economia-ruta">
        <li><a href="{{ route($prefijoProyecto.'index') }}">Mis proyectos</a></li>
        <li aria-hidden="true">/</li>
        <li>
            <a href="{{ route($prefijoProyecto.'show',$proyecto) }}" @if(request()->routeIs($prefijoProyecto.'show')) aria-current="page" @endif>{{ $nombreProyectoRuta }}</a>
        </li>
    </ol>
</nav>
<details class="section" @if(request()->routeIs($prefijoProyecto.'show')) open @endif>
    <summary>Secciones de este proyecto</summary>
    <nav aria-label="Secciones del proyecto">
        <ul class="economia-ruta">
            @foreach ($seccionesProyecto as $grupo => [$nombre, $ruta, $fragmento])
                @php($activa = request()->routeIs($prefijoProyecto.$grupo, $prefijoProyecto.$grupo.'.*') || ($grupo === 'evidencias' && request()->routeIs($prefijoProyecto.'referencias')))
                <li><a href="{{ route($prefijoProyecto.$ruta,$proyecto).$fragmento }}"
                    @if($activa) aria-current="{{ request()->routeIs($prefijoProyecto.$ruta) ? 'page' : 'location' }}" @endif>{{ $nombre }}</a></li>
            @endforeach
        </ul>
    </nav>
</details>
