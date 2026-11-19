<!doctype html>
<html lang="es"><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"><title>ASAGUA — Balance de comprobación</title>
<style>
@page{margin:1.4cm 1.6cm 1.6cm}body{font-family:DejaVu Sans,sans-serif;color:#333;font-size:9px}h1{color:#0A4D8C;font-size:20px;margin:0 0 4px;text-align:center}h2{color:#1B75BB;font-size:13px;margin:4px 0 12px;text-align:center}p.subtitulo{color:#666;font-size:10px;text-align:center;margin:0 0 12px}table{border-collapse:collapse;width:100%}th{background:#0A4D8C;color:#fff;font-weight:bold;text-align:center}th,td{border:.4px solid #CCC;padding:4px}tbody tr:nth-child(even){background:#EAF4FC}td.money,th.money{text-align:right;white-space:nowrap}tfoot th{background:#29A8E0;color:#111;text-align:right}tfoot th:first-child{text-align:left}
</style></head><body>
<h1>ASAGUA</h1><p class="subtitulo">{{ $subtitulo }}</p><h2>BALANCE DE COMPROBACIÓN</h2>
<table><thead><tr><th>Cuenta</th><th>Código</th><th>Debe</th><th>Haber</th><th>Saldo deudor</th><th>Saldo acreedor</th></tr></thead><tbody>
@forelse($balance['cuentas'] as $cuenta)
    <tr><td>{{ $cuenta['cuenta'] }}</td><td>{{ $cuenta['codigo'] }}</td><td class="money">{{ $cuenta['debe'] }}</td><td class="money">{{ $cuenta['haber'] }}</td><td class="money">{{ $cuenta['saldo_deudor'] }}</td><td class="money">{{ $cuenta['saldo_acreedor'] }}</td></tr>
@empty
    <tr><td colspan="6">No hay movimientos para el filtro seleccionado.</td></tr>
@endforelse
</tbody><tfoot><tr><th colspan="2">TOTAL</th><th>{{ $balance['total_debe'] }}</th><th>{{ $balance['total_haber'] }}</th><th>{{ $balance['total_deudor'] }}</th><th>{{ $balance['total_acreedor'] }}</th></tr></tfoot></table>
</body></html>
