<div class="table-wrap">
    <table>
        <thead><tr><th>Período</th><th>Fecha original</th><th>Origen</th><th>Monto</th><th>Dirección</th><th>Naturaleza</th><th>Clasificación</th><th>Dato</th><th>Tratamiento</th></tr></thead>
        <tbody>
            @foreach ($alternativa['periodos'] as $numero => $periodo)
                @foreach ($periodo['movimientos'] as $movimiento)
                    @include('ingenieria-economica.evaluaciones._movimiento')
                @endforeach
            @endforeach
            @foreach ($alternativa['excluidos'] as $movimiento)
                @include('ingenieria-economica.evaluaciones._movimiento', ['numero' => '—'])
            @endforeach
        </tbody>
    </table>
</div>
