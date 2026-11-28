@extends('layouts.ingenieria-economica')

@use('App\Services\IngenieriaEconomica\ContextoEvaluacion')

@section('economia-content')
@include('ingenieria-economica.documental._cabecera',['titulo'=>'Validación del proyecto'])
<p>{{ $evaluacion ? 'Evaluación guardada #'.$evaluacion->id : 'Sin evaluación guardada' }}. Se verifican la estructura y los registros documentales por separado.</p>
@if ($aviso)
    <p class="errors">{{ $aviso }}</p>
@endif
@if ($calidad)
    @include('ingenieria-economica.documental._calidad')
@endif
@if ($evaluacion)
    <a class="button" href="{{ ContextoEvaluacion::enlace('informes.index', $proyecto) }}">Abrir informe con este alcance</a>
@else
    <a class="button" href="{{ ContextoEvaluacion::enlace('informes.index', $proyecto) }}">Elegir evaluación guardada</a>
@endif
@endsection
