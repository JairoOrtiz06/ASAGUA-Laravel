@php
    $primera = reset($r['simulaciones']);
@endphp
<section class="section panel-link">
    <h3>Matriz de sensibilidad</h3>
    <p>X · {{ $primera['variables'][0]['nombre'] }} ({{ $primera['variables'][0]['modo'] === 'absoluto' ? $primera['variables'][0]['unidad'] : \App\Services\IngenieriaEconomica\VariablesSensibilidadService::MODOS[$primera['variables'][0]['modo']] }})</p>
    <p>Y · {{ $primera['variables'][1]['nombre'] }} ({{ $primera['variables'][1]['modo'] === 'absoluto' ? $primera['variables'][1]['unidad'] : \App\Services\IngenieriaEconomica\VariablesSensibilidadService::MODOS[$primera['variables'][1]['modo']] }})</p>
    <p>Los menores costos son comparaciones económicas condicionadas a la equivalencia técnica. Las diferencias se expresan como A − B.</p>
    <div class="table-wrap" tabindex="0" role="region" aria-label="Matriz de sensibilidad desplazable"><table>
        <thead><tr><th>Y ↓ / X →</th>@foreach ($r['ejes'][0] as $x)<th>{{ $numero($x) }}</th>@endforeach</tr></thead>
        <tbody>@foreach ($r['ejes'][1] as $j => $y)
            <tr><th scope="row">{{ $numero($y) }}</th>
                @foreach ($r['ejes'][0] as $i => $x)
                    @php($s = $r['simulaciones'][$i * count($r['ejes'][1]) + $j])
                    @php($lectura = $s['lectura_costos'] ?? app(\App\Services\IngenieriaEconomica\SensibilidadService::class)->lecturaCostos($base, $s['alternativas'], $s['menor_vpc'], historica: true))
                    <td>
                        <strong>{{ $lectura['estado'] }}@if ($lectura['menor'] !== null) · {{ $nombres[$lectura['menor']] }} @endif</strong>
                        @if ($lectura['preliminar'])<div>Comparación preliminar</div>@endif
                        @foreach ($lectura['excluidas'] as $idExcluida)
                            <small>{{ $nombres[$idExcluida] }} · Revisa viabilidad o suficiencia de datos.</small>
                        @endforeach
                        @foreach ($s['alternativas'] as $id => $a)<div>{{ $nombres[$id] }} · VPC {{ $dinero($a['indicadores']['vpc']) }} {{ $moneda }}</div>@endforeach
                        <small>Δ VPC {{ $dinero($s['comparaciones'][0]['diferencia_vpc']) }} {{ $moneda }} · Δ CAUE {{ $dinero($s['comparaciones'][0]['diferencia_caue']) }} {{ $moneda }}/año</small>
                    </td>
                @endforeach
            </tr>
        @endforeach</tbody>
    </table></div>
    <details class="section"><summary>Línea aproximada de indiferencia</summary>
        <p>Se interpola el cambio de signo de VPC entre celdas vecinas del eje Y.</p>
        <ul>@forelse ($r['cruces_matriz'] as [$x, $y])<li>X = {{ $numero($x) }} · Y ≈ {{ $numero($y) }}</li>@empty<li>No se detectó un cruce en las celdas consultadas.</li>@endforelse</ul>
    </details>
</section>
