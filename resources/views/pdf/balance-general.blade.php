<!doctype html>
<html lang="es"><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"><title>ASAGUA — Balance general</title>
<style>
@page{margin:1.4cm 1.6cm 1.6cm}body{font-family:DejaVu Sans,sans-serif;color:#333;font-size:9px}h1{color:#0A4D8C;font-size:20px;margin:0 0 4px;text-align:center}h2{color:#1B75BB;font-size:13px;margin:4px 0 12px;text-align:center}h3{color:#1B75BB;font-size:13px;margin:14px 0 4px}p.subtitulo{color:#666;font-size:10px;text-align:center;margin:0 0 12px}table{border-collapse:collapse;width:100%;margin-bottom:8px}th{background:#0A4D8C;color:#fff;font-weight:bold;text-align:center}th,td{border:.4px solid #CCC;padding:4px}tbody tr:nth-child(even){background:#EAF4FC}td.money,th.money{text-align:right;white-space:nowrap}tfoot th{background:#29A8E0;color:#111;text-align:right}tfoot th:first-child{text-align:left}.totales{margin-top:12px}.estado{color:#0A4D8C;font-size:12px;font-weight:bold;text-align:center}
</style></head><body>
<h1>ASAGUA</h1><p class="subtitulo">{{ $subtitulo }}</p><h2>BALANCE GENERAL</h2>
@foreach([['clave'=>'AC_CORRIENTE','titulo'=>'ACTIVO CORRIENTE'],['clave'=>'AC_NO_CORRIENTE','titulo'=>'ACTIVO NO CORRIENTE'],['clave'=>'PAS_CORRIENTE','titulo'=>'PASIVO CORRIENTE'],['clave'=>'PATRIMONIO','titulo'=>'PATRIMONIO']] as $seccion)
    @if($balance[$seccion['clave']])
        <h3>{{ $seccion['titulo'] }}</h3><table><thead><tr><th>Cuenta</th><th>Importe</th></tr></thead><tbody>
        @foreach($balance[$seccion['clave']] as $fila)<tr><td>{{ $fila['cuenta'] }}</td><td class="money">{{ $fila['saldo'] }}</td></tr>@endforeach
        </tbody></table>
    @endif
@endforeach
<table class="totales"><thead><tr><th>Totales</th><th>Importe</th></tr></thead><tbody>
<tr><td>TOTAL ACTIVOS</td><td class="money">{{ $balance['total_activos'] }}</td></tr>
<tr><td>TOTAL PASIVOS</td><td class="money">{{ $balance['total_pasivos'] }}</td></tr>
<tr><td>TOTAL PATRIMONIO</td><td class="money">{{ $balance['total_patrimonio'] }}</td></tr>
<tr><td>TOTAL PASIVO + PATRIMONIO</td><td class="money">{{ $balance['total_pasivos_patrimonio'] }}</td></tr>
<tr><td>Diferencia (Activos − P+P)</td><td class="money">{{ $balance['diferencia'] }}</td></tr>
</tbody></table>
<p class="estado">{{ $balance['ecuacion_ok'] ? 'Balance general balanceado correctamente' : 'Advertencia: el balance general presenta diferencias' }}</p>
</body></html>
