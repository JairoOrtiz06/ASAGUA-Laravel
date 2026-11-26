@php
    $casosGrafica = collect($r['simulaciones'])->values()->all();
    if ($d['modo'] === 'escenarios') {
        $baseGrafica = array_map(fn ($a) => ['indicadores' => $a['indicadores'], 'cambios_pct' => ['caue' => \Brick\Math\BigDecimal::of($a['indicadores']['caue'])->isZero() ? null : '0']], $base['alternativas']);
        array_splice($casosGrafica, 1, 0, [['nombre' => 'Base', 'alternativas' => $baseGrafica]]);
    }
    $grafica = ['bases' => $nombres, 'punto' => $r['equilibrio'], 'casos' => array_map(fn ($s) => [
        'nombre' => $s['nombre'],
        'alternativas' => array_map(fn ($a) => [
            'van' => $a['indicadores']['van'], 'caue' => $a['indicadores']['caue'],
            'relativo' => $a['cambios_pct']['caue'] ?? $a['elasticidades']['caue']['cambio_indicador'] ?? null,
        ], $s['alternativas']),
    ], $casosGrafica), 'numerica' => $d['modo'] === 'unidimensional'];
@endphp
<section class="section panel-link">
    <h3>{{ $r['equilibrio'] ? 'Gráfica de cruces' : 'Evolución de indicadores' }}</h3>
    @unless ($r['equilibrio'])
        <label for="serie-grafica">Indicador</label>
        <select id="serie-grafica"><option value="van">VAN ({{ $moneda }})</option><option value="caue">CAUE ({{ $moneda }}/año)</option><option value="relativo">Cambio relativo del CAUE (%)</option></select>
    @endunless
    <p>Los puntos representan valores evaluados. Consulta las tablas para leer sus cifras y unidades.</p>
    @if ($r['equilibrio'])
        <p>Eje horizontal · {{ $r['equilibrio']['variable']['nombre'] }} ({{ $r['equilibrio']['modo'] === 'absoluto' ? $r['equilibrio']['variable']['unidad'] : \App\Services\IngenieriaEconomica\VariablesSensibilidadService::MODOS[$r['equilibrio']['modo']] }}).
            Eje vertical · Δ {{ strtoupper($r['equilibrio']['criterio']) }} ({{ $moneda }}{{ $r['equilibrio']['criterio'] === 'caue' ? '/año' : '' }}).</p>
        <details><summary>Datos del muestreo</summary>
            <div class="table-wrap" tabindex="0" role="region" aria-label="Muestras del equilibrio"><table>
                <thead><tr><th>Entrada</th><th>Diferencia A − B</th></tr></thead>
                <tbody>@foreach ($r['equilibrio']['muestras'] as [$entrada, $diferencia])<tr><th scope="row">{{ $numero($entrada) }}</th><td>{{ $numero($diferencia) }}</td></tr>@endforeach</tbody>
            </table></div>
        </details>
    @elseif ($d['modo'] === 'unidimensional')
        @php($ejeGrafica = $r['simulaciones'][0]['variables'][0])
        <p>Eje horizontal · {{ $ejeGrafica['nombre'] }} ({{ $ejeGrafica['modo'] === 'absoluto' ? $ejeGrafica['unidad'] : \App\Services\IngenieriaEconomica\VariablesSensibilidadService::MODOS[$ejeGrafica['modo']] }}).</p>
    @endif
    <canvas id="grafica-sensibilidad" width="880" height="285" style="width:100%;height:auto" role="img" aria-label="Gráfica de sensibilidad. Los valores también están disponibles en las tablas."></canvas>
    <div id="leyenda-sensibilidad" class="actions"></div>
</section>
<script>
    (() => {
        const datos = {{ Illuminate\Support\Js::from($grafica) }};
        const canvas = document.querySelector('#grafica-sensibilidad');
        const ctx = canvas.getContext('2d');
        if (!ctx) return;
        const selector = document.querySelector('#serie-grafica');
        const colores = ['#0a4d8c', '#15803d', '#b45309', '#7e22ce', '#be185d', '#0369a1'];
        const leyenda = document.querySelector('#leyenda-sensibilidad');
        const valor = (v) => v === null ? NaN : Number(v);
        const dibujar = () => {
            const tipo = selector?.value ?? 'delta';
            const series = datos.punto ? [{nombre: datos.punto.par.join(' − '), puntos: datos.punto.muestras.map(([x, y]) => ({x: valor(x), y: valor(y)}))}]
                : Object.entries(datos.bases).map(([id, nombre]) => ({nombre, puntos: datos.casos.map((c, i) => ({x: datos.numerica ? Number(c.nombre) : i, y: valor(c.alternativas[id][tipo])}))}));
            const validos = series.flatMap(s => s.puntos).filter(p => Number.isFinite(p.x) && Number.isFinite(p.y));
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            leyenda.replaceChildren();
            if (!validos.length) {
                ctx.fillText('No hay resultados numéricos para graficar.', 30, 45);
                return;
            }
            const xmin = Math.min(...validos.map(p => p.x));
            const xmax = Math.max(...validos.map(p => p.x));
            const ymin = Math.min(0, ...validos.map(p => p.y));
            const ymax = Math.max(0, ...validos.map(p => p.y));
            const x = (v) => 90 + 745 * (v - xmin) / (xmax - xmin || 1);
            const y = (v) => 240 - 200 * (v - ymin) / (ymax - ymin || 1);
            ctx.strokeStyle = '#a9c5d8';
            ctx.strokeRect(90, 40, 745, 200);
            ctx.beginPath(); ctx.moveTo(90, y(0)); ctx.lineTo(835, y(0)); ctx.stroke();
            ctx.fillStyle = '#17324d';
            ctx.font = '14px Arial';
            const fmt = (v) => v.toLocaleString('es', {maximumSignificantDigits: 7});
            ctx.fillText(fmt(ymax), 4, 44); ctx.fillText(fmt(ymin), 4, 240);
            if (datos.punto || datos.numerica) {
                ctx.fillText(fmt(xmin), 90, 266);
                if (xmax !== xmin) ctx.fillText(fmt(xmax), 780, 266);
            } else {
                datos.casos.forEach((c, i) => ctx.fillText(c.nombre, x(i) - 35, 266));
            }
            series.forEach((s, i) => {
                ctx.strokeStyle = ctx.fillStyle = colores[i];
                ctx.lineWidth = 2;
                s.puntos.filter(p => Number.isFinite(p.y)).forEach(p => {
                    ctx.beginPath(); ctx.arc(x(p.x), y(p.y), 3, 0, 2 * Math.PI); ctx.fill();
                });
                const etiqueta = document.createElement('span');
                etiqueta.style.color = colores[i];
                etiqueta.textContent = s.nombre;
                leyenda.append(etiqueta);
            });
            datos.punto?.cruces.forEach((c) => {
                ctx.beginPath(); ctx.fillStyle = '#9a3027'; ctx.arc(x(Number(c.valor)), y(0), 4, 0, 2 * Math.PI); ctx.fill();
            });
        };
        selector?.addEventListener('change', dibujar);
        dibujar();
    })();
</script>
