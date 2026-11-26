@extends('layouts.ingenieria-economica')

@php
    use App\Models\IngenieriaEconomica\CalculoActivo;
    use App\Models\IngenieriaEconomica\FuenteActivo;
    use App\Services\IngenieriaEconomica\DecimalFinanciero;
    use Brick\Math\BigDecimal;
    $importe = fn ($v) => DecimalFinanciero::dinero(BigDecimal::of($v)).' '.$activo->moneda;
    $valoracion = $ultimaValoracion?->instantanea;
    $tablaContable = $ultimoCalculoContable?->instantanea;
    $fuentesValoracion = $activo->fuentes->whereNotNull('importe_observado')->sortByDesc('fecha')->groupBy('uso');
@endphp

@section('economia-content')
<div class="topbar">
    <div><h2>{{ $activo->nombre }}</h2><p class="muted">{{ $proyecto->nombre }} · {{ $activo->alternativa?->nombre ?? 'Compartido en el proyecto' }}</p></div>
    <div class="actions">
        <a class="button secondary" href="{{ route('ingenieria-economica.proyectos.evidencias.index',[$proyecto,'tipo'=>'activo','registro'=>$activo->id]) }}">Respaldo documental</a>
        <a class="button secondary" href="{{ route('ingenieria-economica.proyectos.activos.index', $proyecto) }}">← Activos</a>
        <a class="button" href="{{ route('ingenieria-economica.proyectos.activos.edit', [$proyecto, $activo]) }}">Editar</a>
        @include('ingenieria-economica._eliminar',['ruta'=>route('ingenieria-economica.proyectos.activos.destroy',[$proyecto,$activo]),'nombre'=>$activo->nombre,'accion'=>'Eliminar','conserva'=>'Los cálculos, documentos y movimientos vinculados se conservan.'])

    </div>
</div>
<dl class="panel-grid summary">
    <div class="panel-link"><dt>Clasificación</dt><dd>{{ $activo::CLASIFICACIONES[$activo->clasificacion_contable] }}</dd></div>
    <div class="panel-link"><dt>Estado</dt><dd>{{ $activo::ESTADOS[$activo->estado_activo] }}</dd></div>
    <div class="panel-link"><dt>Costo capitalizado</dt><dd>{{ DecimalFinanciero::dinero($activo->baseCapitalizada()) }} {{ $activo->moneda }}</dd></div>
    <div class="panel-link"><dt>Residual</dt><dd>{{ $importe($activo->valor_residual) }}</dd></div>
    <div class="panel-link"><dt>Adquisición</dt><dd>{{ $activo->fecha_adquisicion?->toDateString() ?? 'Sin fecha' }}</dd></div>
    <div class="panel-link"><dt>Disponible para uso</dt><dd>{{ $activo->fecha_disponible_uso?->toDateString() ?? 'Sin fecha' }}</dd></div>
    <div class="panel-link"><dt>Vida útil</dt><dd>{{ $activo->vida_indefinida ? 'Indefinida' : ($activo->vida_util_anios ?? '—') }}</dd></div>
    <div class="panel-link"><dt>Uso declarado</dt><dd>{{ $activo->porcentaje_uso }} %</dd></div>
</dl>
<section class="section panel-link" id="valoracion">
    <h3>Valoración y vida del activo</h3>
    <p>El costo capitalizado suma adquisición y costos capitalizables. El valor en libros refleja el saldo contable, el de mercado estima un precio actual y el rescate corresponde a una fecha de salida.</p>
    <dl class="panel-grid summary">
        <div><dt>Costo de adquisición</dt><dd>{{ $importe($activo->costo_adquisicion) }}</dd></div>
        <div><dt>Costos capitalizables</dt><dd>{{ $importe($activo->costos_capitalizables) }}</dd></div>
        @foreach (['libros'=>'Valor en libros documentado','mercado'=>'Valor de mercado documentado','rescate'=>'Valor de rescate documentado','residual'=>'Fuente del residual estimado'] as $uso => $etiqueta)
            @php($fuenteValor = $fuentesValoracion->get($uso)?->first())
            <div><dt>{{ $etiqueta }}</dt><dd>
                @if ($fuenteValor)
                    {{ $importe($fuenteValor->importe_observado) }} · {{ $fuenteValor->fecha->format('d/m/Y') }} · {{ $fuenteValor->referencia }}
                @else
                    Sin valoración independiente registrada
                @endif
            </dd></div>
        @endforeach
    </dl>
    @if ($tablaContable)
        <p><a href="{{ route('ingenieria-economica.proyectos.activos.calculos.show',[$proyecto,$activo,$ultimoCalculoContable]) }}">Último cálculo contable</a>
        del {{ $ultimoCalculoContable->created_at->format('d/m/Y') }}.
        @foreach ($tablaContable['metodos'] as $metodo=>$tabla)
            {{ $metodo === 'lineal' ? 'Método lineal' : 'Doble saldo decreciente' }} · Saldo al corte {{ $importe($tabla['saldo']) }}.
        @endforeach</p>
    @endif
    @if ($valoracion)
        <p><a href="{{ route('ingenieria-economica.proyectos.activos.calculos.show',[$proyecto,$activo,$ultimaValoracion]) }}">Última comparación guardada</a>
            · Mercado {{ $importe($valoracion['mercado_actual']) }} · Rescate al horizonte al conservar {{ $importe($valoracion['parametros']['conservar']['rescate']) }}.
            @if ($edad = $valoracion['vidas_economicas']['conservar']['menor_caue']) Menor CAUE entre edades evaluadas · {{ $edad }} años. @endif
        </p>
    @endif
