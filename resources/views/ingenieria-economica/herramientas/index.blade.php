@extends('layouts.ingenieria-economica')

@section('economia-content')
<div class="topbar">
    <div>
        <h2>Herramientas financieras</h2>
        <p class="muted">Calcula equivalencias y consulta el procedimiento de cada operación.</p>
    </div>
</div>

<div class="panel-grid">
    @foreach ($herramientas as $clave => $nombre)
        <a class="panel-link" href="{{ route('ingenieria-economica.herramientas.show', $clave) }}"><strong>{{ $nombre }}</strong></a>
    @endforeach
</div>
@endsection
