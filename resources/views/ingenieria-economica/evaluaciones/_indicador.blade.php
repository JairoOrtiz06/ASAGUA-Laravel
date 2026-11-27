@php
    use App\Services\IngenieriaEconomica\MemoriaCalculoService;
@endphp

<details class="section">
    <summary>Memoria de {{ MemoriaCalculoService::NOMBRES[$clave] }}</summary>
    <p><code>{{ $indicador['formula'] }}</code></p>
    <p>{{ $indicador['variables'] }}</p>
    <p>Tasa del flujo <strong>{{ $porcentaje($escenario['tasa']) }}</strong> · Anual <strong>{{ $porcentaje($escenario['anual']) }}</strong></p>
    <p>{{ $indicador['sustitucion'] }}</p>
    <p><strong>{{ $indicador['resultado'] }}{{ in_array($clave, ['van', 'vpc', 'caue'], true) ? ' '.$moneda.($clave === 'caue' ? '/año' : '') : '' }}</strong></p>
    <p>{{ $indicador['interpretacion'] }}</p>
    @if ($clave === 'tir')
        @include('ingenieria-economica.evaluaciones._tir', ['tir' => $alternativa['indicadores']['tir']])
    @endif
    <div class="table-wrap">
        <table>
            <thead><tr><th>Período</th><th>Desde</th><th>Cierre</th>@foreach ($indicador['columnas'] as $etiqueta)<th>{{ $etiqueta }}</th>@endforeach</tr></thead>
            <tbody>
                @foreach ($alternativa['periodos'] as $numero => $periodo)
                    <tr>
                        <th scope="row">{{ $numero }}</th><td>{{ $periodo['desde'] }}</td><td>{{ $periodo['hasta'] }}</td>
                        @foreach ($indicador['columnas'] as $columna => $etiqueta)
                            <td>{{ $columna === 'factor' ? $decimal($periodo[$columna]) : $memoria->importe($periodo[$columna]) }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</details>
