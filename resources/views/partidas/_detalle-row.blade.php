@php
    $modoDocumento = $detalle['modo_documento'] ?? (($detalle['id_documento'] ?? null) ? 'existente' : 'ninguno');
@endphp
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
    <td>
        <label>Documento
            <select class="modo-documento" name="detalles[{{ $index }}][modo_documento]">
                <option value="ninguno" @selected($modoDocumento === 'ninguno')>Sin documento</option>
                <option value="existente" @selected($modoDocumento === 'existente')>Usar existente</option>
                <option value="nuevo" @selected($modoDocumento === 'nuevo')>Registrar nuevo</option>
            </select>
        </label>
        <div class="documento-existente" @if($modoDocumento !== 'existente') hidden @endif>
            <select name="detalles[{{ $index }}][id_documento]">
                <option value="">Seleccione un documento</option>
                @foreach($documentos as $documento)
                    <option value="{{ $documento->id_documento }}" data-nombre="{{ $documento->nombre_documento }}" @selected((string) ($detalle['id_documento'] ?? '') === (string) $documento->id_documento)>
                        {{ $documento->nombre_documento }} — {{ $documento->tipo_documento }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="documento-nuevo" @if($modoDocumento !== 'nuevo') hidden @endif>
            <input type="text" name="detalles[{{ $index }}][documento_nuevo][nombre_documento]" value="{{ data_get($detalle, 'documento_nuevo.nombre_documento', '') }}" maxlength="255" placeholder="Nombre del documento">
            <input type="text" name="detalles[{{ $index }}][documento_nuevo][tipo_documento]" value="{{ data_get($detalle, 'documento_nuevo.tipo_documento', '') }}" maxlength="50" placeholder="Tipo">
            <input type="datetime-local" name="detalles[{{ $index }}][documento_nuevo][fecha_creacion]" value="{{ data_get($detalle, 'documento_nuevo.fecha_creacion', '') }}">
            <textarea name="detalles[{{ $index }}][documento_nuevo][descripcion]" rows="2" placeholder="Descripción">{{ data_get($detalle, 'documento_nuevo.descripcion', '') }}</textarea>
            <input type="file" name="detalles[{{ $index }}][documento_nuevo][archivo]">
        </div>
        <small class="documento-estado muted"></small>
        <button type="button" class="button warning quitar-detalle">Quitar</button>
    </td>
</tr>
