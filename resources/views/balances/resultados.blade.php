@extends('layouts.app')
@section('title', 'ASAGUA — Estado de resultados')
@section('content')
<div class="topbar"><div><h2>Estado de resultados</h2><p class="muted">Análisis de ingresos y gastos.</p></div></div>
<form method="GET" action="{{ route('balances.resultados') }}" class="form-grid" style="margin-bottom:18px">
    <label>Fecha de inicio<input type="date" name="fecha_inicio" value="{{ request('fecha_inicio') }}"></label>
    <label>Fecha final<input type="date" name="fecha_fin" value="{{ request('fecha_fin') }}"></label>
    <div class="actions field-full"><button type="submit">Consultar</button><a class="button secondary" href="{{ route('balances.resultados') }}">Reiniciar filtro</a></div>
</form>

<div class="panel-grid">
    @foreach([['titulo' => 'INGRESOS', 'clave' => 'ingresos', 'total' => 'total_ingresos'], ['titulo' => 'GASTOS', 'clave' => 'gastos', 'total' => 'total_gastos']] as $seccion)
        <div class="card" style="min-height:0;padding:18px">
            <h3 style="color:var(--navy);margin-top:0">{{ $seccion['titulo'] }}</h3>
            @if($estado[$seccion['clave']])
                <div class="table-wrap"><table>
                    <thead><tr><th>Cuenta</th><th>Total</th></tr></thead>
                    <tbody>
                    @foreach($estado[$seccion['clave']] as $fila)
                        <tr><td>{{ $fila['cuenta'] }}</td><td>{{ $fila['saldo'] }}</td></tr>
                    @endforeach
                    </tbody>
                </table></div>
            @else
                <p class="muted">Tabla sin contenido</p>
            @endif
            <p style="text-align:right"><strong>Total {{ strtolower($seccion['titulo']) }}: {{ $estado[$seccion['total']] }}</strong></p>
        </div>
    @endforeach
</div>

<div class="alert" style="text-align:center">
    <strong>{{ $estado['es_utilidad'] ? 'UTILIDAD NETA DEL EJERCICIO' : 'PÉRDIDA NETA DEL EJERCICIO' }}:</strong>
    {{ $estado['utilidad_neta'] }}
</div>
@endsection
