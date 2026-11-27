@props(['titulo', 'icono'])

<header class="asagua-banner">
    <span class="asagua-icono" aria-hidden="true">{{ $icono }}</span>
    <div class="asagua-banner-texto">
        <h2>{{ $titulo }}</h2>
        <p>{{ $slot }}</p>
    </div>
</header>
