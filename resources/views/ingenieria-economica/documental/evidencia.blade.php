@extends('layouts.ingenieria-economica')

@php
    use App\Models\IngenieriaEconomica\VinculoDocumental;
@endphp
@section('economia-content')
@include('ingenieria-economica.documental._cabecera',['titulo'=>$item->titulo])
@if (in_array($item->estado, ['existente','pendiente'], true))

        @include('ingenieria-economica._eliminar',['ruta'=>route('ingenieria-economica.proyectos.evidencias.destroy',[$proyecto,$item]),'nombre'=>$item->titulo,'accion'=>'Eliminar borrador','conserva'=>'Los documentos originales y las versiones históricas se conservan.'])
@endif
<dl class="panel-grid summary">
    <div class="panel-link"><dt>Procedencia</dt><dd>{{ $item->procedencia }}</dd></div>
    <div class="panel-link"><dt>Tipo y fecha</dt><dd>{{ $item::TIPOS[$item->tipo] }} · {{ $item->fecha_documento->format('d/m/Y') }}</dd></div>
    <div class="panel-link"><dt>Calidad del dato</dt><dd>{{ $item::CALIDADES[$item->calidad] }}</dd></div>
    <div class="panel-link"><dt>Estado</dt><dd>{{ $item::ESTADOS[$item->estado] }}</dd></div>
    <div class="panel-link"><dt>Responsable registrado</dt><dd>{{ $item->usuario->nombre }} {{ $item->usuario->apellido }}</dd></div>
</dl>
@if ($item->observaciones)<p class="description">{{ $item->observaciones }}</p>@endif
@if ($item->archivo)
    <p><a class="button" href="{{ route('ingenieria-economica.proyectos.evidencias.download',[$proyecto,$item]) }}">Descargar adjunto</a></p>
    <p class="muted description">SHA-256 {{ $item->sha256 }}</p>
@else
    <p class="muted">Sin adjunto en el expediente.</p>
@endif
@if ($faltaRespaldo)
    <p class="muted">{{ $faltaRespaldo }}</p>
@endif
@if (!$item->archivo)
    <form class="section panel-link" method="POST" enctype="multipart/form-data" action="{{ route('ingenieria-economica.proyectos.evidencias.adjuntar',[$proyecto,$item]) }}">
        @csrf
        <label for="archivo">Adjuntar respaldo</label>
        <input id="archivo" name="archivo" type="file" accept="application/pdf,image/png,image/jpeg" required>
        <div class="actions section"><button class="button">Adjuntar archivo</button></div>
    </form>
@endif
<form class="section panel-link" id="revision" method="POST" action="{{ route('ingenieria-economica.proyectos.evidencias.estado',[$proyecto,$item]) }}">
    @csrf
    <h3>Registrar revisión documental</h3>
    <p>Existente indica que se recibió un archivo. Revisado indica que se comprobó el contenido y la procedencia. Validado requiere revisión previa y un archivo o documento contable verificable.</p>
    <p class="muted">Para usar una cotización histórica en una tarifa futura, verifica aparte su vigencia para la fecha de decisión.</p>
    <div class="form-grid">
        @include('ingenieria-economica._campo',['campo'=>'estado','etiqueta'=>'Nuevo estado','tipo'=>'select','opciones'=>array_diff_key($item::ESTADOS,['existente'=>true]),'obligatorio'=>true])
        <div class="field-full"><label for="revision-nota">Motivo de revisión</label><textarea name="nota" id="revision-nota" maxlength="4000" required></textarea></div>
    </div>
    <div class="actions"><button class="button">Registrar revisión</button></div>
</form>
@include('ingenieria-economica.documental._selector')
<section class="section panel-link">
    <h3>Vínculos guardados</h3>
    <ul class="records section">@forelse ($item->vinculos as $v)
        <li><strong>{{ VinculoDocumental::TIPOS[$v->tipo] }} #{{ $v->destino_id }} · {{ $v->etiqueta }}</strong>
            @if ($v->conciliacion)<p>Conciliación · {{ VinculoDocumental::CONCILIACIONES[$v->conciliacion] }}</p>@endif
            @include('ingenieria-economica.documental._referencia',['ref'=>$datos[$v->id],'contable'=>$v->conciliacion !== null])
            <p class="description">{{ $v->nota }}</p>
            <a href="{{ route('ingenieria-economica.proyectos.evidencias.show',[$proyecto,$item,'tipo'=>$v->tipo,'registro'=>$v->destino_id]).'#confirmar-vinculo' }}">Revisar vínculo</a>
            <details class="section">
                <summary>Desvincular {{ $v->etiqueta }}</summary>
                <form method="POST" action="{{ route('ingenieria-economica.proyectos.evidencias.desvincular',[$proyecto,$item,$v->id]) }}"
                    onsubmit="return confirm(this.dataset.confirmacion)"
                    data-confirmacion="{{ '¿Desvincular «'.$v->etiqueta.'»? La evidencia, el archivo y el historial se conservan.' }}">
                    @csrf
                    @method('DELETE')
                    <label for="motivo-{{ $v->id }}">Motivo de la desvinculación</label>
                    <textarea id="motivo-{{ $v->id }}" name="nota" maxlength="4000" required></textarea>
                    <button class="button warning" type="submit">Desvincular</button>
                </form>
            </details>
        </li>
    @empty
        <li>Sin referencia contable vinculada y sin otros vínculos.</li>
    @endforelse</ul>
    @unless ($item->vinculos->contains(fn ($v)=>$v->conciliacion !== null) || $item->vinculos->isEmpty())
        <p>Sin referencia contable vinculada.</p>
    @endunless
</section>
<section class="section panel-link"><h3>Historial de cambios</h3>
    <ul class="records">@foreach ($item->historial->sortByDesc('id') as $evento)
        <li>{{ $evento->created_at->format('d/m/Y H:i') }} · {{ $evento->usuario->nombre }} {{ $evento->usuario->apellido }} · {{ ucfirst($evento->accion) }}
            @if ($evento->accion === 'estado')
                · {{ $item::ESTADOS[$evento->datos['anterior']] }} → {{ $item::ESTADOS[$evento->datos['estado']] }}
            @elseif ($evento->accion === 'desvinculo')
                · Vínculo retirado de {{ $evento->datos['anterior']['etiqueta'] }}
            @elseif ($evento->accion === 'vinculo')
                · {{ $evento->datos['etiqueta'] }} · {{ VinculoDocumental::CONCILIACIONES[$evento->datos['conciliacion']] ?? 'Vínculo económico' }}
                @if ($evento->datos['anterior']['nota'] ?? null)<p class="description">Nota previa · {{ $evento->datos['anterior']['nota'] }}</p>@endif
            @endif
            @if ($evento->datos['nota'] ?? null)<p class="description">{{ $evento->datos['nota'] }}</p>@endif
        </li>
    @endforeach</ul>
</section>
@endsection
