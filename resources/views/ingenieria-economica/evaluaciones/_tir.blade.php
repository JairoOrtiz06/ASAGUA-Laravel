@if ($tir['raices'])
    <div class="table-wrap">
        <table aria-label="Raíces y procedimientos de TIR">
            <thead><tr><th>TIR efectiva por período</th><th>Intervalo final</th><th>Iteraciones</th><th>Método</th><th>VAN residual</th></tr></thead>
            <tbody>
                @foreach ($tir['raices'] as $raiz)
                    <tr><td>{{ $porcentaje($raiz['tasa']) }}</td><td>{{ $porcentaje($raiz['desde']) }} a {{ $porcentaje($raiz['hasta']) }}</td><td>{{ $raiz['iteraciones'] }}</td><td>{{ $raiz['metodo'] }}</td><td>{{ $decimal($raiz['residual']) }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
