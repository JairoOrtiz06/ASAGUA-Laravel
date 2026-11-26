<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'ASAGUA')</title>
    <link rel="stylesheet" href="{{ asset('css/asagua-inicios.css') }}">
</head>
<body class="asagua-portal">
<main class="portal-shell">
    <section class="portal-card">
        @yield('content')
    </section>
</main>
</body>
</html>
