@extends('layouts.ingenieria-economica')

@use('App\Services\IngenieriaEconomica\ContextoEvaluacion')

@php
    use App\Models\IngenieriaEconomica\Alternativa;
    use App\Models\IngenieriaEconomica\Escenario;
    use App\Models\IngenieriaEconomica\Evaluacion;
    use App\Services\IngenieriaEconomica\DecimalFinanciero;
    use Brick\Math\BigDecimal;

    $escenario = $resultado['escenario'];
    $moneda = $resultado['moneda'];
    $decimal = fn (string $valor) => DecimalFinanciero::mostrar(BigDecimal::of($valor), 16);
    $porcentaje = fn (string $valor) => $decimal((string) BigDecimal::of($valor)->multipliedBy(100)).' %';
@endphp

@section('economia-content')
<div class="topbar">
    <div>
        <h2>{{ $evaluacion ? 'Evaluación #'.$evaluacion->id : 'Vista previa de evaluación' }}</h2>
        <p class="muted">{{ $resultado['proyecto']['nombre'] }} · {{ $escenario['nombre'] }} · {{ Evaluacion::PERSPECTIVAS_PRESENTACION[$resultado['perspectiva']] }}</p>
    </div>
    <div class="actions">
        @if ($evaluacion)
            <a class="button secondary" href="{{ ContextoEvaluacion::enlace('validacion', $proyecto) }}">Validar esta evaluación</a>
            <a class="button secondary" href="{{ ContextoEvaluacion::enlace('evidencias.index',[$proyecto,'tipo'=>'evaluacion','registro'=>$evaluacion->id,'evaluacion_fuentes'=>$evaluacion->id]) }}">Respaldo documental</a>
        @endif
        @if ($evaluacion && ! isset($resultado['fiscal']))
            <a class="button" href="{{ ContextoEvaluacion::enlace('sensibilidad.create', ['proyecto' => $proyecto, 'evaluacion_id' => $evaluacion->id]) }}">Simular esta versión</a>
        @endif
        @if ($evaluacion)
            <a class="button button-regresar" href="{{ ContextoEvaluacion::enlace('show', $proyecto) }}"><span class="button-regresar-icono" aria-hidden="true">←</span> Volver al proyecto</a>
        @endif
        <a class="button button-regresar" href="{{ ContextoEvaluacion::enlace('evaluaciones.index', $proyecto) }}"><span class="button-regresar-icono" aria-hidden="true">←</span> Evaluaciones</a>
    </div>
</div>

<p class="muted">{{ $evaluacion ? 'Versión guardada. Estos resultados proceden de su instantánea histórica.' : 'Vista previa calculada. Guarda la evaluación para conservar esta versión.' }}</p>
@if ($evaluacion)
    <p><a href="{{ route('ingenieria-economica.proyectos.evaluaciones.index', [$proyecto, ...ContextoEvaluacion::entrada(ContextoEvaluacion::desdeInstantanea($resultado))]) }}#seleccion-evaluacion">Preparar nuevo cálculo con datos actuales</a></p>
@endif

<section class="panel-link">
    <h3>Parámetros y supuestos</h3>
    <dl class="panel-grid summary section">
        <div><dt>Horizonte</dt><dd>{{ $escenario['horizonte'] }} años · {{ $escenario['base'] }} a {{ $escenario['fin'] }}</dd></div>
        <div><dt>Periodicidad del descuento</dt><dd>{{ Escenario::PERIODOS[$escenario['periodicidad']] }} · Escenario {{ strtolower(Escenario::PERIODOS[$escenario['periodicidad_escenario']]) }}</dd></div>
        <div><dt>Tasa original</dt><dd>{{ $escenario['original'] !== null ? $porcentaje($escenario['original']) : 'Sin cotización registrada' }} · {{ $escenario['modalidad_tasa'] ?? '—' }} · {{ $escenario['periodicidad_tasa'] ?? '—' }}</dd></div>
        <div><dt>TMAR efectiva anual</dt><dd>{{ $porcentaje($escenario['anual']) }} · {{ Escenario::ESTADOS_TASA[$escenario['estado_tasa']] }}</dd></div>
        <div><dt>Tasa efectiva por período</dt><dd>{{ $porcentaje($escenario['tasa']) }} · fracción {{ $decimal($escenario['tasa']) }}</dd></div>
        <div><dt>Fuente de TMAR</dt><dd>{{ $escenario['fuente_tasa'] ?? 'Sin fuente registrada' }}</dd></div>
        <div><dt>Moneda y motor</dt><dd>{{ $moneda }} · {{ $resultado['version'] }}</dd></div>
        @if ($evaluacion)
            <div><dt>Guardada</dt><dd>{{ $evaluacion->created_at->format('d/m/Y H:i') }} · Contador #{{ $evaluacion->id_usuario }}</dd></div>
        @endif
    </dl>
    <p>{{ $resultado['convencion'] }}</p>
    <p>{{ $resultado['supuesto_horizonte'] }}</p>
    @if ($resultado['supuestos'])<p class="description">{{ $resultado['supuestos'] }}</p>@endif
    @foreach ($resultado['advertencias'] as $aviso)<p class="economia-aviso"><strong>Advertencia.</strong> {{ $aviso }}</p>@endforeach
    @unless ($evaluacion)
        <form method="POST" action="{{ ContextoEvaluacion::enlace('evaluaciones.store', $proyecto) }}">
            @csrf
            @foreach ($datos['alternativas'] as $id)
                <input type="hidden" name="alternativas[]" value="{{ $id }}">
            @endforeach
            @foreach (['escenario_id', 'perspectiva', 'moneda', 'periodicidad', 'referencia_id', 'equivalencia', 'supuestos'] as $campo)
                @if (isset($datos[$campo]))
                    <input type="hidden" name="{{ $campo }}" value="{{ $datos[$campo] }}">
                @endif
            @endforeach
            @foreach ($datos['fiscal'] ?? [] as $claveFiscal => $valorFiscal)
                @if ($claveFiscal === 'alternativas')
                    @foreach ($valorFiscal ?? [] as $idFiscal => $camposFiscales)
                        @foreach ($camposFiscales as $campoFiscal => $textoFiscal)
                            <textarea hidden name="fiscal[alternativas][{{ $idFiscal }}][{{ $campoFiscal }}]">{{ $textoFiscal }}</textarea>
                        @endforeach
                    @endforeach
                @elseif (is_scalar($valorFiscal) || $valorFiscal === null)
                    <textarea hidden name="fiscal[{{ $claveFiscal }}]">{{ $valorFiscal }}</textarea>
                @endif
            @endforeach
            <input type="hidden" name="firma" value="{{ $firma }}">
            <button class="button" type="submit">Guardar evaluación</button>
        </form>
    @endunless
