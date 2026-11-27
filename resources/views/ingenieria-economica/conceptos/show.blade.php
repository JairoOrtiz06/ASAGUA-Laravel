@extends('layouts.ingenieria-economica')

@php
    use App\Models\IngenieriaEconomica\Concepto;
    use App\Models\IngenieriaEconomica\FuenteConcepto;
    use App\Services\IngenieriaEconomica\DecimalFinanciero;
    use Brick\Math\BigDecimal;
@endphp

@section('economia-content')
<div class="topbar">
    <div>
        <h2>{{ $concepto->nombre }}</h2>
        <p class="muted">{{ $proyecto->nombre }} · {{ $concepto->alternativa?->nombre ?? 'Común del proyecto' }}</p>
    </div>
    <div class="actions">
        <a class="button secondary" href="{{ route('ingenieria-economica.proyectos.evidencias.index',[$proyecto,'tipo'=>'concepto','registro'=>$concepto->id]) }}">Respaldo documental</a>
        <a class="button secondary" href="{{ route('ingenieria-economica.proyectos.conceptos.index', ['proyecto' => $proyecto, 'alternativa_id' => $concepto->alternativa_id]) }}">← Conceptos</a>
        <a class="button" href="{{ route('ingenieria-economica.proyectos.conceptos.edit', [$proyecto, $concepto]) }}">Editar concepto</a>
        @if ($concepto->estado_dato !== 'confirmado' && $concepto->fuentes->isEmpty())
            @include('ingenieria-economica._eliminar', [
                'ruta' => route('ingenieria-economica.proyectos.conceptos.destroy', [$proyecto, $concepto]),
                'nombre' => $concepto->nombre, 'accion' => 'Eliminar',
                'conserva' => 'Las evidencias y las evaluaciones guardadas se conservan.',
            ])
        @else
            <p class="muted">{{ $concepto->estado_dato === 'confirmado'
                ? 'Concepto confirmado. Puedes corregirlo desde Editar concepto.'
                : 'Este concepto tiene fuentes. Puedes revisar sus referencias en el detalle.' }}</p>
        @endif
    </div>
</div>

<dl class="panel-grid summary">
    <div class="panel-link"><dt>Monto</dt><dd>{{ DecimalFinanciero::dinero(BigDecimal::of($concepto->monto)) }} {{ $concepto->moneda }}</dd></div>
    <div class="panel-link"><dt>Tipo y dirección</dt><dd>{{ Concepto::TIPOS[$concepto->tipo_concepto] }} · {{ Concepto::DIRECCIONES[Concepto::direccion($concepto->tipo_concepto, $concepto->direccion_otro)] }}</dd></div>
    <div class="panel-link"><dt>Clasificación</dt><dd>{{ Concepto::CLASIFICACIONES[$concepto->clasificacion_decision] }}</dd></div>
    <div class="panel-link"><dt>Naturaleza</dt><dd>{{ Concepto::NATURALEZAS[$concepto->naturaleza_economica] }}</dd></div>
    <div class="panel-link"><dt>Programación</dt><dd>{{ Concepto::PERIODICIDADES[$concepto->periodicidad] }} · {{ Concepto::MOMENTOS[$concepto->momento_pago] }}</dd></div>
    <div class="panel-link"><dt>Estado del dato</dt><dd>{{ Concepto::ESTADOS[$concepto->estado_dato] }}</dd></div>
</dl>
<section class="section panel-link">
    <h3>Fechas y recurrencia</h3>
    @if ($concepto->activo_id)
        <p><a href="{{ route('ingenieria-economica.proyectos.activos.show', [$proyecto, $concepto->activo_id]) }}">Activo vinculado</a> · {{ $concepto->activo_operacion }}</p>
    @endif
    <p>Fecha observada: {{ $concepto->fecha_real?->format('d/m/Y') ?? '—' }} · Fecha programada: {{ $concepto->fecha_programada?->format('d/m/Y') ?? '—' }} · Mes de inicio: {{ $concepto->periodo_inicio ?? 'Automático' }} · Último mes de pago: {{ $concepto->periodo_fin ?? '—' }}</p>
    <p>Máximo de ocurrencias: {{ $concepto->numero_ocurrencias ?? '—' }} · Crecimiento por ocurrencia: {{ $concepto->porcentaje_crecimiento ?? '—' }}</p>
    @if ($concepto->descripcion)<p class="description">{{ $concepto->descripcion }}</p>@endif
    @if ($concepto->observaciones)<p class="description">{{ $concepto->observaciones }}</p>@endif
</section>

<section class="section panel-link">
    <div class="topbar">
        <h3>Evidencias</h3>
        <a class="button" href="{{ route('ingenieria-economica.proyectos.conceptos.fuentes.create', [$proyecto, $concepto]) }}">Agregar evidencia</a>
    </div>
    <ul class="records">
        @forelse ($concepto->fuentes as $fuente)
            <li>
                <strong>{{ FuenteConcepto::TIPOS[$fuente->tipo] }} · {{ $fuente->referencia }}</strong>
                <p>{{ $fuente->fecha->format('d/m/Y') }} · Observado: {{ $fuente->importe_observado ?? '—' }} · Utilizado: {{ $fuente->importe_utilizado }} {{ $concepto->moneda }}</p>
                <p class="description">{{ $fuente->justificacion }}</p>
                <div class="actions">
                    @if ($fuente->documento)
                        <a href="{{ route('documentos.show', $fuente->documento) }}">{{ $fuente->documento->nombre_documento }}</a>
                    @endif
                    @if ($fuente->detalle?->partida)
                        <a href="{{ route('partidas.show', $fuente->detalle->partida) }}">Partida {{ $fuente->detalle->partida->numero_partida }} · Detalle {{ $fuente->id_detalle }}</a>
                    @endif
                    <a class="button secondary" href="{{ route('ingenieria-economica.proyectos.conceptos.fuentes.edit', [$proyecto, $concepto, $fuente]) }}">Editar evidencia</a>
        @include('ingenieria-economica._eliminar',['ruta'=>route('ingenieria-economica.proyectos.conceptos.fuentes.destroy',[$proyecto,$concepto,$fuente]),'nombre'=>$fuente->referencia,'accion'=>'Retirar referencia','conserva'=>'El documento original se conserva.'])

                </div>
            </li>
        @empty
            <li class="muted">Todavía no hay evidencias asociadas.</li>
        @endforelse
    </ul>
</section>
@endsection
