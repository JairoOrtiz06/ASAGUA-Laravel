<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0c3767">
    <title>@yield('title', 'ASAGUA')</title>
    <link rel="stylesheet" href="{{ asset('css/asagua-app.css') }}">
    @stack('styles')
</head>
<body class="asagua-app">
<div class="app">
    <aside class="sidebar">
        <a class="brand" href="{{ auth()->check() && auth()->user()->rol === 'Contador' ? route('portal.contador') : route('dashboard') }}" aria-label="ASAGUA, inicio">
            <span class="brand-mark" aria-hidden="true"><svg viewBox="0 0 32 32" fill="none"><path d="M16 3C12 9 6 15 6 21a10 10 0 0 0 20 0c0-6-6-12-10-18Z" fill="currentColor"/><path d="M11 21c0 2.9 2.1 5 5 5" stroke="#fff" stroke-width="2" stroke-linecap="round"/></svg></span>
            <span class="brand-copy"><strong>ASAGUA</strong><small>Sistema de gestión</small></span>
        </a>
        @auth
            <div class="sidebar-caption">ESPACIO DE TRABAJO</div>
            <nav class="nav" aria-label="Navegación principal">
                @section('sidebar-navigation')
                <a href="{{ auth()->user()->rol === 'Contador' ? route('portal.contador') : route('dashboard') }}" @class(['active' => request()->routeIs('portal.contador', 'dashboard', 'panel.*')])><span class="nav-icon" aria-hidden="true">⌂</span><span>Inicio</span></a>
                <a href="{{ route('catalogo.index') }}" @class(['active' => request()->routeIs('catalogo.*')])><span class="nav-icon" aria-hidden="true">▤</span><span>Catálogo de cuentas</span></a>
                @if(in_array(auth()->user()->rol, ['Contador', 'Auditor'], true))
                    <a href="{{ route('partidas.index') }}" @class(['active' => request()->routeIs('partidas.index', 'partidas.show')])><span class="nav-icon" aria-hidden="true">▧</span><span>Partidas contables</span></a>
                    <a href="{{ route('documentos.index') }}" @class(['active' => request()->routeIs('documentos.*')])><span class="nav-icon" aria-hidden="true">▣</span><span>Documentos fuente</span></a>
                    <div class="nav-group-label">CONSULTAS E INFORMES</div>
                    <a href="{{ route('libros.diario') }}" @class(['active' => request()->routeIs('libros.diario')])><span class="nav-icon" aria-hidden="true">≡</span><span>Libro diario</span></a>
                    <a href="{{ route('libros.mayor') }}" @class(['active' => request()->routeIs('libros.mayor')])><span class="nav-icon" aria-hidden="true">▥</span><span>Libro mayor</span></a>
                    <a href="{{ route('balances.comprobacion') }}" @class(['active' => request()->routeIs('balances.comprobacion')])><span class="nav-icon" aria-hidden="true">⊞</span><span>Balance de comprobación</span></a>
                    <a href="{{ route('balances.general') }}" @class(['active' => request()->routeIs('balances.general')])><span class="nav-icon" aria-hidden="true">▦</span><span>Balance general</span></a>
                    <a href="{{ route('balances.resultados') }}" @class(['active' => request()->routeIs('balances.resultados')])><span class="nav-icon" aria-hidden="true">↗</span><span>Estado de resultados</span></a>
                    @if(auth()->user()->rol === 'Contador')<a href="{{ route('hoja-trabajo.index') }}" @class(['active' => request()->routeIs('hoja-trabajo.*')])><span class="nav-icon" aria-hidden="true">▦</span><span>Hoja de trabajo</span></a>@endif
                @endif
                @if(auth()->user()->rol === 'Contador')
                    <div class="nav-group-label">GESTIÓN CONTABLE</div>
                    <a href="{{ route('partidas.create') }}" @class(['active' => request()->routeIs('partidas.create', 'partidas.store')])><span class="nav-icon" aria-hidden="true">＋</span><span>Registrar partida</span></a>
                    <a href="{{ route('ajustes.index') }}" @class(['active' => request()->routeIs('ajustes.*')])><span class="nav-icon" aria-hidden="true">⚙</span><span>Ajustes contables</span></a>
                @endif
                @if(auth()->user()->rol === 'Admin')
                    <a href="{{ route('admin.users.index') }}" @class(['active' => request()->routeIs('admin.users.*')])><span class="nav-icon" aria-hidden="true">♙</span><span>Gestión de usuarios</span></a>
                @endif
                @show
            </nav>
            <div class="sidebar-bottom"><div class="sidebar-note"><span class="status-dot"></span><span>Plataforma ASAGUA<small>Gestión institucional</small></span></div>
                <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="logout-button"><span aria-hidden="true">↪</span> Cerrar sesión</button></form>
            </div>
        @endauth
    </aside>
    <main class="main">
        @auth
            @php($routeTitle = match (true) {
                request()->routeIs('ingenieria-economica.*') => 'Ingeniería Económica',
                request()->routeIs('balances.*') => 'Estados financieros',
                request()->routeIs('libros.*') => 'Libros contables',
                request()->routeIs('partidas.*') => 'Partidas contables',
                request()->routeIs('documentos.*') => 'Documentos fuente',
                request()->routeIs('catalogo.*') => 'Catálogo de cuentas',
                request()->routeIs('admin.users.*') => 'Gestión de usuarios',
                request()->routeIs('ajustes.*') => 'Ajustes contables',
                request()->routeIs('hoja-trabajo.*') => 'Hoja de trabajo',
                default => 'Panel de trabajo',
            })
            <header class="app-topbar"><div class="topbar-context"><span class="topbar-kicker">ASAGUA <span aria-hidden="true">/</span> <span>@yield('section-name', $routeTitle)</span></span><span class="topbar-date">Sistema administrativo y contable</span></div>
                <div class="topbar-user"><span class="user-avatar">{{ substr(auth()->user()->nombre ?? 'U', 0, 1) }}{{ substr(auth()->user()->apellido ?? '', 0, 1) }}</span><span class="user-details"><strong>{{ auth()->user()->nombre }} {{ auth()->user()->apellido }}</strong><small>{{ auth()->user()->rol }}</small></span></div>
            </header>
        @endauth
        <section class="card">
            @if(session('status')) <div class="alert" role="status">{{ session('status') }}</div> @endif
            @if(session('error')) <div class="errors" role="alert">{{ session('error') }}</div> @endif
            @if($errors->any())<div class="errors" role="alert"><strong>Revisa la información ingresada.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            @yield('content')
        </section>
        <footer class="app-footer"><span>ASAGUA · Sistema institucional de gestión</span><span>Uso interno</span></footer>
    </main>
</div>
</body>
</html>
