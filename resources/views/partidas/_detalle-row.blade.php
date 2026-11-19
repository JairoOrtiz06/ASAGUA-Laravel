<tr>
    <td>
        <select class="cuenta" name="detalles[{{ $index }}][id_cuenta]" required>
            <option value="">Seleccione una cuenta</option>
            @foreach($cuentas as $cuenta)
                <option value="{{ $cuenta->id_cuenta }}" data-nombre="{{ $cuenta->nombre_cuenta }}" @selected((string) ($detalle['id_cuenta'] ?? '') === (string) $cuenta->id_cuenta)>
                    {{ $cuenta->codigo_cuenta }} — {{ $cuenta->nombre_cuenta }}
                </option>
            @endforeach
        </select>
    </td>
    <td><input class="descripcion" type="text" name="detalles[{{ $index }}][descripcion]" value="{{ $detalle['descripcion'] ?? '' }}" maxlength="255"></td>
    <td><input type="number" name="detalles[{{ $index }}][debe]" value="{{ $detalle['debe'] ?? '' }}" min="0" step="0.01" placeholder="0.00" required></td>
    <td><input type="number" name="detalles[{{ $index }}][haber]" value="{{ $detalle['haber'] ?? '' }}" min="0" step="0.01" placeholder="0.00" required></td>
    <td><button type="button" class="button warning quitar-detalle">Quitar</button></td>
</tr>
