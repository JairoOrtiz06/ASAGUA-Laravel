@php
    use App\Models\IngenieriaEconomica\VinculoDocumental;
    $rutaDetalle = 'ingenieria-economica.proyectos.evidencias.show';
    $esContable = in_array($tipo, \App\Services\IngenieriaEconomica\ReferenciaDocumentalService::CONTABLES, true);
@endphp
<section class="section panel-link" id="vincular">
    <h3>Vincular un registro</h3>
    <form method="GET" action="{{ route($rutaDetalle, [$proyecto, $item]).'#vincular' }}">
        <div class="form-grid">
            @include('ingenieria-economica._campo',['campo'=>'tipo','etiqueta'=>'Tipo de registro','tipo'=>'select','opciones'=>VinculoDocumental::TIPOS,'valor'=>$tipo,'obligatorio'=>true])
            @include('ingenieria-economica._campo',['campo'=>'q','etiqueta'=>'Buscar por nombre, descripción o número','valor'=>$filtros['q'] ?? null,'maximo'=>100])
        </div>
        <div class="actions"><button class="button">Buscar registros</button></div>
    </form>
    @if ($opciones)
        <ul class="records" aria-label="Registros disponibles">
            @forelse ($opciones as $opcion)
                <li><a @if (($seleccionado['id'] ?? null) === $opcion['id']) aria-current="true" @endif
                       href="{{ route($rutaDetalle, [$proyecto, $item, ...array_filter($filtros), 'tipo'=>$tipo, 'registro'=>$opcion['id']]).'#confirmar-vinculo' }}">{{ $opcion['etiqueta'] }}</a>
                    @include('ingenieria-economica.documental._referencia',['ref'=>$opcion,'contable'=>$esContable])
                </li>
            @empty
                <li>No se encontraron registros para esta búsqueda.</li>
            @endforelse
        </ul>
        @if ($opciones->hasPages())
            <nav class="actions pagination" aria-label="Páginas de registros">
                @if ($opciones->previousPageUrl()) <a href="{{ $opciones->previousPageUrl() }}#vincular">Anterior</a> @endif
                <span>Página {{ $opciones->currentPage() }}</span>
                @if ($opciones->nextPageUrl()) <a href="{{ $opciones->nextPageUrl() }}#vincular">Siguiente</a> @endif
            </nav>
        @endif
    @endif
    @if ($seleccionado)
        @php($actual = $item->vinculos->first(fn ($v) => $v->tipo === $tipo && $v->destino_id === $seleccionado['id']))
        <form class="section" id="confirmar-vinculo" method="POST" action="{{ route('ingenieria-economica.proyectos.evidencias.vincular',[$proyecto,$item]) }}">
            @csrf
            <h4>{{ $actual ? 'Actualizar vínculo' : 'Confirmar vínculo' }} · {{ $seleccionado['etiqueta'] }}</h4>
            <input type="hidden" name="tipo_vinculo" value="{{ $tipo }}">
            <input type="hidden" name="destino_id" value="{{ $seleccionado['id'] }}">
            @if ($esContable)
                @include('ingenieria-economica._campo',['campo'=>'conciliacion','etiqueta'=>'Resultado de conciliación documental','tipo'=>'select','opciones'=>VinculoDocumental::CONCILIACIONES,'valor'=>$actual?->conciliacion ?? 'pendiente','ayuda'=>'Marca conciliado solo tras contrastar el comprobante, el asiento y el dato económico.'])
            @endif
            <div class="field"><label for="vinculo-nota">Qué respalda este documento</label>
                <textarea name="nota" id="vinculo-nota" maxlength="4000" required>{{ old('tipo_vinculo') ? old('nota') : $actual?->nota }}</textarea>
            </div>
            <div class="actions"><button class="button">Guardar vínculo</button></div>
        </form>
    @endif
</section>
