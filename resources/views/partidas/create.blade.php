@extends('layouts.app')
@section('title', 'ASAGUA — Registrar partida')
@section('content')
<div class="topbar"><div><h2>Registrar partida</h2><p class="muted">El total del Debe debe coincidir con el total del Haber.</p></div></div>
<form method="POST" action="{{ route('partidas.store') }}">
    @csrf
    <div class="form-grid">
        <label>Fecha
            <input type="date" name="fecha" value="{{ old('fecha', now()->format('Y-m-d')) }}" required>
        </label>
        <label>Concepto
            <input type="text" name="concepto" value="{{ old('concepto') }}" maxlength="255" placeholder="Asiento contable">
        </label>
    </div>
    <h3>Detalles</h3>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Cuenta</th><th>Descripción</th><th>Debe</th><th>Haber</th><th></th></tr></thead>
            <tbody id="detalles">
            @foreach(old('detalles', [['id_cuenta' => '', 'descripcion' => '', 'debe' => '', 'haber' => '']]) as $index => $detalle)
                @include('partidas._detalle-row', ['index' => $index, 'detalle' => $detalle])
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="actions" style="margin-top:15px">
        <button type="button" class="button secondary" id="agregar-detalle">＋ Agregar detalle</button>
        <button type="submit" class="button success">Guardar partida</button>
        <a class="button secondary" href="{{ route('partidas.index') }}">Cancelar</a>
    </div>
</form>
<template id="detalle-template">
    @include('partidas._detalle-row', ['index' => '__INDEX__', 'detalle' => ['id_cuenta' => '', 'descripcion' => '', 'debe' => '', 'haber' => '']])
</template>
<script>
    const cuentas = @json($cuentas->map(fn ($cuenta) => ['id' => $cuenta->id_cuenta, 'nombre' => $cuenta->nombre_cuenta]));
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
