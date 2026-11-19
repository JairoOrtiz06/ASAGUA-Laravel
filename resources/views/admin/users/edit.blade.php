@extends('layouts.app')
@section('title', 'ASAGUA — Editar usuario')
@section('content')<h2>Editar usuario #{{ $user->id_usuario }}</h2><form method="POST" action="{{ route('admin.users.update', $user) }}">@csrf @method('PUT') @include('admin.users.form')<div class="actions" style="margin-top:20px"><button type="submit">Guardar cambios</button><a class="button secondary" href="{{ route('admin.users.index') }}">Cancelar</a></div></form>@endsection
