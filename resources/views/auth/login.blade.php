<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>ASAGUA — Iniciar sesión</title>
<style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#0a4d8c;font-family:Arial,sans-serif}.card{width:min(360px,90vw);padding:32px;background:#fff;border-radius:18px}.brand{text-align:center;color:#0a4d8c;margin-bottom:25px}.brand h1{font-size:36px;margin:0}.brand p{color:#1b75bb}label{display:block;font-weight:bold;color:#17324d;margin-top:14px}input{width:100%;box-sizing:border-box;padding:11px;margin-top:6px;border:1px solid #a9c5d8;border-radius:6px}button{width:100%;padding:12px;margin-top:22px;border:0;border-radius:7px;background:#0a4d8c;color:#fff;font-weight:bold;cursor:pointer}.error{padding:10px;background:#fee2e2;color:#991b1b;border-radius:6px}</style>
</head><body><main class="card"><div class="brand"><h1>💧 ASAGUA</h1><p>Col. Las Flores, Mazariego y Suárez<br>Sistema Contable</p></div>
@if($errors->any())<div class="error">{{ $errors->first() }}</div>@endif
<form method="POST" action="{{ route('login.store') }}">@csrf
<label for="usuario">Usuario</label><input id="usuario" name="usuario" value="{{ old('usuario') }}" required autofocus>
<label for="password">Contraseña</label><input id="password" type="password" name="password" required>
<button type="submit">Acceder 💧</button></form></main></body></html>
