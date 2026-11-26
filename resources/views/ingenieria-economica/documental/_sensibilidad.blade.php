<section class="section">
    <h3>Análisis de sensibilidad</h3>
    <p>#{{ $s['id'] }} · {{ $s['nombre'] }} · {{ $s['estado'] }} · Motor {{ $s['version'] }}. Los valores alternativos representan supuestos de simulación.</p>
    <p class="description">{{ $s['datos']['configuracion']['supuestos'] }}</p>
    <ul>@foreach ($s['datos']['advertencias'] as $aviso)<li>{{ $aviso }}</li>@endforeach</ul>
    @if ($s['datos']['configuracion']['modo'] === 'matriz')
        <h4>Matriz de dos variables · {{ count($s['datos']['ejes'][0]) }} × {{ count($s['datos']['ejes'][1]) }}</h4>
        <p>Cada combinación se presenta en una fila por alternativa. La indiferencia se aproxima por interpolación.</p>
        <ul>@forelse ($s['datos']['cruces_matriz'] as [$x,$y])
            <li>X {{ $decimal($x) }} · Y {{ $decimal($y) }}</li>
        @empty<li>Sin cruces detectados en la matriz.</li>@endforelse</ul>
    @endif
    @if ($punto = $s['datos']['equilibrio'])
        <h4>Punto de equilibrio</h4>
        <p>{{ $punto['variable']['nombre'] }} · {{ strtoupper($punto['criterio']) }} · {{ implode(' − ',$punto['par']) }}.
            {{ $punto['estado'] }} Intervalo {{ implode(' a ',$punto['intervalo']) }} · Tolerancia {{ $punto['tolerancia'] }}.</p>
        <ul>@foreach ($punto['cruces'] as $c)
            <li>{{ $decimal($c['valor']) }} {{ $punto['modo'] === 'absoluto' ? $punto['variable']['unidad'] : $punto['modo'] }}
                @if ($punto['es_horas_absolutas']) · {{ $decimal(\Brick\Math\BigDecimal::of($c['valor'])->multipliedBy(60)->toScale(2,\Brick\Math\RoundingMode::HalfUp)) }} min/día @endif
                · Residual {{ $decimal($c['residual']) }} · Δ izquierda {{ $decimal($c['izquierda'][1]) }} · Δ derecha {{ $decimal($c['derecha'][1]) }}.</li>
        @endforeach</ul>
    @endif
    @if ($s['datos']['simulaciones'])
        <div class="table-wrap"><table>
            <thead><tr><th>Simulación</th><th>Alternativa</th><th>VAN</th><th>VPC</th><th>CAUE/año</th></tr></thead>
            <tbody>@foreach ($s['datos']['simulaciones'] as $fila)
                @foreach ($fila['alternativas'] as $id=>$a)
                    <tr><th scope="row">{{ $fila['nombre'] }}</th><td>{{ $r['alternativas'][$id]['nombre'] }}</td>
                        @foreach (['van','vpc','caue'] as $k)<td>{{ $importe($a['indicadores'][$k]) }}</td>@endforeach
                    </tr>
                @endforeach
            @endforeach</tbody>
        </table></div>
        @foreach ($s['datos']['simulaciones'] as $fila)
            <h4>{{ $fila['nombre'] }} · TMAR {{ $porcentaje($fila['anual']) }} · {{ $fila['horizonte'] }} años</h4>
            @if (isset($fila['lectura_costos']) && $s['datos']['configuracion']['modo'] === 'matriz')
                <p>{{ $fila['lectura_costos']['estado'] }}
                    @if ($idMenor = $fila['lectura_costos']['menor']) · {{ $r['alternativas'][$idMenor]['nombre'] }} @endif
                    @if ($fila['lectura_costos']['preliminar']) · Comparación preliminar @endif
                </p>
            @endif
            @if ($fila['supuesto'])<p class="description">{{ $fila['supuesto'] }}</p>@endif
            <ul>@foreach ($fila['variables'] as $v)
                <li>{{ $v['nombre'] }} · {{ $decimal($v['base']) }} → {{ $decimal($v['nuevo']) }} {{ $v['unidad'] }}</li>
            @endforeach
            @foreach ($fila['comparaciones'] as $c)
                <li>{{ $r['alternativas'][$c['id_a']]['nombre'] }} − {{ $r['alternativas'][$c['id_b']]['nombre'] }}
                    · Δ VPC {{ $importe($c['diferencia_vpc']) }} · Δ CAUE {{ $importe($c['diferencia_caue']) }} · {{ $c['criterio'] }}</li>
            @endforeach</ul>
        @endforeach
    @endif
</section>
