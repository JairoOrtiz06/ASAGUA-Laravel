@extends('layouts.ingenieria-economica')

@use('App\Services\IngenieriaEconomica\ContextoEvaluacion')

@section('economia-content')
@php
    use App\Services\IngenieriaEconomica\DecimalFinanciero;
    use App\Services\IngenieriaEconomica\ProyeccionFlujosService;
    $editando = $escenario->exists;
@endphp
<div class="topbar">
    <div>
        <h2>{{ $editando ? 'Editar escenario' : 'Nuevo escenario' }}</h2>
        <p class="muted">{{ $proyecto->nombre }}</p>
    </div>
    <a class="button button-regresar" href="{{ ContextoEvaluacion::enlace('escenarios.index', $proyecto) }}"><span class="button-regresar-icono" aria-hidden="true">←</span> Volver a escenarios</a>
</div>

@if ($editando && $escenario->horizonte_anios > ProyeccionFlujosService::MAX_ANIOS)
    <p class="economia-aviso">El horizonte guardado supera el límite operativo. Corrígelo antes de generar cálculos nuevos. Las versiones históricas se conservan.</p>
@elseif (! $editando && $escenario->horizonte_anios === null)
    <p class="economia-aviso">El horizonte del proyecto está fuera del rango operativo. Indica un horizonte válido para este escenario.</p>
@endif
<section class="panel-link economia-form">
    <form method="POST" action="{{ $editando ? ContextoEvaluacion::enlace('escenarios.update', [$proyecto, $escenario]) : ContextoEvaluacion::enlace('escenarios.store', $proyecto) }}"
        data-vista-previa="{{ ContextoEvaluacion::enlace('escenarios.previsualizar', $proyecto) }}" data-vista-inicial="{{ $editando ? 1 : 0 }}">
        @csrf
        @if ($editando)
            @method('PATCH')
        @endif
        <div class="form-grid">
            @include('ingenieria-economica._campo', [
                'campo' => 'nombre',
                'etiqueta' => 'Nombre',
                'valor' => $escenario->nombre,
                'obligatorio' => true,
                'maximo' => 150,
            ])
            @include('ingenieria-economica._campo', [
                'campo' => 'fecha_base',
                'etiqueta' => 'Fecha base del escenario',
                'tipo' => 'date',
                'valor' => $escenario->fecha_base?->format('Y-m-d'),
                'ayuda' => 'Si queda vacía se usa la fecha base del proyecto.',
            ])
            @include('ingenieria-economica._campo', [
                'campo' => 'horizonte_anios',
                'etiqueta' => 'Horizonte en años',
                'valor' => $escenario->horizonte_anios,
                'maximoNumerico' => ProyeccionFlujosService::MAX_ANIOS,
                'ayuda' => 'Indica entre 1 y '.ProyeccionFlujosService::MAX_ANIOS.' años.',
                'tipo' => 'number',
                'obligatorio' => true,
            ])
            @include('ingenieria-economica._campo', [
                'campo' => 'periodicidad',
                'etiqueta' => 'Periodicidad del escenario',
                'valor' => $escenario->periodicidad,
                'tipo' => 'select',
                'obligatorio' => true,
                'opciones' => $escenario::PERIODOS,
            ])
            @include('ingenieria-economica._campo', [
                'campo' => 'estado_tasa',
                'etiqueta' => 'Estado de la tasa',
                'valor' => $escenario->estado_tasa,
                'tipo' => 'select',
                'obligatorio' => true,
                'opciones' => $escenario::ESTADOS_TASA,
            ])
            @include('ingenieria-economica._campo', [
                'campo' => 'tasa_original', 'nombreCampo' => 'tasa_porcentaje',
                'etiqueta' => 'Tasa original (%)', 'modoEntrada' => 'decimal',
                'valor' => old('tasa_porcentaje', $escenario->tasa_original === null ? null : DecimalFinanciero::porcentaje($escenario->tasa_original)),
                'omitirValorAnterior' => true,
                'ayuda' => 'Escribe 0.25 para una tasa de 0.25 %. Puedes usar coma o punto decimal. No escribas el signo %.',
            ])
            @include('ingenieria-economica._campo', [
                'campo' => 'periodicidad_tasa',
                'etiqueta' => 'Periodicidad o capitalización de la tasa',
                'valor' => $escenario->periodicidad_tasa,
                'tipo' => 'select',
                'opciones' => $escenario::PERIODOS_TASA,
                'ayuda' => 'Para una tasa nominal anual, elige capitalización mensual, trimestral o semestral.',
            ])
            @include('ingenieria-economica._campo', [
                'campo' => 'modalidad_tasa',
                'etiqueta' => 'Modalidad de la tasa',
                'valor' => $escenario->modalidad_tasa,
                'tipo' => 'select',
                'opciones' => $escenario::MODALIDADES,
            ])
            @include('ingenieria-economica._campo', [
                'campo' => 'fuente_tasa',
                'etiqueta' => 'Fuente de la tasa',
                'valor' => $escenario->fuente_tasa,
                'tipo' => 'textarea',
                'maximo' => 2000,
            ])
            @include('ingenieria-economica._campo', [
                'campo' => 'descripcion',
                'etiqueta' => 'Descripción',
                'valor' => $escenario->descripcion,
                'tipo' => 'textarea',
            ])
        </div>
        <section class="section panel-link">
            <h3>Equivalencia de la TMAR</h3>
            @include('ingenieria-economica._campo',['campo'=>'periodicidad_descuento','etiqueta'=>'Período de descuento para consultar',
                'tipo'=>'select','opciones'=>$escenario::PERIODOS,'textoVacio'=>'Usar la periodicidad del escenario',
                'ayuda'=>'Esta selección solo consulta la equivalencia. El período que se usa para descontar se elige en Evaluación.'])
            <div class="section" data-resultado role="status">
                @if ($escenario->tasa_efectiva_anual !== null)
                    Efectiva anual guardada {{ DecimalFinanciero::porcentaje($escenario->tasa_efectiva_anual) }} %.
                @else
                    Completa la tasa original, la modalidad y su periodicidad.
                @endif
            </div>
            <div class="actions section">
                <button type="button" class="button secondary" data-actualizar>Actualizar vista previa</button>
                <a class="button secondary" href="{{ route('ingenieria-economica.herramientas.show', 'tasas') }}"
                    target="_blank" rel="noopener">Conversión de tasas <span class="proyecto-solo-lector">(abre en una pestaña nueva)</span></a>
            </div>
        </section>
        <div class="actions section">
            <button class="button" type="submit">Guardar escenario</button>
        </div>
    </form>
</section>
<script src="{{ asset('js/ingenieria-vista-previa.js') }}" defer></script>
@endsection
