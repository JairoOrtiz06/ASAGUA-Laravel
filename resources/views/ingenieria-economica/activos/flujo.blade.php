@extends('layouts.ingenieria-economica')

@use('App\Services\IngenieriaEconomica\ContextoEvaluacion')

@php
    use App\Models\IngenieriaEconomica\Concepto;
    use App\Services\IngenieriaEconomica\IntegracionActivoService;
@endphp

@section('economia-content')
<div class="topbar"><h2>Vincular movimiento de {{ $activo->nombre }}</h2><a class="button button-regresar" href="{{ ContextoEvaluacion::enlace('activos.show', [$proyecto, $activo]) }}"><span class="button-regresar-icono" aria-hidden="true">←</span> Activo</a></div>
@if ($escenarios->isEmpty() || $fuentes->isEmpty())
    <p>Registra un escenario y una evidencia del movimiento monetario antes de continuar.</p>
@else
    <form class="panel-link economia-form" method="POST" action="{{ ContextoEvaluacion::enlace('activos.flujo.store', [$proyecto, $activo]) }}">
        @csrf
        <div class="form-grid">
            @include('ingenieria-economica._campo', ['campo' => 'escenario_id', 'etiqueta' => 'Escenario de referencia para comprobar fechas', 'tipo' => 'select', 'opciones' => $escenarios, 'obligatorio' => true])
            @include('ingenieria-economica._campo', ['campo' => 'fuente_id', 'etiqueta' => 'Evidencia', 'tipo' => 'select', 'opciones' => $fuentes, 'obligatorio' => true])
            @include('ingenieria-economica._campo', ['campo' => 'concepto_id', 'etiqueta' => 'Concepto ya registrado', 'tipo' => 'select', 'opciones' => $conceptos, 'ayuda' => 'Elige el concepto equivalente si el movimiento ya existe. Deja vacío para crearlo.'])
            @include('ingenieria-economica._campo', ['campo' => 'operacion', 'etiqueta' => 'Operación', 'tipo' => 'select', 'opciones' => IntegracionActivoService::OPERACIONES, 'obligatorio' => true])
            @include('ingenieria-economica._campo', ['campo' => 'fecha', 'etiqueta' => 'Fecha del movimiento', 'tipo' => 'date', 'obligatorio' => true])
            @include('ingenieria-economica._campo', ['campo' => 'observado', 'etiqueta' => 'Registro temporal', 'tipo' => 'select', 'opciones' => [0 => 'Proyectado', 1 => 'Observado'], 'valor' => 0, 'obligatorio' => true])
            @include('ingenieria-economica._campo', ['campo' => 'monto', 'etiqueta' => 'Importe monetario', 'obligatorio' => true])
            @include('ingenieria-economica._campo', ['campo' => 'moneda', 'etiqueta' => 'Moneda de la evaluación', 'valor' => $activo->moneda, 'obligatorio' => true, 'maximo' => 3])
            @include('ingenieria-economica._campo', ['campo' => 'estado_dato', 'etiqueta' => 'Calidad del dato', 'tipo' => 'select', 'opciones' => Concepto::ESTADOS, 'valor' => 'provisional', 'obligatorio' => true])
            @include('ingenieria-economica._campo', ['campo' => 'justificacion', 'etiqueta' => 'Justifica el movimiento de efectivo', 'tipo' => 'textarea'])
        </div>
        <div class="actions">
            <label class="flujo-opcion"><input type="checkbox" name="confirmar" value="1" required> Confirmo el importe y la vinculación del movimiento económico.</label>
            <button class="button" type="submit">Confirmar movimiento</button>
        </div>
    </form>
@endif
@endsection
