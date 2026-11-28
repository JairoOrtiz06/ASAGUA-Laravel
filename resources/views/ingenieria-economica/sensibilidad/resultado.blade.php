@extends('layouts.ingenieria-economica')

@use('App\Services\IngenieriaEconomica\ContextoEvaluacion')

@php
    use App\Models\IngenieriaEconomica\Evaluacion;
    use App\Services\IngenieriaEconomica\DecimalFinanciero;
    use App\Services\IngenieriaEconomica\MemoriaCalculoService;
    use App\Services\IngenieriaEconomica\SensibilidadService;
    use App\Services\IngenieriaEconomica\VariablesSensibilidadService;
    use Brick\Math\BigDecimal;

    $base = $r['base'];
    $d = $r['configuracion'];
    $moneda = $d['moneda'];
    $nombres = array_map(fn ($a) => $a['nombre'], $base['alternativas']);
    $numero = fn ($v, $escala = 10) => $v === null ? 'No aplicable' : DecimalFinanciero::mostrar(BigDecimal::of($v), $escala);
    $dinero = fn ($v) => $v === null ? 'No aplicable' : DecimalFinanciero::dinero(BigDecimal::of($v));
    $porcentaje = fn ($v) => $numero((string) BigDecimal::of($v)->multipliedBy(100)).' %';
    $valor = function ($i, $k) use ($numero, $dinero, $porcentaje) {
        if ($k === 'tir') {
            $t = $i['tir'];
            return $t['estado'] === 'unica' ? $porcentaje($t['raices'][0]['tasa']).' / período' : MemoriaCalculoService::estadoTir($t);
        }
        return $k === 'rcb' ? $numero($i[$k]) : $dinero($i[$k]);
    };
    $parametros = json_encode($d, JSON_THROW_ON_ERROR);
@endphp

@section('economia-content')
<div class="topbar">
    <div><h2>{{ $d['nombre'] }}{{ $guardado ? ' #'.$guardado->id : '' }}</h2><p class="muted">{{ $base['proyecto']['nombre'] }} · {{ SensibilidadService::MODOS[$d['modo']] }} · {{ Evaluacion::PERSPECTIVAS[$d['perspectiva']] }}</p></div>
    <a class="button button-regresar" href="{{ ContextoEvaluacion::enlace('sensibilidad.index', $proyecto) }}"><span class="button-regresar-icono" aria-hidden="true">←</span> Análisis</a>
</div>
<section class="panel-link">
    <h3>Base, supuestos y advertencias</h3>
    <p>{{ $base['escenario']['nombre'] }} · {{ $base['escenario']['base'] }} a {{ $base['escenario']['fin'] }} · {{ $base['escenario']['horizonte'] }} años · {{ $moneda }}.</p>
    <p>TMAR base {{ $porcentaje($base['escenario']['anual']) }} anual · {{ $porcentaje($base['escenario']['tasa']) }} {{ $base['escenario']['periodicidad'] }} · {{ $base['escenario']['fuente_tasa'] ?? 'Sin fuente de tasa registrada' }}.</p>
    <p>Evaluación de origen · {{ isset($d['evaluacion_id']) ? '#'.$d['evaluacion_id'] : 'Escenario capturado con datos vigentes al calcular' }}.
        Estado TMAR · {{ $base['escenario']['estado_tasa'] }}.</p>
    <p>Motor {{ $r['version'] }} · Estado {{ $d['estado'] }}{{ $guardado ? ' · '.$guardado->created_at->format('d/m/Y H:i').' · Contador #'.$guardado->id_usuario : ' · Vista previa' }}.</p>
    @if ($d['descripcion'] ?? null)<p class="description">{{ $d['descripcion'] }}</p>@endif
    <p class="description">{{ $d['supuestos'] }}</p>
    <h4>Advertencias e interpretación</h4>
    <ul>
        @foreach ($r['advertencias'] as $aviso)<li>{{ $aviso }}</li>@endforeach
        @foreach ($base['alternativas'] as $a)
            @foreach ($a['avisos'] as $aviso)<li>{{ $a['nombre'] }} · {{ $aviso }}</li>@endforeach
        @endforeach
    </ul>
    @if (! $guardado)
        <form method="POST" action="{{ ContextoEvaluacion::enlace('sensibilidad.store', $proyecto) }}">
            @csrf
            <input type="hidden" name="parametros" value="{{ $parametros }}">
            <input type="hidden" name="firma" value="{{ $firma }}">
            <button class="button">Guardar versión</button>
        </form>
    @endif
