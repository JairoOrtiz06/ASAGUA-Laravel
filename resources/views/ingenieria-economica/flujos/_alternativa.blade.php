@use('App\Services\IngenieriaEconomica\ContextoEvaluacion')

@php
    use App\Services\IngenieriaEconomica\DecimalFinanciero;
@endphp

<section class="section panel-link">
    <div class="topbar">
        <h3>{{ $resultado['alternativa']->nombre }}</h3>
        <a class="button secondary" href="{{ ContextoEvaluacion::enlace('conceptos.index', ['proyecto' => $proyecto, 'alternativa_id' => $resultado['alternativa']->id]) }}">Ver conceptos</a>
    </div>
    @if ($resultado['avisos'])
        <div class="section"><strong>Advertencias de la proyección</strong><ul>
            @foreach ($resultado['avisos'] as $advertencia)<li>{{ $advertencia }}</li>@endforeach
        </ul></div>
    @endif
    <dl class="panel-grid summary">
        @foreach (['entradas' => 'Entradas de caja', 'salidas' => 'Salidas de caja', 'neto' => 'Flujo neto de caja', 'neto_economico' => 'Flujo económico ampliado (nominal)', 'oportunidad' => 'Costos de oportunidad', 'ahorro_estimado' => 'Ahorros estimados fuera de caja'] as $campo => $etiqueta)
            <div class="panel-link"><dt>{{ $etiqueta }}</dt><dd>{{ DecimalFinanciero::dinero($resultado['totales'][$campo]) }} {{ $moneda }}</dd></div>
        @endforeach
    </dl>

    <p class="muted section">El flujo económico nominal incorpora la oportunidad y los ahorros estimados de la proyección. La evaluación revisa su justificación. Un flujo de caja de cero puede coexistir con un costo económico.</p>
    @if ($resultado['grafica'])
        <h4>Gráfica del flujo neto de caja</h4>
        <p class="muted">Escala por alternativa. Las salidas netas se muestran a la izquierda del cero y las entradas netas a la derecha.</p>
        <ul class="flujo-grafica" aria-label="Flujo neto por período">
            @foreach ($resultado['grafica'] as $barra)
                <li>
                    <span>P{{ $barra['periodo'] }}</span>
                    <span aria-hidden="true" class="flujo-eje">
                        <span @class(['flujo-barra', 'negativa' => $barra['negativo']]) style="width:{{ $barra['ancho'] }}%"></span>
                    </span>
                    <span>{{ $barra['valor'] }}</span>
                </li>
            @endforeach
        </ul>
    @endif

    <div class="section table-wrap">
        <table>
            <caption>Flujos por período ({{ $moneda }})</caption>
            <thead>
                <tr><th>Período</th><th>Ingresos</th><th>Ahorros</th><th>Inversiones</th><th>Costos</th><th>Rescates</th><th>Entradas monetarias</th><th>Salidas monetarias</th><th>Neto de caja</th><th>Neto económico nominal</th><th>Acumulado de caja</th><th>Oportunidad</th><th>Ahorro estimado</th><th>Detalle</th></tr>
            </thead>
            <tbody>
                @forelse ($resultado['visibles'] as $periodo)
                    <tr>
                        <th scope="row">{{ $periodo['numero'] }}<br><small>{{ $periodo['desde']->format('d/m/Y') }}–{{ $periodo['hasta']->format('d/m/Y') }}</small></th>
                        @foreach (['ingresos', 'ahorros', 'inversiones', 'costos', 'rescates', 'entradas', 'salidas', 'neto', 'neto_economico', 'acumulado', 'oportunidad', 'ahorro_estimado'] as $campo)
                            <td>{{ DecimalFinanciero::dinero($periodo[$campo]) }}</td>
                        @endforeach
                        <td>
                            @if ($periodo['movimientos'])
                                <a href="#movimientos-{{ $resultado['alternativa']->id }}-{{ $periodo['numero'] }}">Ver ({{ count($periodo['movimientos']) }})</a>
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="14">El rango seleccionado queda fuera del horizonte.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @foreach ($resultado['visibles'] as $periodo)
        @if ($periodo['movimientos'])
            <details class="section" id="movimientos-{{ $resultado['alternativa']->id }}-{{ $periodo['numero'] }}">
                <summary>Movimientos del período {{ $periodo['numero'] }} ({{ count($periodo['movimientos']) }})</summary>
                @include('ingenieria-economica.flujos._movimientos', ['movimientos' => $periodo['movimientos']])
            </details>
        @endif
    @endforeach
    @if ($resultado['excluidos'])
        <details class="section">
            <summary>Movimientos fuera de los totales ({{ count($resultado['excluidos']) }})</summary>
            @include('ingenieria-economica.flujos._movimientos', ['movimientos' => $resultado['excluidos']])
        </details>
    @endif
</section>
