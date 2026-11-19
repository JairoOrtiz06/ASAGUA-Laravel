@extends('layouts.app')
@section('title', 'ASAGUA — Documentos fuente')
@section('content')
<div class="topbar">
    <div><h2>Documentos fuente</h2><p class="muted">Documentos binarios y metadatos registrados en ASAGUA.</p></div>
    @if(auth()->user()->rol === 'Contador')
        <a class="button success" href="{{ route('documentos.create') }}">＋ Registrar documento</a>
    @endif
</div>
<div class="table-wrap">
    <table>
        <thead><tr><th>ID</th><th>Nombre</th><th>Tipo</th><th>Fecha</th><th>Descripción</th><th>Detalles asociados</th><th></th></tr></thead>
        <tbody>
        @forelse($documentos as $documento)
            <tr>
                <td>{{ $documento->id_documento }}</td>
                <td>{{ $documento->nombre_documento }}</td>
                <td>{{ $documento->tipo_documento }}</td>
                <td>{{ $documento->fecha_creacion?->format('d/m/Y H:i:s') ?? '—' }}</td>
                <td>{{ $documento->descripcion ?? '—' }}</td>
                <td>{{ $documento->detalles_count }}</td>
                <td><a class="button" href="{{ route('documentos.show', $documento) }}">Ver</a></td>
            </tr>
        @empty
            <tr><td colspan="7" class="muted">No hay documentos fuente registrados.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="pagination">{{ $documentos->links() }}</div>
@endsection
