<!doctype html>
<html lang="es"><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"><title>ASAGUA — Estado de resultados</title>
<style>
@page{margin:1.4cm 1.6cm 1.6cm}body{font-family:DejaVu Sans,sans-serif;color:#333;font-size:9px}h1{color:#0A4D8C;font-size:20px;margin:0 0 4px;text-align:center}h2{color:#1B75BB;font-size:13px;margin:4px 0 12px;text-align:center}h3{color:#1B75BB;font-size:13px;margin:14px 0 4px}p.subtitulo{color:#666;font-size:10px;text-align:center;margin:0 0 12px}table{border-collapse:collapse;width:100%;margin-bottom:10px}th{background:#0A4D8C;color:#fff;font-weight:bold;text-align:center}th,td{border:.4px solid #CCC;padding:4px}tbody tr:nth-child(even){background:#EAF4FC}td.money,th.money{text-align:right;white-space:nowrap}tfoot th{background:#29A8E0;color:#111;text-align:right}tfoot th:first-child{text-align:left}.resultado{margin-top:14px}.resultado td{font-size:12px;font-weight:bold;text-align:center}
</style></head><body>
<h1>ASAGUA</h1><p class="subtitulo">{{ $subtitulo }}</p><h2>ESTADO DE RESULTADOS</h2>
<h3>INGRESOS</h3><table><thead><tr><th>Cuenta</th><th>Total</th></tr></thead><tbody>
@forelse($estado['ingresos'] as $fila)<tr><td>{{ $fila['cuenta'] }}</td><td class="money">{{ $fila['saldo'] }}</td></tr>@empty<tr><td colspan="2">Tabla sin contenido</td></tr>@endforelse
</tbody><tfoot><tr><th>TOTAL INGRESOS</th><th>{{ $estado['total_ingresos'] }}</th></tr></tfoot></table>
<h3>GASTOS</h3><table><thead><tr><th>Cuenta</th><th>Total</th></tr></thead><tbody>
@forelse($estado['gastos'] as $fila)<tr><td>{{ $fila['cuenta'] }}</td><td class="money">{{ $fila['saldo'] }}</td></tr>@empty<tr><td colspan="2">Tabla sin contenido</td></tr>@endforelse
</tbody><tfoot><tr><th>TOTAL GASTOS</th><th>{{ $estado['total_gastos'] }}</th></tr></tfoot></table>
<table class="resultado"><thead><tr><th>Resultado del ejercicio</th></tr></thead><tbody><tr><td>{{ $estado['es_utilidad'] ? 'UTILIDAD NETA: ' : 'PÉRDIDA NETA: ' }}{{ $estado['utilidad_neta'] }}</td></tr></tbody></table>
</body></html>
