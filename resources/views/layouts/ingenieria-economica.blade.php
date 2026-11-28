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
        .economia .proyectos-busqueda { margin:0 0 1rem; }
        .economia .proyectos-busqueda input { width:min(100%, 22rem); }
        .economia .button.button-regresar {
            min-height:44px;
            padding:7px 13px 7px 8px;
            border-color:#5a908d;
            background:#eff9f7;
            color:#124e4c;
            box-shadow:none;
            transition:none;
        }
        .economia .button.button-regresar:hover {
            border-color:#226c68;
            background:#dff2ef;
            box-shadow:none;
            transform:none;
        }
        .economia .button.button-regresar:active { background:#cce8e4; }
        .economia .button-regresar-icono {
            display:grid;
            flex:0 0 26px;
            width:26px;
            height:26px;
            place-items:center;
            border-radius:50%;
            background:#d2ebe7;
            color:inherit;
            font-size:1.1rem;
            line-height:1;
        }
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
        .economia .proyecto-titulo {
            margin:0 0 7px; font-size:clamp(1.35rem,2vw,1.8rem); font-weight:800;
            letter-spacing:-.035em; overflow-wrap:anywhere;
        }
        .economia .proyecto-encabezado { margin-bottom:12px; }
        .economia .proyecto-contexto {
            display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:.8rem 1rem;
            padding:.85rem; border:1px solid var(--line); border-radius:12px; background:#f7fafc; line-height:1.4;
        }
        .economia .proyecto-contexto dt { font-size:.82rem; }
        .economia .proyecto-contexto dd { margin-top:.2rem; }
        .economia .proyecto-descripcion { margin:.5rem 0 0; overflow-wrap:anywhere; }
        .economia .proyecto-descripcion-breve { display:block; margin-top:.2rem; font-weight:400; }
        .economia .proyecto-descripcion[open] .proyecto-descripcion-breve { display:none; }
        .economia .proyecto-intro { margin:.35rem 0 .85rem; }
        .economia .proyecto-recorrido {
            margin:0; padding:0; list-style:none; border:1px solid var(--line); border-radius:12px;
        }
        .economia .proyecto-recorrido > li {
            display:grid; grid-template-columns:auto minmax(0,1fr) minmax(0,13rem);
            gap:.45rem .8rem; align-items:start; padding:.65rem .9rem;
        }
        .economia .proyecto-recorrido > li + li { border-top:1px solid var(--line); }
        .economia .proyecto-numero {
            display:grid; place-items:center; width:2rem; height:2rem; border-radius:50%;
            color:#0b595b; background:#e0f2f0; font-weight:800;
        }
        .economia .proyecto-paso-recomendado { background:#edf8f6; box-shadow:inset 4px 0 #267873; }
        .economia .proyecto-estado { display:flex; flex-wrap:wrap; align-items:baseline; gap:.25rem .5rem; margin-top:.4rem; font-size:.82rem; }
        .economia .proyecto-estado strong { padding:.1rem .45rem; border-radius:5px; background:#e9f1f7; color:#25445e; }
        .economia .proyecto-estado .proyecto-estado-completado { background:#d8ede7; color:#184d42; }
        .economia .proyecto-estado .proyecto-estado-revision { background:#fbefd1; color:#664209; }
        .economia .proyecto-accion-recomendada { flex-basis:100%; color:#124e4c; font-weight:700; font-size:.8rem; }
        .economia .proyecto-paso-texto { align-self:center; }
        .economia .proyecto-paso-texto p { margin:.22rem 0 0; }
        .economia .proyecto-paso-acciones {
            display:flex; flex-wrap:wrap; align-items:center; gap:.15rem .65rem; min-width:0;
        }
        @media(min-width:1150px) {
            .economia .proyecto-recorrido > li { grid-template-columns:auto minmax(0,1fr) minmax(0,22rem); }
        }
        .economia .proyecto-paso-acciones .button {
            min-height:44px; max-width:100%; text-align:center;
        }
        .economia .proyecto-paso-acciones > .button { background:#0d5c9e; }
        .economia .proyecto-descripcion > summary {
            padding:.75rem 0; color:var(--navy); font-weight:700;
        }
        .economia .proyecto-solo-lector {
            position:absolute; width:1px; height:1px; padding:0; margin:-1px;
            overflow:hidden; clip-path:inset(50%); white-space:nowrap;
        }
        @media(max-width:1200px) {
            .economia .proyecto-contexto { grid-template-columns:repeat(3,minmax(0,1fr)); }
        }
        @media(max-width:900px) {
            .economia .proyecto-contexto { grid-template-columns:repeat(2,minmax(0,1fr)); }
        }
        @media(max-width:620px) {
            .economia .proyecto-recorrido > li { grid-template-columns:auto minmax(0,1fr); }
            .economia .proyecto-paso-acciones { grid-column:2; }
        }
        @media(prefers-reduced-motion:reduce) {
            .economia .proyecto-paso-acciones .button { transition:none; transform:none; }
        }
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
        @yield('economia-content')
    </div>
    <script src="{{ asset('js/ingenieria-accesibilidad.js') }}" defer></script>
@endsection
