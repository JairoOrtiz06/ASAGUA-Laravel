@extends('layouts.ingenieria-economica')

@section('economia-content')
@php
    use App\Services\IngenieriaEconomica\ProyeccionFlujosService;
    $editando = $proyecto->exists;
@endphp
<div class="topbar">
    <div>
        <h2>{{ $editando ? 'Editar proyecto' : 'Nuevo proyecto' }}</h2>
    </div>
    <a class="button secondary" href="{{ $editando ? route('ingenieria-economica.proyectos.show', $proyecto) : route('ingenieria-economica.proyectos.index') }}">← Volver</a>
</div>

@if ($editando && ($proyecto->horizonte_anios < 1 || $proyecto->horizonte_anios > ProyeccionFlujosService::MAX_ANIOS))
    <p class="economia-aviso">El horizonte guardado está fuera del rango operativo. Corrígelo antes de guardar los cambios. Las evaluaciones e informes históricos se conservan.</p>
@endif
<section class="panel-link economia-form">
    <form method="POST" action="{{ $editando ? route('ingenieria-economica.proyectos.update', $proyecto) : route('ingenieria-economica.proyectos.store') }}">
        @csrf
        @if ($editando)
            @method('PATCH')
        @endif
        <div class="form-grid">
            @include('ingenieria-economica._campo', [
                'campo' => 'nombre',
                'etiqueta' => 'Nombre',
                'valor' => $proyecto->nombre,
                'obligatorio' => true,
                'maximo' => 150,
            ])
            @include('ingenieria-economica._campo', [
                'campo' => 'organizacion',
                'etiqueta' => 'Organización',
                'valor' => $proyecto->organizacion,
                'maximo' => 150,
            ])
            @include('ingenieria-economica._campo', [
                'campo' => 'tipo_decision',
                'etiqueta' => 'Tipo de decisión',
                'valor' => $proyecto->tipo_decision,
                'tipo' => 'select',
                'obligatorio' => true,
                'opciones' => $proyecto::TIPOS_DECISION,
            ])
            @include('ingenieria-economica._campo', [
                'campo' => 'estado',
                'etiqueta' => 'Estado de preparación',
                'valor' => $proyecto->estado,
                'tipo' => 'select',
                'obligatorio' => true,
                'opciones' => $proyecto::ESTADOS,
            ])
            @include('ingenieria-economica._campo', [
                'campo' => 'fecha_base',
                'etiqueta' => 'Fecha base',
                'valor' => $proyecto->fecha_base?->format('Y-m-d'),
                'tipo' => 'date',
                'obligatorio' => true,
            ])
            @include('ingenieria-economica._campo', [
                'campo' => 'horizonte_anios',
                'etiqueta' => 'Horizonte en años',
                'valor' => $proyecto->horizonte_anios,
                'maximoNumerico' => ProyeccionFlujosService::MAX_ANIOS,
                'ayuda' => 'Indica entre 1 y '.ProyeccionFlujosService::MAX_ANIOS.' años.',
                'tipo' => 'number',
                'obligatorio' => true,
            ])
            @include('ingenieria-economica._campo', [
                'campo' => 'descripcion',
                'etiqueta' => 'Descripción',
                'valor' => $proyecto->descripcion,
                'tipo' => 'textarea',
            ])
        </div>
        <div class="actions">
            <button class="button" type="submit">{{ $editando ? 'Guardar cambios' : 'Crear proyecto' }}</button>
        </div>
    </form>
</section>
@endsection
