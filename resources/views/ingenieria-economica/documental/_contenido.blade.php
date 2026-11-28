@use('App\Services\IngenieriaEconomica\ContextoEvaluacion')

@php
    use App\Models\IngenieriaEconomica\Concepto;
    use App\Models\IngenieriaEconomica\Evaluacion;
    use App\Models\IngenieriaEconomica\EvidenciaDocumental;
    use App\Models\IngenieriaEconomica\VinculoDocumental;
    use App\Services\IngenieriaEconomica\DecimalFinanciero;
    use App\Services\IngenieriaEconomica\MemoriaCalculoService;
    use Brick\Math\BigDecimal;

    $r = $informe['evaluacion'];
    $memoria = app(MemoriaCalculoService::class);
    $e = $r['escenario'];
    $calidad = $informe['calidad'];
    $moneda = $r['moneda'];
    $fecha = fn ($v) => \Carbon\CarbonImmutable::parse($v)->format('d/m/Y');
    $importe = fn ($v) => DecimalFinanciero::dinero(BigDecimal::of($v));
    $decimal = fn ($v) => DecimalFinanciero::mostrar(BigDecimal::of($v),12);
    $porcentaje = fn ($v) => $decimal(BigDecimal::of($v)->multipliedBy(100)).' %';
@endphp

@if (!$pdf && ($informe['estudiantes'] || $informe['docentes']))
    @if ($informe['estudiantes'])<p class="description">Estudiantes · {{ $informe['estudiantes'] }}</p>@endif
    @if ($informe['docentes'])<p class="description">Docentes · {{ $informe['docentes'] }}</p>@endif
@endif
<section class="section">
    <h3>Resumen ejecutivo</h3>
    <p>Resultado calculado de la evaluación guardada #{{ $informe['evaluacion_id'] }} del {{ $fecha($informe['evaluacion_fecha']) }}.
        Motor {{ $r['version'] }} · {{ Evaluacion::PERSPECTIVAS_PRESENTACION[$r['perspectiva']] }} · {{ $moneda }}.</p>
    <p>{{ count($r['alternativas']) }} alternativa(s) estudiada(s). Estado de emisión {{ $informe['estado'] }}. Bloqueos documentales {{ count($calidad['bloqueos']) }}.</p>
    @if ($r['advertencias'])
        <div class="{{ $pdf ? 'errors' : 'economia-aviso' }}">
            <strong>Advertencias de la evaluación</strong>
            <ul>@foreach ($r['advertencias'] as $aviso)<li>{{ $aviso }}</li>@endforeach</ul>
        </div>
    @endif
    @if ($calidad['bloqueos'])
        <div class="{{ $pdf ? 'errors' : 'economia-bloqueo' }}">
            <strong>Bloqueos para emitir como validado</strong>
            <ul>@foreach ($calidad['bloqueos'] as $b)<li>{{ $b['requisito'] }} · {{ $b['detalle'] }}</li>@endforeach</ul>
            @if (!$pdf && isset($proyecto))
                <a href="{{ ContextoEvaluacion::enlace('validacion',[$proyecto,'evaluacion_id'=>$informe['evaluacion_id']]) }}">Consultar validación actual del proyecto</a>
            @endif
        </div>
    @endif

    @if (isset($informe['resumen']['alternativas']))
        <ul>@foreach ($informe['resumen']['conclusiones'] as $conclusion)<li>{{ $conclusion }}</li>@endforeach</ul>
    @endif

    <h3>Problema y alternativas</h3>
    <p class="description">{{ $informe['descripcion_problema'] ?? 'El proyecto no tiene una descripción registrada al emitir el informe.' }}</p>
    <p>Organización {{ $r['proyecto']['organizacion'] ?? 'Sin registrar' }} · Tipo de decisión {{ $r['proyecto']['tipo_decision'] }} · Fecha base {{ $fecha($e['base']) }}.</p>
    <p>La fecha base separa los movimientos pertinentes de los anteriores. Para estudiar una decisión histórica con otra fecha se necesita una evaluación separada.</p>
    <ul>@foreach ($r['alternativas'] as $id => $a)<li>{{ $a['nombre'] }} · Viabilidad técnica guardada {{ $a['viabilidad'] }}.
        @foreach ($informe['resumen']['alternativas'][$id]['etiquetas'] ?? [] as $etiqueta)
            <p>{{ $etiqueta }}</p>
        @endforeach
        @if ($a['observaciones'])<p class="description">{{ $a['observaciones'] }}</p>@endif</li>@endforeach</ul>

    <h3>Escenario, TMAR y equivalencias</h3>
    <p>{{ $e['nombre'] }} · {{ $e['horizonte'] }} años · Cierre {{ $fecha($e['fin']) }} · Periodicidad del descuento {{ $e['periodicidad'] }}.</p>
    <p>TMAR original {{ $e['original'] === null ? 'sin cotización registrada' : $porcentaje($e['original']) }} · {{ $e['modalidad_tasa'] ?? '—' }} · {{ $e['periodicidad_tasa'] ?? '—' }}.
        Efectiva anual {{ $porcentaje($e['anual']) }} · Efectiva del período {{ $porcentaje($e['tasa']) }}.</p>
    <p class="description">Estado TMAR {{ $e['estado_tasa'] }} · {{ $e['fuente_tasa'] ?? 'Sin fuente registrada' }}.</p>
    <p>{{ $r['convencion'] }} {{ $r['supuesto_horizonte'] }}</p>
    @if ($r['supuestos'])<p class="description">Supuestos de la evaluación · {{ $r['supuestos'] }}</p>@endif
