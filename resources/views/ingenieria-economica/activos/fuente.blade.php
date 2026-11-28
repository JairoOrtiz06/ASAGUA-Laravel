@extends('layouts.ingenieria-economica')

@use('App\Services\IngenieriaEconomica\ContextoEvaluacion')

@php
    use App\Models\IngenieriaEconomica\FuenteActivo;
    use App\Models\IngenieriaEconomica\FuenteConcepto;
@endphp

@section('economia-content')
<div class="topbar"><h2>Agregar evidencia de {{ $activo->nombre }}</h2><a class="button button-regresar" href="{{ ContextoEvaluacion::enlace('activos.show', [$proyecto, $activo]) }}"><span class="button-regresar-icono" aria-hidden="true">←</span> Activo</a></div>
<details class="section" @if ($filtros) open @endif>
    <summary>Buscar una referencia contable</summary>
    <form class="actions" method="GET">
        @include('ingenieria-economica._campo',['campo'=>'tipo_contable','etiqueta'=>'Tipo','tipo'=>'select','opciones'=>['documento'=>'Documento','detalle'=>'Detalle de partida'],'valor'=>$tipo])
        @include('ingenieria-economica._campo',['campo'=>'q','etiqueta'=>'Nombre, cuenta o número de partida','valor'=>$filtros['q'] ?? null])
        <button class="button">Buscar</button>
    </form>
    <nav class="actions pagination" aria-label="Resultados contables">
        @if ($paginas->previousPageUrl())<a href="{{ $paginas->previousPageUrl() }}">Anterior</a>@endif
        @if ($paginas->nextPageUrl())<a href="{{ $paginas->nextPageUrl() }}">Siguiente</a>@endif
    </nav>
</details>
<form class="panel-link economia-form" method="POST" action="{{ ContextoEvaluacion::enlace('activos.fuentes.store', [$proyecto, $activo]) }}">
    @csrf
    <div class="form-grid">
        @include('ingenieria-economica._campo', ['campo' => 'uso', 'etiqueta' => 'Uso de la evidencia', 'tipo' => 'select', 'opciones' => FuenteActivo::USOS, 'obligatorio' => true])
        @include('ingenieria-economica._campo', ['campo' => 'tipo', 'etiqueta' => 'Tipo de evidencia', 'tipo' => 'select', 'opciones' => FuenteConcepto::TIPOS, 'obligatorio' => true])
        @include('ingenieria-economica._campo', ['campo' => 'referencia', 'etiqueta' => 'Referencia', 'obligatorio' => true, 'maximo' => 255])
        @include('ingenieria-economica._campo', ['campo' => 'fecha', 'etiqueta' => 'Fecha de evidencia', 'tipo' => 'date', 'obligatorio' => true])
        @include('ingenieria-economica._campo', ['campo'=>$campo,'etiqueta'=>$tipo === 'documento' ? 'Documento contable' : 'Detalle de partida',
            'tipo'=>'select','opciones'=>$opciones,'textoVacio'=>'Sin referencia contable',
            'ayuda'=>'Selecciona por nombre en esta lista o busca otra referencia arriba.'])
        @include('ingenieria-economica._campo', ['campo' => 'importe_observado', 'etiqueta' => 'Importe observado'])
        @include('ingenieria-economica._campo', ['campo' => 'justificacion', 'etiqueta' => 'Justificación', 'tipo' => 'textarea', 'ayuda' => 'Indica qué respalda el documento. Una partida por sí sola no confirma un pago.'])
    </div>
    <div class="actions"><button class="button" type="submit">Vincular evidencia</button></div>
</form>
@endsection
