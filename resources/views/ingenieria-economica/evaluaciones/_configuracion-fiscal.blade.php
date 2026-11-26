@php
    use App\Services\IngenieriaEconomica\EvaluacionFiscalService;
@endphp
@if ($evidenciasFiscales->isEmpty())
    <p class="muted">{{ EvaluacionFiscalService::PENDIENTE }}. Registra y valida el respaldo en el expediente del proyecto.</p>
@else
    <details class="section" @if (old('perspectiva') === 'fiscal') open @endif>
        <summary>Configuración para la evaluación después de impuestos</summary>
        <p>Usa la perspectiva fiscal y el descuento anual. Concilia el impuesto sobre la renta atribuible al proyecto. Los importes deben estar documentados en la moneda elegida y la tasa debe cubrir todo el horizonte.</p>
        <div class="form-grid">
            @include('ingenieria-economica._campo', ['campo'=>'fiscal.tratamiento','nombreCampo'=>'fiscal[tratamiento]','etiqueta'=>'Tratamiento del impuesto sobre la renta','tipo'=>'select','opciones'=>['ordinario'=>'Cálculo ordinario documentado','no_aplicable_documentado'=>'No aplicación documentada para el proyecto']])
            @include('ingenieria-economica._campo', ['campo'=>'fiscal.estado','nombreCampo'=>'fiscal[estado]','etiqueta'=>'Estado de los parámetros','tipo'=>'select','opciones'=>['pendiente'=>'Pendiente','estimado'=>'Estimado','confirmado'=>'Confirmado con evidencia']])
            @include('ingenieria-economica._campo', ['campo'=>'fiscal.tasa','nombreCampo'=>'fiscal[tasa]','etiqueta'=>'Tasa ordinaria en fracción','ayuda'=>'Introduce 0.20 para representar 20 %. Introduce 0 únicamente si está respaldado.'])
            @include('ingenieria-economica._campo', ['campo'=>'fiscal.tasa_venta','nombreCampo'=>'fiscal[tasa_venta]','etiqueta'=>'Tasa de disposición en fracción','ayuda'=>'Completa si hay venta o rescate de activos sujetos a análisis.'])
            @include('ingenieria-economica._campo', ['campo'=>'fiscal.vigente_desde','nombreCampo'=>'fiscal[vigente_desde]','etiqueta'=>'Vigencia desde','tipo'=>'date'])
            @include('ingenieria-economica._campo', ['campo'=>'fiscal.vigente_hasta','nombreCampo'=>'fiscal[vigente_hasta]','etiqueta'=>'Vigencia hasta','tipo'=>'date'])
            @include('ingenieria-economica._campo', ['campo'=>'fiscal.evidencia','nombreCampo'=>'fiscal[evidencia]','etiqueta'=>'Evidencia validada del expediente','tipo'=>'select','opciones'=>$evidenciasFiscales])
            @include('ingenieria-economica._campo', ['campo'=>'fiscal.nota','nombreCampo'=>'fiscal[nota]','etiqueta'=>'Justificación fiscal','tipo'=>'textarea','ayuda'=>'Identifica la sección o anexo que respalda la base, deducciones, disposiciones y fechas de imputación.'])
        </div>
        <p>El período 0 corresponde a la fecha base. Los siguientes se numeran desde 1. Para tratamiento ordinario, registra cada período aunque la base sea cero. Si el respaldo documenta que el impuesto no aplica al proyecto, indica tasa 0 y deja las filas vacías. Usa punto decimal y separa columnas con punto y coma.</p>
        @foreach ($alternativas as $alternativa)
            <details class="section" @if (old('fiscal.alternativas.'.$alternativa->id)) open @endif>
                <summary>Base y conciliación · {{ $alternativa->nombre }}</summary>
                @php($prefijo = 'fiscal.alternativas.'.$alternativa->id)
                @php($nombreGrupo = 'fiscal[alternativas]['.$alternativa->id.']')
                <div class="form-grid">
                    @include('ingenieria-economica._campo', ['campo'=>$prefijo.'.base','nombreCampo'=>$nombreGrupo.'[base]','etiqueta'=>'Ingresos gravados y gastos deducibles','tipo'=>'textarea','maximo'=>12000,'ayuda'=>'Período; ingreso gravado ordinario; gasto deducible. Ejemplo 0;0;0. Deja fuera ventas de activos e impuestos.'])
                    @include('ingenieria-economica._campo', ['campo'=>$prefijo.'.pagos','nombreCampo'=>$nombreGrupo.'[pagos]','etiqueta'=>'Impuesto que ya figura en caja','tipo'=>'textarea','maximo'=>12000,'ayuda'=>'Período; código de origen del impuesto, por ejemplo 1;c25. Consulta el código en Fuentes y datos originales de la evaluación de caja.'])
                    @include('ingenieria-economica._campo', ['campo'=>$prefijo.'.deducciones','nombreCampo'=>$nombreGrupo.'[deducciones]','etiqueta'=>'Deducciones fiscales de activos','tipo'=>'textarea','maximo'=>12000,'ayuda'=>'Período; activo; método; importe. Usa manual para una cuota fiscal distinta. Usa lineal o doble_saldo solo cuando el método y la cuota coinciden y están documentados.'])
                    @include('ingenieria-economica._campo', ['campo'=>$prefijo.'.ventas','nombreCampo'=>$nombreGrupo.'[ventas]','etiqueta'=>'Disposición de activos','tipo'=>'textarea','maximo'=>12000,'ayuda'=>'Período; activo; base fiscal pendiente. La venta o rescate debe estar vinculado al activo en los flujos.'])
                </div>
                @if ($activosFiscales->isNotEmpty())
                    <p class="muted">Referencias para las filas de activos.
                        @foreach ($activosFiscales->whereIn('alternativa_id', [null, $alternativa->id]) as $activo)
                            #{{ $activo->id }} {{ $activo->nombre }}@unless($loop->last) · @endunless
                        @endforeach
                    </p>
                @endif
            </details>
        @endforeach
        <label class="flujo-opcion section">
            <input type="checkbox" name="fiscal[conciliado]" value="1" @checked(old('fiscal.conciliado'))>
            Confirmo que el respaldo cubre los parámetros y que declaré todos los pagos de este impuesto incluidos en caja.
        </label>
        <p class="muted">Las bases negativas no generan devoluciones. Este cálculo contempla una vigencia, sin compensación de pérdidas, créditos, IVA ni calendarios especiales de pago.</p>
    </details>
@endif
