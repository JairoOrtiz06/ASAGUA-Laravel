@extends('layouts.app')
@section('title', 'ASAGUA — Ajustes contables')
@section('content')
<div class="topbar">
    <div><h2>Partidas de ajuste</h2><p class="muted">Ajustes contabilizados en el Libro Diario.</p></div>
    <a class="button success" href="{{ route('ajustes.create') }}">＋ Registrar ajuste</a>
</div>
<div class="table-wrap">
    <table>
        <thead><tr><th>Número</th><th>Fecha</th><th>Tipo</th><th>Concepto</th><th>Registró</th><th>Detalles</th><th></th></tr></thead>
        <tbody>
        @forelse($ajustes as $ajuste)
            <tr>
                <td>{{ $ajuste->numero_partida }}</td>
                <td>{{ $ajuste->fecha?->format('d/m/Y') }}</td>
                <td>{{ $ajuste->tipo_ajuste }}</td>
                <td>{{ $ajuste->concepto_ajuste }}</td>
                <td>{{ $ajuste->usuario?->nombre ?? '—' }}</td>
                <td>{{ $ajuste->detalles_count }}</td>
                <td><a class="button" href="{{ route('ajustes.show', $ajuste) }}">Ver detalle</a></td>
            </tr>
        @empty
            <tr><td colspan="7" class="muted">No hay ajustes contabilizados.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="pagination">{{ $ajustes->links() }}</div>
@endsection
