@extends('layouts.ingenieria-economica')

@section('economia-content')
<div class="topbar">
    <div><h2>{{ $activo->clasificacion_contable === 'activo_intangible' ? 'Amortización lineal' : 'Comparar métodos de depreciación' }}</h2><p class="muted">{{ $activo->nombre }}</p></div>
    <a class="button secondary" href="{{ route('ingenieria-economica.proyectos.activos.show', [$proyecto, $activo]) }}">← Activo</a>
</div>
<form class="panel-link economia-form" method="POST" action="{{ route('ingenieria-economica.proyectos.activos.depreciacion.store', [$proyecto, $activo]) }}">
    @csrf
    <div class="form-grid">
        @include('ingenieria-economica._campo', ['campo' => 'fecha_corte', 'etiqueta' => 'Fecha de corte contable', 'tipo' => 'date', 'ayuda' => 'Incluye el día de corte. Si queda vacía se calcula toda la vida útil.'])
        @if ($activo->clasificacion_contable === 'activo_tangible')
            @include('ingenieria-economica._campo', ['campo' => 'ajuste_final', 'etiqueta' => 'Ajuste final de doble saldo', 'tipo' => 'select', 'opciones' => [0 => 'Conservar saldo no depreciado', 1 => 'Ajustar al residual si queda saldo'], 'valor' => 0])
            @include('ingenieria-economica._campo', ['campo' => 'justificacion_ajuste', 'etiqueta' => 'Justificación de la política de ajuste', 'tipo' => 'textarea'])
        @endif
    </div>
    <div class="actions"><button class="button" type="submit">Calcular y guardar versión</button></div>
</form>
@endsection
