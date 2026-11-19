@extends('layouts.app')
@section('title', 'ASAGUA — Registrar cuenta')
@section('content')<h2>Registrar cuenta contable</h2><p class="muted">Los tipos, niveles y clasificaciones se capturan según el catálogo autorizado.</p><form method="POST" action="{{ route('admin.cuentas.store') }}">@csrf @include('catalogo.form')<div class="actions" style="margin-top:20px"><button type="submit">Guardar</button><a class="button secondary" href="{{ route('catalogo.index') }}">Cancelar</a></div></form>@endsection
