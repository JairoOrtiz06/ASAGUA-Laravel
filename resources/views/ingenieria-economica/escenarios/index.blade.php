@extends('layouts.ingenieria-economica')

@use('App\Services\IngenieriaEconomica\ContextoEvaluacion')

@php
    use App\Services\IngenieriaEconomica\DecimalFinanciero;
@endphp

@section('economia-content')
<section id="escenarios" tabindex="-1" aria-labelledby="titulo-escenarios">
    <div class="topbar">
        <h1 id="titulo-escenarios">Escenarios</h1>
        <div class="actions">
            <a class="button button-regresar" href="{{ ContextoEvaluacion::enlace('show', $proyecto) }}"><span class="button-regresar-icono" aria-hidden="true">←</span> Volver al proyecto</a>
            <a class="button" href="{{ ContextoEvaluacion::enlace('escenarios.create', $proyecto) }}">Configurar escenario</a>
        </div>
    </div>
    @error('escenario')
        <p class="errors">{{ $message }}</p>
    @enderror
    <ul class="records">
        @forelse ($escenarios as $escenario)
            <li>
                <strong>{{ $escenario->nombre }}</strong>
                <a href="{{ ContextoEvaluacion::enlace('evidencias.index',[$proyecto,'tipo'=>'escenario','registro'=>$escenario->id]) }}">Respaldo documental</a>
                @if ($escenario->es_principal)
                    <span class="muted">· Principal</span>
                @endif
                <div class="muted">
                    Fecha base {{ ($escenario->fecha_base ?? $proyecto->fecha_base)->format('d/m/Y') }} ·
                    {{ $escenario->horizonte_anios }} años · {{ $escenario::PERIODOS[$escenario->periodicidad] }} ·
                    Tasa {{ strtolower($escenario::ESTADOS_TASA[$escenario->estado_tasa]) }}
                </div>
                @if ($escenario->tasa_original !== null)
                    <div class="muted">
                        Tasa original: {{ DecimalFinanciero::porcentaje($escenario->tasa_original) }} %
                        · {{ $escenario::MODALIDADES[$escenario->modalidad_tasa] }}
                        · {{ $escenario::PERIODOS_TASA[$escenario->periodicidad_tasa] }}
                        @if ($escenario->tasa_efectiva_anual !== null)
                            · Efectiva anual: {{ DecimalFinanciero::porcentaje($escenario->tasa_efectiva_anual) }} %
                        @endif
                    </div>
                @endif
                @if ($escenario->fuente_tasa)
                    <p class="description">Fuente: {{ $escenario->fuente_tasa }}</p>
                @endif
                <div class="actions">
                    <a class="button" href="{{ ContextoEvaluacion::enlace('escenarios.edit', [$proyecto, $escenario]) }}">Editar <span class="proyecto-solo-lector">{{ $escenario->nombre }}</span></a>

                    @include('ingenieria-economica._eliminar',['ruta'=>ContextoEvaluacion::enlace('escenarios.destroy',[$proyecto,$escenario]),'nombre'=>$escenario->nombre,'accion'=>'Eliminar','conserva'=>'Los resultados guardados y las evidencias se conservan.'])
                    @unless ($escenario->es_principal)
                        <form method="POST" action="{{ ContextoEvaluacion::enlace('escenarios.principal', [$proyecto, $escenario]) }}">
                            @csrf
                            @method('PATCH')
                            <button class="button secondary" type="submit">Definir como principal</button>
                        </form>
                    @endunless
                </div>
            </li>
        @empty
            <li class="muted">Todavía no hay escenarios configurados.</li>
        @endforelse
    </ul>
    @include('ingenieria-economica._paginacion', ['paginas' => $escenarios->withQueryString()])
</section>
@endsection
