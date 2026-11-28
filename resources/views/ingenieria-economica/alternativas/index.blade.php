@extends('layouts.ingenieria-economica')

@use('App\Services\IngenieriaEconomica\ContextoEvaluacion')

@section('economia-content')
<section id="alternativas" tabindex="-1" aria-labelledby="titulo-alternativas">
    <div class="topbar">
        <h1 id="titulo-alternativas">Alternativas</h1>
        <div class="actions">
            <a class="button button-regresar" href="{{ ContextoEvaluacion::enlace('show', $proyecto) }}"><span class="button-regresar-icono" aria-hidden="true">←</span> Volver al proyecto</a>
            <a class="button" href="{{ ContextoEvaluacion::enlace('alternativas.create', $proyecto) }}">Agregar alternativa</a>
        </div>
    </div>
    @error('alternativa')
        <p class="errors">{{ $message }}</p>
    @enderror
    <ul class="records">
        @forelse ($alternativas as $alternativa)
            <li>
                <strong>{{ $alternativa->nombre }}</strong>
                <a href="{{ ContextoEvaluacion::enlace('evidencias.index',[$proyecto,'tipo'=>'alternativa','registro'=>$alternativa->id]) }}">Respaldo documental</a>
                <div class="muted">
                    {{ $alternativa->proveedor ? $alternativa->proveedor . ' · ' : '' }}
                    {{ $alternativa::ESTADOS[$alternativa->estado_implementacion] }} ·
                    Viabilidad técnica: {{ $alternativa::VIABILIDADES[$alternativa->viabilidad_tecnica] }}
                    @if ($alternativa->fecha_implementacion)
                        · {{ $alternativa->fecha_implementacion->format('d/m/Y') }}
                    @endif
                </div>
                @if ($alternativa->descripcion)
                    <p class="description">{{ $alternativa->descripcion }}</p>
                @endif
                @if ($alternativa->observaciones)
                    <p class="description">{{ $alternativa->observaciones }}</p>
                @endif
                <div class="actions">
                    <a class="button" href="{{ ContextoEvaluacion::enlace('alternativas.edit', [$proyecto, $alternativa]) }}">Editar <span class="proyecto-solo-lector">{{ $alternativa->nombre }}</span></a>
                    <form method="POST" action="{{ ContextoEvaluacion::enlace('alternativas.destroy', [$proyecto, $alternativa]) }}"
                        onsubmit="return confirm('¿Eliminar esta alternativa? Solo se permite si no se ha implementado y no tiene registros vinculados.')">
                        @csrf
                        @method('DELETE')
                        <button class="button warning" type="submit" aria-label="Eliminar alternativa {{ $alternativa->nombre }}">Eliminar</button>
                    </form>
                    <a class="button secondary" href="{{ ContextoEvaluacion::enlace('conceptos.index', ['proyecto' => $proyecto, 'alternativa_id' => $alternativa->id]) }}">Conceptos</a>
                    <a class="button secondary" href="{{ ContextoEvaluacion::enlace('flujos.index', ['proyecto' => $proyecto, 'alternativas' => [$alternativa->id]]) }}">Ver flujos</a>
                </div>
            </li>
        @empty
            <li class="muted">Todavía no hay alternativas registradas.</li>
        @endforelse
    </ul>
    @include('ingenieria-economica._paginacion', ['paginas' => $alternativas->withQueryString()])
</section>
@endsection
