@extends('layouts.ingenieria-economica')

@use('App\Services\IngenieriaEconomica\ContextoEvaluacion')

@php
    use App\Models\IngenieriaEconomica\Concepto;
    use App\Services\IngenieriaEconomica\DecimalFinanciero;
    use Brick\Math\BigDecimal;

    $alternativaId = $filtros['alternativa_id'] ?? null;
@endphp

@section('economia-content')
<div class="topbar">
    <div>
        <h2>{{ $alternativaId ? 'Conceptos económicos' : 'Conceptos comunes del proyecto' }}</h2>
        <p class="muted">{{ $proyecto->nombre }} @if($alternativaId) · {{ $alternativas[$alternativaId] }} @endif</p>
    </div>
    <div class="actions">
        <a class="button button-regresar" href="{{ ContextoEvaluacion::enlace('show', $proyecto) }}"><span class="button-regresar-icono" aria-hidden="true">←</span> Proyecto</a>
        <a class="button secondary" href="{{ ContextoEvaluacion::enlace('activos.index', $proyecto) }}">Consultar activos de la inversión</a>
        <a class="button" href="{{ ContextoEvaluacion::enlace('conceptos.create', ['proyecto' => $proyecto, 'alternativa_id' => $alternativaId]) }}">Nuevo concepto</a>
    </div>
</div>

@if ($idsContextuales = ContextoEvaluacion::actual()?->datos['alternativas'])
    <div class="actions section" aria-label="Datos de la selección">
        <a class="button secondary" href="{{ ContextoEvaluacion::enlace('conceptos.index', $proyecto) }}">Datos comunes</a>
        @foreach ($alternativas->only($idsContextuales) as $id => $nombre)
            <a class="button secondary" href="{{ ContextoEvaluacion::enlace('conceptos.index', [$proyecto, 'alternativa_id' => $id]) }}">Datos de {{ $nombre }}</a>
        @endforeach
    </div>
@endif
@if (!$alternativaId)
    <p>Registra aquí los conceptos que afectan por igual a todas las alternativas. Se aplican desde una única fuente del proyecto.</p>
@endif

<form class="panel-link" method="GET" action="{{ ContextoEvaluacion::enlace('conceptos.index', $proyecto) }}">
    @include('ingenieria-economica._contexto-campos')
    <div class="form-grid">
        <div class="field">
            <label for="alternativa_id">Ámbito</label>
            <select id="alternativa_id" name="alternativa_id">
                <option value="">Conceptos comunes</option>
                @foreach ($alternativas as $id => $nombre)
                    <option value="{{ $id }}" @selected((string) $id === (string) $alternativaId)>{{ $nombre }}</option>
                @endforeach
            </select>
        </div>
        @include('ingenieria-economica._campo', ['campo' => 'tipo_concepto', 'etiqueta' => 'Tipo', 'tipo' => 'select', 'opciones' => Concepto::TIPOS, 'valor' => $filtros['tipo_concepto'] ?? null])
        @include('ingenieria-economica._campo', ['campo' => 'estado_dato', 'etiqueta' => 'Estado del dato', 'tipo' => 'select', 'opciones' => Concepto::ESTADOS, 'valor' => $filtros['estado_dato'] ?? null])
    </div>
    <div class="actions"><button class="button" type="submit">Filtrar conceptos</button></div>
</form>

<div class="section table-wrap">
    <table>
        <thead>
            <tr><th>Concepto</th><th>Tipo</th><th>Monto</th><th>Periodicidad</th><th>Clasificación</th><th>Estado</th></tr>
        </thead>
        <tbody>
            @forelse ($conceptos as $concepto)
                <tr>
                    <th scope="row"><a href="{{ ContextoEvaluacion::enlace('conceptos.show', [$proyecto, $concepto]) }}">{{ $concepto->nombre }}</a></th>
                    <td>{{ Concepto::TIPOS[$concepto->tipo_concepto] }}</td>
                    <td>{{ DecimalFinanciero::dinero(BigDecimal::of($concepto->monto)) }} {{ $concepto->moneda }}</td>
                    <td>{{ Concepto::PERIODICIDADES[$concepto->periodicidad] }}</td>
                    <td>{{ Concepto::CLASIFICACIONES[$concepto->clasificacion_decision] }}</td>
                    <td>{{ Concepto::ESTADOS[$concepto->estado_dato] }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">No hay conceptos que coincidan con la selección.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@include('ingenieria-economica._paginacion', ['paginas' => $conceptos])
@endsection
