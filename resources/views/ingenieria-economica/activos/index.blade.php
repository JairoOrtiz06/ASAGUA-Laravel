@extends('layouts.ingenieria-economica')

@section('economia-content')
<div class="topbar">
    <div><h2>Activos y depreciación</h2><p class="muted">{{ $proyecto->nombre }}</p></div>
    <div class="actions">
        <a class="button secondary" href="{{ route('ingenieria-economica.proyectos.show', $proyecto) }}">← Proyecto</a>
        <a class="button" href="{{ route('ingenieria-economica.proyectos.activos.create', $proyecto) }}">Registrar activo</a>
    </div>
</div>
<div class="table-wrap">
    <table>
        <thead><tr><th>Activo</th><th>Clasificación</th><th>Ámbito</th><th>Vida útil</th><th>Estado</th></tr></thead>
        <tbody>
            @forelse ($activos as $activo)
                <tr>
                    <th scope="row"><a href="{{ route('ingenieria-economica.proyectos.activos.show', [$proyecto, $activo]) }}">{{ $activo->nombre }}</a></th>
                    <td>{{ $activo::CLASIFICACIONES[$activo->clasificacion_contable] }}</td>
                    <td>{{ $activo->alternativa?->nombre ?? 'Compartido en el proyecto' }}</td>
                    <td>{{ $activo->vida_indefinida ? 'Indefinida' : ($activo->vida_util_anios ?? '—') }}</td>
                    <td>{{ $activo::ESTADOS[$activo->estado_activo] }}</td>
                </tr>
            @empty
                <tr><td colspan="5">Aún no hay activos registrados.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@include('ingenieria-economica._paginacion', ['paginas' => $activos])
@endsection
