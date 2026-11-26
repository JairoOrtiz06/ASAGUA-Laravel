@php
    $columnas = \App\Http\Requests\IngenieriaEconomica\ReemplazoActivoRequest::COLUMNAS[$tipoFila];
    $rutaFila = 'filas.'.$opcion.'.'.$tipoFila;
    $filas = old($rutaFila, []);
@endphp
<details class="field-full section" data-filas @if ($filas) open @endif>
    <summary>{{ $tipoFila === 'reposiciones' ? 'Reposiciones durante el horizonte' : 'Edades para estimar la vida económica' }}</summary>
    <p class="muted">{{ $tipoFila === 'reposiciones'
        ? 'La reposición ocurre al cierre del año. Los costos del nuevo activo aplican desde el año siguiente.'
        : 'Cada fila indica la edad de retiro. Se mantienen constantes la operación y el mantenimiento anual definidos arriba.' }}</p>
    <div data-lista-filas>
        @foreach (is_array($filas) ? $filas : [] as $indice => $fila)
            @include('ingenieria-economica.activos._fila-reemplazo')
        @endforeach
    </div>
    <template>
        @include('ingenieria-economica.activos._fila-reemplazo', ['indice'=>'__fila__','fila'=>[]])
    </template>
    <button class="button secondary" type="button" data-agregar>Agregar fila</button>
    <noscript><p>Activa JavaScript para agregar o eliminar filas.</p></noscript>
</details>
