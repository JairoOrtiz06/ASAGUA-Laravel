@php
    use App\Services\IngenieriaEconomica\DecimalFinanciero;
@endphp

<section class="section panel-link">
    <h3>Comparación preliminar</h3>
    <p class="muted">Los totales cubren todo el horizonte. Cada alternativa incluye una vez los conceptos comunes. Revisa los avisos y la viabilidad antes de interpretar diferencias.</p>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Alternativa</th><th>Inversión inicial</th><th>Costos recurrentes</th><th>Entradas</th><th>Salidas</th><th>Acumulado sin descuento</th><th>Neto común incluido</th></tr></thead>
            <tbody>
                @foreach ($resultados as $comparada)
                    <tr>
                        <th scope="row">{{ $comparada['alternativa']->nombre }}</th>
                        @foreach (['inicial', 'costos_recurrentes', 'entradas', 'salidas', 'acumulado', 'comunes_netos'] as $clave)
                            <td>{{ DecimalFinanciero::dinero($comparada['totales'][$clave]) }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <h4>Flujo neto por período</h4>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Período</th>@foreach ($resultados as $comparada)<th>{{ $comparada['alternativa']->nombre }}</th>@endforeach</tr></thead>
            <tbody>
                @forelse (reset($resultados)['visibles'] as $numero => $periodo)
                    <tr>
                        <th scope="row">{{ $numero }}</th>
                        @foreach ($resultados as $comparada)
                            <td>{{ DecimalFinanciero::dinero($comparada['visibles'][$numero]['neto']) }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ count($resultados) + 1 }}">El rango seleccionado queda fuera del horizonte.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
