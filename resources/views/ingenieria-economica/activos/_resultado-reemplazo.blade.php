<section class="panel-link">
    <h3>Escenario y convención</h3>
    <p>{{ $r['escenario']['nombre'] }} · {{ $r['escenario']['base'] }} a {{ $r['escenario']['cierre'] }} · {{ $r['escenario']['horizonte'] }} años · {{ $r['escenario']['periodicidad'] }} · {{ $moneda }}.</p>
    <p>TMAR efectiva anual {{ \App\Services\IngenieriaEconomica\DecimalFinanciero::porcentaje($r['escenario']['tasa_anual']) }} % · Tasa por período {{ $decimal((string) \Brick\Math\BigDecimal::of($r['escenario']['tasa_periodo'])->withPointMovedRight(2)) }} % · {{ $r['escenario']['estado_tasa'] }}. {{ $r['escenario']['fuente_tasa'] }}</p>
    <p>{{ $r['convencion'] }}</p>
    <p>{{ $r['criterio'] }}</p>
    <p class="description">{{ $r['parametros']['justificacion'] }}</p>
    @if (!empty($r['parametros']['valoracion_fuente']))
        <p class="description">Valoración · {{ $r['parametros']['valoracion_fecha'] ?? 'Fecha sin informar' }} · {{ $r['parametros']['valoracion_fuente'] }}</p>
    @endif
    <p>Importes antes de impuestos en {{ $moneda }}. El CAUE se expresa en {{ $moneda }}/año.</p>
    <dl class="panel-grid summary">
        <div><dt>Valor en libros declarado</dt><dd>{{ isset($r['parametros']['valor_libros_actual']) ? $importe($r['parametros']['valor_libros_actual']) : 'Sin informar' }}</dd></div>
        <div><dt>Mercado actual</dt><dd>{{ $importe($r['mercado_actual']) }}</dd></div>
        <div><dt>Venta hoy</dt><dd>{{ $importe($r['parametros']['venta_actual']) }}</dd></div>
        <div><dt>Oportunidad neta de conservar</dt><dd>{{ $importe($r['oportunidad_conservar']) }}</dd></div>
        <div><dt>Inversión adicional neta al reemplazar</dt><dd>{{ $importe($r['inversion_adicional']) }}</dd></div>
        <div><dt>VAN incremental de reemplazar − conservar</dt><dd>{{ $importe($r['van_incremental']) }}</dd></div>
        <div><dt>Diferencia de CAUE neto</dt><dd>{{ $importe($r['diferencia_caue']) }}</dd></div>
    </dl>
</section>
@if (isset($r['retiro_inmediato']))
    <section class="section panel-link">
        <h3>Retirar hoy sin reemplazar</h3>
        <p>Venta hoy menos costo de retiro hoy = {{ $importe($r['retiro_inmediato']) }} {{ $moneda }} netos.</p>
        <p>Referencia de salida inmediata. Si el servicio debe continuar, esta opción no es comparable con conservar o reemplazar. No se atribuyen costos futuros, CAUE ni efectos fiscales.</p>
    </section>
@endif
@foreach (['conservar' => 'Conservar', 'reemplazar' => 'Reemplazar'] as $opcion => $titulo)
    @php($tabla = $r[$opcion])
    <section class="section panel-link">
        <h3>{{ $titulo }}</h3>
        <p>VPC bruto {{ $importe($tabla['vpc']) }} · VP recuperaciones {{ $importe($tabla['vp_beneficios']) }} · VPC neto {{ $importe($tabla['vpc_neto']) }} {{ $moneda }}.</p>
        <p>CAUE neto = VPC neto × A/P = {{ $importe($tabla['vpc_neto']) }} × {{ $decimal($tabla['factor_ap']) }} ≈ <strong>{{ $importe($tabla['caue_neto']) }} {{ $moneda }}/año</strong>.</p>
        <div class="table-wrap"><table>
            <thead><tr><th>Período</th><th>Fecha</th><th>Movimientos</th><th>Recuperaciones</th><th>Costos</th><th>Neto</th><th>P/F</th><th>VP neto</th></tr></thead>
            <tbody>
                @foreach ($tabla['periodos'] as $periodo => $fila)
                    <tr><th scope="row">{{ $periodo }}</th><td>{{ $fila['fecha'] }}</td><td>
                        @foreach ($fila['eventos'] as [$nombre, $sentido, $monto])
                            <div>{{ $nombre }} · {{ $importe($monto) }}</div>
                        @endforeach
                    </td><td>{{ $importe($fila['beneficios']) }}</td><td>{{ $importe($fila['costos']) }}</td><td>{{ $importe($fila['neto']) }}</td><td>{{ $decimal($fila['factor']) }}</td><td>{{ $importe($fila['vp']) }}</td></tr>
                @endforeach
            </tbody>
        </table></div>
        @php($vida = $r['vidas_economicas'][$opcion])
        <h4>Vida económica entre las edades documentadas</h4>
        @if ($vida['filas'])
            <p>Se mantienen constantes la operación anual de {{ $importe($r['parametros'][$opcion]['operacion']) }} y el mantenimiento anual de {{ $importe($r['parametros'][$opcion]['mantenimiento']) }} {{ $moneda }}. El resultado se limita a esas edades y a ese perfil de costos.</p>
            <p>Menor costo anual entre las edades ingresadas · {{ $vida['menor_caue'] }} año(s).</p>
            <div class="table-wrap"><table>
                <thead><tr><th>Edad</th><th>Rescate</th><th>Retiro</th><th>VPC neto</th><th>A/P</th><th>CAUE neto ({{ $moneda }}/año)</th><th>Justificación</th></tr></thead>
                <tbody>@foreach ($vida['filas'] as $edad => $v)
                    <tr><th scope="row">{{ $edad }}</th><td>{{ $importe($v['rescate']) }}</td><td>{{ $importe($v['retiro']) }}</td><td>{{ $importe($v['vpc_neto']) }}</td><td>{{ $decimal($v['factor_ap']) }}</td><td>{{ $importe($v['caue_neto']) }}</td><td>{{ $v['nota'] }}</td></tr>
                @endforeach</tbody>
            </table></div>
        @else
            <p>Registra edades candidatas con rescate y retiro documentados para estimar la vida económica.</p>
        @endif
    </section>
@endforeach
<section class="section panel-link"><h3>Flujo incremental por período</h3>
    <div class="table-wrap"><table>
        <thead><tr><th>Período</th><th>Fecha</th><th>Reemplazar − conservar</th><th>P/F</th><th>VP incremental</th></tr></thead>
        <tbody>@foreach ($r['incremental'] as $periodo => $fila)
            <tr><th scope="row">{{ $periodo }}</th><td>{{ $fila['fecha'] }}</td><td>{{ $importe($fila['neto']) }}</td><td>{{ $decimal($fila['factor']) }}</td><td>{{ $importe($fila['vp']) }}</td></tr>
        @endforeach</tbody>
    </table></div>
</section>
