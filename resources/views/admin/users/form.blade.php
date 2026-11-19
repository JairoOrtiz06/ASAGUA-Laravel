<div class="form-grid">
    <label>Nombre<input name="nombre" value="{{ old('nombre', $user->nombre ?? '') }}" required></label>
    <label>Apellido<input name="apellido" value="{{ old('apellido', $user->apellido ?? '') }}" required></label>
    <label>Email<input type="email" name="email" value="{{ old('email', $user->email ?? '') }}" required></label>
    <label>Usuario<input name="usuario" value="{{ old('usuario', $user->usuario ?? '') }}" required></label>
    <label>Contraseña<input type="password" name="password" {{ isset($user) ? '' : 'required' }}>{{ isset($user) ? 'Dejar vacío para conservarla.' : '' }}</label>
    <label>Rol<select name="rol" required>@foreach(['Admin', 'Contador', 'Auditor'] as $role)<option value="{{ $role }}" @selected(old('rol', $user->rol ?? 'Contador') === $role)>{{ $role }}</option>@endforeach</select></label>
</div>
