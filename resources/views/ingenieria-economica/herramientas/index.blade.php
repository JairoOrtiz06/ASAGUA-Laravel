@extends('layouts.ingenieria-economica')

@section('economia-content')
@php
    $herramientaInfo = [
        'tasas' => ['icono' => '%', 'detalle' => 'Convierte tasas nominales y efectivas entre distintas periodicidades.', 'tipo' => 'Conversión'],
        'interes-compuesto' => ['icono' => 'ƒ', 'detalle' => 'Calcula el valor presente o futuro de un capital con interés compuesto.', 'tipo' => 'Capitalización'],
        'factores' => ['icono' => '↗', 'detalle' => 'Consulta factores financieros para mover valores entre fechas equivalentes.', 'tipo' => 'Equivalencias'],
        'series' => ['icono' => '∑', 'detalle' => 'Resuelve anualidades y series uniformes vencidas, anticipadas o diferidas.', 'tipo' => 'Pagos periódicos'],
    ];
@endphp
<section class="economia-herramientas" aria-labelledby="titulo-herramientas">
    <header class="economia-encabezado">
        <div><span class="economia-eyebrow">CAJA DE HERRAMIENTAS</span><h2 id="titulo-herramientas">Herramientas financieras</h2><p>Calcula equivalencias y consulta el procedimiento de cada operación.</p></div>
        <span class="economia-header-mark" aria-hidden="true">∑</span>
    </header>
    <div class="tool-grid">
        @foreach ($herramientas as $clave => $nombre)
            @php($info = $herramientaInfo[$clave] ?? ['icono' => '↗', 'detalle' => 'Abre esta herramienta para consultar sus parámetros y realizar el cálculo.', 'tipo' => 'Cálculo financiero'])
            <a class="tool-card tool-card--{{ $clave }}" href="{{ route('ingenieria-economica.herramientas.show', $clave) }}">
                <span class="tool-card-top"><span class="tool-card-icon" aria-hidden="true">{{ $info['icono'] }}</span><span class="tool-card-type">{{ $info['tipo'] }}</span></span>
                <span class="tool-card-copy"><strong>{{ $nombre }}</strong><span>{{ $info['detalle'] }}</span></span>
                <span class="tool-card-bottom"><span>Abrir calculadora</span><span class="tool-card-arrow" aria-hidden="true">↗</span></span>
            </a>
        @endforeach
    </div>
    <aside class="economia-ayuda"><span aria-hidden="true">i</span><p>Cada herramienta conserva el procedimiento financiero utilizado para obtener el resultado.</p></aside>
</section>
@endsection
