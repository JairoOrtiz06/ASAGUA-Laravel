@extends('layouts.ingenieria-economica')

@use('App\Services\IngenieriaEconomica\ContextoEvaluacion')

@php
    use App\Models\IngenieriaEconomica\FuenteConcepto;
@endphp

@section('economia-content')
<div class="topbar">
    <div><h2>{{ $fuente->exists ? 'Editar evidencia' : 'Agregar evidencia' }}</h2><p class="muted">{{ $concepto->nombre }}</p></div>
    <a class="button button-regresar" href="{{ ContextoEvaluacion::enlace('conceptos.show', [$proyecto, $concepto]) }}"><span class="button-regresar-icono" aria-hidden="true">←</span> Concepto</a>
</div>

<form class="panel-link economia-form" method="POST" action="{{ $fuente->exists ? ContextoEvaluacion::enlace('conceptos.fuentes.update', [$proyecto, $concepto, $fuente]) : ContextoEvaluacion::enlace('conceptos.fuentes.store', [$proyecto, $concepto]) }}">
    @csrf
    @if ($fuente->exists) @method('PATCH') @endif
    <div class="form-grid">
        @include('ingenieria-economica._campo', ['campo' => 'tipo', 'etiqueta' => 'Tipo de evidencia', 'tipo' => 'select', 'opciones' => FuenteConcepto::TIPOS, 'valor' => $fuente->tipo, 'obligatorio' => true])
        @include('ingenieria-economica._campo', ['campo' => 'referencia', 'etiqueta' => 'Referencia de la fuente', 'valor' => $fuente->referencia, 'maximo' => 255, 'obligatorio' => true])
        @include('ingenieria-economica._campo', ['campo' => 'fecha', 'etiqueta' => 'Fecha de la evidencia', 'tipo' => 'date', 'valor' => $fuente->fecha?->format('Y-m-d'), 'obligatorio' => true])
        @include('ingenieria-economica._campo', ['campo' => 'id_documento', 'etiqueta' => 'ID del documento contable existente', 'tipo' => 'number', 'valor' => $fuente->id_documento, 'maximoNumerico' => 2147483647])
        @include('ingenieria-economica._campo', ['campo' => 'id_detalle', 'etiqueta' => 'ID de detalle de partida existente', 'tipo' => 'number', 'valor' => $fuente->id_detalle, 'maximoNumerico' => 2147483647])
        @include('ingenieria-economica._campo', ['campo' => 'importe_observado', 'etiqueta' => 'Importe observado ('.$concepto->moneda.')', 'valor' => $fuente->importe_observado])
        @include('ingenieria-economica._campo', ['campo' => 'importe_utilizado', 'etiqueta' => 'Importe utilizado ('.$concepto->moneda.')', 'valor' => $fuente->importe_utilizado, 'obligatorio' => true])
        @include('ingenieria-economica._campo', [
            'campo' => 'justificacion', 'etiqueta' => 'Justificación del importe y su uso', 'tipo' => 'textarea', 'valor' => $fuente->justificacion,
            'ayuda' => 'Un asiento contable requiere evidencia adicional para confirmar un cobro o un pago.',
        ])
    </div>
    <div class="actions"><button class="button" type="submit">Guardar evidencia</button></div>
</form>
@endsection
