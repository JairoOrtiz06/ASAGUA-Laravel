@extends('layouts.app')
@section('title', 'ASAGUA — Balance de comprobación')
@section('content')
<div class="topbar"><div><h2>Balance de comprobación</h2><p class="muted">Saldos por cuenta a partir de los movimientos del diario.</p></div></div>
<form method="GET" action="{{ route('balances.comprobacion') }}" class="form-grid" style="margin-bottom:18px">
    <label>Fecha de inicio<input type="date" name="fecha_inicio" value="{{ request('fecha_inicio') }}"></label>
    <label>Fecha final<input type="date" name="fecha_fin" value="{{ request('fecha_fin') }}"></label>
    <div class="actions field-full"><button type="submit">Consultar</button><a class="button secondary" href="{{ route('balances.comprobacion') }}">Reiniciar filtro</a></div>
</form>
<div class="table-wrap">
    <table>
        <thead><tr><th>Cuenta</th><th>Código</th><th>Partidas</th><th>Debe</th><th>Haber</th><th>Saldo deudor</th><th>Saldo acreedor</th></tr></thead>
        <tbody>
        @forelse($balance['cuentas'] as $cuenta)
            <tr>
                <td>{{ $cuenta['cuenta'] }}</td>
                <td>{{ $cuenta['codigo'] }}</td>
                <td>@foreach($cuenta['conceptos'] as $concepto){{ $concepto }}@if(! $loop->last)<br>@endif @endforeach</td>
                <td>{{ $cuenta['debe'] }}</td>
                <td>{{ $cuenta['haber'] }}</td>
                <td>{{ $cuenta['saldo_deudor'] }}</td>
                <td>{{ $cuenta['saldo_acreedor'] }}</td>
            </tr>
        @empty
            <tr><td colspan="7" class="muted">No hay movimientos para el filtro seleccionado.</td></tr>
        @endforelse
        </tbody>
        @if($balance['cuentas'])
            <tfoot><tr><th colspan="3">Totales</th><th>{{ $balance['total_debe'] }}</th><th>{{ $balance['total_haber'] }}</th><th>{{ $balance['total_deudor'] }}</th><th>{{ $balance['total_acreedor'] }}</th></tr></tfoot>
        @endif
    </table>
</div>
@if($balance['cuentas'])
    <p class="muted" style="margin-top:15px">
        @if($balance['total_debe'] === $balance['total_haber'] && $balance['total_deudor'] === $balance['total_acreedor'])
            ✅ Balanceado
        @else
            ⚠ Desbalanceado
        @endif
    </p>
@endif
@endsection
