@extends('layouts.app')
@section('title', 'ASAGUA — Partidas contables')
@section('content')
<div class="topbar">
    <div><h2>Partidas contables</h2><p class="muted">Consulta de partidas registradas y sus detalles.</p></div>
    @if(auth()->user()->rol === 'Contador')
        <a class="button success" href="{{ route('partidas.create') }}">＋ Registrar partida</a>
    @endif
</div>
<div class="table-wrap">
    <table>
        <thead><tr><th>Número</th><th>Fecha</th><th>Concepto</th><th>Registró</th><th>Detalles</th><th></th></tr></thead>
        <tbody>
        @forelse($partidas as $partida)
            <tr>
                <td>{{ $partida->numero_partida }}</td>
                <td>{{ $partida->fecha?->format('d/m/Y') }}</td>
                <td>{{ $partida->concepto }}</td>
                <td>{{ $partida->usuario?->usuario ?? '—' }}</td>
                <td>{{ $partida->detalles_count }}</td>
                <td><a class="button" href="{{ route('partidas.show', $partida) }}">Ver</a></td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted">No hay partidas registradas.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="pagination">{{ $partidas->links() }}</div>
@endsection
