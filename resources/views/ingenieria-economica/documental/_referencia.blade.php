@if ($ref)
    @foreach (['partida'=>'Partida','fecha'=>'Fecha','concepto'=>'Concepto','cuenta'=>'Cuenta','debe'=>'Debe','haber'=>'Haber',
        'documento'=>'Documento de respaldo','alternativa'=>'Alternativa','importe'=>'Importe','periodicidad'=>'Periodicidad','estado'=>'Estado'] as $clave=>$label)
        @if (isset($ref[$clave]))<div>{{ $label }} · {{ $ref[$clave] }}</div>@endif
    @endforeach
@elseif ($contable)
    <p>Sin referencia contable vinculada actualmente verificable.</p>
@endif
