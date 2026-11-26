@extends('layouts.ingenieria-economica')

@php
    use App\Models\IngenieriaEconomica\Evaluacion;
    use App\Services\IngenieriaEconomica\ProyeccionFlujosService;

    $seleccionadas = array_map('strval', array_filter((array) old('alternativas', []), 'is_scalar'));
@endphp

@section('economia-content')
<div class="topbar">
    <div>
        <h2>Evaluación financiera</h2>
        <p class="muted">{{ $proyecto->nombre }}</p>
    </div>
    <div class="actions">
        <a class="button secondary" href="{{ route('ingenieria-economica.proyectos.show', $proyecto) }}">← Proyecto</a>
        <a class="button secondary" href="{{ route('ingenieria-economica.proyectos.index') }}">Cambiar proyecto</a>
    </div>
</div>

@if ($escenarios->isEmpty() || $alternativas->isEmpty())
    <p class="muted">Registra un escenario con TMAR y al menos una alternativa para evaluar.</p>
@else
    <form class="panel-link" method="POST" action="{{ route('ingenieria-economica.proyectos.evaluaciones.preview', $proyecto) }}">
        @csrf
        <div class="form-grid">
            @include('ingenieria-economica._campo', [
                'campo' => 'escenario_id', 'etiqueta' => 'Escenario', 'tipo' => 'select', 'obligatorio' => true,
                'valor' => $escenarios->first()->id,
                'opciones' => $escenarios->mapWithKeys(fn ($item) => [$item->id => $item->nombre.' · '.$item->horizonte_anios.' años · '.$item->periodicidad.' · TEA '.($item->tasa_efectiva_anual === null ? 'pendiente' : \App\Services\IngenieriaEconomica\DecimalFinanciero::porcentaje($item->tasa_efectiva_anual).' %')]),
            ])
            @include('ingenieria-economica._campo', [
                'campo' => 'perspectiva', 'etiqueta' => 'Perspectiva', 'tipo' => 'select',
                'opciones' => $evidenciasFiscales->isEmpty() ? Evaluacion::PERSPECTIVAS : Evaluacion::PERSPECTIVAS_PRESENTACION, 'valor' => 'caja', 'obligatorio' => true,
            ])
            @include('ingenieria-economica._campo', [
                'campo' => 'periodicidad', 'etiqueta' => 'Periodicidad del descuento', 'tipo' => 'select',
                'opciones' => ProyeccionFlujosService::AGRUPACIONES, 'valor' => 'escenario', 'obligatorio' => true,
                'ayuda' => 'Cada flujo se descuenta al cierre del período elegido. Usa mensual para valorar pagos mensuales en sus cierres mensuales.',
            ])
            @include('ingenieria-economica._campo', ['campo' => 'moneda', 'etiqueta' => 'Moneda', 'valor' => 'USD', 'maximo' => 3, 'obligatorio' => true])
            <fieldset class="field-full">
                <legend>Alternativas a evaluar</legend>
                @foreach ($alternativas as $alternativa)
                    <label class="flujo-opcion">
                        <input type="checkbox" name="alternativas[]" value="{{ $alternativa->id }}" @checked(in_array((string) $alternativa->id, $seleccionadas, true))>
                        {{ $alternativa->nombre }}
                    </label>
                @endforeach
            </fieldset>
            @include('ingenieria-economica._campo', [
                'campo' => 'referencia_id', 'etiqueta' => 'Alternativa de referencia (B)', 'tipo' => 'select',
                'opciones' => $alternativas->pluck('nombre', 'id'),
                'ayuda' => 'Para la comparación A − B. Si queda vacía, se toma la primera alternativa seleccionada.',
            ])
            @include('ingenieria-economica._campo', [
                'campo' => 'equivalencia', 'etiqueta' => 'Equivalencia técnica y de servicio', 'tipo' => 'select',
                'opciones' => [0 => 'Pendiente de confirmar', 1 => 'Confirmada para el horizonte común'], 'valor' => 0,
                'ayuda' => 'Confirma condiciones técnicas, normativas y operativas. Las vidas útiles y reemplazos deben quedar sustentados.',
            ])
            @include('ingenieria-economica._campo', [
                'campo' => 'supuestos', 'etiqueta' => 'Supuestos y restricciones', 'tipo' => 'textarea',
                'ayuda' => 'Documenta cómo se cubre el horizonte, los supuestos de vida útil y cualquier condición pendiente.',
            ])
        </div>
        @include('ingenieria-economica.evaluaciones._configuracion-fiscal')
        <div class="actions"><button class="button" type="submit">Generar evaluación</button></div>
    </form>
@endif

<section class="section panel-link">
    <h3>Evaluaciones guardadas</h3>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Versión guardada</th><th>Fecha y hora</th><th>Perspectiva</th><th>Moneda</th><th>Motor</th></tr></thead>
            <tbody>
                @forelse ($evaluaciones as $guardada)
                    <tr>
                        <th scope="row"><a href="{{ route('ingenieria-economica.proyectos.evaluaciones.show', [$proyecto, $guardada]) }}">Evaluación #{{ $guardada->id }}</a></th>
                        <td>{{ $guardada->created_at->format('d/m/Y H:i') }}</td>
                        <td>{{ Evaluacion::PERSPECTIVAS_PRESENTACION[$guardada->perspectiva_historica ?? $guardada->perspectiva] }}</td>
                        <td>{{ $guardada->moneda }}</td>
                        <td>{{ $guardada->version_motor }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5">Aún no hay evaluaciones guardadas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @include('ingenieria-economica._paginacion', ['paginas' => $evaluaciones])
</section>
@endsection
