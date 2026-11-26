@extends('layouts.ingenieria-economica')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/asagua-inicios.css') }}">
@endpush

@section('economia-content')
<div class="asagua-inicio-economico">
    <x-asagua.banner titulo="Ingeniería Económica" icono="↗">
        Gestiona proyectos de inversión, compara alternativas y analiza los resultados económicos para apoyar la toma de decisiones.
    </x-asagua.banner>

    <nav class="asagua-accesos" aria-label="Accesos de Ingeniería Económica">
        <x-asagua.acceso :href="route('ingenieria-economica.proyectos.index')" icono="▰" titulo="Mis proyectos"
            descripcion="Consulta y continúa trabajando en tus proyectos registrados." />
        <x-asagua.acceso :href="route('ingenieria-economica.proyectos.create')" icono="＋" titulo="Nuevo proyecto"
            descripcion="Registra una decisión de inversión, su fecha base y su horizonte." :acento="true" />
        <x-asagua.acceso :href="route('ingenieria-economica.herramientas.index')" icono="▦" titulo="Herramientas financieras"
            descripcion="Convierte tasas y calcula equivalencias de capitales y pagos uniformes." />
    </nav>
</div>
@endsection
