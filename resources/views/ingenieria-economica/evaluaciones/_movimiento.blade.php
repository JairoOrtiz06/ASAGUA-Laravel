@php
    use App\Models\IngenieriaEconomica\Concepto;
@endphp

<tr>
    <td>{{ $numero }}</td><td>{{ $movimiento['fecha'] }}</td>
    <td><a href="#origen-{{ $movimiento['origen'] }}">{{ $resultado['origenes'][$movimiento['origen']]['nombre'] }}</a></td>
    <td>{{ $memoria->importe($movimiento['monto']) }}</td>
    <td>{{ Concepto::DIRECCIONES[$movimiento['direccion']] }}</td>
    <td>{{ Concepto::NATURALEZAS[$movimiento['naturaleza']] }}</td>
    <td>{{ Concepto::CLASIFICACIONES[$movimiento['clasificacion']] }}</td>
    <td>{{ $movimiento['observado'] ? 'Observado' : 'Proyectado' }} · {{ Concepto::ESTADOS[$movimiento['estado']] }}</td>
    <td>{{ $movimiento['motivo'] ?? 'Incluido' }}</td>
</tr>
