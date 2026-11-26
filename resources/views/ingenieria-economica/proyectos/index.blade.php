@extends('layouts.ingenieria-economica')

@section('economia-content')
<section aria-labelledby="mis-proyectos">
    <div class="topbar">
        <div>
            <h2 id="mis-proyectos">Mis proyectos</h2>
            <p class="muted">Consulta tus proyectos de inversión y gestiona sus alternativas y escenarios.</p>
        </div>
        <a class="button" href="{{ route('ingenieria-economica.proyectos.create') }}">Nuevo proyecto</a>
    </div>
    <div class="table-wrap">
        <table aria-labelledby="mis-proyectos">
            <thead>
                <tr>
                    <th scope="col">Proyecto</th>
                    <th scope="col">Organización</th>
                    <th scope="col">Tipo de decisión</th>
                    <th scope="col">Fecha base</th>
                    <th scope="col">Horizonte</th>
                    <th scope="col">Estado</th>
                    <th scope="col">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($proyectos as $proyecto)
                    <tr>
                        <th scope="row">{{ $proyecto->nombre }}</th>
                        <td>{{ $proyecto->organizacion ?? '—' }}</td>
                        <td>{{ $proyecto::TIPOS_DECISION[$proyecto->tipo_decision] }}</td>
                        <td>{{ $proyecto->fecha_base->format('d/m/Y') }}</td>
                        <td>{{ $proyecto->horizonte_anios }} {{ $proyecto->horizonte_anios === 1 ? 'año' : 'años' }}</td>
                        <td>{{ $proyecto::ESTADOS[$proyecto->estado] }}</td>
                        <td>
                            <div class="actions">
                                <a class="button" href="{{ route('ingenieria-economica.proyectos.show', $proyecto) }}"
                                    aria-label="Ver proyecto {{ $proyecto->nombre }}">Ver proyecto</a>
                                @can('delete', $proyecto)
                                    <form method="POST" action="{{ route('ingenieria-economica.proyectos.destroy', $proyecto) }}"
                                        onsubmit="return confirm('¿Eliminar este proyecto? Esta acción es permanente y solo se permite si no tiene registros vinculados.')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="button warning" type="submit" aria-label="Eliminar proyecto {{ $proyecto->nombre }}">Eliminar</button>
                                    </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted">Todavía no tienes proyectos registrados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @include('ingenieria-economica._paginacion', ['paginas' => $proyectos])
</section>
@endsection
