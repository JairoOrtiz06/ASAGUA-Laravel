@extends('layouts.ingenieria-economica')

@use('App\Services\IngenieriaEconomica\ContextoEvaluacion')

@php
    use App\Models\IngenieriaEconomica\Concepto;
@endphp

@section('economia-content')
<div class="topbar">
    <div>
        <h2>Registrar ajuste manual</h2>
        <p class="muted">{{ $proyecto->nombre }} · Se conserva al volver a generar los flujos.</p>
    </div>
    <a class="button button-regresar" href="{{ ContextoEvaluacion::enlace('flujos.index', ['proyecto' => $proyecto] + $datos) }}"><span class="button-regresar-icono" aria-hidden="true">←</span> Flujos</a>
</div>
<form class="panel-link economia-form" method="POST" action="{{ ContextoEvaluacion::enlace('ajustes-flujo.store', $proyecto) }}">
    @csrf
    <div class="form-grid">
        @include('ingenieria-economica._campo', ['campo' => 'escenario_id', 'etiqueta' => 'Escenario', 'tipo' => 'select', 'opciones' => $escenarios, 'valor' => $datos['escenario_id'] ?? null, 'obligatorio' => true])
        @include('ingenieria-economica._campo', ['campo' => 'alternativa_id', 'etiqueta' => 'Alternativa', 'tipo' => 'select', 'opciones' => $alternativas, 'valor' => $datos['alternativas'][0] ?? null, 'obligatorio' => true])
        @include('ingenieria-economica.conceptos._movimiento', [
            'datos' => ['moneda' => $datos['moneda'] ?? 'USD', 'clasificacion_decision' => 'diferencial'],
            'clasificaciones' => array_diff_key(Concepto::CLASIFICACIONES, ['comun' => true]),
        ])
        @include('ingenieria-economica._campo', ['campo' => 'fecha_flujo', 'etiqueta' => 'Fecha del movimiento', 'tipo' => 'date', 'obligatorio' => true])
        @include('ingenieria-economica._campo', ['campo' => 'observado', 'etiqueta' => 'Registro temporal', 'tipo' => 'select', 'opciones' => [1 => 'Observado', 0 => 'Proyectado'], 'valor' => 0, 'obligatorio' => true])
        @include('ingenieria-economica._campo', [
            'campo' => 'justificacion', 'etiqueta' => 'Justificación del ajuste', 'tipo' => 'textarea',
            'ayuda' => 'Explica por qué se agrega y cómo evitaste duplicar un concepto programado.',
        ])
    </div>
    <div class="actions"><button class="button" type="submit">Guardar ajuste</button></div>
</form>
@endsection
