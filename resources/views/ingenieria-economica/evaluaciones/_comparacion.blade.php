@php
    use App\Services\IngenieriaEconomica\MemoriaCalculoService;
@endphp

<section class="section panel-link">
    <h3>Comparación incremental</h3>
    <p>A: <strong>{{ $resultado['alternativas'][$comparacion['id_a']]['nombre'] }}</strong> · B: <strong>{{ $resultado['alternativas'][$comparacion['id_b']]['nombre'] }}</strong></p>
    <p>{{ $interpretacion['pares'][$comparacion['id_a']][$comparacion['id_b']] }}</p>
    <p>Flujo incremental A − B después de separar el neto común de cada alternativa. Los movimientos hundidos excluidos figuran en el detalle de movimientos originales.</p>
    <dl class="panel-grid summary">
        <div class="panel-link"><dt>VAN incremental</dt><dd>{{ $memoria->importe($comparacion['van']) }} {{ $moneda }}</dd></div>
        <div class="panel-link"><dt>Diferencia de VPC A − B</dt><dd>{{ $memoria->importe($comparacion['diferencia_vpc']) }} {{ $moneda }}</dd></div>
        <div class="panel-link"><dt>Diferencia de CAUE A − B</dt><dd>{{ $memoria->importe($comparacion['diferencia_caue']) }} {{ $moneda }}/año</dd></div>
    </dl>
    <p><strong>TIR incremental</strong> · {{ MemoriaCalculoService::estadoTir($comparacion['tir']) }}</p>
    @include('ingenieria-economica.evaluaciones._tir', ['tir' => $comparacion['tir']])
    <details class="section">
        <summary>Memoria incremental por período</summary>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Período</th><th>Cierre</th><th>Propio A</th><th>Propio B</th><th>Común A retirado</th><th>Común B retirado</th><th>A − B</th><th>Factor P/F</th><th>VP incremental</th></tr></thead>
                <tbody>
                    @foreach ($comparacion['periodos'] as $numero => $periodo)
                        <tr>
                            <th scope="row">{{ $numero }}</th><td>{{ $periodo['hasta'] }}</td>
                            @foreach (['a', 'b', 'comun_a', 'comun_b', 'neto', 'factor', 'vp'] as $campo)
                                <td>{{ $campo === 'factor' ? $decimal($periodo[$campo]) : $memoria->importe($periodo[$campo]) }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </details>
</section>
