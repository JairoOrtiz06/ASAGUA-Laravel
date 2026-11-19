@extends('layouts.app')
@section('title', 'ASAGUA — Gestión de Usuarios')
@section('content')
<div class="topbar"><div><h2>Gestión de Usuarios</h2><p class="muted">Usuarios de la tabla tblusuarios.</p></div><a class="button" href="{{ route('admin.users.create') }}">＋ Registrar usuario</a></div>
<form method="GET" action="{{ route('admin.users.index') }}" class="actions" style="margin-bottom:18px"><input style="max-width:300px" name="q" value="{{ $search }}" placeholder="Buscar usuario…"><button type="submit">Buscar</button>@if($search)<a class="button secondary" href="{{ route('admin.users.index') }}">Limpiar</a>@endif</form>
<div class="table-wrap"><table><thead><tr><th>ID</th><th>Nombre</th><th>Apellido</th><th>Email</th><th>Usuario</th><th>Rol</th><th>Activo</th><th>Acciones</th></tr></thead><tbody>
@forelse($users as $user)<tr><td>{{ $user->id_usuario }}</td><td>{{ $user->nombre }}</td><td>{{ $user->apellido }}</td><td>{{ $user->email }}</td><td>{{ $user->usuario }}</td><td>{{ $user->rol }}</td><td>{{ $user->activo ? 'Sí' : 'No' }}</td><td class="actions"><a class="button" href="{{ route('admin.users.edit', $user) }}">Editar</a><form class="inline" method="POST" action="{{ route('admin.users.status', $user) }}">@csrf @method('PATCH')<button class="{{ $user->activo ? 'button warning' : 'button success' }}" type="submit">{{ $user->activo ? 'Desactivar' : 'Activar' }}</button></form></td></tr>@empty<tr><td colspan="8">No hay usuarios.</td></tr>@endforelse
</tbody></table></div><div class="pagination">{{ $users->links() }}</div>
@endsection
