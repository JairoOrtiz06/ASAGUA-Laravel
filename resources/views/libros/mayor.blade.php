@extends('layouts.app')
@section('title', 'ASAGUA — Libro mayor')
@section('content')
<div class="topbar"><div><h2>Libro mayor</h2><p class="muted">Consulta de cargos, abonos y saldo por cuenta contable.</p></div></div>
<form method="GET" action="{{ route('libros.mayor') }}" class="form-grid" style="margin-bottom:18px">
    <label>Cuenta contable
        <select name="cuenta_id" required>
            <option value="">Seleccione una cuenta</option>
            @foreach($cuentas as $cuenta)
                <option value="{{ $cuenta->id_cuenta }}" @selected((string) request('cuenta_id') === (string) $cuenta->id_cuenta)>
                    {{ $cuenta->codigo_cuenta }} — {{ $cuenta->nombre_cuenta }}
                </option>
            @endforeach
        </select>
    </label>
    <label>Fecha de inicio<input type="date" name="fecha_inicio" value="{{ request('fecha_inicio') }}"></label>
    <label>Fecha final<input type="date" name="fecha_fin" value="{{ request('fecha_fin') }}"></label>
    <div class="actions"><button type="submit">Consultar</button><a class="button secondary" href="{{ route('libros.mayor') }}">Reiniciar</a></div>
</form>
@if($mayor)
    <div class="alert"><strong>{{ $mayor['cuenta']?->codigo_cuenta }} — {{ $mayor['cuenta']?->nombre_cuenta }}</strong><br>
        Total Debe: {{ $mayor['total_debe'] }} | Total Haber: {{ $mayor['total_haber'] }} |
        Saldo {{ $mayor['naturaleza'] }}: {{ $mayor['saldo'] }}
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Fecha</th><th>Partida</th><th>Descripción</th><th>Debe / Cargo</th><th>Haber / Abono</th><th>Tipo</th></tr></thead>
            <tbody>
            @forelse($mayor['movimientos'] as $movimiento)
                <tr>
                    <td>{{ $movimiento['fecha'] }}</td>
                    <td>#{{ $movimiento['numero_partida'] }}</td>
                    <td>{{ $movimiento['descripcion'] ?: $movimiento['concepto_visible'] }}</td>
                    <td>{{ $movimiento['debe'] }}</td>
                    <td>{{ $movimiento['haber'] }}</td>
                    <td>{{ $movimiento['es_ajuste'] ? 'Ajuste: '.$movimiento['tipo_ajuste'] : 'Ordinaria' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">La cuenta no tiene movimientos para el filtro.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endif
@endsection
