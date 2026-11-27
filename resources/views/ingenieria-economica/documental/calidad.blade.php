@extends('layouts.ingenieria-economica')

@section('economia-content')
@include('ingenieria-economica.documental._cabecera',['titulo'=>'Validación del proyecto'])
<p>{{ $evaluacion ? 'Evaluación guardada #'.$evaluacion->id : 'Sin evaluación guardada' }}. Se verifican la estructura y los registros documentales por separado.</p>
@include('ingenieria-economica.documental._calidad')
@endsection
