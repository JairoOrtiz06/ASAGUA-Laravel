@extends('layouts.ingenieria-economica')

@section('economia-content')
<div class="topbar">
    <div><h2>Conservación y reemplazo</h2><p class="muted">Activo existente · {{ $activo->nombre }} · {{ $activo->moneda }}</p></div>
    <a class="button secondary" href="{{ route('ingenieria-economica.proyectos.activos.show', [$proyecto, $activo]) }}">← Activo</a>
</div>
@if ($escenarios->isEmpty())
    <p>Registra un escenario con TMAR antes de comparar.</p>
@else
    <form class="panel-link" method="POST" action="{{ route('ingenieria-economica.proyectos.activos.reemplazo.store', [$proyecto, $activo]) }}">
        @csrf
        <div class="form-grid">
            @include('ingenieria-economica._campo', ['campo' => 'escenario_id', 'etiqueta' => 'Escenario común', 'tipo' => 'select', 'opciones' => $escenarios->mapWithKeys(fn ($item) => [$item->id => $item->nombre.' · '.$item->horizonte_anios.' años · '.$item->periodicidad.' · TEA '.($item->tasa_efectiva_anual === null ? 'pendiente' : \App\Services\IngenieriaEconomica\DecimalFinanciero::porcentaje($item->tasa_efectiva_anual).' %')]), 'obligatorio' => true])
            @include('ingenieria-economica._campo', ['campo' => 'nuevo_id', 'etiqueta' => 'Activo nuevo registrado (opcional)', 'tipo' => 'select', 'opciones' => $nuevos, 'ayuda' => 'Las cifras de este análisis deben documentarse en el formulario.'])
            @include('ingenieria-economica._campo', ['campo' => 'politica', 'etiqueta' => 'Política del horizonte común', 'tipo' => 'select', 'opciones' => ['sin_reposicion' => 'Sin reposición durante el horizonte', 'reposiciones_documentadas' => 'Reposiciones documentadas'], 'valor' => 'sin_reposicion', 'obligatorio' => true])
            @include('ingenieria-economica._campo', ['campo' => 'equivalencia', 'etiqueta' => 'Equivalencia técnica y de calidad', 'tipo' => 'select', 'opciones' => [0 => 'Pendiente', 1 => 'Verificada'], 'valor' => 0])
            @include('ingenieria-economica._campo', ['campo' => 'justificacion', 'etiqueta' => 'Supuestos, fuentes y justificación de valores terminales', 'tipo' => 'textarea'])
            @foreach (['valor_libros_actual' => 'Valor en libros actual (informativo)', 'mercado_actual' => 'Valor de mercado actual', 'venta_actual' => 'Importe de venta hoy', 'retiro_ahora' => 'Costo de retiro hoy', 'precio' => 'Precio del nuevo', 'instalacion' => 'Instalación del nuevo', 'puesta_marcha' => 'Puesta en marcha del nuevo'] as $campo => $etiqueta)
                @include('ingenieria-economica._campo', ['campo' => $campo, 'etiqueta' => $etiqueta, 'obligatorio' => $campo !== 'valor_libros_actual'])
            @endforeach
            @include('ingenieria-economica._campo',['campo'=>'valoracion_fecha','etiqueta'=>'Fecha de referencia de las valoraciones','tipo'=>'date'])
            @include('ingenieria-economica._campo',['campo'=>'valoracion_fuente','etiqueta'=>'Fuente y alcance de las valoraciones','tipo'=>'textarea','maximo'=>1000,
                'ayuda'=>'Identifica qué documento respalda mercado, venta, valor en libros o importes cero. Indica la fecha de cada valor si difieren.'])
        </div>
        <p>El valor en libros refleja el saldo contable; el valor de mercado es una estimación. La oportunidad de conservar se mide por la venta neta que se dejaría de obtener.</p>
        <p><a href="{{ route('ingenieria-economica.proyectos.evidencias.index',[$proyecto,'tipo'=>'activo','registro'=>$activo->id]) }}">Consultar respaldos del activo</a></p>
        <label class="flujo-opcion"><input type="checkbox" name="ceros_documentados" value="1" @checked(old('ceros_documentados'))> Los costos cero consignados están documentados</label>
        <label class="flujo-opcion"><input type="checkbox" name="considerar_retiro" value="1" @checked(old('considerar_retiro'))> Mostrar retiro inmediato sin continuidad del servicio</label>
        @foreach (['conservar' => 'Conservar', 'reemplazar' => 'Reemplazar'] as $opcion => $titulo)
            <fieldset class="section">
                <legend>{{ $titulo }}</legend>
                <div class="form-grid">
                    @foreach (['vida' => 'Vida disponible (años)', 'operacion' => 'Operación anual', 'mantenimiento' => 'Mantenimiento anual', 'rescate' => 'Rescate al cierre del escenario', 'retiro' => 'Costo de retiro al cierre'] as $campo => $etiqueta)
                        <div>
                            <label for="{{ $opcion }}-{{ $campo }}">{{ $etiqueta }} *</label>
                            <input id="{{ $opcion }}-{{ $campo }}" name="{{ $opcion }}[{{ $campo }}]" inputmode="{{ $campo === 'vida' ? 'numeric' : 'decimal' }}" value="{{ is_scalar(old($opcion.'.'.$campo)) ? old($opcion.'.'.$campo) : '' }}" required>
                        </div>
                    @endforeach
                    @include('ingenieria-economica.activos._filas-reemplazo',['tipoFila'=>'reposiciones'])
                    @include('ingenieria-economica.activos._filas-reemplazo',['tipoFila'=>'candidatos'])
                </div>
            </fieldset>
        @endforeach
        <div class="section actions"><button class="button" type="submit">Comparar y guardar versión</button></div>
    </form>
@endif
<script src="{{ asset('js/ingenieria-activos.js') }}" defer></script>
@endsection
