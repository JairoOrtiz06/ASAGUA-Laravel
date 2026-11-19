@extends('layouts.app')
@section('title', 'ASAGUA — Registrar ajuste')
@section('content')
<div class="topbar"><div><h2>Registrar partida de ajuste</h2><p class="muted">El ajuste se identificará en el Libro Diario con el tipo seleccionado.</p></div></div>
<form method="POST" action="{{ route('ajustes.store') }}">
    @csrf
    <div class="form-grid">
        <label>Inicio del período
            <input type="date" name="periodo_inicio" value="{{ old('periodo_inicio', now()->startOfYear()->format('Y-m-d')) }}" required>
        </label>
        <label>Fin del período
            <input type="date" name="periodo_fin" value="{{ old('periodo_fin', now()->endOfYear()->format('Y-m-d')) }}" required>
        </label>
        <label>Fecha del ajuste
            <input type="date" name="fecha" value="{{ old('fecha', now()->format('Y-m-d')) }}" required>
        </label>
        <label>Tipo de ajuste
            <select name="tipo" required>
                <option value="">Seleccione un tipo</option>
                @foreach($tiposAjuste as $tipo)
                    <option value="{{ $tipo }}" @selected(old('tipo') === $tipo)>{{ $tipo }}</option>
                @endforeach
            </select>
        </label>
        <label class="field-full">Concepto / justificación
            <input type="text" name="concepto" value="{{ old('concepto') }}" maxlength="255" required>
        </label>
    </div>
    <h3>Movimientos</h3>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Cuenta</th><th>Descripción</th><th>Debe</th><th>Haber</th><th></th></tr></thead>
            <tbody id="detalles">
            @foreach(old('detalles', [['id_cuenta' => '', 'descripcion' => '', 'debe' => '', 'haber' => '']]) as $index => $detalle)
                @include('ajustes._detalle-row', ['index' => $index, 'detalle' => $detalle])
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="actions" style="margin-top:15px">
        <button type="button" class="button secondary" id="agregar-detalle">＋ Agregar movimiento</button>
        <button type="submit" class="button success">Guardar ajuste</button>
        <a class="button secondary" href="{{ route('ajustes.index') }}">Cancelar</a>
    </div>
</form>
<template id="detalle-template">
    @include('ajustes._detalle-row', ['index' => '__INDEX__', 'detalle' => ['id_cuenta' => '', 'descripcion' => '', 'debe' => '', 'haber' => '']])
</template>
<script>
    const cuerpo = document.getElementById('detalles');
    const plantilla = document.getElementById('detalle-template').innerHTML;
    let indice = cuerpo.querySelectorAll('tr').length;
    document.getElementById('agregar-detalle').addEventListener('click', () => {
        cuerpo.insertAdjacentHTML('beforeend', plantilla.replaceAll('__INDEX__', indice++));
    });
    cuerpo.addEventListener('change', (event) => {
        if (!event.target.matches('.cuenta')) return;
        const option = event.target.selectedOptions[0];
        const descripcion = event.target.closest('tr').querySelector('.descripcion');
        if (!descripcion.value && option?.dataset.nombre) descripcion.value = option.dataset.nombre;
    });
    cuerpo.addEventListener('click', (event) => {
        if (event.target.matches('.quitar-detalle')) {
            const filas = cuerpo.querySelectorAll('tr');
            if (filas.length > 1) event.target.closest('tr').remove();
        }
    });
</script>
@endsection
