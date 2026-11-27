@php
    use App\Models\IngenieriaEconomica\Concepto;
    use App\Services\IngenieriaEconomica\DecimalFinanciero;
@endphp

<ul class="records">
    @foreach ($movimientos as $movimiento)
        <li>
            <strong>{{ $movimiento['fecha']->format('d/m/Y') }} · {{ $movimiento['nombre'] }} · {{ DecimalFinanciero::dinero($movimiento['monto']) }} {{ $moneda }}</strong>
            <div class="muted">
                {{ $movimiento['origen'] }} · {{ $movimiento['observado'] ? 'Observado' : 'Proyectado' }}
                @if ($movimiento['historico']) · Histórico @endif
                · {{ Concepto::ESTADOS[$movimiento['estado']] }}
                · {{ Concepto::CLASIFICACIONES[$movimiento['clasificacion']] }}
                · {{ Concepto::DIRECCIONES[$movimiento['direccion']] }}
                · {{ Concepto::NATURALEZAS[$movimiento['naturaleza']] }}
                @isset ($movimiento['periodo_escenario']) · Período original {{ $movimiento['periodo_escenario'] }} @endisset
            </div>
            @isset ($movimiento['motivo'])
                <p>Excluido de los totales · {{ $movimiento['motivo'] }}.</p>
            @elseif (in_array($movimiento['naturaleza'], ['costo_oportunidad','ahorro_estimado'], true))
                <p>Fuera del flujo de caja. Se muestra en la proyección económica nominal.</p>
            @endisset
            @if ($movimiento['concepto'])
                <a href="{{ route('ingenieria-economica.proyectos.conceptos.show', [$proyecto, $movimiento['concepto']]) }}">Concepto y evidencias ({{ $movimiento['concepto']->fuentes->count() }})</a>
            @endif
            @if ($movimiento['ajuste'])
                <p class="description">{{ $movimiento['ajuste']->justificacion }}</p>
                <form method="POST" onsubmit="return confirm('¿Eliminar este ajuste manual?')" action="{{ route('ingenieria-economica.proyectos.ajustes-flujo.destroy', [$proyecto, $movimiento['ajuste']->id]) }}">
                    @csrf
                    @method('DELETE')
                    <button class="button warning">Eliminar ajuste</button>
                </form>
            @endif
        </li>
    @endforeach
</ul>
