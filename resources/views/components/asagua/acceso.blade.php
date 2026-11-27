@props(['href', 'icono', 'titulo', 'descripcion', 'acento' => false])

<a @class(['asagua-acceso', 'asagua-acceso--violeta' => $acento]) href="{{ $href }}">
    <span class="asagua-icono" aria-hidden="true">{{ $icono }}</span>
    <span class="asagua-acceso-texto">
        <strong>{{ $titulo }}</strong>
        <span>{{ $descripcion }}</span>
    </span>
    <span class="asagua-flecha" aria-hidden="true">→</span>
</a>
