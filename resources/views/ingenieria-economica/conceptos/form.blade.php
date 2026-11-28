@extends('layouts.ingenieria-economica')

@use('App\Services\IngenieriaEconomica\ContextoEvaluacion')

@php
    use App\Models\IngenieriaEconomica\Concepto;

    $editando = $concepto->exists;
    $regresar = $editando
        ? ContextoEvaluacion::enlace('conceptos.show', [$proyecto, $concepto])
        : ContextoEvaluacion::enlace('conceptos.index', ['proyecto' => $proyecto, 'alternativa_id' => $concepto->alternativa_id]);
@endphp

@section('economia-content')
<div class="topbar">
    <div>
        <h2>{{ $editando ? 'Editar concepto' : 'Nuevo concepto económico' }}</h2>
        <p class="muted">{{ $proyecto->nombre }} · {{ $concepto->alternativa?->nombre ?? 'Común del proyecto' }}</p>
    </div>
    <a class="button button-regresar" href="{{ $regresar }}"><span class="button-regresar-icono" aria-hidden="true">←</span> Volver</a>
</div>

<form class="panel-link economia-form" method="POST" action="{{ $editando ? ContextoEvaluacion::enlace('conceptos.update', [$proyecto, $concepto]) : ContextoEvaluacion::enlace('conceptos.store', $proyecto) }}" @if ($escenarios->isNotEmpty()) data-vista-previa="{{ $editando
        ? ContextoEvaluacion::enlace('conceptos.previsualizarEdicion', [$proyecto, $concepto])
        : ContextoEvaluacion::enlace('conceptos.previsualizar', $proyecto) }}" data-vista-inicial="{{ $editando ? 1 : 0 }}" @endif>
    @csrf
    @if ($editando) @method('PATCH') @endif
    <h3>Información básica</h3>
    <div class="form-grid section">
        @if ($editando)
            <p>Alternativa · {{ $concepto->alternativa?->nombre ?? 'Compartido en el proyecto' }}</p>
        @else
            @include('ingenieria-economica._campo', ['campo'=>'alternativa_id','etiqueta'=>'Alternativa','tipo'=>'select',
                'opciones'=>$alternativas,'valor'=>$concepto->alternativa_id,'textoVacio'=>'Compartido en el proyecto',
                'ayuda'=>'Deja Compartido en el proyecto para un costo común. Selecciona una alternativa para los demás conceptos.'])
        @endif
        @include('ingenieria-economica.conceptos._movimiento', [
            'grupoMovimiento' => 'basico',
            'datos' => $concepto->getAttributes(),
            'clasificaciones' => $concepto->alternativa_id === null
                ? ['comun' => Concepto::CLASIFICACIONES['comun']]
                : array_diff_key(Concepto::CLASIFICACIONES, ['comun' => true]),
        ])
    </div>
    <section class="section">
        <h3>Programación temporal</h3>
        <div class="form-grid section">
        @include('ingenieria-economica._campo', ['campo' => 'periodicidad', 'etiqueta' => 'Periodicidad', 'tipo' => 'select', 'opciones' => Concepto::PERIODICIDADES, 'valor' => $concepto->periodicidad, 'obligatorio' => true])
        @include('ingenieria-economica._campo', ['campo' => 'momento_pago', 'etiqueta' => 'Momento del pago', 'tipo' => 'select', 'opciones' => Concepto::MOMENTOS, 'valor' => $concepto->momento_pago, 'obligatorio' => true, 'ayuda' => 'Inicio: pago anticipado. Final: pago vencido al cierre de cada intervalo.'])
        @include('ingenieria-economica._campo', [
            'campo' => 'periodo_inicio', 'etiqueta' => 'Mes de inicio respecto a la fecha base', 'tipo' => 'number',
            'valor' => $concepto->periodo_inicio, 'minimo' => 0, 'maximoNumerico' => 1200,
            'ayuda' => 'Para recurrencias vencidas, usa 1 para el primer mes. Vacío o 0 también inicia el primer tramo en la fecha base. Para pagos al inicio, 0 es la fecha base.',
        ])
        @include('ingenieria-economica._campo', [
            'campo' => 'periodo_fin', 'etiqueta' => 'Último mes permitido para pagar', 'tipo' => 'number',
            'valor' => $concepto->periodo_fin, 'minimo' => 0, 'maximoNumerico' => 1200,
            'ayuda' => 'Solo para recurrencias. En pagos vencidos, el mes de cierre se incluye. Si queda vacío, aplica el horizonte.',
        ])
        @include('ingenieria-economica._campo', [
            'campo' => 'numero_ocurrencias', 'etiqueta' => 'Máximo de ocurrencias', 'tipo' => 'number', 'valor' => $concepto->numero_ocurrencias,
            'maximoNumerico' => 1200, 'ayuda' => 'Solo para recurrencias. También se respeta el último mes de pago.',
        ])
        </div>
    </section>
    <section class="section">
        <h3>Clasificación económica</h3>
        <div class="form-grid section">
        @include('ingenieria-economica.conceptos._movimiento', [
            'grupoMovimiento' => 'clasificacion',
            'datos' => $concepto->getAttributes(),
            'clasificaciones' => $editando && $concepto->alternativa_id === null
                ? ['comun' => Concepto::CLASIFICACIONES['comun']]
                : ($editando ? array_diff_key(Concepto::CLASIFICACIONES, ['comun' => true]) : Concepto::CLASIFICACIONES),
        ])
        </div>
    </section>
    <details class="section" @if ($errors->any() || $concepto->fecha_real || $concepto->fecha_programada) open @endif>
        <summary>Opciones avanzadas y evidencias</summary>
        <div class="form-grid section">
        @include('ingenieria-economica._campo', [
            'campo' => 'fecha_real', 'etiqueta' => 'Fecha del movimiento observado', 'tipo' => 'date', 'valor' => $concepto->fecha_real?->format('Y-m-d'),
            'ayuda' => 'Solo para un movimiento único. Usa esta fecha o el mes programado.',
        ])
        @include('ingenieria-economica._campo', [
            'campo' => 'fecha_programada', 'etiqueta' => 'Fecha exacta programada', 'tipo' => 'date',
            'valor' => $concepto->fecha_programada?->format('Y-m-d'),
            'ayuda' => 'Solo para un movimiento único. Completa esta fecha, la fecha observada o el mes programado.',
        ])
        @include('ingenieria-economica._campo', [
            'campo' => 'porcentaje_crecimiento', 'etiqueta' => 'Crecimiento por ocurrencia', 'valor' => $concepto->porcentaje_crecimiento,
            'ayuda' => 'Fracción decimal. Por ejemplo, 0.05 aumenta 5 % cada pago posterior.',
        ])
        @include('ingenieria-economica._campo', ['campo' => 'descripcion', 'etiqueta' => 'Descripción', 'tipo' => 'textarea', 'valor' => $concepto->descripcion])
        @include('ingenieria-economica._campo', ['campo' => 'observaciones', 'etiqueta' => 'Observaciones', 'tipo' => 'textarea', 'valor' => $concepto->observaciones])
        </div>
        @if ($editando)
            <a href="{{ ContextoEvaluacion::enlace('evidencias.index',[$proyecto,'tipo'=>'concepto','registro'=>$concepto->id,'origen'=>'editar-concepto']) }}">Revisar o vincular evidencias</a>
        @else
            <p class="muted">Podrás vincular evidencias desde el detalle del concepto guardado.</p>
        @endif
    </details>
    <section class="section panel-link" aria-label="Vista previa del calendario">
        <h3>Vista previa del calendario</h3>
        @if ($escenarios->isNotEmpty())
            @include('ingenieria-economica._campo',['campo'=>'escenario_calendario','etiqueta'=>'Escenario para la vista previa',
                'tipo'=>'select','opciones'=>$escenarios,'valor'=>ContextoEvaluacion::actual()?->datos['escenario_id'] ?? $escenarios->keys()->first()])
            <div class="section" data-resultado role="status">Completa los datos para revisar las fechas y el importe nominal.</div>
            <div class="actions section"><button type="button" data-actualizar class="button secondary">Actualizar vista previa</button></div>
            <noscript><p>Activa JavaScript para actualizar la vista previa. Puedes guardar el formulario normalmente.</p></noscript>
        @else
            <p><a href="{{ ContextoEvaluacion::enlace('escenarios.create',$proyecto) }}">Configura un escenario</a> para comprobar el calendario.</p>
        @endif
    </section>
    <div class="actions section"><button class="button" type="submit">Guardar concepto</button></div>
</form>
<script src="{{ asset('js/ingenieria-vista-previa.js') }}" defer></script>
@endsection
