@php
    $fiscal = $r['fiscal'];
    $parametrosFiscales = $fiscal['parametros'];
    $dineroFiscal = fn ($valor) => \App\Services\IngenieriaEconomica\DecimalFinanciero::dinero(\Brick\Math\BigDecimal::of($valor));
    $tasaFiscal = fn ($valor) => (string) \Brick\Math\BigDecimal::of($valor)->multipliedBy(100)->strippedOfTrailingZeros().' %';
@endphp
<section class="section">
    <h3>Memoria de evaluación después de impuestos</h3>
    <p>{{ $parametrosFiscales['tratamiento'] === 'no_aplicable_documentado' ? 'No aplicación documentada para este proyecto' : 'Cálculo ordinario documentado' }}.
        Tasa ordinaria {{ $tasaFiscal($parametrosFiscales['tasa']) }}.
        @isset($parametrosFiscales['tasa_venta']) Tasa de disposición {{ $tasaFiscal($parametrosFiscales['tasa_venta']) }}. @endisset
        Vigencia {{ $parametrosFiscales['vigente_desde'] }} a {{ $parametrosFiscales['vigente_hasta'] }}.</p>
    <p>Fuente validada #{{ $fiscal['fuente']['id'] }} · {{ $fiscal['fuente']['titulo'] }} · {{ $fiscal['fuente']['procedencia'] }} · {{ $fiscal['fuente']['fecha'] }}.</p>
    <p class="description">{{ $parametrosFiscales['nota'] }}</p>
    <p>{{ \App\Services\IngenieriaEconomica\EvaluacionFiscalService::FORMULA }}</p>
    <p>La TMAR se conserva en ambas columnas. Se supone que el impuesto se imputa al cierre de cada período anual, o a la fecha base para el período 0 documentado. La amortización y la depreciación fiscal solo reducen la base. No se computan devoluciones, compensaciones de pérdidas, créditos ni IVA.</p>
    @foreach ($fiscal['alternativas'] as $idFiscal => $alternativaFiscal)
        <h4>{{ $r['alternativas'][$idFiscal]['nombre'] }}</h4>
        <p>VAN antes {{ $dineroFiscal($alternativaFiscal['antes']['van']) }} {{ $r['moneda'] }} · VAN después {{ $dineroFiscal($r['alternativas'][$idFiscal]['indicadores']['van']) }} {{ $r['moneda'] }}.</p>
        <p>VPC antes {{ $dineroFiscal($alternativaFiscal['antes']['vpc']) }} {{ $r['moneda'] }} · VPC después {{ $dineroFiscal($r['alternativas'][$idFiscal]['indicadores']['vpc']) }} {{ $r['moneda'] }}.
            CAUE antes {{ $dineroFiscal($alternativaFiscal['antes']['caue']) }} {{ $r['moneda'] }}/año · CAUE después {{ $dineroFiscal($r['alternativas'][$idFiscal]['indicadores']['caue']) }} {{ $r['moneda'] }}/año.</p>
        <div class="table-wrap"><table>
            <thead><tr><th>Período</th><th>Flujo antes</th><th>Base imponible</th><th>Impuesto total</th><th>Ya incluido</th><th>Impuesto adicional</th><th>Flujo después</th></tr></thead>
            <tbody>
                @foreach ($alternativaFiscal['periodos'] as $t => $filaFiscal)
                    <tr><th scope="row">{{ $t }}</th>
                    @foreach (['antes','base','total','pagado','adicional','despues'] as $columnaFiscal)
                        <td>{{ $dineroFiscal($filaFiscal[$columnaFiscal]) }}</td>
                    @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table></div>
        <p>Importes en {{ $r['moneda'] }}. Sustitución por período.</p>
        <ul>
        @foreach ($alternativaFiscal['periodos'] as $t => $filaFiscal)
            <li>Período {{ $t }} · Base {{ $dineroFiscal($filaFiscal['ingreso']) }} − {{ $dineroFiscal($filaFiscal['gasto']) }} − {{ $dineroFiscal($filaFiscal['dep']) }} de depreciación fiscal − {{ $dineroFiscal($filaFiscal['amort']) }} de amortización fiscal.
                Impuesto ordinario {{ $dineroFiscal($filaFiscal['ordinario']) }} y de disposición {{ $dineroFiscal($filaFiscal['disposicion']) }}.
                @foreach ($filaFiscal['pagos_origen'] as $codigoFiscal => $pagoFiscal) · Pago incluido {{ $codigoFiscal }} {{ $pagoFiscal }} @endforeach
                @foreach ($filaFiscal['activos'] as $activoFiscal)
                    <p>{{ $activoFiscal['nombre'] }} · {{ $activoFiscal['tipo'] }} fiscal {{ $dineroFiscal($activoFiscal['cuota_fiscal']) }} ({{ $activoFiscal['metodo'] }}).
                        @isset($activoFiscal['cuota_contable']) Cuota contable contrastada {{ $dineroFiscal($activoFiscal['cuota_contable']) }}. @endisset
                        {{ $activoFiscal['nota'] }}</p>
                @endforeach
                @foreach ($filaFiscal['ventas'] as $ventaFiscal)
                    <p>{{ $ventaFiscal['nombre'] }} · Venta o rescate {{ $dineroFiscal($ventaFiscal['precio']) }} − base fiscal pendiente {{ $dineroFiscal($ventaFiscal['base_fiscal']) }}
                        = {{ $dineroFiscal($ventaFiscal['ganancia']) }}. {{ $ventaFiscal['nota'] }}</p>
                @endforeach
            </li>
        @endforeach
        </ul>
    @endforeach
</section>
