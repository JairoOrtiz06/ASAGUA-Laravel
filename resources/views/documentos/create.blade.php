@extends('layouts.app')
@section('title', 'ASAGUA — Registrar documento fuente')
@section('content')
<div class="topbar"><div><h2>Registrar documento fuente</h2><p class="muted">Los archivos se almacenan en la columna binaria existente; el archivo es opcional, como en el sistema original.</p></div></div>
<form method="POST" action="{{ route('documentos.store') }}" enctype="multipart/form-data">
    @csrf
    <div class="form-grid">
        <label>Nombre del documento
            <input type="text" name="nombre_documento" value="{{ old('nombre_documento') }}" maxlength="255" required>
        </label>
        <label>Tipo de documento
            <input type="text" name="tipo_documento" value="{{ old('tipo_documento', 'PDF') }}" maxlength="50" required>
        </label>
        <label>Fecha de creación
            <input type="datetime-local" name="fecha_creacion" value="{{ old('fecha_creacion') }}">
        </label>
        <label>Detalle contable asociado
            <select name="id_detalle">
                <option value="">Sin asociación</option>
                @foreach($detalles as $detalle)
                    <option value="{{ $detalle->id_detalle }}" @selected((string) old('id_detalle') === (string) $detalle->id_detalle)>
                        Partida #{{ $detalle->partida?->numero_partida }} — {{ $detalle->cuenta?->codigo_cuenta }} {{ $detalle->cuenta?->nombre_cuenta }}
                    </option>
                @endforeach
            </select>
        </label>
        <label class="field-full">Descripción
            <textarea name="descripcion" rows="3" style="width:100%;margin-top:6px;padding:10px;border:1px solid #a9c5d8;border-radius:6px">{{ old('descripcion') }}</textarea>
        </label>
        <label class="field-full">Archivo adjunto (máximo 40 MB)
            <input type="file" name="archivo" style="padding:8px">
        </label>
    </div>
    <div class="actions" style="margin-top:15px">
        <button type="submit" class="button success">Guardar documento</button>
        <a class="button secondary" href="{{ route('documentos.index') }}">Cancelar</a>
    </div>
</form>
@endsection
