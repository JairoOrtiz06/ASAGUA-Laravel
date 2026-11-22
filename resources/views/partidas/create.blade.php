@extends('layouts.app')
@section('title', 'ASAGUA — Registrar partida')
@section('content')
<div class="topbar"><div><h2>Registrar partida</h2><p class="muted">El total del Debe debe coincidir con el total del Haber.</p></div></div>
<form method="POST" action="{{ route('partidas.store') }}" enctype="multipart/form-data">
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
                @include('partidas._detalle-row', ['index' => $index, 'detalle' => $detalle, 'documentos' => $documentos])
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
    @include('partidas._detalle-row', ['index' => '__INDEX__', 'detalle' => ['id_cuenta' => '', 'descripcion' => '', 'debe' => '', 'haber' => '', 'modo_documento' => 'ninguno'], 'documentos' => $documentos])
</template>
<script>
    const cuentas = @json($cuentas->map(fn ($cuenta) => ['id' => $cuenta->id_cuenta, 'nombre' => $cuenta->nombre_cuenta]));
    const cuerpo = document.getElementById('detalles');
    const plantilla = document.getElementById('detalle-template').innerHTML;
    let indice = cuerpo.querySelectorAll('tr').length;
    const actualizarDocumento = (fila) => {
        const modo = fila.querySelector('.modo-documento').value;
        const existente = fila.querySelector('.documento-existente');
        const nuevo = fila.querySelector('.documento-nuevo');
        const estado = fila.querySelector('.documento-estado');
        existente.hidden = modo !== 'existente';
        nuevo.hidden = modo !== 'nuevo';
        if (modo === 'ninguno') {
            existente.querySelector('select').value = '';
            estado.textContent = 'Sin documento asociado';
        } else if (modo === 'existente') {
            const opcion = existente.querySelector('select').selectedOptions[0];
            estado.textContent = opcion?.dataset.nombre ? `Seleccionado: ${opcion.dataset.nombre}` : 'Seleccione un documento existente';
        } else {
            estado.textContent = 'Se registrará un documento nuevo al guardar la partida';
        }
    };
    cuerpo.querySelectorAll('tr').forEach(actualizarDocumento);
    document.getElementById('agregar-detalle').addEventListener('click', () => {
        cuerpo.insertAdjacentHTML('beforeend', plantilla.replaceAll('__INDEX__', indice++));
        actualizarDocumento(cuerpo.lastElementChild);
    });
    cuerpo.addEventListener('change', (event) => {
        if (event.target.matches('.modo-documento, .documento-existente select')) {
            actualizarDocumento(event.target.closest('tr'));
            return;
        }
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
