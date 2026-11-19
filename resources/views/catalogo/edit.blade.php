@extends('layouts.app')
@section('title', 'ASAGUA — Editar cuenta')
@section('content')<h2>Editar cuenta #{{ $cuenta->id_cuenta }}</h2><form method="POST" action="{{ route('admin.cuentas.update', $cuenta) }}">@csrf @method('PUT') @include('catalogo.form')<div class="actions" style="margin-top:20px"><button type="submit">Guardar cambios</button><a class="button secondary" href="{{ route('catalogo.index') }}">Cancelar</a></div></form>@endsection
