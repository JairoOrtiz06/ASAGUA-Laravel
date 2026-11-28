<?php

declare(strict_types=1);

namespace App\Services\IngenieriaEconomica;

use App\Models\IngenieriaEconomica\Activo;
use App\Models\IngenieriaEconomica\Proyecto;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use DomainException;

class EvaluacionFiscalService
{
    public const PENDIENTE = 'Evaluación después de impuestos pendiente de información tributaria';

    public const FORMULA = 'Base = ingresos gravados − gastos deducibles − depreciación fiscal − amortización fiscal. '
        .'Impuesto = máximo(base, 0) × tasa ordinaria + máximo(ganancia por disposición, 0) × tasa de disposición. '
        .'Flujo después = flujo antes − impuesto. Los impuestos ya incluidos se concilian una sola vez.';

    public function __construct(
        private IndicadoresFinancierosService $indicadores,
        private DepreciacionActivoService $depreciacion,
        private ArchivoDocumentalService $archivos,
        private ReferenciaDocumentalService $referencias,
    ) {}

    public function aplicar(Proyecto $proyecto, array $r, array $config): array
    {
        $e = $r['escenario'];
        $documento = $proyecto->evidenciasDocumentales()->with('vinculos')->find($config['evidencia'] ?? null);
        if (($config['estado'] ?? null) !== 'confirmado' || ! $documento || $documento->estado !== 'validado' || $documento->calidad !== 'documentado'
            || (! $this->archivos->evidenciaDisponible($documento->archivo, $documento->sha256)
                && ! $documento->vinculos->contains(fn ($v) => $v->tipo === 'documento' && $v->conciliacion === 'conciliado'
                    && $this->referencias->resolver($proyecto, 'documento', $v->destino_id) !== null))) {
            $this->pendiente('Selecciona evidencia válida del proyecto y confirma los supuestos fiscales.');
        }
        if ($e['periodicidad'] !== 'anual' || $config['vigente_desde'] > $e['base'] || $config['vigente_hasta'] < $e['fin']) {
            $this->pendiente('Esta metodología requiere cierres anuales y una misma vigencia que cubra todo el horizonte.');
        }
        $tasa = BigDecimal::of($config['tasa']);
        $tasaVenta = isset($config['tasa_venta']) ? BigDecimal::of($config['tasa_venta']) : null;
        $noSujeto = $config['tratamiento'] === 'no_aplicable_documentado';
        if ($noSujeto && (! $tasa->isZero() || ($tasaVenta && ! $tasaVenta->isZero()))) {
            $this->pendiente('Indica cero expresamente cuando el respaldo cubra la no aplicación del impuesto al proyecto.');
        }
        $fiscal = [
            'parametros' => array_diff_key($config, ['alternativas' => true]),
            'fuente' => $documento->only(['id', 'titulo', 'procedencia', 'estado', 'calidad', 'sha256'])
                + ['fecha' => $documento->fecha_documento->toDateString()],
            'alternativas' => [],
        ];
        $activos = $proyecto->activos()->get()->keyBy('id');
        foreach ($r['alternativas'] as $id => $alternativa) {
            if (! collect($alternativa['periodos'])->flatMap(fn ($p) => $p['movimientos'])->contains(fn ($m) => $m['motivo'] === null)) {
                $this->pendiente('La alternativa '.$alternativa['nombre'].' requiere movimientos de caja incluidos para evaluar el efecto tributario.');
            }

            $entrada = $config['alternativas'][$id] ?? [];
            if (! $noSujeto && blank($entrada['base'] ?? null)) {
                $this->pendiente('Registra la base fiscal de cada período de '.$alternativa['nombre'].', incluso si es cero.');
            }
            $bases = $this->filas($entrada['base'] ?? null, 2, $e['horizonte'], true);
            $deducciones = $this->filas($entrada['deducciones'] ?? null, 3, $e['horizonte']);
            $ventas = $this->filas($entrada['ventas'] ?? null, 2, $e['horizonte']);
            $pagos = $this->filas($entrada['pagos'] ?? null, 1, $e['horizonte']);
            $antes = $despues = $memoria = $activosVendidos = [];
            foreach ($alternativa['periodos'] as $t => $periodo) {
                $movimientos = collect($periodo['movimientos'])->whereNull('motivo');
                $costo = BigDecimal::of($periodo['costos']);
                $beneficio = BigDecimal::of($periodo['beneficios']);
                $pagado = BigDecimal::zero();
                $pagosOrigen = [];
                foreach ($pagos[$t] ?? [] as [$origen]) {
                    $coincidentes = $movimientos->where('origen', $origen)->where('direccion', 'salida')
                        ->where('clasificacion', 'diferencial')->where('naturaleza', 'desembolso_real');
                    if (! preg_match('/^[ca][1-9][0-9]*$/D', $origen) || isset($pagosOrigen[$origen]) || $coincidentes->isEmpty()) {
                        $this->pendiente('Cada impuesto ya incluido debe identificar un origen de caja diferencial, sin repetirlo.');
                    }
                    $pagosOrigen[$origen] = $r['origenes'][$origen]['nombre'];
                    foreach ($coincidentes as $mov) {
                        $pagado = $pagado->plus($mov['monto']);
                    }
                }

                if (! $noSujeto && ! isset($bases[$t])) {
                    $this->pendiente('Falta la base del período '.$t.' para '.$alternativa['nombre'].'.');
                }
                [$ingreso, $gasto] = array_map($this->monto(...), $bases[$t][0] ?? ['0', '0']);
                $dep = $amort = $ganancias = $ventasBrutas = BigDecimal::zero();
                $detalleActivos = $detalleVentas = [];
                foreach ($deducciones[$t] ?? [] as [$aid, $modo, $importe]) {
                    $activo = $this->activo($activos->get($aid), $id, $r['moneda']);
                    if ($t === 0 || isset($detalleActivos[$activo->id]) || isset($activosVendidos[$activo->id]) || ! $activo->fecha_disponible_uso
                        || $activo->fecha_disponible_uso->toDateString() > $periodo['hasta']) {
                        $this->pendiente('La deducción requiere un activo disponible en el período y una sola fila por activo.');
                    }
                    $tangible = $activo->clasificacion_contable === 'activo_tangible';
                    $nota = $tangible ? $activo->depreciacion_fiscal : $activo->amortizacion_fiscal;
                    if (blank($nota)) {
                        $this->pendiente('Documenta el tratamiento fiscal de '.$activo->nombre.' en Activos.');
                    }
                    $valor = $this->monto($importe);
                    $cuotaContable = null;
                    if ($modo !== 'manual') {
                        if (! in_array($modo, ['lineal', 'doble_saldo'], true) || (! $tangible && $modo !== 'lineal')) {
                            $this->pendiente('El método indicado no está disponible para este activo.');
                        }
                        $tabla = $this->depreciacion->calcular($activo, []);
                        $fila = collect($tabla['metodos'][$modo]['filas'] ?? [])->first(fn ($f) =>
                            $f['desde'] === CarbonImmutable::parse($periodo['desde'])->subDay()->toDateString()
                            && $f['hasta'] === CarbonImmutable::parse($periodo['hasta'])->subDay()->toDateString());
                        if (! $fila || ! $valor->isEqualTo(DecimalFinanciero::dinero(BigDecimal::of($fila['importe'])))) {
                            $this->pendiente('La cuota fiscal debe coincidir con la cuota contable a centavos y con el mismo ciclo anual. Para una base distinta, registra la cuota fiscal manual documentada.');
                        }
                        $cuotaContable = $fila['importe'];
                    }
                    if ($tangible) {
                        $dep = $dep->plus($valor);
                    } else {
                        $amort = $amort->plus($valor);
                    }
                    $detalleActivos[$activo->id] = ['nombre' => $activo->nombre, 'tipo' => $tangible ? 'Depreciación' : 'Amortización',
                        'metodo' => $modo, 'nota' => $nota, 'cuota_fiscal' => (string) $valor, 'cuota_contable' => $cuotaContable];
                }

                $disposiciones = $movimientos->filter(fn ($m) => $m['direccion'] === 'entrada'
                    && (in_array($r['origenes'][$m['origen']]['activo_operacion'] ?? null, ['venta', 'rescate'], true)
                        || $m['tipo'] === 'valor_rescate'));
                foreach ($ventas[$t] ?? [] as [$aid, $baseFiscal]) {
                    $activo = $this->activo($activos->get($aid), $id, $r['moneda']);
                    $procede = $disposiciones->filter(fn ($m) => (int) ($r['origenes'][$m['origen']]['activo_id'] ?? 0) === (int) $aid);
                    if ($tasaVenta === null || isset($activosVendidos[$activo->id]) || $procede->count() !== 1 || blank($activo->efecto_tributario)) {
                        $this->pendiente('La disposición requiere tasa explícita, una venta o rescate vinculado y el tratamiento documentado en Activos.');
                    }
                    $precio = BigDecimal::of($procede->first()['monto']);
                    $baseFiscal = $this->monto($baseFiscal);
                    $ganancia = $precio->minus($baseFiscal);
                    $ganancias = $ganancias->plus(BigDecimal::max($ganancia, BigDecimal::zero()));
                    $ventasBrutas = $ventasBrutas->plus($precio);
                    $activosVendidos[$activo->id] = true;
                    $detalleVentas[$activo->id] = ['nombre' => $activo->nombre, 'precio' => (string) $precio,
                        'base_fiscal' => (string) $baseFiscal, 'ganancia' => (string) $ganancia, 'nota' => $activo->efecto_tributario];
                }
                if (! $noSujeto && count($detalleVentas) !== $disposiciones->count()) {
                    $this->pendiente('Identifica y documenta cada venta o rescate. Los rescates sin activo vinculado no admiten cálculo fiscal.');
                }
                if ($noSujeto && (isset($bases[$t]) || $detalleActivos || $detalleVentas || $pagosOrigen)) {
                    $this->pendiente('La no aplicación documentada se registra sin bases, deducciones, disposiciones ni pagos de este impuesto.');
                }
                if ($ingreso->isGreaterThan($beneficio->minus($ventasBrutas)) || $gasto->isGreaterThan($costo->minus($pagado))) {
                    $this->pendiente('La base ordinaria excede la caja atribuible del período. Excluye disposiciones e impuestos ya incluidos.');
                }

                $base = $ingreso->minus($gasto)->minus($dep)->minus($amort);
                $ordinario = BigDecimal::max($base, BigDecimal::zero())->multipliedBy($tasa);
                $disposicion = $tasaVenta === null ? BigDecimal::zero() : $ganancias->multipliedBy($tasaVenta);
                $total = $ordinario->plus($disposicion);
                if ($pagado->isGreaterThan($total)) {
                    $this->pendiente('El pago incluido excede el impuesto calculado. Las devoluciones y créditos requieren otro tratamiento.');
                }
                $antes[$t] = ['beneficios' => $beneficio, 'costos' => $costo->minus($pagado)];
                $despues[$t] = ['beneficios' => $beneficio, 'costos' => $costo->minus($pagado)->plus($total)];
                $memoria[$t] = [
                    'antes' => (string) $beneficio->minus($antes[$t]['costos']), 'despues' => (string) $beneficio->minus($despues[$t]['costos']),
                    'ingreso' => (string) $ingreso, 'gasto' => (string) $gasto, 'dep' => (string) $dep, 'amort' => (string) $amort,
                    'base' => (string) $base, 'ganancia' => (string) $ganancias, 'ordinario' => (string) $ordinario,
                    'disposicion' => (string) $disposicion, 'total' => (string) $total, 'pagado' => (string) $pagado,
                    'adicional' => (string) $total->minus($pagado), 'pagos_origen' => $pagosOrigen,
                    'activos' => $detalleActivos, 'ventas' => $detalleVentas,
                ];
            }
            $indicadoresAntes = $this->indicadores->calcular($antes, BigDecimal::of($e['tasa']), BigDecimal::of($e['anual']), $e['horizonte']);
            $indicadoresDespues = $this->indicadores->calcular($despues, BigDecimal::of($e['tasa']), BigDecimal::of($e['anual']), $e['horizonte']);
            foreach ($indicadoresDespues['periodos'] as $t => $fila) {
                $alternativa['periodos'][$t] = array_replace($alternativa['periodos'][$t], $fila);
            }
            unset($indicadoresAntes['periodos'], $indicadoresDespues['periodos']);
            $alternativa['indicadores'] = $indicadoresDespues;
            $r['alternativas'][$id] = $alternativa;
            $fiscal['alternativas'][$id] = ['periodos' => $memoria, 'antes' => $indicadoresAntes];
        }

        $idsActivos = collect($fiscal['alternativas'])->flatMap(fn ($a) => collect($a['periodos'])
            ->flatMap(fn ($periodo) => [...array_keys($periodo['activos']), ...array_keys($periodo['ventas'])]))->unique();
        $fiscal['activos_verificados'] = $idsActivos->mapWithKeys(fn ($id) => [$id => $activos->get($id)->getAttributes()])->all();

        $r['fiscal'] = $fiscal;

        return $r;
    }

