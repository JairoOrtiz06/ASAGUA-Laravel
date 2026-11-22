@extends('layouts.ingenieria-economica')
@section('title', 'ASAGUA — Ingeniería Económica')
@section('content')
<div class="module-header">
    <div>
        <div class="brand"><h1>💧 ASAGUA</h1><small>Col. Las Flores, Mazariego y Suárez</small></div>
        <br>
        <h2>Ingeniería Económica</h2>
        <p class="muted">Componente inicial de Ingeniería Económica.</p>
    </div>
    <a class="button secondary" href="{{ route('portal.contador') }}">← Portal principal</a>
</div>

<div class="module-content">
    <p>Este componente está disponible como pantalla inicial. Sus funcionalidades se incorporarán posteriormente.</p>
</div>
@endsection
