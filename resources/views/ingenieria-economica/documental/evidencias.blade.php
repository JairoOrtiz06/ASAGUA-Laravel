@extends('layouts.ingenieria-economica')

@section('economia-content')
@include('ingenieria-economica.documental._cabecera',['titulo'=>'Evidencias documentales'])
<div class="actions">
    <a class="button" href="{{ route('ingenieria-economica.proyectos.evidencias.create',$proyecto) }}">Registrar evidencia</a>
    <a class="button secondary" href="{{ route('ingenieria-economica.proyectos.referencias',$proyecto) }}">Referencias contables</a>
</div>
@if ($destino)
    <p class="section"><strong>{{ $destino['etiqueta'] }}</strong> · Las evidencias ya vinculadas aparecen primero. Selecciona una evidencia para revisar o confirmar el vínculo.</p>
@endif
<div class="section table-wrap"><table>
    <thead><tr><th>Evidencia</th><th>Procedencia</th><th>Fecha</th><th>Calidad del dato</th><th>Estado del documento</th></tr></thead>
    <tbody>@forelse ($evidencias as $e)
        <tr><th scope="row"><a href="{{ route('ingenieria-economica.proyectos.evidencias.show',[$proyecto,$e,...array_intersect_key($filtros,['tipo'=>true,'registro'=>true])]).($destino ? '#confirmar-vinculo' : '') }}">{{ $e->titulo }}</a>@if ($destino && $e->vinculado_al_destino) · Vinculada @endif</th>
            <td>{{ $e->procedencia }}</td><td>{{ $e->fecha_documento->format('d/m/Y') }}</td>
            <td>{{ $e::CALIDADES[$e->calidad] }}</td><td>{{ $e::ESTADOS[$e->estado] }}</td></tr>
    @empty
        <tr><td colspan="5">El expediente aún no tiene evidencias registradas.</td></tr>
    @endforelse</tbody>
</table></div>
@include('ingenieria-economica._paginacion',['paginas'=>$evidencias])

<section class="section panel-link">
    <h3>Fuentes registradas en conceptos y activos</h3>
    <p class="muted">Las citas conservan el dato, la fecha y la procedencia originales. El estado del respaldo central se muestra por separado.</p>
    <ul class="records">
        @foreach (['conceptos'=>'concepto','activos'=>'activo'] as $grupo=>$tipoFuente)
            @foreach ($previas[$grupo] as $item)
                <li><a href="{{ route('ingenieria-economica.proyectos.'.$grupo.'.show',[$proyecto,$item]) }}">{{ $item->nombre }}</a>
                    <ul>@foreach ($item->fuentes as $fuente)
                        <li>{{ $fuente->referencia }} · {{ $fuente->fecha?->format('d/m/Y') }}. {{ $fuente->justificacion }}</li>
                    @endforeach</ul>
                    @include('ingenieria-economica.documental._respaldo-central',['respaldo'=>$centrales->get($tipoFuente.':'.$item->id, collect())])
                    <a href="{{ route('ingenieria-economica.proyectos.evidencias.index',[$proyecto,'tipo'=>$tipoFuente,'registro'=>$item->id]) }}">Revisar o vincular evidencia</a>
                </li>
            @endforeach
        @endforeach
    </ul>
    @foreach (['conceptos'=>'Citas de conceptos','activos'=>'Citas de activos'] as $grupo => $etiquetaPaginacion)
        @include('ingenieria-economica._paginacion', ['paginas'=>$previas[$grupo],'etiquetaPaginacion'=>$etiquetaPaginacion])
    @endforeach
    @if ($previas['conceptos']->isEmpty() && $previas['activos']->isEmpty())<p>Aún no hay fuentes en conceptos o activos.</p>@endif
</section>
<section class="section panel-link">
    <h3>Fuentes de evaluaciones guardadas</h3>
    @if ($versiones->isNotEmpty())
        <form class="actions" method="GET" action="{{ route('ingenieria-economica.proyectos.evidencias.index',$proyecto) }}">
            <label for="evaluacion-fuentes">Versión de evaluación</label>
            <select id="evaluacion-fuentes" name="evaluacion_fuentes">
                @if ($evaluacion && ! $versiones->contains('id', $evaluacion->id))
                    <option value="{{ $evaluacion->id }}" selected>#{{ $evaluacion->id }} · {{ $evaluacion->instantanea['escenario']['nombre'] }}</option>
                @endif
                @foreach ($versiones as $version)
                    <option value="{{ $version->id }}" @selected($evaluacion?->id === $version->id)>#{{ $version->id }} · {{ $version->escenario_historico }}</option>
                @endforeach
            </select>
            <button class="button">Consultar versión</button>
        </form>
    @endif
    @if ($versiones->hasPages())
        <nav class="actions pagination" aria-label="Páginas de evaluaciones">
            @if ($versiones->previousPageUrl())<a href="{{ $versiones->previousPageUrl() }}">Versiones anteriores</a>@endif
            @if ($versiones->nextPageUrl())<a href="{{ $versiones->nextPageUrl() }}">Más versiones</a>@endif
        </nav>
    @endif
    @if ($evaluacion)
        @php($captura = $evaluacion->instantanea)
        <p><a href="{{ route('ingenieria-economica.proyectos.evaluaciones.show',[$proyecto,$evaluacion]) }}">Ver evaluación #{{ $evaluacion->id }}</a>
            · TMAR {{ $captura['escenario']['fuente_tasa'] ?? 'sin fuente registrada' }}.</p>
        @if (isset($captura['fiscal']['fuente']))<p>Fuente tributaria · {{ $captura['fiscal']['fuente']['titulo'] }}.</p>@endif
        <ul>@foreach ($captura['origenes'] as $fuenteOrigen)
            @foreach ($fuenteOrigen['fuentes'] as $fuente)
                <li>{{ $fuenteOrigen['nombre'] }} · {{ $fuente['referencia'] }} · {{ $fuente['fecha'] }}</li>
            @endforeach
        @endforeach</ul>
        @include('ingenieria-economica.documental._respaldo-central',['respaldo'=>$centrales->get('evaluacion:'.$evaluacion->id, collect())])
        <a href="{{ route('ingenieria-economica.proyectos.evidencias.index',[$proyecto,'tipo'=>'evaluacion','registro'=>$evaluacion->id,'evaluacion_fuentes'=>$evaluacion->id]) }}">Revisar o vincular respaldo de evaluación</a>
    @else
        <p>Aún no hay evaluaciones guardadas.</p>
    @endif
</section>
@endsection
