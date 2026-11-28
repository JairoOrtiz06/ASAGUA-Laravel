@extends('layouts.ingenieria-economica')

@php
    use App\Services\IngenieriaEconomica\ConversionTasasService;
    use App\Services\IngenieriaEconomica\DecimalFinanciero;
    use App\Services\IngenieriaEconomica\FactoresFinancierosService;
    use App\Services\IngenieriaEconomica\SeriesUniformesService;

    $periodicidades = ConversionTasasService::PERIODOS;
    $modalidades = ConversionTasasService::MODALIDADES;
    $fechasValoracion = ['presente' => 'Valor presente', 'futuro' => 'Valor futuro'];
@endphp

@section('title', 'ASAGUA — '.$titulo)

@section('economia-content')
<div class="topbar">
    <div>
        <h2>{{ $titulo }}</h2>
        <p class="muted">Escribe la tasa en porcentaje. Usa 0.25 para 0.25 %.</p>
    </div>
    <a class="button button-regresar" href="{{ route('ingenieria-economica.herramientas.index') }}"><span class="button-regresar-icono" aria-hidden="true">←</span> Herramientas</a>
</div>

<section class="panel-link economia-form">
    <form method="POST" action="{{ route('ingenieria-economica.herramientas.calcular', $herramienta) }}" id="calculadora-financiera">
        @csrf
        <div class="form-grid">
            @include('ingenieria-economica._campo', [
                'campo' => 'tasa', 'nombreCampo' => 'tasa_porcentaje', 'etiqueta' => 'Tasa de origen (%)',
                'valor' => old('tasa_porcentaje', isset($datos['tasa']) ? \App\Services\IngenieriaEconomica\DecimalFinanciero::porcentaje((string) $datos['tasa']) : null),
                'omitirValorAnterior' => true, 'modoEntrada' => 'decimal',
                'obligatorio' => true,
            ])
            @include('ingenieria-economica._campo', [
                'campo' => 'modalidad', 'etiqueta' => 'Modalidad de origen', 'tipo' => 'select',
                'opciones' => $modalidades, 'valor' => $datos['modalidad'] ?? 'efectiva', 'obligatorio' => true,
            ])
            @include('ingenieria-economica._campo', [
                'campo' => 'periodicidad_tasa', 'etiqueta' => 'Periodicidad o capitalización de origen', 'tipo' => 'select',
                'opciones' => $periodicidades, 'valor' => $datos['periodicidad_tasa'] ?? 'anual', 'obligatorio' => true,
                'ayuda' => 'Con modalidad nominal anual, elige capitalización mensual, trimestral o semestral.',
            ])
            @if ($herramienta === 'tasas')
                @include('ingenieria-economica._campo', [
                    'campo' => 'modalidad_destino', 'etiqueta' => 'Modalidad de destino', 'tipo' => 'select',
                    'opciones' => $modalidades, 'valor' => $datos['modalidad_destino'] ?? 'efectiva', 'obligatorio' => true,
                ])
                @include('ingenieria-economica._campo', [
                    'campo' => 'destino', 'etiqueta' => 'Periodicidad o capitalización de destino', 'tipo' => 'select',
                    'opciones' => $periodicidades, 'valor' => $datos['destino'] ?? 'anual', 'obligatorio' => true,
                ])
            @else
                @include('ingenieria-economica._campo', [
                    'campo' => 'periodicidad', 'etiqueta' => 'Periodicidad del cálculo', 'tipo' => 'select',
                    'opciones' => $periodicidades, 'valor' => $datos['periodicidad'] ?? 'anual', 'obligatorio' => true,
                ])
            @endif

            @if (in_array($herramienta, ['interes-compuesto', 'series'], true))
                @include('ingenieria-economica._campo', [
                    'campo' => 'operacion', 'etiqueta' => 'Operación', 'tipo' => 'select', 'obligatorio' => true,
                    'opciones' => $herramienta === 'series' ? $fechasValoracion + ['anualidad' => 'Anualidad equivalente', 'periodos' => 'Número de pagos'] : $fechasValoracion,
                    'valor' => $datos['operacion'] ?? 'futuro',
                ])
            @endif

            @if ($herramienta === 'series')
                @include('ingenieria-economica._campo', [
                    'campo' => 'tipo_serie', 'etiqueta' => 'Tipo de serie', 'tipo' => 'select', 'obligatorio' => true,
                    'opciones' => SeriesUniformesService::TIPOS, 'valor' => $datos['tipo_serie'] ?? 'vencida',
                    'ayuda' => 'Vencida: primer pago en t = 1. Anticipada: primer pago en t = 0.',
                ])
                <div data-campo-serie="primer_pago">
                    @include('ingenieria-economica._campo', [
                        'campo' => 'primer_pago', 'etiqueta' => 'Período del primer pago diferido', 'tipo' => 'number',
                        'minimo' => 2, 'maximoNumerico' => DecimalFinanciero::MAX_PERIODOS, 'valor' => $datos['primer_pago'] ?? null,
                        'ayuda' => 'Cuenta desde t = 0. Escribe 3 si el primer pago ocurre al final del tercer período.',
                    ])
                </div>
                <div data-campo-serie="valor_base">
                    @include('ingenieria-economica._campo', [
                        'campo' => 'valor_base', 'etiqueta' => 'Fecha del valor objetivo', 'tipo' => 'select',
                        'opciones' => $fechasValoracion, 'valor' => $datos['valor_base'] ?? 'presente',
                    ])
                </div>
                <div data-campo-serie="objetivo">
                    @include('ingenieria-economica._campo', [
                        'campo' => 'objetivo', 'etiqueta' => 'Valor objetivo', 'valor' => $datos['objetivo'] ?? null,
                        'ayuda' => 'Valor presente o futuro que debe igualar la serie al despejar el número de pagos.',
                    ])
                </div>
            @endif

            @if ($herramienta !== 'tasas')
                <div @if ($herramienta === 'series') data-campo-serie="periodos" @endif>
                    @include('ingenieria-economica._campo', [
                        'campo' => 'periodos', 'etiqueta' => $herramienta === 'series' ? 'Número de pagos' : 'Número de períodos',
                        'tipo' => 'number', 'minimo' => $herramienta === 'interes-compuesto' ? 0 : 1, 'maximoNumerico' => DecimalFinanciero::MAX_PERIODOS,
                        'valor' => $datos['periodos'] ?? null, 'obligatorio' => $herramienta !== 'series',
                    ])
                </div>
                @include('ingenieria-economica._campo', [
                    'campo' => 'cantidad', 'etiqueta' => match ($herramienta) {
                        'interes-compuesto' => 'Capital',
                        'series' => 'Importe de entrada',
                        default => 'Cantidad para aplicar el factor',
                    },
                    'valor' => $datos['cantidad'] ?? null, 'obligatorio' => $herramienta !== 'factores',
                    'ayuda' => match ($herramienta) {
                        'interes-compuesto' => 'Para calcular F, escribe el capital presente. Para calcular P, escribe el capital futuro.',
                        'series' => 'Escribe el pago uniforme, excepto al calcular la anualidad, donde se ingresa el valor objetivo.',
                        default => 'Opcional. Si indicas una cantidad, selecciona un factor.',
                    },
                ])
            @endif
            @if ($herramienta === 'factores')
                @include('ingenieria-economica._campo', [
                    'campo' => 'factor', 'etiqueta' => 'Factor para aplicar', 'tipo' => 'select',
                    'opciones' => array_combine(array_keys(FactoresFinancierosService::NOMBRES), array_keys(FactoresFinancierosService::NOMBRES)),
                    'valor' => $datos['factor'] ?? null,
                ])
            @endif
        </div>
        <div class="actions">
            <button class="button" type="submit">Calcular</button>
            @if ($resultado !== null)
                <a class="button secondary" href="{{ route('ingenieria-economica.herramientas.show', $herramienta) }}">Nuevo cálculo</a>
            @endif
        </div>
    </form>
</section>

@if ($resultado !== null)
    @include('ingenieria-economica.herramientas._resultado')
@endif

@if ($herramienta === 'series')
    <script>
        (() => {
            const form = document.querySelector('#calculadora-financiera');
            const tipo = form.elements.tipo_serie;
            const operacion = form.elements.operacion;

            const actualizar = () => {
                const requiere = {
                    primer_pago: tipo.value === 'diferida',
                    valor_base: ['anualidad', 'periodos'].includes(operacion.value),
                    objetivo: operacion.value === 'periodos',
                    periodos: operacion.value !== 'periodos',
                };

                form.querySelectorAll('[data-campo-serie]').forEach((contenedor) => {
                    const visible = requiere[contenedor.dataset.campoSerie];
                    const control = contenedor.querySelector('input, select');
                    contenedor.hidden = !visible;
                    control.disabled = !visible;
                    control.required = visible;
                });
            };

            tipo.addEventListener('change', actualizar);
            operacion.addEventListener('change', actualizar);
            actualizar();
        })();
    </script>
@endif
@endsection