    private function activo(?Activo $activo, int $alternativa, string $moneda): Activo
    {
        if (! $activo || ($activo->alternativa_id !== null && (int) $activo->alternativa_id !== $alternativa)
            || $activo->moneda !== $moneda || ! in_array($activo->clasificacion_contable, ['activo_tangible', 'activo_intangible'], true)) {
            $this->pendiente('Selecciona un activo reconocido de la alternativa o compartido por el proyecto, en la misma moneda.');
        }

        return $activo;
    }

    private function filas(?string $texto, int $columnas, int $horizonte, bool $unica = false): array
    {
        $filas = [];
        foreach (preg_split('/\R/', trim($texto ?? '')) as $linea) {
            if (blank($linea)) {
                continue;
            }
            $partes = array_map('trim', explode(';', $linea));
            $t = array_shift($partes);
            if (! preg_match('/^(0|[1-9][0-9]{0,2})$/D', $t) || (int) $t > $horizonte
                || count($partes) !== $columnas || ($unica && isset($filas[$t]))) {
                $this->pendiente('Verifica el período, la cantidad de columnas y las filas repetidas en la configuración fiscal.');
            }
            $filas[$t][] = $partes;
        }

        return $filas;
    }

    private function monto(string $monto): BigDecimal
    {
        if (! preg_match('/^[0-9]{1,14}(?:\.[0-9]{1,6})?$/D', $monto)) {
            $this->pendiente('Usa importes no negativos con punto decimal y hasta seis decimales.');
        }

        return BigDecimal::of($monto);
    }

    private function pendiente(string $detalle): never
    {
        throw new DomainException(self::PENDIENTE.'. '.$detalle);
    }
}
