@use('App\Services\IngenieriaEconomica\ContextoEvaluacion')

@php
    use App\Services\IngenieriaEconomica\CalidadProyectoService;
    $accionesGrupo = [
        'Proyecto'=>['Completar proyecto','edit'], 'Alternativas'=>['Revisar alternativas','alternativas.index'],
        'Escenario'=>['Configurar escenario','escenarios.create'], 'Conceptos'=>['Revisar conceptos','conceptos.index'],
        'Flujos'=>['Revisar flujos','flujos.index'], 'Evaluación'=>['Revisar evaluaciones','evaluaciones.index'],
        'Sensibilidad'=>['Revisar sensibilidad','sensibilidad.index'], 'Activos'=>['Revisar activos','activos.index'],
    ];
    $accionesRegistro = [
        'concepto'=>['Ver concepto','conceptos.show'], 'activo'=>['Ver activo','activos.show'],
        'escenario'=>['Revisar tasa y fuente','escenarios.edit'], 'alternativa'=>['Revisar alternativa','alternativas.edit'],
        'evaluacion'=>['Ver evaluación','evaluaciones.show'],
    ];
    $porCategoria = collect($calidad['requisitos'])->groupBy(fn ($c) => $c['categoria'] ?? 'Calidad de datos');
@endphp
<section class="section panel-link">
    <h3>Preparación documental</h3>
    <p>@foreach (CalidadProyectoService::ESTADOS as $codigo=>$nombre) {{ $nombre }} {{ $calidad['conteos'][$codigo] ?? 0 }} · @endforeach
        <strong>Bloqueos críticos {{ count($calidad['bloqueos']) }}</strong>.</p>
    <p class="muted">Validado indica que se comprobó la regla indicada. Pendiente señala un dato o respaldo faltante. Requiere revisión indica que hay información que contrastar. No aplicable indica que el control no corresponde.</p>
    @if ($calidad['bloqueos'])
        <p>Resuelve los requisitos marcados <strong>Bloquea informe validado</strong>. Puedes emitir un informe preliminar.</p>
    @endif
    @foreach ($porCategoria as $categoria=>$requisitos)
        <section class="section">
            <h4>{{ $categoria }}</h4>
            <div class="table-wrap"><table>
                <thead><tr><th scope="col">Requisito</th><th scope="col">Estado</th><th scope="col">Razón y siguiente paso</th></tr></thead>
                <tbody>@foreach ($requisitos as $c)
                    @php
                        [$tipoAccion, $idAccion] = $c['destino'] ?? [null, null];
                        [$accion, $ruta] = $accionesRegistro[$tipoAccion] ?? $accionesGrupo[$c['grupo']];
                        $parametros = $idAccion ? [$proyecto ?? null,$idAccion] : [$proyecto ?? null];
                        $pendiente = in_array($c['estado'], ['pendiente','revision'], true);
                    @endphp
                    <tr>
                        <th scope="row">{{ $c['requisito'] }}<br><small>{{ $c['grupo'] }}</small></th>
                        <td>{{ CalidadProyectoService::ESTADOS[$c['estado']] }}
                            @if ($pendiente && $c['critico'])<br><strong>Bloquea informe validado</strong>@endif
                        </td>
                        <td>{{ $c['detalle'] }}
                            @if (isset($proyecto))
                            <div class="actions">
                                <a href="{{ ContextoEvaluacion::enlace($ruta, $parametros) }}">{{ $accion }}</a>
                                @if ($tipoAccion && ($categoria === 'Respaldo documental' || in_array($tipoAccion, ['alternativa','activo'], true)))
                                    <a href="{{ ContextoEvaluacion::enlace('evidencias.index',[$proyecto,'tipo'=>$tipoAccion,'registro'=>$idAccion,'origen'=>'validacion']) }}">{{ $pendiente ? 'Vincular o revisar evidencia' : 'Ver respaldo documental' }}</a>
                                @endif
                            </div>
                            @endif
                        </td>
                    </tr>
                @endforeach</tbody>
            </table></div>
        </section>
    @endforeach
</section>
