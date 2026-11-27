@extends('layouts.ingenieria-economica')

@section('economia-content')
@php($editando = $alternativa->exists)
<div class="topbar">
    <div>
        <h2>{{ $editando ? 'Editar alternativa' : 'Nueva alternativa' }}</h2>
        <p class="muted">{{ $proyecto->nombre }}</p>
    </div>
    <a class="button secondary" href="{{ route('ingenieria-economica.proyectos.show', $proyecto) . '#alternativas' }}">← Volver</a>
</div>

<section class="panel-link economia-form">
    <form method="POST" action="{{ $editando ? route('ingenieria-economica.proyectos.alternativas.update', [$proyecto, $alternativa]) : route('ingenieria-economica.proyectos.alternativas.store', $proyecto) }}">
        @csrf
        @if ($editando)
            @method('PATCH')
        @endif
        <div class="form-grid">
            @include('ingenieria-economica._campo', [
                'campo' => 'nombre',
                'etiqueta' => 'Nombre',
                'valor' => $alternativa->nombre,
                'obligatorio' => true,
                'maximo' => 150,
            ])
            @include('ingenieria-economica._campo', [
                'campo' => 'proveedor',
                'etiqueta' => 'Proveedor',
                'valor' => $alternativa->proveedor,
                'maximo' => 150,
            ])
            @include('ingenieria-economica._campo', [
                'campo' => 'estado_implementacion',
                'etiqueta' => 'Estado de implementación',
                'valor' => $alternativa->estado_implementacion,
                'tipo' => 'select',
                'obligatorio' => true,
                'opciones' => $alternativa::ESTADOS,
            ])
            @include('ingenieria-economica._campo', [
                'campo' => 'fecha_implementacion',
                'etiqueta' => 'Fecha de implementación',
                'valor' => $alternativa->fecha_implementacion?->format('Y-m-d'),
                'tipo' => 'date',
            ])
            @include('ingenieria-economica._campo', [
                'campo' => 'viabilidad_tecnica',
                'etiqueta' => 'Viabilidad técnica',
                'valor' => $alternativa->viabilidad_tecnica,
                'tipo' => 'select',
                'obligatorio' => true,
                'opciones' => $alternativa::VIABILIDADES,
            ])
            @include('ingenieria-economica._campo', [
                'campo' => 'descripcion',
                'etiqueta' => 'Descripción',
                'valor' => $alternativa->descripcion,
                'tipo' => 'textarea',
            ])
            @include('ingenieria-economica._campo', [
                'campo' => 'observaciones',
                'etiqueta' => 'Observaciones',
                'valor' => $alternativa->observaciones,
                'tipo' => 'textarea',
            ])
        </div>
        <div class="actions">
            <button class="button" type="submit">Guardar</button>
        </div>
    </form>
</section>
@endsection
