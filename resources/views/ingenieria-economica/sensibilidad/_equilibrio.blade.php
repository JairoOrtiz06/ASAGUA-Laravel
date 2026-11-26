<section class="section panel-link">
    <h3>Punto de equilibrio · {{ strtoupper($punto['criterio']) }}</h3>
    <p>{{ implode(' − ', $punto['par']) }} · {{ $punto['variable']['nombre'] }} ({{ $punto['modo'] === 'absoluto' ? $punto['variable']['unidad'] : ($punto['modo'] === 'puntos' ? 'puntos porcentuales' : '% relativo') }}).</p>
    <p>{{ $punto['formula'] }} Intervalo {{ implode(' a ', $punto['intervalo']) }} · Tolerancia {{ $punto['tolerancia'] }} en la entrada y en el residual.</p>
    <p><strong>{{ $punto['estado'] }}</strong> El cruce expresa igualdad económica bajo los supuestos registrados. La TMAR, el horizonte y las demás variables se mantienen en sus valores de referencia, salvo la variable analizada. La búsqueda por muestreo puede omitir cruces muy próximos.</p>
    @if ($base['escenario']['estado_tasa'] !== 'validada' || ! $base['equivalencia'] || collect($base['alternativas'])->contains(fn ($a) => $a['viabilidad'] !== 'viable'))
        <p><strong>Comparación preliminar.</strong> Revisa TMAR, viabilidad y equivalencia antes de usar el cruce para tomar una decisión.</p>
    @endif
    @if ($punto['cruces'])
        <div class="table-wrap" tabindex="0" role="region" aria-label="Resultados del equilibrio"><table>
            <thead><tr><th>Entrada crítica</th><th>Residual ({{ $moneda }}{{ $punto['criterio'] === 'caue' ? '/año' : '' }})</th><th>Indicador en el cruce</th><th>Izquierda (entrada, Δ)</th><th>Derecha (entrada, Δ)</th></tr></thead>
            <tbody>@foreach ($punto['cruces'] as $i => $c)
                <tr>
                    <th scope="row">{{ $numero($c['valor'], 8) }} @if ($punto['es_horas_absolutas']) h/día ≈ {{ $numero((string) \Brick\Math\BigDecimal::of($c['valor'])->multipliedBy(60), 2) }} min/día @endif</th>
                    <td>{{ $numero($c['residual']) }}</td>
                    <td>@foreach ($r['simulaciones'][$i]['alternativas'] ?? [] as $id => $a)
                        <div>{{ $nombres[$id] }} · {{ $dinero($a['indicadores'][$punto['criterio']]) }} {{ $moneda }}{{ $punto['criterio'] === 'caue' ? '/año' : '' }}</div>
                    @endforeach</td>
                    <td>{{ $numero($c['izquierda'][0]) }}, {{ $numero($c['izquierda'][1]) }}</td>
                    <td>{{ $numero($c['derecha'][0]) }}, {{ $numero($c['derecha'][1]) }}</td>
                </tr>
            @endforeach</tbody>
        </table></div>
    @endif
</section>
