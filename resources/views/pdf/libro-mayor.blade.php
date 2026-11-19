<!doctype html>
<html lang="es"><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"><title>ASAGUA — Libro mayor</title>
<style>
@page{margin:1.4cm 1.6cm 1.6cm}body{font-family:DejaVu Sans,sans-serif;color:#333;font-size:9px}h1{color:#0A4D8C;font-size:20px;margin:0 0 4px;text-align:center}h2{color:#1B75BB;font-size:13px;margin:4px 0 12px;text-align:center}p.subtitulo{color:#666;font-size:10px;text-align:center;margin:0 0 12px}h3{color:#1B75BB;font-size:13px;margin:14px 0 4px}table{border-collapse:collapse;width:100%;margin-bottom:10px}th{background:#0A4D8C;color:#fff;font-weight:bold;text-align:center}th,td{border:.4px solid #CCC;padding:4px}tbody tr:nth-child(even){background:#EAF4FC}td.money,th.money{text-align:right;white-space:nowrap}tfoot th{background:#29A8E0;color:#111;text-align:right}tfoot th:first-child{text-align:left}
</style></head><body>
<h1>ASAGUA</h1><p class="subtitulo">{{ $subtitulo }}</p><h2>LIBRO MAYOR</h2>
@if($mayor['cuenta'])
    <h3>{{ $mayor['cuenta']->codigo_cuenta }} — {{ $mayor['cuenta']->nombre_cuenta }}</h3>
    <table><thead><tr><th>Fecha</th><th>Partida</th><th>Descripción</th><th>Debe</th><th>Haber</th></tr></thead><tbody>
    @forelse($mayor['movimientos'] as $movimiento)
        <tr><td>{{ $movimiento['fecha'] }}</td><td>#{{ $movimiento['numero_partida'] }}</td><td>{{ $movimiento['descripcion'] ?: $movimiento['concepto_visible'] }}</td><td class="money">{{ $movimiento['debe'] }}</td><td class="money">{{ $movimiento['haber'] }}</td></tr>
    @empty
        <tr><td colspan="5">La cuenta no tiene movimientos para el filtro seleccionado.</td></tr>
    @endforelse
    </tbody><tfoot><tr><th colspan="3">Total</th><th>{{ $mayor['total_debe'] }}</th><th>{{ $mayor['total_haber'] }}</th></tr><tr><th colspan="3">Saldo ({{ $mayor['naturaleza'] }})</th><th colspan="2">{{ $mayor['saldo'] }}</th></tr></tfoot></table>
@else
    <p>No se encontró la cuenta contable seleccionada.</p>
@endif
</body></html>
