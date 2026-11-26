@extends('layouts.ingenieria-economica')
@php
    use App\Models\IngenieriaEconomica\Evaluacion;
    use App\Models\IngenieriaEconomica\CalculoActivo;
    $ruta = 'ingenieria-economica.proyectos.informes.';
    $seleccion = array_map('strval',array_filter((array) old('calculos',$d['calculos'] ?? []),'is_scalar'));
@endphp
@section('economia-content')
@include('ingenieria-economica.documental._cabecera',['titulo'=>'Resultados económicos','nombreProyecto'=>$informe['evaluacion']['proyecto']['nombre'] ?? null])
@if ($aviso)<p class="errors">{{ $aviso }}</p>@endif
@if ($evaluaciones->isEmpty())
    <p>Guarda una evaluación financiera o económica para consolidar sus resultados.</p>
    <a class="button" href="{{ route('ingenieria-economica.proyectos.evaluaciones.index',$proyecto) }}">Abrir evaluaciones</a>
@else
    <p>El escenario, la perspectiva y las alternativas se toman de la evaluación guardada. El PDF puede incluir el análisis de sensibilidad guardado que selecciones y los cálculos de activos compatibles.</p>
    <form class="panel-link" method="GET" action="{{ route($ruta.'index',$proyecto) }}">
        <div class="form-grid">
            <div><label for="consulta-evaluacion">Consultar evaluación guardada</label>
                <select name="evaluacion_id" id="consulta-evaluacion" required><option value="">Seleccionar</option>
                    @foreach ($evaluaciones as $e)<option value="{{ $e->id }}" @selected((int)($d['evaluacion_id'] ?? 0)===$e->id)>#{{ $e->id }} · {{ ($e->escenario_historico ?? 'Escenario sin nombre en la instantánea') }} · {{ Evaluacion::PERSPECTIVAS_PRESENTACION[$e->perspectiva_historica ?? $e->perspectiva] }}</option>@endforeach
                </select>
            </div>
            <div><label for="consulta-sensibilidad">Consultar sensibilidad (opcional)</label>
                <select name="sensibilidad_id" id="consulta-sensibilidad"><option value="">Sin análisis</option>
                    @foreach ($sensibilidades as $s)<option value="{{ $s->id }}" @selected((int)($d['sensibilidad_id'] ?? 0)===$s->id)>#{{ $s->id }} · {{ $s->nombre }}</option>@endforeach
                </select>
            </div>
        </div>
        <div class="actions"><button class="button secondary">Consultar resultados</button></div>
    </form>
    <form class="section panel-link" method="POST" action="{{ route($ruta.'preview',$proyecto) }}">
        @csrf
        <div class="form-grid">
            @include('ingenieria-economica._campo',['campo'=>'evaluacion_id','idCampo'=>'evaluacion-informe','etiqueta'=>'Evaluación, escenario y perspectiva','tipo'=>'select','obligatorio'=>true,
                'valor'=>$d['evaluacion_id'] ?? null,'opciones'=>$evaluaciones->mapWithKeys(fn ($e)=>[$e->id=>'#'.$e->id.' · '.($e->escenario_historico ?? 'Escenario sin nombre en la instantánea').' · '.Evaluacion::PERSPECTIVAS_PRESENTACION[$e->perspectiva_historica ?? $e->perspectiva].' · '.$e->moneda])])
            @include('ingenieria-economica._campo',['campo'=>'sensibilidad_id','idCampo'=>'sensibilidad-informe','etiqueta'=>'Análisis de sensibilidad guardado','tipo'=>'select',
                'valor'=>$d['sensibilidad_id'] ?? null,'opciones'=>$sensibilidades->mapWithKeys(fn ($s)=>[$s->id=>"#{$s->id} · {$s->nombre} · {$s->perspectiva}"])])
            @include('ingenieria-economica._campo',['campo'=>'estado','etiqueta'=>'Tipo de informe','tipo'=>'select','obligatorio'=>true,
                'opciones'=>['preliminar'=>'Preliminar','validado'=>'Validado (sujeto a requisitos)'],'valor'=>'preliminar'])
            @include('ingenieria-economica._campo',['campo'=>'estudiantes','etiqueta'=>'Estudiantes (opcional)','maximo'=>700])
            @include('ingenieria-economica._campo',['campo'=>'docentes','etiqueta'=>'Docentes (opcional)','maximo'=>700])
            @include('ingenieria-economica._campo',['campo'=>'comentarios','etiqueta'=>'Comentarios del analista','tipo'=>'textarea','maximo'=>10000])
            @if ($calculos->isNotEmpty())
                <fieldset class="field-full"><legend>Cálculos de activos a incluir</legend>
                    @foreach ($calculos as $c)
                        <label class="flujo-opcion"><input type="checkbox" name="calculos[]" value="{{ $c->id }}" @checked(in_array((string)$c->id,$seleccion,true))>
                            #{{ $c->id }} · {{ $c->activo->nombre }} · {{ CalculoActivo::METODOS[$c->metodo] }}</label>
                    @endforeach
                </fieldset>
            @endif
        </div>
        <div class="actions">
            <button class="button" type="submit">Vista previa del informe</button>
        </div>
    </form>
@endif
@if ($informe)
    <section class="section panel-link">
        <h3>Consolidado de la evaluación #{{ $informe['evaluacion_id'] }}</h3>
        <p><a href="{{ route('ingenieria-economica.proyectos.evaluaciones.show',[$proyecto,$informe['evaluacion_id']]) }}">Abrir memoria de la evaluación original</a></p>
        @include('ingenieria-economica.documental._contenido',['pdf'=>false])
    </section>
@endif
<section class="section panel-link"><h3>Historial de informes</h3>
    <div class="table-wrap"><table>
        <thead><tr><th>Versión</th><th>Evaluación</th><th>Fecha</th><th>Estado</th><th>PDF</th></tr></thead>
        <tbody>@forelse ($historial as $item)
            <tr><th scope="row"><a href="{{ route($ruta.'show',[$proyecto,$item]) }}">Informe #{{ $item->id }}</a></th>
                <td>#{{ $item->evaluacion_id }}</td><td>{{ $item->created_at->format('d/m/Y H:i') }}</td><td>{{ $item->estado }}</td>
                <td><a href="{{ route($ruta.'download',[$proyecto,$item]) }}">Descargar versión</a></td></tr>
        @empty
            <tr><td colspan="5">Aún no hay versiones guardadas.</td></tr>
        @endforelse</tbody>
    </table></div>
    @include('ingenieria-economica._paginacion',['paginas'=>$historial->withQueryString()])
</section>
@endsection
