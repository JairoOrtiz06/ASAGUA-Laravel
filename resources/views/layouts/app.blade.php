<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'ASAGUA')</title>
    <style>
        :root { --navy:#0a4d8c; --blue:#29a8e0; --cream:#eaf4fc; --text:#17324d; }
        * { box-sizing:border-box; } body { margin:0; font-family:Arial,sans-serif; color:var(--text); background:var(--navy); }
        a { color:inherit; } .app { min-height:100vh; display:flex; } .sidebar { width:245px; padding:28px 18px; background:var(--cream); }
        .brand { text-align:center; margin-bottom:30px; } .brand h1 { margin:0; color:var(--navy); } .brand small { color:#1b75bb; }
        .nav a { display:block; padding:11px 13px; margin:5px 0; text-decoration:none; border-radius:8px; font-weight:600; }
        .nav a:hover, .nav a.active { color:#fff; background:var(--navy); } .main { flex:1; padding:28px; background:var(--navy); }
        .card { background:#fff; border-radius:16px; padding:28px; min-height:calc(100vh - 56px); } h2 { color:var(--navy); margin-top:0; }
        .topbar { display:flex; justify-content:space-between; align-items:center; gap:15px; margin-bottom:25px; }
        .button, button { border:0; border-radius:7px; padding:10px 15px; cursor:pointer; text-decoration:none; display:inline-block; background:var(--navy); color:#fff; font-weight:600; }
        .button.secondary { background:#6b7280; } .button.warning { background:#b45309; } .button.success { background:#15803d; }
        .table-wrap { overflow:auto; } table { width:100%; border-collapse:collapse; } th,td { padding:11px; border-bottom:1px solid #dbe7f0; text-align:left; }
        th { background:var(--cream); color:var(--navy); } .form-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:15px; }
        label { display:block; font-weight:600; } input, select { width:100%; margin-top:6px; padding:10px; border:1px solid #a9c5d8; border-radius:6px; }
        .field-full { grid-column:1/-1; } .actions { display:flex; gap:8px; align-items:center; flex-wrap:wrap; } .alert { padding:12px; border-radius:7px; background:#dff5e7; color:#166534; margin-bottom:16px; }
        .errors { padding:12px; border-radius:7px; background:#fee2e2; color:#991b1b; margin-bottom:16px; } .muted { color:#5c7183; }
        .panel-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(210px,1fr)); gap:15px; } .panel-link { padding:20px; border:1px solid #c5ddeb; border-radius:10px; text-decoration:none; }
        .inline { display:inline; } .pagination { margin-top:18px; }
        @media(max-width:760px) { .app { display:block; } .sidebar { width:auto; } .main { padding:12px; } .card { min-height:0; } .form-grid { grid-template-columns:1fr; } }
    </style>
</head>
<body>
<div class="app">
    <aside class="sidebar">
        <div class="brand"><h1>💧 ASAGUA</h1><small>Col. Las Flores, Mazariego y Suárez</small></div>
        @auth
            <nav class="nav">
                <a href="{{ auth()->user()->rol === 'Contador' ? route('portal.contador') : route('dashboard') }}">🏠 Principal</a>
                <a href="{{ route('catalogo.index') }}">📚 Catálogo de cuentas</a>
                @if(in_array(auth()->user()->rol, ['Contador', 'Auditor'], true))
                    <a href="{{ route('partidas.index') }}">🧾 Partidas contables</a>
                    <a href="{{ route('documentos.index') }}">📄 Documentos fuente</a>
                    <a href="{{ route('libros.diario') }}">📖 Libro diario</a>
                    <a href="{{ route('libros.mayor') }}">📚 Libro mayor</a>
                    <a href="{{ route('balances.comprobacion') }}">📊 Balance de comprobación</a>
                    <a href="{{ route('balances.general') }}">🏦 Balance general</a>
                    <a href="{{ route('balances.resultados') }}">📈 Estado de resultados</a>
                    @if(auth()->user()->rol === 'Contador')
                        <a href="{{ route('hoja-trabajo.index') }}">🧮 Hoja de trabajo</a>
                    @endif
                @endif
                @if(auth()->user()->rol === 'Contador')
                    <a href="{{ route('partidas.create') }}">＋ Registrar partida</a>
                    <a href="{{ route('ajustes.index') }}">⚙ Ajustes contables</a>
                    
                @endif
                @if(auth()->user()->rol === 'Admin')
                    <a href="{{ route('admin.users.index') }}">👥 Gestión de Usuarios</a>
                @endif
            </nav>
            <form method="POST" action="{{ route('logout') }}" style="margin-top:35px">
                @csrf <button type="submit" class="button secondary" style="width:100%">🚪 Cerrar sesión</button>
            </form>
        @endif
    </aside>
    <main class="main"><section class="card">
        @if(session('status')) <div class="alert">{{ session('status') }}</div> @endif
        @if(session('error')) <div class="errors">{{ session('error') }}</div> @endif
        @if($errors->any())
            <div class="errors"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        @yield('content')
    </section></main>
</div>
</body>
</html>
