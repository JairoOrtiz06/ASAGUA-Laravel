<tr><td>{{ $fecha($m['fecha']) }}</td>
    <td>{{ $m['origen'] }} · {{ $r['origenes'][$m['origen']]['nombre'] }} · {{ $m['estado'] }} · {{ $m['observado']?'observado':'proyectado' }}</td>
    <td>{{ $importe($m['monto']) }}</td>
    <td>{{ $m['motivo'] ?? 'Incluido' }} · {{ \App\Models\IngenieriaEconomica\Concepto::NATURALEZAS[$m['naturaleza']] }}</td></tr>
