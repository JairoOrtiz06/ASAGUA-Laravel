@extends('layouts.ingenieria-economica')

@use('App\Services\IngenieriaEconomica\ContextoEvaluacion')

@section('economia-content')
@include('ingenieria-economica.documental._cabecera',['titulo'=>'Referencias contables'])
<p>Consulta de solo lectura. El Debe y Haber se presentan como datos del asiento y requieren evidencia adicional para sustentar un cobro o desembolso.</p>
<ul class="records">@forelse ($items as $item)
    <li><a href="{{ ContextoEvaluacion::enlace('evidencias.show',[$proyecto,$item]) }}">{{ $item->titulo }}</a>
        @foreach ($item->vinculos as $v)
            <div class="section"><strong>{{ $v->etiqueta }} #{{ $v->destino_id }}</strong> · {{ $v::CONCILIACIONES[$v->conciliacion] }}
                @include('ingenieria-economica.documental._referencia',['ref'=>$referencias[$v->id],'contable'=>true])
            </div>
        @endforeach
    </li>
@empty
    <li>Sin referencia contable vinculada en el expediente.</li>
@endforelse</ul>
@include('ingenieria-economica._paginacion',['paginas'=>$items])
<section class="section panel-link"><h3>Referencias previas en conceptos y activos</h3>
    <ul class="records">
        @foreach ($previas as $grupo=>$lista)
            @foreach ($lista as $item)
                @foreach ($item->fuentes->filter(fn ($f)=>$f->id_detalle || $f->id_documento) as $fuente)
                    <li><a href="{{ route('ingenieria-economica.proyectos.'.$grupo.'.show',[$proyecto,$item]) }}">{{ $item->nombre }}</a> · {{ $fuente->referencia }}
                        · Detalle #{{ $fuente->id_detalle ?? '—' }} · Documento #{{ $fuente->id_documento ?? '—' }}</li>
                @endforeach
            @endforeach
        @endforeach
    </ul>
    @foreach (['conceptos'=>'Citas de conceptos','activos'=>'Citas de activos'] as $grupo => $etiquetaPaginacion)
        @include('ingenieria-economica._paginacion', ['paginas'=>$previas[$grupo],'etiquetaPaginacion'=>$etiquetaPaginacion])
    @endforeach
</section>
@endsection
