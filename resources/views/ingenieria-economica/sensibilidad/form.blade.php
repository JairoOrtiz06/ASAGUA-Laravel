@extends('layouts.ingenieria-economica')

@php
    use App\Models\IngenieriaEconomica\Evaluacion;
    use App\Services\IngenieriaEconomica\ProyeccionFlujosService;
    use App\Services\IngenieriaEconomica\SensibilidadService;
    use App\Services\IngenieriaEconomica\VariablesSensibilidadService;

    $ruta = 'ingenieria-economica.proyectos.sensibilidad.';
    $estadoInicial = fn ($campo, $defecto = '') => is_scalar(old($campo)) ? old($campo) : $defecto;
    $seleccion = array_map('strval', array_filter((array) old('alternativas', array_slice(array_keys($alternativas), 0, 2)), 'is_scalar'));
    $indicadores = (array) old('indicadores', ['van','vpc','caue','rcb','tir']);
@endphp

@section('economia-content')
<div class="topbar">
    <div><h2>Nuevo análisis de sensibilidad</h2><p class="muted">{{ $proyecto->nombre }}</p></div>
    <a class="button secondary" href="{{ route($ruta.'index', $proyecto) }}">← Análisis guardados</a>
</div>
<form class="panel-link" method="GET" action="{{ route($ruta.'create', $proyecto) }}">
    <div class="form-grid">
        @include('ingenieria-economica._campo', ['campo'=>'escenario_id','etiqueta'=>'Escenario con datos vigentes','tipo'=>'select','opciones'=>$escenarios->pluck('nombre','id'),'valor'=>$escenario?->id])
        @include('ingenieria-economica._campo', ['campo'=>'evaluacion_id','etiqueta'=>'Evaluación guardada (opcional)','tipo'=>'select',
            'opciones'=>$evaluaciones->mapWithKeys(fn ($e) => [$e->id => '#'.$e->id.' · '.$e->moneda.' · '.$e->perspectiva.' · '.$e->created_at->format('d/m/Y H:i')]),
            'valor'=>$evaluacion?->id,'ayuda'=>'Una versión guardada fija su escenario y moneda y carga sus parámetros históricos.'])
        @include('ingenieria-economica._campo', ['campo'=>'moneda','etiqueta'=>'Moneda de la base vigente','valor'=>$moneda,'maximo'=>3])
    </div>
    <div class="actions"><button class="button secondary" type="submit">Cargar variables de la base</button></div>
