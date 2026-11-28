@extends('layouts.ingenieria-economica')

@use('App\Services\IngenieriaEconomica\ContextoEvaluacion')

@section('economia-content')
@include('ingenieria-economica.documental._cabecera',['titulo'=>$guardado?'Informe guardado #'.$guardado->id:'Vista previa del informe','nombreProyecto'=>$informe['evaluacion']['proyecto']['nombre']])
@if ($guardado)
    <p><strong>{{ ucfirst($guardado->estado) }}</strong> · Folio {{ $guardado->folio }} · {{ $guardado->created_at->format('d/m/Y H:i') }}</p>
    <a class="button" href="{{ ContextoEvaluacion::enlace('informes.download',[$proyecto,$guardado]) }}">Descargar PDF guardado</a>
    @unless (isset($informe['resumen']['alternativas']))
        <p class="muted">Versión emitida con el criterio de presentación anterior. Consulta la evaluación original para ver la interpretación vigente.</p>
        <a href="{{ ContextoEvaluacion::enlace('evaluaciones.show',[$proyecto,$guardado->evaluacion_id]) }}">Abrir evaluación original</a>
    @endunless
    @if ($posteriores->isNotEmpty())
        <p>Evidencias vinculadas después de emitir esta versión</p>
        <ul>@foreach ($posteriores as $e)<li><a href="{{ ContextoEvaluacion::enlace('evidencias.show',[$proyecto,$e]) }}">{{ $e->titulo }}</a></li>@endforeach</ul>
    @endif
@else
    @if ($informe['estado']==='validado' && ! $informe['calidad']['puede_validar'])
        <p class="economia-bloqueo"><strong>Bloqueo de emisión.</strong> La emisión como validado está bloqueada por requisitos críticos. Puedes volver y generar una vista previa preliminar.
            <a href="{{ ContextoEvaluacion::enlace('validacion',[$proyecto,'evaluacion_id'=>$informe['evaluacion_id']]) }}">Revisar requisitos</a>.
        </p>
    @else
        <form method="POST" action="{{ ContextoEvaluacion::enlace('informes.store',$proyecto) }}">
            @csrf
            @foreach ($d as $k=>$v)
                @if ($k==='calculos')
                    @foreach ($v ?? [] as $id)<input type="hidden" name="calculos[]" value="{{ $id }}">@endforeach
                @elseif (is_scalar($v))
                    <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                @endif
            @endforeach
            <input type="hidden" name="firma" value="{{ $firma }}">
            <button class="button">Guardar versión y PDF</button>
        </form>
    @endif
@endif
@include('ingenieria-economica.documental._contenido',['pdf'=>false])
@endsection
