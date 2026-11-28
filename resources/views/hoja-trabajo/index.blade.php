@extends('layouts.app')
@section('title', 'ASAGUA — Hoja de trabajo contable')
@section('content')
<div class="topbar"><div><h2>Hoja de trabajo contable</h2><p class="muted">Resumen de comprobación, ajustes, estados financieros y diferencias.</p></div></div>
<form method="GET" action="{{ route('hoja-trabajo.index') }}" class="form-grid" style="margin-bottom:18px">
    <label>Fecha de inicio<input type="date" name="fecha_inicio" value="{{ request('fecha_inicio') }}"></label>
    <label>Fecha final<input type="date" name="fecha_fin" value="{{ request('fecha_fin') }}"></label>
    <div class="actions field-full"><button type="submit">Consultar</button><a class="button secondary" href="{{ route('hoja-trabajo.index') }}">Reiniciar filtro</a></div>
</form>

<details class="card" style="margin-bottom:18px;padding:15px" @if($errors->has('fecha_corte') || $errors->has('confirmar_cierre')) open @endif>
    <summary>Cierre contable @if($ultimoCierre) · Cerrado hasta {{ $ultimoCierre }} @endif</summary>
    <form method="POST" action="{{ route('hoja-trabajo.cerrar') }}" class="form-grid" style="margin-top:12px">
        @csrf
        <label>Fecha de corte<input type="date" name="fecha_corte" value="{{ old('fecha_corte') }}" required></label>
        <label class="field-full"><span><input type="checkbox" name="confirmar_cierre" value="1" required> Confirmo que se debe impedir el registro de partidas y ajustes con fecha igual o anterior al corte.</span></label>
        <div class="actions field-full"><button type="submit">Cerrar período</button></div>
    </form>
</details>

<div class="alert">
    <strong>Período:</strong> {{ $hoja['fecha_inicio'] ?: 'inicio' }} — {{ $hoja['fecha_fin'] ?: 'hoy' }}.
    El balance ajustado incorpora el saldo inicial, los movimientos ordinarios y los ajustes. El estado de resultados cubre el período seleccionado.
</div>

<div class="table-wrap">
    <table style="min-width:1100px">
        <thead>
        <tr>
            <th rowspan="2">Código</th><th rowspan="2">Cuenta</th>
            <th colspan="2">Saldo inicial</th>
            <th colspan="2">Balance de comprobación</th>
            <th colspan="2">Ajustes</th>
            <th colspan="2">Balance ajustado</th>
            <th colspan="2">Estado de resultados</th>
            <th colspan="2">Balance general al corte</th>
        </tr>
        <tr><th>Debe</th><th>Haber</th><th>Debe</th><th>Haber</th><th>Debe</th><th>Haber</th><th>Debe</th><th>Haber</th><th>Debe</th><th>Haber</th><th>Debe</th><th>Haber</th></tr>
        </thead>
        <tbody>
        @forelse($hoja['filas'] as $fila)
            <tr>
                <td>{{ $fila['codigo'] ?: '—' }}</td><td>{{ $fila['cuenta'] }}</td>
                <td>{{ $fila['saldo_inicial']['debe'] }}</td><td>{{ $fila['saldo_inicial']['haber'] }}</td>
                <td>{{ $fila['balance_comprobacion']['debe'] }}</td><td>{{ $fila['balance_comprobacion']['haber'] }}</td>
                <td>{{ $fila['ajustes']['debe'] }}</td><td>{{ $fila['ajustes']['haber'] }}</td>
                <td>{{ $fila['balance_ajustado']['debe'] }}</td><td>{{ $fila['balance_ajustado']['haber'] }}</td>
                <td>{{ $fila['estado_resultados']['debe'] }}</td><td>{{ $fila['estado_resultados']['haber'] }}</td>
                <td>{{ $fila['balance_general']['debe'] }}</td><td>{{ $fila['balance_general']['haber'] }}</td>
            </tr>
        @empty
            <tr><td colspan="14" class="muted">No hay movimientos para el período seleccionado.</td></tr>
        @endforelse
        </tbody>
        <tfoot>
        <tr><th colspan="2">Totales</th>
            <th>{{ $hoja['totales']['saldo_inicial']['debe'] }}</th><th>{{ $hoja['totales']['saldo_inicial']['haber'] }}</th>
            <th>{{ $hoja['totales']['balance_comprobacion']['debe'] }}</th><th>{{ $hoja['totales']['balance_comprobacion']['haber'] }}</th>
            <th>{{ $hoja['totales']['ajustes']['debe'] }}</th><th>{{ $hoja['totales']['ajustes']['haber'] }}</th>
            <th>{{ $hoja['totales']['balance_ajustado']['debe'] }}</th><th>{{ $hoja['totales']['balance_ajustado']['haber'] }}</th>
            <th>{{ $hoja['totales']['estado_resultados']['debe'] }}</th><th>{{ $hoja['totales']['estado_resultados']['haber'] }}</th>
            <th>{{ $hoja['totales']['balance_general']['debe'] }}</th><th>{{ $hoja['totales']['balance_general']['haber'] }}</th>
        </tr>
        </tfoot>
    </table>
</div>

<div class="panel-grid" style="margin-top:18px">
    <div class="card" style="min-height:0;padding:15px"><strong>Balance de comprobación:</strong> {{ $hoja['balance_comprobacion_cuadra'] ? 'Cuadra' : 'Presenta diferencias' }}</div>
    <div class="card" style="min-height:0;padding:15px"><strong>Ajustes:</strong> {{ $hoja['ajustes_cuadran'] ? 'Cuadran' : 'Presentan diferencias' }}</div>
    <div class="card" style="min-height:0;padding:15px"><strong>Balance ajustado:</strong> {{ $hoja['balance_ajustado_cuadra'] ? 'Cuadra' : 'Presenta diferencias' }}</div>
    <div class="card" style="min-height:0;padding:15px"><strong>Resultado:</strong> {{ $hoja['es_utilidad'] ? 'Utilidad' : 'Pérdida' }} {{ $hoja['utilidad_neta'] }}</div>
    <div class="card" style="min-height:0;padding:15px"><strong>Balance general:</strong> {{ $hoja['balance_general_cuadra'] ? 'Ecuación balanceada' : 'Presenta diferencias' }}</div>
</div>
@endsection