</form>
@if ($aviso)<p class="errors">{{ $aviso }}</p>@endif
@if ($escenario && $catalogo)
<form class="section panel-link" id="formulario-sensibilidad" method="POST" action="{{ route($ruta.'preview', $proyecto) }}">
    @csrf
    <input type="hidden" name="escenario_id" value="{{ $escenario->id }}">
    <input type="hidden" name="moneda" value="{{ $moneda }}">
    @if ($evaluacion)<input type="hidden" name="evaluacion_id" value="{{ $evaluacion->id }}">@endif
    <p><strong>{{ $escenario->nombre }}</strong> · {{ $moneda }} · {{ $evaluacion ? 'Evaluación #'.$evaluacion->id : 'Datos vigentes' }}</p>
    <div class="form-grid">
        @include('ingenieria-economica._campo', ['campo'=>'nombre','etiqueta'=>'Nombre del análisis','obligatorio'=>true,'maximo'=>150])
        @include('ingenieria-economica._campo', ['campo'=>'estado','etiqueta'=>'Validación de los supuestos','tipo'=>'select','opciones'=>['provisional'=>'Provisional','validado'=>'Validado por el contador'],'valor'=>'provisional','obligatorio'=>true])
        @include('ingenieria-economica._campo', ['campo'=>'descripcion','etiqueta'=>'Descripción','tipo'=>'textarea'])
        @include('ingenieria-economica._campo', ['campo'=>'supuestos','etiqueta'=>'Supuestos, origen y justificación','tipo'=>'textarea'])
        @include('ingenieria-economica._campo', ['campo'=>'perspectiva','etiqueta'=>'Perspectiva','tipo'=>'select','opciones'=>Evaluacion::PERSPECTIVAS,'valor'=>$evaluacion?->perspectiva ?? 'caja','obligatorio'=>true])
        @include('ingenieria-economica._campo', ['campo'=>'periodicidad','etiqueta'=>'Periodicidad del descuento','tipo'=>'select','opciones'=>ProyeccionFlujosService::AGRUPACIONES,'valor'=>$evaluacion?->instantanea['escenario']['periodicidad'] ?? 'escenario','obligatorio'=>true])
        @include('ingenieria-economica._campo', ['campo'=>'modo','etiqueta'=>'Tipo de análisis','tipo'=>'select','opciones'=>SensibilidadService::MODOS,'valor'=>'unidimensional','obligatorio'=>true])
        @include('ingenieria-economica._campo', ['campo'=>'referencia_id','etiqueta'=>'Alternativa B de referencia','tipo'=>'select','opciones'=>$alternativas,'ayuda'=>'Si queda vacía, se usa la segunda alternativa seleccionada.'])
        <fieldset class="field-full">
            <legend>Alternativas</legend>
            @foreach ($alternativas as $id => $nombre)
                <label class="flujo-opcion"><input type="checkbox" name="alternativas[]" value="{{ $id }}" @checked(in_array((string) $id, $seleccion, true))> {{ $nombre }}</label>
            @endforeach
        </fieldset>
        <fieldset class="field-full">
            <legend>Indicadores a presentar</legend>
            @foreach (SensibilidadService::INDICADORES as $id => $nombre)
                <label class="flujo-opcion"><input type="checkbox" name="indicadores[]" value="{{ $id }}" @checked(in_array($id, $indicadores, true))> {{ $nombre }}</label>
            @endforeach
        </fieldset>
    </div>

    <p class="muted">Usa punto decimal. Para una tasa absoluta, 5 significa 5 %. Un cambio de 5 puntos se suma a la tasa. Una variación relativa de 5 % multiplica el valor base por 1.05.</p>
    @foreach ([0 => 'Variable / eje X', 1 => 'Eje Y'] as $i => $titulo)
        <fieldset class="section" data-eje data-sensibilidad="{{ $i === 0 ? 'unidimensional matriz equilibrio' : 'matriz' }}">
            <legend>{{ $titulo }}</legend>
            <div class="form-grid">
                <div><label for="variable-{{ $i }}">Variable</label>
                    <select data-variable id="variable-{{ $i }}" aria-describedby="ayuda-eje-{{ $i }}" name="ejes[{{ $i }}][variable]">
                        <option value="">Seleccionar</option>
                        @foreach ($catalogo as $id => $v)
                            <option value="{{ $id }}" data-unidad="{{ $v['unidad'] }}" data-base="{{ $v['base'] }}" @selected($estadoInicial("ejes.$i.variable") === $id)>{{ $v['nombre'] }} ({{ $v['unidad'] }}{{ $v['base'] !== null ? ' · base '.$v['base'] : '' }})</option>
                        @endforeach
                    </select>
                </div>
                <div><label for="modo-{{ $i }}">Modo de variación</label>
                    <select data-variacion id="modo-{{ $i }}" aria-describedby="ayuda-eje-{{ $i }}" name="ejes[{{ $i }}][modo]">
                        @foreach (VariablesSensibilidadService::MODOS as $id => $nombre)
                            <option value="{{ $id }}" @selected($estadoInicial("ejes.$i.modo") === $id)>{{ $nombre }}</option>
                        @endforeach
                    </select>
                </div>
                @foreach (['valores'=>'Valores separados por espacios o comas','min'=>'Mínimo','max'=>'Máximo','paso'=>'Incremento','cantidad'=>'Cantidad de simulaciones'] as $campo => $etiqueta)
                    <div @if (in_array($campo,['valores','paso','cantidad'],true)) data-sensibilidad="{{ $i === 0 ? 'unidimensional matriz' : 'matriz' }}" @endif>
                        <label for="eje-{{ $i }}-{{ $campo }}">{{ $etiqueta }}</label>
                        <input aria-describedby="ayuda-eje-{{ $i }}" id="eje-{{ $i }}-{{ $campo }}" name="ejes[{{ $i }}][{{ $campo }}]" value="{{ $estadoInicial("ejes.$i.$campo") }}">
                    </div>
                @endforeach
            </div>
            <p id="ayuda-eje-{{ $i }}" data-ayuda-eje role="status" class="muted"></p>
            <p class="muted">Lista explícita o rango con paso. Sin paso, indica la cantidad para distribuir los valores entre los extremos. Para equilibrio usa mínimo y máximo.</p>
        </fieldset>
    @endforeach
    <div class="section" data-sensibilidad="equilibrio">
        @include('ingenieria-economica._campo', ['campo'=>'criterio','etiqueta'=>'Indicador a igualar','tipo'=>'select','opciones'=>['vpc'=>'VPC','caue'=>'CAUE'],'valor'=>'vpc'])
    </div>
    @foreach (['optimista' => 'Optimista', 'pesimista' => 'Pesimista'] as $tipo => $titulo)
        <fieldset class="section" data-sensibilidad="escenarios">
            <legend>{{ $titulo }}</legend>
            <label for="justificacion-{{ $tipo }}">Justificación</label>
            <textarea id="justificacion-{{ $tipo }}" name="escenarios[{{ $tipo }}][justificacion]" rows="2">{{ $estadoInicial("escenarios.$tipo.justificacion") }}</textarea>
            @for ($i=0; $i<4; $i++)
                <div class="form-grid section">
                    <div><label for="{{ $tipo }}-{{ $i }}-variable">Variable {{ $i + 1 }}</label>
                        <select id="{{ $tipo }}-{{ $i }}-variable" name="escenarios[{{ $tipo }}][cambios][{{ $i }}][variable]">
                            <option value="">Sin cambio</option>
                            @foreach ($catalogo as $id => $v)
                                <option value="{{ $id }}" @selected($estadoInicial("escenarios.$tipo.cambios.$i.variable") === $id)>{{ $v['nombre'] }} ({{ $v['unidad'] }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div><label for="{{ $tipo }}-{{ $i }}-modo">Modo</label>
                        <select id="{{ $tipo }}-{{ $i }}-modo" name="escenarios[{{ $tipo }}][cambios][{{ $i }}][modo]">
                            @foreach (VariablesSensibilidadService::MODOS as $id => $nombre)
                                <option value="{{ $id }}" @selected($estadoInicial("escenarios.$tipo.cambios.$i.modo") === $id)>{{ $nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div><label for="{{ $tipo }}-{{ $i }}-valor">Valor</label><input id="{{ $tipo }}-{{ $i }}-valor" name="escenarios[{{ $tipo }}][cambios][{{ $i }}][valor]" value="{{ $estadoInicial("escenarios.$tipo.cambios.$i.valor") }}"></div>
                </div>
            @endfor
        </fieldset>
    @endforeach

    @foreach ($catalogo as $clave => $v)
        @if (str_starts_with($clave, 'horas:'))
            @php($id = substr($clave, 6))
            <details class="section panel-link" @if(old("oportunidad.$id.salario") !== null) open @endif>
                <summary>Parámetros laborales · {{ $v['nombre'] }}</summary>
                <p>Completa estos datos si se variarán horas o salario. El concepto original conserva su importe.</p>
                <div class="form-grid">
                    @foreach (['salario'=>"Salario mensual ($moneda)",'jornada'=>'Horas laborales diarias','dias'=>'Días laborales al mes','horas'=>'Horas adicionales diarias de referencia'] as $campo => $etiqueta)
                        <div><label for="labor-{{ $id }}-{{ $campo }}">{{ $etiqueta }}</label><input id="labor-{{ $id }}-{{ $campo }}" name="oportunidad[{{ $id }}][{{ $campo }}]" value="{{ $estadoInicial("oportunidad.$id.$campo") }}"></div>
                    @endforeach
                </div>
            </details>
        @endif
    @endforeach
    <div class="section actions"><button class="button" type="submit">Generar simulaciones</button></div>
</form>
<script>
    (() => {
        const form = document.querySelector('#formulario-sensibilidad');
        const modo = form.elements.modo;
        const actualizar = () => {
            form.querySelectorAll('[data-sensibilidad]').forEach((elemento) => {
                const visible = elemento.dataset.sensibilidad.split(' ').includes(modo.value);
                elemento.hidden = !visible;
                if (elemento.tagName === 'FIELDSET') elemento.disabled = !visible;
                else elemento.querySelectorAll('input, select, textarea').forEach(campo => campo.disabled = !visible);
            });
        };
        for (const eje of form.querySelectorAll('[data-eje]')) {
            const variable = eje.querySelector('[data-variable]');
            const variacion = eje.querySelector('[data-variacion]');
            const ayuda = eje.querySelector('[data-ayuda-eje]');
            const puntos = variacion.querySelector('option[value="puntos"]');
            const describir = () => {
                const seleccion = variable.selectedOptions[0];
                const unidad = seleccion?.dataset.unidad;
                puntos.disabled = !unidad?.startsWith('%');
                if (puntos.disabled && variacion.value === 'puntos') variacion.value = 'absoluto';
                ayuda.textContent = unidad
                    ? `Valor inicial: ${seleccion.dataset.base ? seleccion.dataset.base + ' ' + unidad : 'según parámetros laborales'}. Introduce los valores en ${variacion.value === 'puntos' ? 'puntos porcentuales' : variacion.value === 'relativo' ? 'porcentaje relativo' : unidad}.`
                    : 'Selecciona la variable para consultar su valor inicial y unidad.';
            };
            variable.addEventListener('change', describir);
            variacion.addEventListener('change', describir);
            describir();
        }
        modo.addEventListener('change', actualizar);
        actualizar();
    })();
</script>
@else
    <p class="muted">Registra al menos un escenario y una alternativa para cargar variables.</p>
@endif
@endsection
