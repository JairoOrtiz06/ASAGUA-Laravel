<div class="form-grid">
    <label>Código de cuenta<input name="codigo_cuenta" value="{{ old('codigo_cuenta', $cuenta->codigo_cuenta ?? '') }}" required></label>
    <label>Nombre de cuenta<input name="nombre_cuenta" value="{{ old('nombre_cuenta', $cuenta->nombre_cuenta ?? '') }}" required></label>
    <label>Tipo de cuenta<input name="tipo_cuenta" value="{{ old('tipo_cuenta', $cuenta->tipo_cuenta ?? '') }}" required></label>
    <label>Nivel<input type="number" name="nivel" value="{{ old('nivel', $cuenta->nivel ?? '') }}" required></label>
    <label class="field-full">Cuenta padre<select name="id_cuenta_padre"><option value="">Sin cuenta padre</option>@foreach($padres as $padre)<option value="{{ $padre->id_cuenta }}" @selected((string) old('id_cuenta_padre', $cuenta->id_cuenta_padre ?? '') === (string) $padre->id_cuenta)>{{ $padre->codigo_cuenta }} — {{ $padre->nombre_cuenta }}</option>@endforeach</select></label>
    <label class="field-full"><input type="hidden" name="estado" value="0"><input type="checkbox" name="estado" value="1" @checked(old('estado', $cuenta->estado ?? true))> Cuenta activa</label>
</div>