</section>

<section class="section panel-link">
    <h3>Indicadores de la base</h3>
    <div class="table-wrap"><table>
        <thead><tr><th>Alternativa</th>@foreach ($d['indicadores'] as $k)<th>{{ SensibilidadService::INDICADORES[$k] }}</th>@endforeach</tr></thead>
        <tbody>@foreach ($base['alternativas'] as $a)
            <tr><th scope="row">{{ $a['nombre'] }}</th>@foreach ($d['indicadores'] as $k)<td>{{ $valor($a['indicadores'], $k) }}</td>@endforeach</tr>
        @endforeach</tbody>
    </table></div>
</section>

@if ($r['equilibrio'])
    @include('ingenieria-economica.sensibilidad._equilibrio', ['punto' => $r['equilibrio']])
@endif
@if ($d['modo'] === 'matriz')
    @include('ingenieria-economica.sensibilidad._matriz')
@endif
@if ($r['simulaciones'])
    <section class="section panel-link">
        <h3>Resultados de simulación</h3>
        @if ($d['modo'] === 'escenarios')
            <p>Los escenarios Optimista y Pesimista usan los cambios y justificaciones ingresados. La fila Base conserva los valores de referencia. El análisis no asigna probabilidades.</p>
        @endif
        <p>Los importes se presentan en {{ $moneda }}. CAUE en {{ $moneda }}/año. El cambio absoluto resta el valor base y conserva la unidad. El cambio relativo divide esa diferencia entre la base y se omite si la base es cero. La sensibilidad relativa es S = (% cambio del indicador) / (% cambio de la variable).</p>
        <div class="table-wrap"><table>
            <thead><tr><th>Simulación</th><th>Alternativa</th>
                @foreach ($d['indicadores'] as $k)<th>{{ SensibilidadService::INDICADORES[$k] }}</th>@endforeach
                <th>Memoria</th></tr></thead>
            <tbody>
                @if ($d['modo'] === 'escenarios')
                    @foreach ($base['alternativas'] as $a)
                        <tr><th scope="row">Base</th><td>{{ $a['nombre'] }}</td>
                            @foreach ($d['indicadores'] as $k)<td>{{ $valor($a['indicadores'], $k) }}</td>@endforeach
                            <td><a href="#memoria">Ver memoria base</a></td></tr>
                    @endforeach
                @endif
                @foreach ($r['simulaciones'] as $i => $s)
                @foreach ($s['alternativas'] as $id => $a)
                    <tr><th scope="row">{{ $s['nombre'] }}</th><td>{{ $nombres[$id] }}</td>
                        @foreach ($d['indicadores'] as $k)
                            <td>{{ $valor($a['indicadores'], $k) }}
                                @if ($k !== 'tir')
                                    <small class="muted"><br>Δ {{ $k === 'rcb' ? $numero($a['deltas'][$k]) : $dinero($a['deltas'][$k]).' '.$moneda.($k === 'caue' ? '/año' : '') }}<br>
                                        Δ relativo {{ $numero($a['cambios_pct'][$k] ?? null) }}{{ isset($a['cambios_pct'][$k]) ? ' %' : '' }}<br>S {{ $numero($a['elasticidades'][$k]['valor']) }}
                                        @if ($a['elasticidades'][$k]['motivo']) · {{ $a['elasticidades'][$k]['motivo'] }} @endif
                                    </small>
                                @endif
                            </td>
                        @endforeach
                        <td>
                            @if ($guardado)
                                <a href="{{ ContextoEvaluacion::enlace('sensibilidad.show', [$proyecto, $guardado, 'memoria' => $i]) }}#memoria">Ver memoria</a>
                            @else
                                <form method="POST" action="{{ ContextoEvaluacion::enlace('sensibilidad.preview', $proyecto) }}#memoria">
                                    @csrf
                                    <input type="hidden" name="parametros" value="{{ $parametros }}">
                                    <button class="button secondary" name="memoria" value="{{ $i }}">Ver memoria</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            @endforeach</tbody>
        </table></div>
        <details class="section" @if ($d['modo'] === 'unidimensional' || $d['modo'] === 'escenarios') open @endif>
            <summary>Cambios, diferencias incrementales y advertencias por simulación</summary>
            <ul class="records">
                @foreach ($r['simulaciones'] as $s)
                    <li>
                        <strong>{{ $s['nombre'] }}</strong> · TMAR {{ $porcentaje($s['anual']) }} · {{ $s['horizonte'] }} años.
                        @if ($s['supuesto'])<p>{{ $s['supuesto'] }}</p>@endif
                        <ul>
                            @foreach ($s['variables'] as $v)
                                @php($unidadCambio = str_starts_with($v['unidad'], '%') ? 'puntos porcentuales' : $v['unidad'])
                                <li>{{ $v['nombre'] }} · Base {{ $numero($v['base']) }} {{ $v['unidad'] }} → Evaluado {{ $numero($v['nuevo']) }} {{ $v['unidad'] }}.
                                    Cambio absoluto {{ $numero((string) BigDecimal::of($v['nuevo'])->minus($v['base'])) }} {{ $unidadCambio === 'puntos porcentuales' && BigDecimal::of($v['nuevo'])->minus($v['base'])->abs()->isEqualTo(1) ? 'punto porcentual' : $unidadCambio }}.
                                    Cambio relativo {{ $v['cambio_pct'] === null ? 'No aplicable (base cero)' : $numero($v['cambio_pct']).' %' }}.</li>
                            @endforeach
                            @foreach ($s['laboral'] as $p)
                                <li>{{ $p['formula'] }} = ({{ $numero($p['parametros']['salario']) }} × {{ $numero($p['parametros']['horas']) }} × {{ $numero($p['parametros']['dias']) }}) / ({{ $numero($p['parametros']['jornada']) }} × {{ $numero($p['parametros']['dias']) }}) ≈ {{ $numero($p['costo_mensual']) }} {{ $moneda }}/mes.</li>
                            @endforeach
                            @foreach ($s['comparaciones'] as $c)
                                <li>{{ $nombres[$c['id_a']] }} − {{ $nombres[$c['id_b']] }} · VAN incremental {{ $dinero($c['van']) }} · Δ VPC {{ $dinero($c['diferencia_vpc']) }} · Δ CAUE {{ $dinero($c['diferencia_caue']) }}. {{ $c['criterio'] }}</li>
                            @endforeach
                            @foreach ($s['alternativas'] as $id => $a)
                                @foreach ($a['avisos'] as $aviso)<li>{{ $nombres[$id] }} · {{ $aviso }}</li>@endforeach
                            @endforeach
                        </ul>
                    </li>
                @endforeach
            </ul>
        </details>
    </section>
