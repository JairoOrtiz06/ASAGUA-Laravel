@extends('layouts.ingenieria-economica')

@php
    use App\Models\IngenieriaEconomica\Concepto;

    $destino = $activo->exists ? route('ingenieria-economica.proyectos.activos.update', [$proyecto, $activo])
        : route('ingenieria-economica.proyectos.activos.store', $proyecto);
@endphp

@section('economia-content')
<div class="topbar">
    <h2>{{ $activo->exists ? 'Editar activo' : 'Registrar activo' }}</h2>
    <a class="button secondary" href="{{ route('ingenieria-economica.proyectos.activos.index', $proyecto) }}">← Activos</a>
</div>
<form class="panel-link economia-form" method="POST" action="{{ $destino }}">
    @csrf
    @if ($activo->exists) @method('PATCH') @endif
    <h3>Identificación</h3>
    <p>Proyecto · {{ $proyecto->nombre }}</p>
    <div class="form-grid">
        @include('ingenieria-economica._campo', ['campo' => 'nombre', 'etiqueta' => 'Nombre', 'valor' => $activo->nombre, 'obligatorio' => true])
        @include('ingenieria-economica._campo', ['campo' => 'alternativa_id', 'etiqueta' => 'Alternativa', 'tipo' => 'select', 'valor' => $activo->alternativa_id, 'opciones' => $alternativas, 'ayuda' => 'Si queda vacía, se registra como compartido del proyecto.'])
        @include('ingenieria-economica._campo', ['campo' => 'categoria', 'etiqueta' => 'Categoría', 'tipo' => 'select', 'valor' => $activo->categoria, 'opciones' => $activo::CATEGORIAS, 'obligatorio' => true])
        @include('ingenieria-economica._campo', ['campo' => 'clasificacion_contable', 'etiqueta' => 'Clasificación contable', 'tipo' => 'select', 'valor' => $activo->clasificacion_contable, 'opciones' => $activo::CLASIFICACIONES, 'obligatorio' => true, 'ayuda' => 'Clasifica explícitamente el software, las licencias, el soporte y las reparaciones.'])
        @include('ingenieria-economica._campo', ['campo'=>'fecha_adquisicion','etiqueta'=>'Fecha de adquisición','tipo'=>'date','valor'=>$activo->fecha_adquisicion?->toDateString()])
        @include('ingenieria-economica._campo', ['campo' => 'estado_activo', 'etiqueta' => 'Estado del activo', 'tipo' => 'select', 'opciones' => $activo::ESTADOS, 'valor' => $activo->estado_activo, 'obligatorio' => true])
    </div>
    <h3 class="section">Valoración</h3>
    <p>El costo capitalizado suma el costo de adquisición y los costos capitalizables. Las valoraciones independientes, con fecha y fuente, se registran como evidencias del activo.</p>
    @if ($activo->exists)
        <p>Costo capitalizado guardado · {{ \App\Services\IngenieriaEconomica\DecimalFinanciero::dinero($activo->baseCapitalizada()) }} {{ $activo->moneda }}.</p>
        <a href="{{ route('ingenieria-economica.proyectos.activos.show',[$proyecto,$activo]).'#valoracion' }}">Ver valor en libros, mercado y rescate</a>
    @endif
    <div class="form-grid section">
        @foreach (['costo_adquisicion' => 'Costo de adquisición', 'costos_capitalizables' => 'Costos capitalizables', 'valor_residual' => 'Valor residual', 'moneda' => 'Moneda', 'porcentaje_uso' => 'Porcentaje de uso (0 a 100)'] as $campo => $etiqueta)
            @include('ingenieria-economica._campo', ['campo' => $campo, 'etiqueta' => $etiqueta, 'valor' => $activo->{$campo}, 'obligatorio' => true])
        @endforeach
        @include('ingenieria-economica._campo', ['campo'=>'justificacion_residual','etiqueta'=>'Justificación del valor residual estimado','tipo'=>'textarea','valor'=>$activo->justificacion_residual])
        @include('ingenieria-economica._campo', ['campo' => 'origen_dato', 'etiqueta' => 'Calidad del dato', 'tipo' => 'select', 'opciones' => Concepto::ESTADOS, 'valor' => $activo->origen_dato, 'obligatorio' => true])
    </div>
    <section class="section" data-tipo-activo="activo_tangible activo_intangible pendiente_revision">
        <h3>Vida útil y cálculo contable</h3>
        <p>La vida útil estima los años de uso. La vida económica compara costos entre edades candidatas. La depreciación distribuye el costo de un tangible y la amortización el de un intangible de vida definida.</p>
        <div class="form-grid">
            @include('ingenieria-economica._campo', ['campo'=>'fecha_disponible_uso','etiqueta'=>'Fecha disponible para uso','tipo'=>'date','valor'=>$activo->fecha_disponible_uso?->toDateString()])
            @include('ingenieria-economica._campo', ['campo' => 'vida_util_anios', 'etiqueta' => 'Vida útil en años', 'tipo' => 'number', 'valor' => $activo->vida_util_anios, 'maximoNumerico' => 100])
            <div data-tipo-activo="activo_intangible">
                @include('ingenieria-economica._campo', ['campo' => 'vida_indefinida', 'etiqueta' => 'Tipo de vida útil', 'tipo' => 'select', 'opciones' => [0 => 'Definida o pendiente', 1 => 'Indefinida'], 'valor' => (int) $activo->vida_indefinida])
            </div>
        </div>
        @if ($activo->exists)
            <p><a href="{{ route('ingenieria-economica.proyectos.activos.show',[$proyecto,$activo]) }}">Consultar métodos, cuotas y vida económica calculada</a></p>
        @endif
    </section>
    <details class="section" @if ($errors->any()) open @endif>
        <summary>Información fiscal y respaldo</summary>
        <p>Documenta fuente, vigencia y estado de revisión. Los métodos contables y su valor en libros no determinan por sí solos la cuota ni la base fiscal pendiente.</p>
        <div class="form-grid">
            @foreach (['depreciacion_fiscal'=>['activo_tangible','Depreciación fiscal documentada'],'amortizacion_fiscal'=>['activo_intangible','Amortización fiscal documentada']] as $campo=>[$tipoActivo,$etiqueta])
                <div class="field-full" data-tipo-activo="{{ $tipoActivo }}">
                    @include('ingenieria-economica._campo',['campo'=>$campo,'etiqueta'=>$etiqueta,'tipo'=>'textarea','valor'=>$activo->{$campo}])
                </div>
            @endforeach
            @include('ingenieria-economica._campo',['campo'=>'efecto_tributario','etiqueta'=>'Disposición, base fiscal pendiente y efecto tributario documentado','tipo'=>'textarea','valor'=>$activo->efecto_tributario])
        </div>
        @if ($activo->exists)
            <p><a href="{{ route('ingenieria-economica.proyectos.evidencias.index',[$proyecto,'tipo'=>'activo','registro'=>$activo->id]) }}">Revisar o vincular respaldo documental</a></p>
        @endif
    </details>
    <div class="form-grid section">
        @foreach (['descripcion'=>'Descripción','observaciones'=>'Observaciones'] as $campo=>$etiqueta)
            @include('ingenieria-economica._campo',['campo'=>$campo,'etiqueta'=>$etiqueta,'tipo'=>'textarea','valor'=>$activo->{$campo}])
        @endforeach
    </div>
    <div class="actions"><button class="button" type="submit">Guardar activo</button></div>
</form>
<script src="{{ asset('js/ingenieria-activos.js') }}" defer></script>
@endsection
