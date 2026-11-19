@extends('layouts.app')
@section('title', 'ASAGUA — Registrar usuario')
@section('content')<h2>Registrar usuario</h2><form method="POST" action="{{ route('admin.users.store') }}">@csrf @include('admin.users.form')<div class="actions" style="margin-top:20px"><button type="submit">Guardar</button><a class="button secondary" href="{{ route('admin.users.index') }}">Cancelar</a></div></form>@endsection