@endif

@if ($d['modo'] === 'unidimensional' || $d['modo'] === 'escenarios' || $r['equilibrio'])
    @include('ingenieria-economica.sensibilidad._grafica')
@endif
<section class="section panel-link" id="memoria">
    <h3>Memoria de cálculo · {{ $indice !== null ? ($r['simulaciones'][$indice]['nombre'] ?? 'Base') : 'Base' }}</h3>
    @if (isset($avisoMemoria) && $avisoMemoria)<p class="economia-aviso"><strong>Advertencia.</strong> {{ $avisoMemoria }}</p>@endif
    <p>{{ $memoria['convencion'] }}</p>
    <p>Horizonte {{ $memoria['escenario']['horizonte'] }} años · Tasa efectiva {{ $porcentaje($memoria['escenario']['tasa']) }} por período.</p>
    <p>VAN = Σ FCₜ × P/F. VPC = Σ costosₜ × P/F. P/F = (1+i)⁻ᵗ. CAUE = VPC × A/P anual. RCB = VP beneficios ÷ VP costos.</p>
    @foreach ($memoria['alternativas'] as $a)
        <details class="section"><summary>{{ $a['nombre'] }} · VAN {{ $dinero($a['indicadores']['van']) }} · VPC {{ $dinero($a['indicadores']['vpc']) }} · CAUE {{ $dinero($a['indicadores']['caue']) }}</summary>
            <p>Factor A/P {{ $numero($a['indicadores']['factor_ap'], 16) }} · VP de beneficios {{ $dinero($a['indicadores']['vp_beneficios']) }}.</p>
            <div class="table-wrap"><table>
                <thead><tr><th>Período</th><th>Cierre</th><th>Beneficios</th><th>Costos</th><th>Flujo neto</th><th>P/F</th><th>VP</th><th>Flujos y fechas originales</th></tr></thead>
                <tbody>@foreach ($a['periodos'] as $i => $fila)
                    <tr><th scope="row">{{ $i }}</th><td>{{ $fila['hasta'] }}</td><td>{{ $dinero($fila['beneficios']) }}</td><td>{{ $dinero($fila['costos']) }}</td>
                        <td>{{ $dinero($fila['neto']) }}</td><td>{{ $numero($fila['factor'], 16) }}</td><td>{{ $dinero($fila['vp']) }}</td>
                        <td>@foreach ($fila['movimientos'] as $m)
                            <div>{{ $m['fecha'] }} · <a href="#fuente-sens-{{ $m['origen'] }}">{{ $memoria['origenes'][$m['origen']]['nombre'] }}</a> · {{ $dinero($m['monto']) }} · {{ $m['motivo'] ?? 'Incluido' }}</div>
                        @endforeach</td></tr>
                @endforeach</tbody>
            </table></div>
            @if ($a['excluidos'])
                <p>Exclusiones</p><ul>@foreach ($a['excluidos'] as $m)<li>{{ $m['fecha'] }} · {{ $memoria['origenes'][$m['origen']]['nombre'] }} · {{ $m['motivo'] }}</li>@endforeach</ul>
            @endif
        </details>
    @endforeach
    @foreach ($memoria['comparaciones'] as $c)
        <details class="section"><summary>Memoria incremental · {{ $nombres[$c['id_a']] }} − {{ $nombres[$c['id_b']] }}</summary>
            <div class="table-wrap"><table>
                <thead><tr><th>Período</th><th>Cierre</th><th>Propio A</th><th>Propio B</th><th>Flujo incremental</th><th>P/F</th><th>VP incremental</th></tr></thead>
                <tbody>@foreach ($c['periodos'] as $i => $f)
                    <tr><th scope="row">{{ $i }}</th><td>{{ $f['hasta'] }}</td><td>{{ $dinero($f['a']) }}</td><td>{{ $dinero($f['b']) }}</td><td>{{ $dinero($f['neto']) }}</td><td>{{ $numero($f['factor'],16) }}</td><td>{{ $dinero($f['vp']) }}</td></tr>
                @endforeach</tbody>
            </table></div>
        </details>
    @endforeach
    <details class="section"><summary>Fuentes y valores del cálculo</summary>
        <ul class="records">@foreach ($memoria['origenes'] as $id => $o)
            <li id="fuente-sens-{{ $id }}">{{ $o['nombre'] }} · {{ $numero($o['monto'], 12) }} {{ $moneda }}.
                {{ $o['observaciones'] ?? '' }}
                <ul>@foreach ($o['fuentes'] as $f)<li>{{ $f['fecha'] }} · {{ $f['referencia'] }} · {{ $f['justificacion'] }}</li>@endforeach</ul>
            </li>
        @endforeach</ul>
    </details>
</section>
@endsection