</section>
@if ($activo->descripcion)<p class="description">{{ $activo->descripcion }}</p>@endif
@if ($activo->observaciones)<p class="description">{{ $activo->observaciones }}</p>@endif
<div class="actions section">
    @if (in_array($activo->clasificacion_contable, ['activo_tangible', 'activo_intangible'], true))
        <a class="button" href="{{ route('ingenieria-economica.proyectos.activos.depreciacion', [$proyecto, $activo]) }}">
            {{ $activo->clasificacion_contable === 'activo_tangible' ? 'Comparar métodos de depreciación' : 'Amortización' }}
        </a>
    @endif
    <a class="button" href="{{ route('ingenieria-economica.proyectos.activos.reemplazo', [$proyecto, $activo]) }}">Analizar conservación y reemplazo</a>
</div>
<section class="section panel-link">
    <div class="topbar"><h3>Evidencias del activo</h3><a class="button" href="{{ route('ingenieria-economica.proyectos.activos.fuentes.create', [$proyecto, $activo]) }}">Agregar evidencia</a></div>
    <ul class="records">
        @forelse ($activo->fuentes as $fuente)
            <li>
                <strong>{{ FuenteActivo::USOS[$fuente->uso] }} · {{ $fuente->referencia }}</strong>
                <div>{{ $fuente->fecha->toDateString() }} · {{ $fuente->importe_observado ?? 'Sin importe observado' }}</div>
                <p class="description">{{ $fuente->justificacion }}</p>
        @include('ingenieria-economica._eliminar',['ruta'=>route('ingenieria-economica.proyectos.activos.fuentes.destroy',[$proyecto,$activo,$fuente->id]),'nombre'=>$fuente->referencia,'accion'=>'Retirar referencia','conserva'=>'El documento original se conserva.'])

                @if ($fuente->documento)<a href="{{ route('documentos.show', $fuente->documento) }}">{{ $fuente->documento->nombre_documento }}</a>@endif
                @if ($fuente->detalle?->partida)<a href="{{ route('partidas.show', $fuente->detalle->partida) }}">Partida {{ $fuente->detalle->partida->numero_partida }}</a>@endif
                @if ($fuente->detalle?->cuenta)<span> · Cuenta {{ $fuente->detalle->cuenta->codigo_cuenta }} {{ $fuente->detalle->cuenta->nombre_cuenta }}</span>@endif
            </li>
        @empty
            <li>Sin evidencias registradas.</li>
        @endforelse
    </ul>
</section>
<section class="section panel-link">
    <div class="topbar"><h3>Movimientos vinculados</h3><a class="button" href="{{ route('ingenieria-economica.proyectos.activos.flujo.create', [$proyecto, $activo]) }}">Vincular movimiento</a></div>
    <ul class="records">
        @forelse ($activo->conceptos as $concepto)
            <li><a href="{{ route('ingenieria-economica.proyectos.conceptos.show', [$proyecto, $concepto]) }}">{{ $concepto->nombre }}</a> · {{ $concepto->activo_operacion }}</li>
        @empty
            <li>Sin movimientos vinculados.</li>
        @endforelse
    </ul>
</section>
<section class="section panel-link">
    <h3>Tratamiento fiscal documentado</h3>
    <p class="muted">La cuota fiscal se registra y respalda en la evaluación. Para una disposición, usa la base fiscal pendiente documentada. El precio de venta procede del flujo vinculado al activo.</p>
    <p><a href="{{ route('ingenieria-economica.proyectos.evaluaciones.index', $proyecto) }}">Abrir Evaluación Económica</a></p>
    @foreach (($activo->clasificacion_contable === 'activo_intangible' ? ['amortizacion_fiscal'=>'Amortización fiscal'] : ['depreciacion_fiscal'=>'Depreciación fiscal']) + ['efecto_tributario'=>'Efecto tributario'] as $campo => $etiqueta)
        <p class="description"><strong>{{ $etiqueta }}</strong> · {{ $activo->{$campo} ?? 'Pendiente de documentar' }}</p>
    @endforeach
</section>
<section class="section panel-link">
    <h3>Historial de cálculos</h3>
    <ul class="records">
        @forelse ($calculos as $calculo)
            <li><a href="{{ route('ingenieria-economica.proyectos.activos.calculos.show', [$proyecto, $activo, $calculo]) }}">{{ CalculoActivo::METODOS[$calculo->metodo] }} #{{ $calculo->id }}</a> · {{ $calculo->created_at->format('d/m/Y H:i') }}</li>
        @empty
            <li>Sin cálculos guardados.</li>
        @endforelse
    </ul>
    @include('ingenieria-economica._paginacion', ['paginas' => $calculos])
</section>
@endsection