</section>

<section class="section">
    <h3>Inversiones, costos e indicadores</h3>
    <div class="table-wrap"><table>
        <thead><tr><th>Alternativa</th><th>Inversión nominal</th><th>Costos recurrentes de caja</th><th>Oportunidad incluida</th><th>VAN ({{ $moneda }})</th><th>VPC ({{ $moneda }})</th><th>CAUE ({{ $moneda }}/año)</th></tr></thead>
        <tbody>@foreach ($r['alternativas'] as $id=>$a)
            <tr><th scope="row">{{ $a['nombre'] }}</th>
                @foreach (['inversion','recurrente','oportunidad'] as $k)<td>{{ $importe($informe['resumen']['resultados'][$id][$k]) }}</td>@endforeach
                @foreach (['van','vpc','caue'] as $k)<td>{{ $importe($a['indicadores'][$k]) }}</td>@endforeach
            </tr>
        @endforeach</tbody>
    </table></div>
    @foreach ($r['alternativas'] as $a)
        <p><strong>{{ $a['nombre'] }}</strong> · RCB {{ $a['indicadores']['rcb'] === null ? 'No aplicable con los datos actuales' : $decimal($a['indicadores']['rcb']) }} ·
            TIR {{ MemoriaCalculoService::estadoTir($a['indicadores']['tir']) }}.
            @foreach ($a['indicadores']['tir']['raices'] as $raiz) {{ $porcentaje($raiz['tasa']) }} efectiva por período. @endforeach</p>
        @if (isset($informe['resumen']['alternativas']))
            <ul>@foreach ($memoria->describir($a, $e) as $clave => $detalle)
                <li>{{ strtoupper($clave) }} · {{ $detalle['interpretacion'] }}</li>
            @endforeach</ul>
        @endif
        @if ($a['avisos'])<ul>@foreach ($a['avisos'] as $aviso)<li>{{ $aviso }}</li>@endforeach</ul>@endif
    @endforeach

    @if (isset($r['fiscal']))
        @include('ingenieria-economica.evaluaciones._resultado-fiscal')
    @elseif (array_key_exists('condicion_fiscal', $informe))
        <p>{{ $informe['condicion_fiscal'] }}</p>
    @endif

    <h3>Comparación incremental</h3>
    @forelse ($r['comparaciones'] as $c)
        <p>{{ $r['alternativas'][$c['id_a']]['nombre'] }} − {{ $r['alternativas'][$c['id_b']]['nombre'] }}
            · VAN incremental {{ $importe($c['van']) }} {{ $moneda }} · Diferencia VPC {{ $importe($c['diferencia_vpc']) }} {{ $moneda }} · Diferencia CAUE {{ $importe($c['diferencia_caue']) }} {{ $moneda }}/año.
            {{ $informe['resumen']['pares'][$c['id_a']][$c['id_b']] ?? $c['criterio'] }}</p>
        <p>TIR incremental · {{ MemoriaCalculoService::estadoTir($c['tir']) }}.
            @foreach ($c['tir']['raices'] as $raiz) {{ $porcentaje($raiz['tasa']) }} por período. @endforeach</p>
    @empty
        <p>La evaluación contiene una sola alternativa; no hay diferencia incremental.</p>
    @endforelse
</section>

@if ($informe['sensibilidad'])
    @include('ingenieria-economica.documental._sensibilidad',['s'=>$informe['sensibilidad']])
@else
    <p class="muted">No se seleccionó una versión de sensibilidad para este informe.</p>
@endif
@if ($informe['calculos'])
    @include('ingenieria-economica.documental._activos')
@else
    <p class="muted">No se seleccionaron cálculos de depreciación, amortización o reemplazo.</p>
@endif

