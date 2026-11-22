<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'ASAGUA')</title>
    <style>
        :root { --navy:#0a4d8c; --blue:#29a8e0; --cream:#eaf4fc; --text:#17324d; --line:#c5ddeb; }
        * { box-sizing:border-box; }
        body { margin:0; min-height:100vh; font-family:Arial,sans-serif; color:var(--text); background:linear-gradient(135deg,#083b70,var(--navy)); }
        a { color:inherit; }
        .portal-shell { min-height:100vh; display:grid; place-items:center; padding:28px; }
        .portal-card { width:min(980px,100%); min-height:620px; padding:42px; background:#fff; border-radius:22px; box-shadow:0 20px 50px rgba(0,0,0,.18); }
        .portal-header { display:flex; justify-content:space-between; align-items:flex-start; gap:20px; margin-bottom:58px; }
        .brand h1 { margin:0; color:var(--navy); font-size:clamp(2rem,5vw,3.1rem); }
        .brand small { color:#1b75bb; }
        h2 { margin:0 0 8px; color:var(--navy); font-size:clamp(1.6rem,3vw,2.25rem); }
        .muted { color:#5c7183; }
        .user-box { text-align:right; }
        .logout { margin-top:12px; border:0; border-radius:7px; padding:9px 13px; cursor:pointer; background:#6b7280; color:#fff; font-weight:600; }
        .component-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:24px; }
        .component-card { display:block; min-height:190px; padding:30px; border:1px solid var(--line); border-radius:16px; text-decoration:none; transition:transform .15s ease, box-shadow .15s ease, border-color .15s ease; }
        .component-card:hover { transform:translateY(-3px); border-color:var(--blue); box-shadow:0 12px 26px rgba(10,77,140,.12); }
        .component-card strong { display:block; margin-bottom:12px; color:var(--navy); font-size:1.25rem; }
        @media(max-width:700px) { .portal-shell { padding:14px; } .portal-card { padding:26px 20px; min-height:0; } .portal-header { display:block; margin-bottom:34px; } .user-box { margin-top:22px; text-align:left; } .component-grid { grid-template-columns:1fr; } }
    </style>
</head>
<body>
<main class="portal-shell">
    <section class="portal-card">
        @yield('content')
    </section>
</main>
</body>
</html>
