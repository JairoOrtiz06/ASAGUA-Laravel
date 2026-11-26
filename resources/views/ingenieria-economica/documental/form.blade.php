@extends('layouts.ingenieria-economica')

@section('economia-content')
@include('ingenieria-economica.documental._cabecera',['titulo'=>'Registrar evidencia'])
<form class="panel-link economia-form" enctype="multipart/form-data" method="POST" action="{{ route('ingenieria-economica.proyectos.evidencias.store',$proyecto) }}">
    @csrf
    <div class="form-grid">
        @include('ingenieria-economica._campo',['campo'=>'titulo','etiqueta'=>'Título','obligatorio'=>true,'maximo'=>160])
        @include('ingenieria-economica._campo',['campo'=>'tipo','etiqueta'=>'Tipo de documento','tipo'=>'select','opciones'=>\App\Models\IngenieriaEconomica\EvidenciaDocumental::TIPOS,'obligatorio'=>true])
        @include('ingenieria-economica._campo',['campo'=>'procedencia','etiqueta'=>'Procedencia','obligatorio'=>true,'maximo'=>250])
        @include('ingenieria-economica._campo',['campo'=>'fecha_documento','etiqueta'=>'Fecha del documento','tipo'=>'date','obligatorio'=>true])
        @include('ingenieria-economica._campo',['campo'=>'calidad','etiqueta'=>'Calidad del dato','tipo'=>'select','opciones'=>\App\Models\IngenieriaEconomica\EvidenciaDocumental::CALIDADES,'obligatorio'=>true])
        <div class="field">
            <label for="archivo">Archivo privado</label>
            <input id="archivo" name="archivo" type="file" accept="application/pdf,image/png,image/jpeg">
            <small class="muted">PDF, PNG o JPEG · Hasta {{ config('ingenieria_documental.max_kib') }} KiB.</small>
        </div>
        @include('ingenieria-economica._campo',['campo'=>'observaciones','etiqueta'=>'Observaciones','tipo'=>'textarea','maximo'=>4000])
    </div>
    <div class="actions"><button class="button">Guardar evidencia</button></div>
</form>
@endsection