<section class="section">
    <h3>Conclusiones y recomendaciones condicionadas</h3>
    <ul>@foreach ($informe['resumen']['conclusiones'] as $c)<li>{{ $c }}</li>@endforeach</ul>
    @if ($informe['comentarios'])
        <h4>Comentarios del analista</h4><p class="description">{{ $informe['comentarios'] }}</p>
    @endif

    <h3>Fuentes y evidencias</h3>
    @forelse ($informe['evidencias'] as $doc)
        <p><strong>Evidencia #{{ $doc['id'] }} · {{ $doc['titulo'] }}</strong> · {{ EvidenciaDocumental::TIPOS[$doc['tipo']] }} · {{ $fecha($doc['fecha']) }}</p>
        <p>{{ $doc['procedencia'] }} · {{ EvidenciaDocumental::CALIDADES[$doc['calidad']] }} · {{ EvidenciaDocumental::ESTADOS[$doc['estado']] }}.</p>
        @if ($doc['observaciones'])<p class="description">{{ $doc['observaciones'] }}</p>@endif
        <ul>@foreach ($doc['vinculos'] as $v)
            <li>{{ VinculoDocumental::TIPOS[$v['tipo']] }} #{{ $v['destino_id'] }} · {{ $v['etiqueta'] }}.
                @if ($v['conciliacion']) {{ VinculoDocumental::CONCILIACIONES[$v['conciliacion']] }}. @endif
                @include('ingenieria-economica.documental._referencia',['ref'=>$v['referencia'],'contable'=>$v['conciliacion'] !== null])
                <div class="description">{{ $v['nota'] }}</div></li>
        @endforeach</ul>
    @empty
        <p>No hay evidencias del expediente vinculadas al alcance de esta versión.</p>
    @endforelse
    <p>Las citas de conceptos y activos se conservan con el estado original del dato. Una evidencia validada respalda su procedencia. La estimación conserva su clasificación.</p>
</section>

@include('ingenieria-economica.documental._calidad')
<section class="section">
    <h3>Anexos · Flujos y memoria de cálculo</h3>
    <p>FC = beneficios − costos. VAN = Σ FCₜ × P/F. P/F = (1+i)⁻ᵗ. VPC = Σ costosₜ × P/F. CAUE = VPC × A/P anual.
        RCB divide beneficios presentes entre costos presentes cuando aplica.</p>
    @foreach ($r['alternativas'] as $a)
        <h4>{{ $a['nombre'] }}</h4>
        <p>Factor A/P {{ $decimal($a['indicadores']['factor_ap']) }} · VP de beneficios {{ $importe($a['indicadores']['vp_beneficios']) }} {{ $moneda }}.</p>
        <div class="table-wrap"><table>
            <thead><tr><th>Período</th><th>Cierre</th><th>Ingresos</th><th>Costos</th><th>Flujo neto</th><th>P/F</th><th>VP</th></tr></thead>
            <tbody>@foreach ($a['periodos'] as $n=>$p)
                <tr><th scope="row">{{ $n }}</th><td>{{ $fecha($p['hasta']) }}</td><td>{{ $importe($p['beneficios']) }}</td><td>{{ $importe($p['costos']) }}</td>
                    <td>{{ $importe($p['neto']) }}</td><td>{{ $decimal($p['factor']) }}</td><td>{{ $importe($p['vp']) }}</td></tr>
            @endforeach</tbody>
        </table></div>
        <div class="table-wrap"><table>
            <thead><tr><th>Fecha original</th><th>Origen y estado</th><th>Importe</th><th>Tratamiento</th></tr></thead>
            <tbody>
                @foreach ($a['periodos'] as $p)
                    @foreach ($p['movimientos'] as $m)
                        @include('ingenieria-economica.documental._movimiento')
                    @endforeach
                @endforeach
                @foreach ($a['excluidos'] as $m)
                    @include('ingenieria-economica.documental._movimiento')
                @endforeach
            </tbody>
        </table></div>
    @endforeach
    @foreach ($r['comparaciones'] as $c)
        <h4>Memoria incremental · {{ $r['alternativas'][$c['id_a']]['nombre'] }} − {{ $r['alternativas'][$c['id_b']]['nombre'] }}</h4>
        <div class="table-wrap"><table>
            <thead><tr><th>Período</th><th>Cierre</th><th>Propio A</th><th>Propio B</th><th>Δ neto</th><th>P/F</th><th>VP incremental</th></tr></thead>
            <tbody>@foreach ($c['periodos'] as $n=>$p)
                <tr><th scope="row">{{ $n }}</th><td>{{ $fecha($p['hasta']) }}</td><td>{{ $importe($p['a']) }}</td><td>{{ $importe($p['b']) }}</td>
                    <td>{{ $importe($p['neto']) }}</td><td>{{ $decimal($p['factor']) }}</td><td>{{ $importe($p['vp']) }}</td></tr>
            @endforeach</tbody>
        </table></div>
    @endforeach
    <h4>Referencias originales de los datos</h4>
    @forelse ($r['origenes'] as $clave=>$origen)
        <p><strong>{{ $clave }} · {{ $origen['nombre'] }}</strong> · Importe original {{ $importe($origen['monto']) }} {{ $moneda }}.</p>
        @if ($origen['observaciones'] ?? null)<p class="description">{{ $origen['observaciones'] }}</p>@endif
        <ul>@forelse ($origen['fuentes'] as $fuente)
            <li>{{ $fecha($fuente['fecha']) }} · {{ $fuente['referencia'] }} · Importe utilizado {{ $importe($fuente['utilizado']) }}.
                Documento #{{ $fuente['id_documento'] ?? '—' }} · Detalle #{{ $fuente['id_detalle'] ?? '—' }}.
                <div class="description">{{ $fuente['justificacion'] }}</div></li>
        @empty
            <li>Sin cita vinculada en la evaluación original.</li>
        @endforelse</ul>
    @empty
        <p>Sin movimientos documentados en la evaluación original.</p>
    @endforelse
</section>
