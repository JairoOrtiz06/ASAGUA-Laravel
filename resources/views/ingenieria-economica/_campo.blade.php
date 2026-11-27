@php
    $valor = ($omitirValorAnterior ?? false) ? ($valor ?? null) : old($campo, $valor ?? null);
    $valor = is_scalar($valor) ? $valor : null;
    $tipo = $tipo ?? 'text';
    $obligatorio = $obligatorio ?? false;
    $idCampo = $idCampo ?? $campo;
    $mensajeCampo = $errors->first($campo);
    $descritoPor = implode(' ', array_filter([isset($ayuda) ? $idCampo.'-ayuda' : null, $mensajeCampo ? $idCampo.'-error' : null]));
@endphp
<div @class(['field', 'field-full' => $tipo === 'textarea'])>
    <label for="{{ $idCampo }}">{{ $etiqueta }}{{ $obligatorio ? ' *' : '' }}</label>
    @if ($tipo === 'select')
        <select id="{{ $idCampo }}" name="{{ $nombreCampo ?? $campo }}" @required($obligatorio) @error($campo) aria-invalid="true" @enderror
            @if($descritoPor) aria-describedby="{{ $descritoPor }}" @endif>
            <option value="">{{ $textoVacio ?? 'Selecciona una opción' }}</option>
            @foreach ($opciones as $clave => $texto)
                <option value="{{ $clave }}" @selected($valor !== null && (string) $valor === (string) $clave)>{{ $texto }}</option>
            @endforeach
        </select>
    @elseif ($tipo === 'textarea')
        <textarea @required($obligatorio) id="{{ $idCampo }}" name="{{ $nombreCampo ?? $campo }}" rows="3" @if($descritoPor) aria-describedby="{{ $descritoPor }}" @endif maxlength="{{ $maximo ?? 5000 }}" @error($campo) aria-invalid="true" @enderror>{{ $valor }}</textarea>
    @else
        <input id="{{ $idCampo }}" name="{{ $nombreCampo ?? $campo }}" type="{{ $tipo }}" value="{{ $valor }}"
            @required($obligatorio) @error($campo) aria-invalid="true" @enderror
            @isset($modoEntrada) inputmode="{{ $modoEntrada }}" @endisset
            @isset($maximo) maxlength="{{ $maximo }}" @endisset
            @if ($tipo === 'number') min="{{ $minimo ?? 1 }}" max="{{ $maximoNumerico ?? 32767 }}" step="1" @endif
            @if($descritoPor) aria-describedby="{{ $descritoPor }}" @endif>
    @endif
    @isset($ayuda)
        <small id="{{ $idCampo }}-ayuda" class="muted">{{ $ayuda }}</small>
    @endisset
    @error($campo)
        <small id="{{ $idCampo }}-error" class="field-error">{{ $message }}</small>
    @enderror
</div>