</section>

<section class="section panel-link">
    <h3>Interpretación de la comparación económica</h3>
    <ul>@foreach ($interpretacion['conclusiones'] as $conclusion)<li>{{ $conclusion }}</li>@endforeach</ul>
</section>

@foreach ($resultado['alternativas'] as $id => $alternativa)
    <section class="section panel-link">
        <h3>{{ $alternativa['nombre'] }}</h3>
        <p>Viabilidad técnica: {{ Alternativa::VIABILIDADES[$alternativa['viabilidad']] }}.</p>
        @foreach ($interpretacion['alternativas'][$id]['etiquetas'] as $etiqueta)
            <p>{{ $etiqueta }}</p>
        @endforeach
        @if ($alternativa['observaciones'])<p class="description">{{ $alternativa['observaciones'] }}</p>@endif
        @if ($alternativa['avisos'])
            <ul class="economia-aviso">@foreach ($alternativa['avisos'] as $aviso)<li>{{ $aviso }}</li>@endforeach</ul>
        @endif
        @php($detalle = $memoria->describir($alternativa, $escenario))
        <dl class="panel-grid summary">
            @foreach (['van' => 'VAN/VPN', 'vpc' => 'VPC', 'caue' => 'CAUE', 'rcb' => 'RCB', 'tir' => 'TIR'] as $clave => $titulo)
                <div class="panel-link">
                    <dt>{{ $titulo }}</dt>
                    <dd>
                        @if ($clave === 'tir' && $alternativa['indicadores']['tir']['estado'] === 'unica')
                            {{ $porcentaje($alternativa['indicadores']['tir']['raices'][0]['tasa']) }} por período
                        @else
                            {{ $detalle[$clave]['resultado'] }}{{ in_array($clave, ['van', 'vpc', 'caue'], true) ? ' '.$moneda.($clave === 'caue' ? '/año' : '') : '' }}
                        @endif
                    </dd>
                </div>
            @endforeach
        </dl>
        @foreach ($detalle as $clave => $indicador)
            @include('ingenieria-economica.evaluaciones._indicador')
        @endforeach
        <details class="section">
            <summary>Movimientos originales y exclusiones</summary>
            @include('ingenieria-economica.evaluaciones._movimientos')
        </details>
    </section>
@endforeach

@if (isset($resultado['fiscal']))
    @include('ingenieria-economica.evaluaciones._resultado-fiscal', ['r' => $resultado, 'pdf' => false])
    <p class="muted">Para comparar supuestos fiscales distintos, genera otra evaluación con sus bases documentadas.</p>
@endif

@foreach ($resultado['comparaciones'] as $comparacion)
    @include('ingenieria-economica.evaluaciones._comparacion')
@endforeach

<section class="section panel-link">
    <h3>Fuentes y datos originales</h3>
    <ul class="records">
        @forelse ($resultado['origenes'] as $codigo => $origen)
            <li id="origen-{{ $codigo }}">
                <strong>{{ $origen['nombre'] }} · {{ $codigo }}</strong>
                <div>Importe original {{ $memoria->importe((string) $origen['monto']) }} {{ $moneda }} @isset($origen['periodicidad']) · {{ $origen['periodicidad'] }} · {{ $origen['momento_pago'] }} @endisset</div>
                @isset($origen['periodicidad'])
                    <div>Mes inicial {{ $origen['inicio'] ?? 'automático' }} · Mes final {{ $origen['fin'] ?? 'automático' }} · Ocurrencias {{ $origen['ocurrencias'] ?? 'automáticas' }} · Crecimiento {{ $origen['crecimiento'] ?? '0' }}</div>
                @endisset
                @if (!empty($origen['descripcion']))<p class="description">{{ $origen['descripcion'] }}</p>@endif
                @if (!empty($origen['observaciones']))<p class="description">{{ $origen['observaciones'] }}</p>@endif
                <ul>
                    @forelse ($origen['fuentes'] as $fuente)
                        <li>{{ $fuente['fecha'] }} · {{ $fuente['tipo'] }} · {{ $fuente['referencia'] }}.
                            Documento {{ $fuente['id_documento'] ?? '—' }} · Detalle {{ $fuente['id_detalle'] ?? '—' }}.
                            Importe observado {{ $fuente['observado'] ?? '—' }} · Utilizado {{ $fuente['utilizado'] }}.
                            {{ $fuente['justificacion'] }}</li>
                    @empty
                        <li class="muted">Sin evidencia vinculada.</li>
                    @endforelse
                </ul>
            </li>
        @empty
            <li class="muted">No hay movimientos con fuentes en esta evaluación.</li>
        @endforelse
    </ul>
</section>
@endsection
