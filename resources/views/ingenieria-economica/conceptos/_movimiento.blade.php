@php
    use App\Models\IngenieriaEconomica\Concepto;
    $grupoMovimiento = $grupoMovimiento ?? 'todos';
@endphp

@if ($grupoMovimiento !== 'clasificacion')
@include('ingenieria-economica._campo', ['campo' => 'nombre', 'etiqueta' => 'Nombre', 'valor' => $datos['nombre'] ?? null, 'obligatorio' => true, 'maximo' => 150])
@include('ingenieria-economica._campo', ['campo' => 'tipo_concepto', 'etiqueta' => 'Tipo de concepto', 'tipo' => 'select', 'opciones' => Concepto::TIPOS, 'valor' => $datos['tipo_concepto'] ?? null, 'obligatorio' => true])
@include('ingenieria-economica._campo', ['campo' => 'monto', 'etiqueta' => 'Monto', 'valor' => $datos['monto'] ?? null, 'obligatorio' => true, 'ayuda' => 'Escribe un importe no negativo.'])
@include('ingenieria-economica._campo', ['campo' => 'moneda', 'etiqueta' => 'Moneda', 'valor' => $datos['moneda'] ?? 'USD', 'obligatorio' => true, 'maximo' => 3])
@include('ingenieria-economica._campo', ['campo' => 'direccion_otro', 'etiqueta' => 'Dirección para el tipo Otro', 'tipo' => 'select', 'opciones' => Concepto::DIRECCIONES, 'valor' => $datos['direccion_otro'] ?? null])
@endif

@if ($grupoMovimiento !== 'basico')
@include('ingenieria-economica._campo', ['campo' => 'clasificacion_decision', 'etiqueta' => 'Clasificación para la decisión', 'tipo' => 'select', 'opciones' => $clasificaciones, 'valor' => $datos['clasificacion_decision'] ?? 'pendiente_clasificacion', 'obligatorio' => true, 'ayuda' => 'Diferencial: cambia entre alternativas. Común: se comparte y se registra una vez. Hundido: desembolso histórico que no vuelve a considerarse como inversión futura.'])
@include('ingenieria-economica._campo', ['campo' => 'naturaleza_economica', 'etiqueta' => 'Naturaleza económica', 'tipo' => 'select', 'opciones' => Concepto::NATURALEZAS, 'valor' => $datos['naturaleza_economica'] ?? null, 'obligatorio' => true, 'ayuda' => 'Desembolso real: salida de caja. Costo de oportunidad: valor de lo que se sacrifica al elegir; se muestra por separado de caja.'])
@include('ingenieria-economica._campo', ['campo' => 'estado_dato', 'etiqueta' => 'Estado del dato', 'tipo' => 'select', 'opciones' => Concepto::ESTADOS, 'valor' => $datos['estado_dato'] ?? 'provisional', 'obligatorio' => true, 'ayuda' => 'Confirmado: información comprobada. Estimado: importe aproximado. Provisional: dato pendiente de verificar.'])
@endif
