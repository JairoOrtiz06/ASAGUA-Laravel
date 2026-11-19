@extends('layouts.app')
@section('title', 'ASAGUA — Partida '.$partida->numero_partida)
@section('content')
<div class="topbar">
    <div><h2>Partida #{{ $partida->numero_partida }}</h2><p class="muted">{{ $partida->concepto }}</p></div>
    <a class="button secondary" href="{{ route('partidas.index') }}">Volver</a>
</div>
<p><strong>Fecha:</strong> {{ $partida->fecha?->format('d/m/Y') }} &nbsp; <strong>Registró:</strong> {{ $partida->usuario?->usuario ?? '—' }}</p>
<div class="table-wrap">
    <table>
        <thead><tr><th>Cuenta</th><th>Descripción</th><th>Debe</th><th>Haber</th></tr></thead>
        <tbody>
        @foreach($partida->detalles as $detalle)
            <tr>
                <td>{{ $detalle->cuenta?->codigo_cuenta }} — {{ $detalle->cuenta?->nombre_cuenta }}</td>
                <td>{{ $detalle->descripcion }}</td>
                <td>{{ $detalle->debe }}</td>
                <td>{{ $detalle->haber }}</td>
            </tr>
        @endforeach
        </tbody>
        <tfoot><tr><th colspan="2">Totales</th><th>{{ $totales->debe }}</th><th>{{ $totales->haber }}</th></tr></tfoot>
    </table>
</div>
@endsection
