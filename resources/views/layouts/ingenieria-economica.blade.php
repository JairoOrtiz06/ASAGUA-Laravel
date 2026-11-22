<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'ASAGUA — Ingeniería Económica')</title>
    <style>
        :root { --navy:#0a4d8c; --cream:#eaf4fc; --text:#17324d; --line:#c5ddeb; }
        * { box-sizing:border-box; }
        body { margin:0; min-height:100vh; font-family:Arial,sans-serif; color:var(--text); background:linear-gradient(135deg,#083b70,var(--navy)); }
        a { color:inherit; }
        .module-shell { min-height:100vh; display:grid; place-items:center; padding:28px; }
        .module-card { width:min(860px,100%); min-height:440px; padding:42px; background:#fff; border-radius:22px; box-shadow:0 20px 50px rgba(0,0,0,.18); }
        .module-header { display:flex; justify-content:space-between; align-items:flex-start; gap:20px; margin-bottom:54px; }
        .brand h1 { margin:0; color:var(--navy); font-size:clamp(2rem,5vw,3.1rem); }
        .brand small { color:#1b75bb; }
        h2 { margin:0 0 8px; color:var(--navy); font-size:clamp(1.6rem,3vw,2.25rem); }
        .muted { color:#5c7183; }
        .button { display:inline-block; padding:10px 15px; border-radius:7px; background:var(--navy); color:#fff; font-weight:600; text-decoration:none; }
        .button.secondary { background:#6b7280; }
        .module-content { padding:28px; border:1px solid var(--line); border-radius:16px; background:var(--cream); }
        @media(max-width:700px) { .module-shell { padding:14px; } .module-card { padding:26px 20px; min-height:0; } .module-header { display:block; margin-bottom:34px; } .module-header .button { margin-top:22px; } }
    </style>
</head>
<body>
<main class="module-shell">
    <section class="module-card">
        @yield('content')
    </section>
</main>
</body>
</html>
