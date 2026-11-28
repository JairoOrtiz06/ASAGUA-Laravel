@use('App\Services\IngenieriaEconomica\ContextoEvaluacion')

@foreach (ContextoEvaluacion::actual()?->consulta()['contexto'] ?? [] as $clave => $valor)
    @if (is_array($valor))
        @foreach ($valor as $idContextual)
            <input type="hidden" name="contexto[{{ $clave }}][]" value="{{ $idContextual }}">
        @endforeach
    @elseif ($valor !== null)
        <input type="hidden" name="contexto[{{ $clave }}]" value="{{ $valor }}">
    @endif
@endforeach
