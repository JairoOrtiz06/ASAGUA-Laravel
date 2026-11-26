@extends('layouts.app')

@section('title', 'ASAGUA — Ingeniería Económica')

@push('styles')
    <style>
        .main:has(.economia), .economia, .economia fieldset, .economia .panel-link { min-width:0; }
        .economia .table-wrap { max-width:100%; overflow-x:auto; }
        .economia select, .economia input, .economia textarea { max-width:100%; scroll-margin-block:1rem; }
        .economia a:focus-visible, .economia button:focus-visible, .economia input:focus-visible,
        .economia select:focus-visible, .economia textarea:focus-visible, .economia summary:focus-visible,
        .economia [tabindex]:focus-visible, .nav:has(.economia-salto) a:focus-visible {
            outline:3px solid #0a4d8c; outline-offset:3px;
        }
        .nav:has(.economia-salto) .economia-salto { position:fixed; top:0.5rem; left:0.5rem; z-index:5; transform:translateY(-200%); }
        .nav:has(.economia-salto) .economia-salto:focus { transform:none; background:white; color:#17324d; }
        .economia .economia-ruta { display:flex; flex-wrap:wrap; gap:.5rem; padding:0; list-style:none; }
        .economia .economia-ruta a[aria-current] { font-weight:700; text-decoration-thickness:2px; }
        .economia .economia-aviso { border-inline-start:4px solid #946200; background:#fff4d3; color:#573c00; padding:.75rem 1rem; }
        .economia .economia-bloqueo { border-inline-start:4px solid #991b1b; background:#fee2e2; color:#7f1d1d; padding:.75rem 1rem; }
        @media(max-width:760px) {
            .main:has(.economia) .card { padding:16px; }
            .app:has(.economia) .sidebar { padding:14px; }
            .app:has(.economia) .sidebar .brand { margin-bottom:10px; }
            .economia .form-grid, .economia .panel-grid { grid-template-columns:minmax(0,1fr); }
            .economia .panel-link { padding:12px; }
            .economia .flujo-grafica li { grid-template-columns:2.5rem minmax(1rem,1fr) minmax(3rem,auto); }
        }
        .economia .topbar { flex-wrap:wrap; }
        .economia .topbar p { margin-bottom:0; }
        .economia .economia-form { max-width:960px; }
        .economia .form-grid + .actions { margin-top:20px; }
        .economia textarea { width:100%; margin-top:6px; padding:10px; border:1px solid #a9c5d8; border-radius:6px; font:inherit; resize:vertical; }
        .economia .field small { display:block; margin-top:6px; }
        .economia .field-error { color:#991b1b; }
        .economia .section { margin-top:24px; }
        .economia .section > .topbar { margin-bottom:12px; }
        .economia h3 { color:var(--navy); margin:0; }
        .economia .records { list-style:none; padding:0; margin:0; }
        .economia .records > li { padding:14px 0; overflow-wrap:anywhere; }
        .economia .records > li + li { border-top:1px solid #dbe7f0; }
        .economia .records .actions { margin-top:10px; }
        .economia .summary { margin:0; }
        .economia .summary dt { font-weight:600; color:var(--navy); }
        .economia .summary dd { margin:6px 0 0; overflow-wrap:anywhere; }
        .economia .description { white-space:pre-wrap; overflow-wrap:anywhere; }
        .economia #resultado-calculo + p, .economia .section li { overflow-wrap:anywhere; }
        .economia .flujo-opcion { display:inline-flex; align-items:center; gap:8px; margin:0 18px 10px 0; }
        .economia .flujo-opcion input { width:auto; margin:0; }
        .economia .flujo-grafica { list-style:none; padding:0; max-height:26rem; overflow:auto; }
        .economia .flujo-grafica li { display:grid; grid-template-columns:3.5rem minmax(5rem,1fr) minmax(5rem,auto); gap:12px; align-items:center; margin:6px 0; }
        .economia .flujo-eje { position:relative; display:block; height:1rem; background:var(--cream); }
        .economia .flujo-eje::before { content:''; position:absolute; top:0; bottom:0; left:50%; border-left:1px solid #a9c5d8; }
        .economia .flujo-barra { position:absolute; top:2px; bottom:2px; left:50%; background:#15803d; }
        .economia .flujo-barra.negativa { left:auto; right:50%; background:#b45309; }
        .economia details:target { outline:2px solid var(--navy); outline-offset:4px; }
        .economia summary { cursor:pointer; }
        .economia-nav-icono { display:inline-block; min-width:1.25em; text-align:center; }
    </style>
@endpush

@section('sidebar-navigation')
    <a class="economia-salto" href="#contenido-economico">Saltar al contenido</a>
    <a href="{{ route('portal.contador') }}"><span class="economia-nav-icono" aria-hidden="true">⌂</span> Portal principal</a>
    <a href="{{ route('ingenieria-economica.index') }}"
        @if(request()->routeIs('ingenieria-economica.index')) class="active" aria-current="page" @endif><span class="economia-nav-icono" aria-hidden="true">↗</span> Inicio de Ingeniería Económica</a>
    <a href="{{ route('ingenieria-economica.proyectos.index') }}"
        @class(['active' => request()->routeIs('ingenieria-economica.proyectos.*')])
        @if(request()->routeIs('ingenieria-economica.proyectos.*')) aria-current="{{ request()->routeIs('ingenieria-economica.proyectos.index') ? 'page' : 'location' }}" @endif><span class="economia-nav-icono" aria-hidden="true">▰</span> Mis proyectos</a>
    <a href="{{ route('ingenieria-economica.herramientas.index') }}"
        @class(['active' => request()->routeIs('ingenieria-economica.herramientas.*')])
        @if(request()->routeIs('ingenieria-economica.herramientas.*')) aria-current="{{ request()->routeIs('ingenieria-economica.herramientas.index') ? 'page' : 'location' }}" @endif><span class="economia-nav-icono" aria-hidden="true">▦</span> Herramientas financieras</a>
@endsection

@section('content')
    <div class="economia" id="contenido-economico" tabindex="-1">
        @if (isset($proyecto) && $proyecto->exists)
            @include('ingenieria-economica._navegacion-proyecto')
        @endif
        @yield('economia-content')
    </div>
    <script src="{{ asset('js/ingenieria-accesibilidad.js') }}" defer></script>
@endsection
