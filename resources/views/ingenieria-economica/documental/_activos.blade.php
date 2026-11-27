<section class="section">
    <h3>Activos y cálculos complementarios</h3>
    @foreach ($informe['calculos'] as $c)
        @php($a = $c['datos'])
        <h4>{{ $a['activo']['nombre'] }} · {{ \App\Models\IngenieriaEconomica\CalculoActivo::METODOS[$c['metodo']] }}</h4>
        <p>Cálculo #{{ $c['id'] }} · {{ $fecha($c['fecha']) }} · Motor {{ $c['version'] }} · {{ $a['activo']['moneda'] }}.</p>
        @if ($c['metodo'] === 'reemplazo')
            <p>Escenario {{ $a['escenario']['nombre'] }} · {{ $fecha($a['escenario']['base']) }} a {{ $fecha($a['escenario']['cierre']) }}.
                {{ $a['convencion'] }} {{ $a['criterio'] }}</p>
            <p class="description">{{ $a['parametros']['justificacion'] }}</p>
            <table><thead><tr><th>Opción</th><th>VPC neto</th><th>CAUE neto</th></tr></thead>
                <tbody>@foreach (['conservar','reemplazar'] as $opcion)
                    <tr><th scope="row">{{ ucfirst($opcion) }}</th><td>{{ $importe($a[$opcion]['vpc_neto']) }}</td><td>{{ $importe($a[$opcion]['caue_neto']) }}</td></tr>
                @endforeach</tbody></table>
            @isset($a['retiro_inmediato'])
                <p>Retiro inmediato sin continuidad del servicio · {{ $importe($a['retiro_inmediato']) }} {{ $a['activo']['moneda'] }} netos. Referencia de salida inmediata, sin CAUE comparable.</p>
            @endisset
            <p>VAN incremental {{ $importe($a['van_incremental']) }} · Diferencia CAUE {{ $importe($a['diferencia_caue']) }}.</p>
        @else
            <p>Costo capitalizado {{ $importe($a['base']) }} · Residual {{ $importe($a['residual']) }} · Vida {{ $a['vida'] ?? 'indefinida' }}.
                {{ $a['convencion'] }}</p>
            @forelse ($a['metodos'] as $metodo=>$m)
                <p>{{ $metodo === 'doble_saldo'?'Doble saldo decreciente':'Método lineal' }} · {{ $m['formula'] }}.</p>
                @if ($m['filas'])
                    <table><thead><tr><th>Año</th><th>Desde</th><th>Hasta</th><th>Cuota</th><th>Acumulado</th><th>Valor en libros</th></tr></thead>
                        <tbody>@foreach ($m['filas'] as $f)
                            <tr><th scope="row">{{ $f['anio'] }}</th><td>{{ $fecha($f['desde']) }}</td><td>{{ $fecha($f['hasta']) }}</td>
                                <td>{{ $importe($f['importe']) }}</td><td>{{ $importe($f['acumulado']) }}</td><td>{{ $importe($f['saldo_final']) }}</td></tr>
                        @endforeach</tbody></table>
                @else
                    <p>El corte es anterior a la disponibilidad de uso.</p>
                @endif
                @if ($m['fin_vida'] && ! \Brick\Math\BigDecimal::of($m['pendiente'])->isZero())
                    <p>Saldo pendiente sobre residual {{ $importe($m['pendiente']) }} al finalizar la vida útil.</p>
                @endif
            @empty
                <p>{{ $a['estado'] }}</p>
            @endforelse
        @endif
        <ul>@foreach (array_merge($a['fuentes'],$a['fuentes_nuevo'] ?? []) as $f)
            <li>{{ $fecha($f['fecha']) }} · {{ $f['referencia'] }} · {{ $f['uso'] }}.
                <p class="description">{{ $f['justificacion'] }}</p></li>
        @endforeach</ul>
    @endforeach
</section>
