@extends('layouts.portal')
@section('title', 'ASAGUA — Portal principal')
@section('content')
<header class="portal-header">
    <a class="portal-marca" href="{{ route('portal.contador') }}" aria-label="ASAGUA, inicio">
        <span class="portal-gota" aria-hidden="true"><svg viewBox="0 0 32 32" fill="none"><path d="M16 3C12 9 6 15 6 21a10 10 0 0 0 20 0c0-6-6-12-10-18Z" fill="currentColor"/><path d="M11 21c0 2.9 2.1 5 5 5" stroke="#fff" stroke-width="2" stroke-linecap="round"/></svg></span>
        <span><h1>ASAGUA</h1><p>Col. Las Flores, Mazariego y Suárez</p></span>
    </a>
    <div class="portal-cuenta">
        <span class="portal-usuario-avatar" aria-hidden="true">{{ substr($user->nombre ?? 'U', 0, 1) }}{{ substr($user->apellido ?? '', 0, 1) }}</span>
        <span class="portal-usuario-texto"><small>Conectado como</small><strong>{{ $user->usuario }}</strong><em>{{ $user->rol }}</em></span>
        <form method="POST" action="{{ route('logout') }}">@csrf<button class="portal-salir" type="submit" aria-label="Cerrar sesión" title="Cerrar sesión"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M10 4H5.8A1.8 1.8 0 0 0 4 5.8v12.4A1.8 1.8 0 0 0 5.8 20H10M14 8l4 4-4 4m4-4H9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></button></form>
    </div>
</header>
<main class="portal-main">
    <x-asagua.banner titulo="Un espacio para gestionar mejor" icono="⌂">
        Bienvenido, {{ $user->nombre }} {{ $user->apellido }}. Elige un área para continuar tu trabajo en ASAGUA.
    </x-asagua.banner>
    <section class="portal-componentes" aria-labelledby="seleccion-componentes">
        <div class="portal-seccion-titulo"><span>PLATAFORMA ASAGUA</span><h2 id="seleccion-componentes">¿En qué área quieres trabajar?</h2><p>Accede a tus herramientas contables o continúa con tus análisis de inversión.</p></div>
        <div class="asagua-accesos">
            <x-asagua.acceso :href="route('panel.contador')" icono="▤" titulo="Sistemas Contables"
                descripcion="Registra operaciones, consulta libros contables y prepara estados financieros." />
            <x-asagua.acceso :href="route('ingenieria-economica.index')" icono="↗" titulo="Ingeniería Económica"
                descripcion="Formula proyectos de inversión, compara alternativas y analiza resultados." :acento="true" />
        </div>
    </section>
    <footer class="portal-footer"><span><i></i> Plataforma disponible</span><span>ASAGUA · Gestión institucional</span></footer>
</main>
@endsection
