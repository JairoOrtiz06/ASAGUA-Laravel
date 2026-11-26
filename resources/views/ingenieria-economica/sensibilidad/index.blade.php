@extends('layouts.ingenieria-economica')

@section('economia-content')
<div class="topbar">
    <div><h2>Análisis de sensibilidad</h2><p class="muted">{{ $proyecto->nombre }}</p></div>
    <div class="actions">
        <a class="button secondary" href="{{ route('ingenieria-economica.proyectos.show', $proyecto) }}">← Proyecto</a>
        <a class="button" href="{{ route('ingenieria-economica.proyectos.sensibilidad.create', $proyecto) }}">Nuevo análisis</a>
    </div>
</div>
<section class="panel-link">
    <h3>Historial de versiones</h3>
    <div class="table-wrap"><table>
        <thead><tr><th>Análisis</th><th>Fecha</th><th>Perspectiva</th><th>Estado</th></tr></thead>
        <tbody>
            @forelse ($analisis as $item)
                <tr><th scope="row"><a href="{{ route('ingenieria-economica.proyectos.sensibilidad.show', [$proyecto, $item]) }}">{{ $item->nombre }} #{{ $item->id }}</a></th>
                    <td>{{ $item->created_at->format('d/m/Y H:i') }}</td><td>{{ $item->perspectiva }}</td><td>{{ $item->estado }}</td></tr>
            @empty
                <tr><td colspan="4">No hay análisis guardados.</td></tr>
            @endforelse
        </tbody>
    </table></div>
    @include('ingenieria-economica._paginacion', ['paginas' => $analisis])
</section>
@endsection
