@extends('layouts.app')
@section('title', 'ASAGUA — Libro diario')
@section('content')
<div class="topbar"><div><h2>Libro diario</h2><p class="muted">Movimientos ordinarios y de ajuste en orden cronológico.</p></div><div class="actions"><a class="button" href="{{ route('reportes.libro-diario.ver', request()->only(['fecha_inicio', 'fecha_fin'])) }}" target="_blank">Ver PDF</a><a class="button secondary" href="{{ route('reportes.libro-diario.descargar', request()->only(['fecha_inicio', 'fecha_fin'])) }}">Descargar PDF</a></div></div>
<form method="GET" action="{{ route('libros.diario') }}" class="form-grid" style="margin-bottom:18px">
    <label>Fecha de inicio<input type="date" name="fecha_inicio" value="{{ request('fecha_inicio') }}"></label>
    <label>Fecha final<input type="date" name="fecha_fin" value="{{ request('fecha_fin') }}"></label>
    <div class="actions field-full"><button type="submit">Consultar</button><a class="button secondary" href="{{ route('libros.diario') }}">Reiniciar filtro</a></div>
</form>
<div class="table-wrap">
    <table>
        <thead><tr><th>Fecha</th><th>Partida</th><th>Cuenta</th><th>Concepto / descripción</th><th>Debe</th><th>Haber</th><th>Tipo</th><th>Documento</th></tr></thead>
        <tbody>
        @forelse($movimientos as $movimiento)
            <tr>
                <td>{{ $movimiento['fecha'] }}</td>
                <td>#{{ $movimiento['numero_partida'] }}</td>
                <td>{{ $movimiento['codigo_cuenta'] }} — {{ $movimiento['nombre_cuenta'] }}</td>
                <td>
                    <strong>{{ $movimiento['concepto_visible'] }}</strong>
                    @if($movimiento['descripcion'])<br><span class="muted">{{ $movimiento['descripcion'] }}</span>@endif
                </td>
                <td>{{ $movimiento['debe'] }}</td>
                <td>{{ $movimiento['haber'] }}</td>
                <td>{{ $movimiento['es_ajuste'] ? 'Ajuste: '.$movimiento['tipo_ajuste'] : 'Ordinaria' }}</td>
                <td>{{ $movimiento['id_documento'] ? '📄' : '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="8" class="muted">No hay movimientos para el filtro seleccionado.</td></tr>
        @endforelse
        </tbody>
        @if($movimientos->isNotEmpty())
            <tfoot><tr><th colspan="4">Totales</th><th>{{ $totalDebe }}</th><th>{{ $totalHaber }}</th><th colspan="2"></th></tr></tfoot>
        @endif
    </table>
</div>
@endsection
