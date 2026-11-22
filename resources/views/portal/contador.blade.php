@extends('layouts.portal')
@section('title', 'ASAGUA — Portal principal')
@section('content')
<div class="portal-header">
    <div>
        <div class="brand"><h1>💧 ASAGUA</h1><small>Col. Las Flores, Mazariego y Suárez</small></div>
        <br>
        <h2>Portal principal</h2>
        <p class="muted">Bienvenido, {{ $user->nombre }} {{ $user->apellido }}.</p>
    </div>
    <div class="user-box">
        <span class="muted">Usuario: {{ $user->usuario }}</span>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="logout" type="submit">🚪 Cerrar sesión</button>
        </form>
    </div>
</div>

<p class="muted">Seleccione el componente que desea utilizar.</p>

<div class="component-grid">
    <a class="component-card" href="{{ route('panel.contador') }}">
        <strong>📚 Sistemas Contables</strong><br>
        <span class="muted">Acceder al panel y módulos contables de ASAGUA.</span>
    </a>
    <a class="component-card" href="{{ route('ingenieria-economica.index') }}">
        <strong>📐 Ingeniería Económica</strong><br>
        <span class="muted">Acceder a la pantalla inicial del componente.</span>
    </a>
</div>
@endsection
