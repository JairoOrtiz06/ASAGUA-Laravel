<!doctype html>
<html lang="es">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>ASAGUA — Libro diario</title>
    <style>
        @page { margin: 1.4cm 1.6cm 1.6cm; }
        body { font-family: DejaVu Sans, sans-serif; color: #333; font-size: 9px; }
        h1 { color: #0A4D8C; font-size: 20px; margin: 0 0 4px; text-align: center; }
        h2 { color: #1B75BB; font-size: 13px; margin: 4px 0 12px; text-align: center; }
        p.subtitulo { color: #666; font-size: 10px; text-align: center; margin: 0 0 12px; }
        table { border-collapse: collapse; width: 100%; }
        th { background: #0A4D8C; color: #fff; font-weight: bold; text-align: center; }
        th, td { border: 0.4px solid #CCC; padding: 4px; }
        tbody tr:nth-child(even) { background: #EAF4FC; }
        td.money { text-align: right; white-space: nowrap; }
        tfoot th { background: #29A8E0; color: #111; text-align: right; }
        tfoot th:first-child { text-align: left; }
    </style>
</head>
<body>
    <h1>ASAGUA</h1>
    <p class="subtitulo">{{ $subtitulo }}</p>
    <h2>LIBRO DIARIO</h2>
    <table>
        <thead>
        <tr>
            <th>Partida</th>
            <th>Fecha</th>
            <th>Cuenta</th>
            <th>Concepto</th>
            <th>Debe</th>
            <th>Haber</th>
        </tr>
        </thead>
        <tbody>
        @forelse($movimientos as $movimiento)
            <tr>
                <td>#{{ $movimiento['numero_partida'] }}</td>
                <td>{{ $movimiento['fecha'] }}</td>
                <td>{{ $movimiento['codigo_cuenta'] }} — {{ $movimiento['nombre_cuenta'] }}</td>
                <td>{{ $movimiento['concepto_visible'] }}@if($movimiento['descripcion']) — {{ $movimiento['descripcion'] }}@endif</td>
                <td class="money">{{ $movimiento['debe'] }}</td>
                <td class="money">{{ $movimiento['haber'] }}</td>
            </tr>
        @empty
            <tr><td colspan="6">No hay movimientos para el filtro seleccionado.</td></tr>
        @endforelse
        </tbody>
        <tfoot>
        <tr>
            <th colspan="4">TOTAL</th>
            <th>{{ $totalDebe }}</th>
            <th>{{ $totalHaber }}</th>
        </tr>
        </tfoot>
    </table>
</body>
</html>
