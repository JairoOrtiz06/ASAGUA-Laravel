@extends('layouts.portal')
@section('title', 'ASAGUA — Portal principal')
@section('content')
<header class="portal-header">
    <div class="portal-marca">
        <span class="portal-gota" aria-hidden="true">💧</span>
        <div><h1>ASAGUA</h1><p>Col. Las Flores, Mazariego y Suárez</p></div>
    </div>
    <div class="portal-cuenta">
        <span><small>Usuario</small><strong>{{ $user->usuario }}</strong></span>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="portal-salir" type="submit">Cerrar sesión</button>
        </form>
    </div>
</header>

<x-asagua.banner titulo="Portal principal" icono="⌂">
    Bienvenido, {{ $user->nombre }} {{ $user->apellido }}.
</x-asagua.banner>

<section class="portal-componentes" aria-labelledby="seleccion-componentes">
    <h3 id="seleccion-componentes">Seleccione el componente que desea utilizar</h3>
    <div class="asagua-accesos">
        <x-asagua.acceso :href="route('panel.contador')" icono="▤" titulo="Sistemas Contables"
            descripcion="Accede al panel y a los módulos contables de ASAGUA." />
        <x-asagua.acceso :href="route('ingenieria-economica.index')" icono="↗" titulo="Ingeniería Económica"
            descripcion="Gestiona proyectos de inversión y compara alternativas económicas." :acento="true" />
    </div>
</section>
@endsection
