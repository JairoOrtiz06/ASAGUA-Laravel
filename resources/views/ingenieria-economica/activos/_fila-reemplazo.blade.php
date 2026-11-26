<fieldset class="section panel-link" data-fila>
    <legend>{{ $tipoFila === 'reposiciones' ? 'Reposición' : 'Edad candidata' }}</legend>
    <div class="form-grid">
        @foreach ($columnas as $campo => $etiqueta)
            @include('ingenieria-economica._campo', [
                'campo'=>$rutaFila.'.'.$indice.'.'.$campo,
                'nombreCampo'=>"filas[$opcion][$tipoFila][$indice][$campo]",
                'etiqueta'=>$etiqueta, 'valor'=>is_array($fila) ? ($fila[$campo] ?? null) : null, 'obligatorio'=>true,
                'tipo'=>in_array($campo,['anio','vida'],true) ? 'number' : 'text', 'maximoNumerico'=>100,
                'maximo'=>$campo === 'nota' ? 140 : null, 'modoEntrada'=>$campo === 'nota' ? null : 'decimal',
            ])
        @endforeach
    </div>
    <button class="button secondary" type="button" data-quitar>Eliminar fila</button>
</fieldset>
