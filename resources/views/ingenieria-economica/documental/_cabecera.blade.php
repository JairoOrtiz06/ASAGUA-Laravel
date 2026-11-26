<div class="topbar">
    <div><h2>{{ $titulo }}</h2><p class="muted">{{ $nombreProyecto ?? $proyecto->nombre }}</p></div>
    <div class="actions">
        <a class="button secondary" href="{{ route('ingenieria-economica.proyectos.show',$proyecto) }}">← Proyecto</a>
        <a class="button secondary" href="{{ route('ingenieria-economica.proyectos.evidencias.index',$proyecto) }}">Expediente</a>
        <a class="button secondary" href="{{ route('ingenieria-economica.proyectos.validacion',$proyecto) }}">Validación</a>
        <a class="button secondary" href="{{ route('ingenieria-economica.proyectos.informes.index',$proyecto) }}">Informes</a>
    </div>
</div>
