@extends('layouts.app')
@section('title', 'ASAGUA — Documento '.$documento->id_documento)
@section('content')
<div class="topbar">
    <div><h2>{{ $documento->nombre_documento }}</h2><p class="muted">Documento fuente #{{ $documento->id_documento }}</p></div>
    <a class="button secondary" href="{{ route('documentos.index') }}">Volver</a>
</div>
<div class="form-grid">
    <p><strong>Tipo:</strong> {{ $documento->tipo_documento }}</p>
    <p><strong>Fecha:</strong> {{ $documento->fecha_creacion?->format('d/m/Y H:i:s') ?? '—' }}</p>
    <p class="field-full"><strong>Descripción:</strong> {{ $documento->descripcion ?? '—' }}</p>
</div>
<h3>Detalles asociados</h3>
<div class="table-wrap">
    <table>
        <thead><tr><th>Partida</th><th>Cuenta</th><th>Descripción</th></tr></thead>
        <tbody>
        @forelse($documento->detalles as $detalle)
            <tr>
                <td>#{{ $detalle->partida?->numero_partida }}</td>
                <td>{{ $detalle->cuenta?->codigo_cuenta }} — {{ $detalle->cuenta?->nombre_cuenta }}</td>
                <td>{{ $detalle->descripcion }}</td>
            </tr>
        @empty
            <tr><td colspan="3" class="muted">No hay detalles asociados.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="actions" style="margin-top:18px">
    @if($documento->archivo !== null)
        <a class="button" href="{{ route('documentos.download', $documento) }}" target="_blank">Ver / descargar archivo</a>
    @else
        <span class="muted">Este documento no contiene archivo binario.</span>
    @endif
</div>
@endsection
