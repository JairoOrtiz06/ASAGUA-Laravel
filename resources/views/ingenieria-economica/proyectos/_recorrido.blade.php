@php
    use App\Services\IngenieriaEconomica\ContextoEvaluacion;

    $version = isset($progreso['evaluacion']) ? ['evaluacion_id' => $progreso['evaluacion']->id] + ($contexto?->complementos ?? []) : [];
    $evaluacionUrl = ContextoEvaluacion::enlace('evaluaciones.index', $proyecto);
    $informeUrl = ContextoEvaluacion::enlace('informes.index', [$proyecto, ...$version]).($hayInformes ? '#historial-informes' : '');
    if (isset($progreso['alcance']['emitido'])) {
        $informeUrl = ContextoEvaluacion::enlace('informes.show', [$proyecto, $progreso['alcance']['emitido']]);
    }
    $resultadosUrl = isset($progreso['evaluacion'])
        ? ContextoEvaluacion::enlace('evaluaciones.show', [$proyecto, $progreso['evaluacion']])
        : $evaluacionUrl.'#evaluaciones-guardadas';
    $estados = ['sin-iniciar' => 'Sin iniciar', 'en-progreso' => 'En progreso', 'revision' => 'Pendiente de revisión',
        'completado' => 'Completado', 'no-aplica' => 'No aplicable'];
    $pasos = [
        [
            'Definir la inversión',
            'Registra las opciones que deseas comparar.',
            ['Ver alternativas', ContextoEvaluacion::enlace('alternativas.index', $proyecto)],
        ],
        [
            'Configurar la evaluación',
            'Indica el plazo y la tasa que utilizarás para los cálculos.',
            ['Ver escenarios', ContextoEvaluacion::enlace('escenarios.index', $proyecto)],
        ],
        [
            'Registrar costos y beneficios',
            'Introduce los importes y datos económicos de cada alternativa.',
            ['Registrar costos y beneficios', ContextoEvaluacion::enlace('conceptos.index', $proyecto)],
        ],
        [
            'Revisar movimientos de dinero',
            'Comprueba las fechas, los pagos y los ingresos proyectados.',
            ['Revisar flujos', ContextoEvaluacion::enlace('flujos.index', $proyecto)],
        ],
        [
            'Realizar los cálculos económicos',
            'Obtén los resultados financieros de tus alternativas.',
            ['Realizar evaluación', $evaluacionUrl],
        ],
        [
            'Analizar los resultados',
            'Compara las opciones y comprueba cómo cambian los resultados.',
            ['Ver resultados', $resultadosUrl],
        ],
        [
            'Verificar y generar informe',
            'Revisa los respaldos y prepara el informe final.',
            ['Resultados e informes', $informeUrl],
        ],
    ];
@endphp

<section class="section" aria-labelledby="recorrido-economico">
    <h2 id="recorrido-economico" tabindex="-1">Recorrido guiado de evaluación económica</h2>
    <p class="proyecto-intro">Sigue estos pasos para evaluar tus alternativas y preparar el informe. Puedes volver a cualquier paso para corregir datos.</p>

    <ol class="proyecto-recorrido" role="list">
        @foreach ($pasos as [$titulo, $descripcion, [$accion, $destino]])
            @php
                $estado = $progreso['pasos'][$loop->index];
                $recomendacion = $progreso['accion'];
                $destacado = $recomendacion && $recomendacion['paso'] === $loop->index;
                if ($destacado && ($contexto || $recomendacion['ruta'] === 'evaluaciones.index')) {
                    $destino = ContextoEvaluacion::enlace($recomendacion['ruta'], [$proyecto, ...$recomendacion['parametros']]);
                    $accion = $recomendacion['titulo'];
                }
            @endphp
            <li @class(['proyecto-paso-recomendado' => $destacado])>
                <span class="proyecto-numero" aria-hidden="true">{{ $loop->iteration }}</span>
                <div class="proyecto-paso-texto">
                    <h3>{{ $titulo }}</h3>
                    <p>{{ $descripcion }}</p>
                    <div class="proyecto-estado" id="motivo-paso-{{ $loop->iteration }}">
                        <strong class="proyecto-estado-{{ $estado['estado'] }}">{{ $estados[$estado['estado']] }}</strong>
                        <span>{{ $estado['motivo'] }}</span>
                    </div>
                </div>
                <div class="proyecto-paso-acciones">
                    @if ($destacado)<span class="proyecto-accion-recomendada">Acción recomendada</span>@endif
                    <a class="button" href="{{ $destino }}" aria-describedby="motivo-paso-{{ $loop->iteration }}">{{ $accion }}</a>
                </div>
            </li>
        @endforeach
    </ol>
</section>
