@extends('layouts.ingenieria-economica')

@php
    use App\Models\IngenieriaEconomica\CalculoActivo;
    use App\Models\IngenieriaEconomica\FuenteActivo;
    use App\Services\IngenieriaEconomica\DecimalFinanciero;
    use Brick\Math\BigDecimal;

    $importe = fn ($valor) => DecimalFinanciero::dinero(BigDecimal::of($valor));
    $decimal = fn ($valor) => DecimalFinanciero::mostrar(BigDecimal::of($valor), 16);
    $moneda = $r['activo']['moneda'];
    $esAmortizacion = $calculo->metodo === 'amortizacion_lineal';
@endphp

@section('economia-content')
<div class="topbar">
    <div><h2>{{ CalculoActivo::METODOS[$calculo->metodo] }} #{{ $calculo->id }}</h2><p class="muted">{{ $r['activo']['nombre'] }} · {{ $calculo->created_at->format('d/m/Y H:i') }} · Motor {{ $calculo->version_motor }} · Contador #{{ $calculo->id_usuario }}</p></div>
    <div class="actions">
        <a class="button secondary" href="{{ route('ingenieria-economica.proyectos.activos.show', [$proyecto, $activo]) }}">← Activo</a>
        <a class="button" href="{{ route('ingenieria-economica.proyectos.activos.calculos.csv', [$proyecto, $activo, $calculo]) }}">Descargar CSV</a>
    </div>
</div>
@if ($calculo->metodo === 'reemplazo')
    @include('ingenieria-economica.activos._resultado-reemplazo')
@else
    <section class="panel-link">
        <h3>Parámetros contables</h3>
        <p>Base {{ $importe($r['base']) }} {{ $moneda }} · Residual {{ $importe($r['residual']) }} · Vida {{ $r['vida'] ?? 'indefinida' }} · Disponible {{ $r['inicio'] ?? 'sin fecha' }} · Corte {{ $r['corte'] ?? 'vida completa' }}.</p>
        <p>{{ $r['convencion'] }} {{ $r['precision'] }} El porcentaje de uso declarado es {{ $r['activo']['porcentaje_uso'] }} %. La tabla muestra la {{ $esAmortizacion ? 'amortización' : 'depreciación' }} del activo completo.</p>
        @if ($r['estado'] !== 'calculado')
            <p>{{ $r['estado'] }}</p>
            <p>Amortización acumulada {{ $importe($r['amortizacion_acumulada']) }} {{ $moneda }} · Valor en libros calculado {{ $importe($r['saldo_sin_amortizacion']) }} {{ $moneda }}, antes de ajustes por deterioro u otros.</p>
        @endif
        @if ($r['ajuste_final'])<p class="description">Política de ajuste final · {{ $r['justificacion_ajuste'] }}</p>@endif
        @if ($r['activo']['justificacion_residual'])<p class="description">{{ $r['activo']['justificacion_residual'] }}</p>@endif
    </section>
    @foreach ($r['metodos'] as $metodo => $tabla)
        <section class="section panel-link">
            <h3>{{ $metodo === 'doble_saldo' ? 'Doble saldo decreciente' : ($calculo->metodo === 'amortizacion_lineal' ? 'Amortización lineal' : 'Línea recta') }}</h3>
            <p><code>{{ $tabla['formula'] }}</code></p>
            <p>C es costo capitalizado, VR es residual, n es vida útil y VL es valor en libros.</p>
            @if ($metodo === 'doble_saldo')
                <p>Tasa anual 2 ÷ {{ $r['vida'] }} = {{ $decimal($tabla['tasa']) }}. En cada año se limita el importe al saldo depreciable.</p>
            @else
                <p>Importe anual ({{ $importe($r['base']) }} − {{ $importe($r['residual']) }}) ÷ {{ $r['vida'] }} = {{ $importe($tabla['anual_lineal']) }} {{ $moneda }}.</p>
            @endif
            <div class="table-wrap"><table>
                <caption>{{ $esAmortizacion ? 'Amortización contable' : 'Depreciación contable' }} · Importes en {{ $moneda }}</caption>
                <thead><tr><th>Año</th><th>Desde</th><th>Hasta</th><th>Días</th><th>Valor en libros inicial</th><th>Importe anual</th><th>Ajuste final</th><th>Cuota del período</th><th>{{ $esAmortizacion ? 'Amortización acumulada' : 'Depreciación acumulada' }}</th><th>Valor en libros final</th><th>Residual</th></tr></thead>
                <tbody>
                    @forelse ($tabla['filas'] as $fila)
                        <tr><th scope="row">{{ $fila['anio'] }}</th><td>{{ $fila['desde'] }}</td><td>{{ $fila['hasta'] }}</td><td>{{ $fila['dias'] }}/{{ $fila['dias_anio'] }}</td>
                            @foreach (['saldo_inicial','ordinario_anual','ajuste','importe','acumulado','saldo_final','residual'] as $columna)
                                <td>{{ $importe($fila[$columna]) }}</td>
                            @endforeach
                        </tr>
                    @empty
                        <tr><td colspan="11">El corte es anterior a la disponibilidad para uso.</td></tr>
                    @endforelse
                </tbody>
            </table></div>
            <p>Acumulado <strong>{{ $importe($tabla['acumulado']) }}</strong> · Valor en libros <strong>{{ $importe($tabla['saldo']) }}</strong> · Saldo pendiente sobre residual <strong>{{ $importe($tabla['pendiente']) }} {{ $moneda }}</strong>.</p>
            @if ($tabla['fin_vida'] && !BigDecimal::of($tabla['pendiente'])->isZero())
                <p class="economia-aviso"><strong>Advertencia.</strong> Quedó saldo sin depreciar al finalizar la vida útil. Puede aplicarse un ajuste final si la política contable lo permite.</p>
            @endif
        </section>
    @endforeach
    <section class="section panel-link">
        <h3>Registro fiscal de la versión</h3>
        <p>Las cifras de la tabla son contables. Consulta Evaluación Económica para calcular impuestos con parámetros respaldados.</p>
        @foreach (['depreciacion_fiscal' => 'Depreciación fiscal','amortizacion_fiscal' => 'Amortización fiscal','efecto_tributario' => 'Efecto tributario'] as $campo => $etiqueta)
            <p class="description"><strong>{{ $etiqueta }}</strong> · {{ $r['activo'][$campo] ?? 'Pendiente de documentar' }}</p>
        @endforeach
    </section>
@endif
<section class="section panel-link">
    <h3>Evidencia de la versión guardada</h3>
    <ul class="records">
        @forelse (array_merge($r['fuentes'], $r['fuentes_nuevo'] ?? []) as $fuente)
            <li><strong>{{ FuenteActivo::USOS[$fuente['uso']] }} · {{ $fuente['referencia'] }}</strong> · {{ $fuente['fecha'] }} · Documento {{ $fuente['id_documento'] ?? '—' }} · Detalle {{ $fuente['id_detalle'] ?? '—' }}<p class="description">{{ $fuente['justificacion'] }}</p></li>
        @empty
            <li>Sin referencias vinculadas a esta versión.</li>
        @endforelse
    </ul>
</section>
@endsection
