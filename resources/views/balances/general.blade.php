@extends('layouts.app')
@section('title', 'ASAGUA — Balance general')
@section('content')
<div class="topbar"><div><h2>Balance general</h2><p class="muted">Estado de situación financiera.</p></div></div>
<form method="GET" action="{{ route('balances.general') }}" class="form-grid" style="margin-bottom:18px">
    <label>Fecha de inicio<input type="date" name="fecha_inicio" value="{{ request('fecha_inicio') }}"></label>
    <label>Fecha final<input type="date" name="fecha_fin" value="{{ request('fecha_fin') }}"></label>
    <div class="actions field-full"><button type="submit">Consultar</button><a class="button secondary" href="{{ route('balances.general') }}">Reiniciar filtro</a></div>
</form>

@php
    $secciones = [
        ['titulo' => 'ACTIVO', 'grupos' => [
            ['nombre' => 'Activo corriente', 'clave' => 'AC_CORRIENTE', 'total' => 'total_activo_corriente'],
            ['nombre' => 'Activo no corriente', 'clave' => 'AC_NO_CORRIENTE', 'total' => 'total_activo_no_corriente'],
        ], 'gran_total' => 'total_activos', 'texto_total' => 'TOTAL ACTIVOS'],
        ['titulo' => 'PASIVO', 'grupos' => [
            ['nombre' => 'Pasivo corriente', 'clave' => 'PAS_CORRIENTE', 'total' => 'total_pasivo_corriente'],
            ['nombre' => 'Pasivo no corriente', 'clave' => 'PAS_NO_CORRIENTE', 'total' => null],
        ], 'gran_total' => 'total_pasivos', 'texto_total' => 'TOTAL PASIVOS'],
        ['titulo' => 'PATRIMONIO', 'grupos' => [
            ['nombre' => 'Capital y reservas', 'clave' => 'PATRIMONIO', 'total' => 'total_patrimonio'],
        ], 'gran_total' => 'total_patrimonio', 'texto_total' => 'TOTAL PATRIMONIO'],
    ];
@endphp

@foreach($secciones as $seccion)
    <div class="card" style="min-height:0;margin-bottom:15px;padding:18px">
        <h3 style="color:var(--navy);margin-top:0">{{ $seccion['titulo'] }}</h3>
        @foreach($seccion['grupos'] as $grupo)
            @if($balance[$grupo['clave']])
                <h4 style="margin-bottom:6px">{{ $grupo['nombre'] }}</h4>
                <div class="table-wrap"><table>
                    <thead><tr><th>Cuenta</th><th>Saldo</th></tr></thead>
                    <tbody>
                    @foreach($balance[$grupo['clave']] as $cuenta)
                        <tr><td>{{ $cuenta['cuenta'] }}</td><td>{{ $cuenta['saldo'] }}</td></tr>
                    @endforeach
                    </tbody>
                </table></div>
                @if($grupo['total'])<p style="text-align:right"><strong>Total {{ $grupo['nombre'] }}: {{ $balance[$grupo['total']] }}</strong></p>@endif
            @elseif($grupo['clave'] === 'PAS_NO_CORRIENTE')
                <p class="muted">Pasivo no corriente: 0.00. Esta sección no participa en el cálculo, conforme al sistema original.</p>
            @endif
        @endforeach
        <p style="text-align:right"><strong>{{ $seccion['texto_total'] }}: {{ $balance[$seccion['gran_total']] }}</strong></p>
    </div>
@endforeach

<div class="alert">
    <strong>TOTAL PASIVO + PATRIMONIO:</strong> {{ $balance['total_pasivos_patrimonio'] }}
    —
    @if($balance['ecuacion_ok']) ✅ Ecuación contable balanceada @else ⚠ Desbalance @endif
</div>
@endsection
