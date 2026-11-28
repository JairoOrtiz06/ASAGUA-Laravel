@use('App\Services\IngenieriaEconomica\ContextoEvaluacion')

@if ($respaldo->isEmpty())
    <p class="muted">Falta vincular evidencia central para verificar este respaldo.</p>
@else
    <ul>
        @foreach ($respaldo as $asociacion)
            @php($evidenciaCentral = $asociacion['evidencia'])
            <li><a href="{{ ContextoEvaluacion::enlace('evidencias.show',[$proyecto,$evidenciaCentral]) }}">{{ $evidenciaCentral->titulo }}</a>
                · {{ $evidenciaCentral::ESTADOS[$evidenciaCentral->estado] }}</li>
        @endforeach
    </ul>
@endif
