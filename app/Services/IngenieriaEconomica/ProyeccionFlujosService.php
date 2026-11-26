<?php

declare(strict_types=1);

namespace App\Services\IngenieriaEconomica;

use App\Models\IngenieriaEconomica\AjusteFlujo;
use App\Models\IngenieriaEconomica\Alternativa;
use App\Models\IngenieriaEconomica\Concepto;
use App\Models\IngenieriaEconomica\Escenario;
use App\Models\IngenieriaEconomica\FuenteConcepto;
use App\Models\IngenieriaEconomica\Proyecto;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use DomainException;

class ProyeccionFlujosService
{
    public const MAX_ANIOS = 100;

    public const MAX_MESES = self::MAX_ANIOS * 12;

    public const AGRUPACIONES = ['escenario' => 'Periodicidad del escenario', 'mensual' => 'Mensual', 'trimestral' => 'Trimestral', 'anual' => 'Anual'];

    public const MONETARIOS = [
        'ingresos', 'ahorros', 'inversiones', 'costos', 'rescates', 'entradas', 'salidas',
        'neto', 'acumulado', 'oportunidad', 'ahorro_estimado', 'neto_economico', 'inicial', 'costos_recurrentes', 'comunes_netos',
    ];

    private const MESES = ['unico' => 1, 'mensual' => 1, 'trimestral' => 3, 'semestral' => 6, 'anual' => 12];

    public function insumos(Proyecto $proyecto, Escenario $escenario, array $ids): array
    {
        $alternativas = $proyecto->alternativas()->whereKey($ids)->orderBy('id')->get();
        if ((int) $escenario->proyecto_id !== (int) $proyecto->id || $ids === [] || $alternativas->count() !== count(array_unique($ids))) {
            throw new DomainException('Selecciona alternativas y un escenario del mismo proyecto.');
        }

        return [
            'alternativas' => $alternativas->keyBy('id')->map->getAttributes()->all(),
            'conceptos' => $proyecto->conceptos()->where(fn ($q) => $q->whereNull('alternativa_id')->orWhereIn('alternativa_id', $ids))
                ->with(['fuentes' => fn ($q) => $q->orderBy('id')])->orderBy('id')->get()
                ->map(fn (Concepto $c) => $c->getAttributes() + ['fuentes' => $c->fuentes->map->getAttributes()->all()])->all(),
            'ajustes' => $escenario->ajustesFlujo()->whereIn('alternativa_id', $ids)->orderBy('id')->get()->map->getAttributes()->all(),
        ];
    }

    public function generar(Proyecto $proyecto, Escenario $escenario, array $ids, string $moneda, string $agrupacion, int $desde = 0, ?int $hasta = null, ?array $insumos = null): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $insumos ??= $this->insumos($proyecto, $escenario, $ids);
        $alternativas = collect($insumos['alternativas'])->map(fn ($a) => (new Alternativa)->setRawAttributes($a));
        if ((int) $escenario->proyecto_id !== (int) $proyecto->id || $ids === [] || array_diff($ids, $alternativas->keys()->all())) {
            throw new DomainException('Selecciona alternativas y un escenario del mismo proyecto.');
        }

        $meses = $escenario->horizonte_anios * 12;
        if ($meses < 12 || $meses > self::MAX_MESES) {
            throw new DomainException('Corrige el horizonte del escenario antes de proyectar. El máximo es '.self::MAX_ANIOS.' años.');
        }

        $base = ($escenario->fecha_base ?? $proyecto->fecha_base)->toImmutable();
        $fin = $base->addMonthsNoOverflow($meses);
        $paso = self::MESES[$agrupacion === 'escenario' ? $escenario->periodicidad : $agrupacion];
        $pasoEscenario = self::MESES[$escenario->periodicidad];
        $conceptosBase = collect($insumos['conceptos'])->map(function (array $datos): Concepto {
            $fuentes = $datos['fuentes'];
            unset($datos['fuentes']);

            return (new Concepto)->setRawAttributes($datos)
                ->setRelation('fuentes', collect($fuentes)->map(fn ($f) => (new FuenteConcepto)->setRawAttributes($f)));
        });
        $comunes = $conceptosBase->whereNull('alternativa_id');
        $propios = $conceptosBase->whereNotNull('alternativa_id')->groupBy('alternativa_id');
        $ajustes = collect($insumos['ajustes'])->map(fn ($a) => (new AjusteFlujo)->setRawAttributes($a))->groupBy('alternativa_id');
        $resultado = [];

        foreach ($ids as $id) {
            $alternativa = $alternativas->get($id);
            $conceptos = $propios->get($id, collect());
            $periodos = $this->periodos($base, $meses, $paso);
            $avisos = [];
            $excluidos = [];

            if ($conceptos->isEmpty()) {
                $avisos[] = 'Faltan conceptos propios registrados para esta alternativa.';
            }
            if ($alternativa->viabilidad_tecnica !== 'viable') {
                $avisos[] = 'La comparabilidad técnica requiere revisión. Viabilidad: '.strtolower($alternativa::VIABILIDADES[$alternativa->viabilidad_tecnica]).'.';
            }

            foreach ($conceptos->concat($comunes) as $concepto) {
                $this->advertir($concepto, $moneda, $avisos);
                if ($concepto->moneda !== $moneda) {
                    continue;
                }

                $programacion = $this->programar($concepto, $base, $meses);
                $hayMovimientos = false;
                foreach ($programacion as [$fecha, $monto]) {
                    $hayMovimientos = true;
                    $movimiento = $this->movimiento($concepto, $fecha, $monto);
                    $this->incorporar($movimiento, $periodos, $excluidos, $base, $fin, $paso, $pasoEscenario, $proyecto->tipo_decision);
                }
                if (! $hayMovimientos) {
                    $avisos[] = "«{$concepto->nombre}» no tiene pagos programados para este horizonte.";
                }
            }

            foreach ($ajustes->get($id, collect()) as $ajuste) {
                $this->advertir($ajuste, $moneda, $avisos);
                if ($ajuste->moneda === $moneda) {
                    $movimiento = $this->movimiento($ajuste, $ajuste->fecha_flujo, BigDecimal::of($ajuste->monto));
                    $this->incorporar($movimiento, $periodos, $excluidos, $base, $fin, $paso, $pasoEscenario, $proyecto->tipo_decision);
                }
            }

            $totales = $this->cerrarPeriodos($periodos);
            usort($excluidos, fn (array $a, array $b) => $a['fecha'] <=> $b['fecha']);
            $visibles = array_filter($periodos, fn (array $periodo) => $periodo['numero'] >= $desde
                && ($hasta === null || $periodo['numero'] <= $hasta));
            $grafica = $this->grafica($visibles);
            $resultado[$id] = compact('alternativa', 'periodos', 'visibles', 'grafica', 'totales', 'avisos', 'excluidos');
        }

        return compact('base', 'fin', 'moneda', 'resultado');
    }

    private function periodos(CarbonImmutable $base, int $meses, int $paso): array
    {
        $periodos = [['numero' => 0, 'desde' => $base, 'hasta' => $base, 'movimientos' => []] + $this->ceros()];
        for ($mes = 0; $mes < $meses; $mes += $paso) {
            $limite = $base->addMonthsNoOverflow(min($mes + $paso, $meses));
            $periodos[] = [
                'numero' => intdiv($mes, $paso) + 1,
                'desde' => $base->addMonthsNoOverflow($mes)->addDay(),
                'hasta' => $limite,
                'movimientos' => [],
            ] + $this->ceros();
        }

        return $periodos;
    }

    public function previsualizarConcepto(Proyecto $proyecto, Escenario $escenario, Concepto $concepto): array
    {
        $base = ($escenario->fecha_base ?? $proyecto->fecha_base)->toImmutable();
        $meses = $escenario->horizonte_anios * 12;
        if ($meses < 12 || $meses > self::MAX_MESES) {
            throw new DomainException('Corrige el horizonte del escenario. El calendario admite hasta '.self::MAX_ANIOS.' años.');
        }

        $fin = $base->addMonthsNoOverflow($meses);
        $paso = self::MESES[$escenario->periodicidad];
        $periodos = $this->periodos($base, $meses, $paso);
        $excluidos = $programados = $incluidos = [];
        $total = BigDecimal::zero();
        foreach ($this->programar($concepto, $base, $meses) as [$fecha, $monto]) {
            $programados[] = $fecha;
            $this->incorporar($this->movimiento($concepto, $fecha, $monto), $periodos, $excluidos, $base, $fin, $paso, $paso, $proyecto->tipo_decision);
        }

        foreach ($periodos as $periodo) {
            foreach ($periodo['movimientos'] as $movimiento) {
                $incluidos[] = DecimalFinanciero::dinero($movimiento['monto']);
                $total = $total->plus($movimiento['monto']);
            }
        }
        $fuera = count(array_filter($excluidos, fn ($m) => $m['fecha']->lt($base) || $m['fecha']->gt($fin)));
        if ($concepto->periodicidad !== 'unico' && ($concepto->periodo_fin !== null || $concepto->numero_ocurrencias !== null)) {
            foreach ($this->programar($concepto, $base, $meses, sinCorte: true) as [$fecha]) {
                $fuera += $fecha->gt($fin) || ($fecha->eq($fin) && $concepto->momento_pago === 'inicio') ? 1 : 0;
            }
        }

        return [
            'escenario' => $escenario->nombre, 'moneda' => $concepto->moneda,
            'base' => $base->format('d/m/Y'), 'cierre' => $fin->format('d/m/Y'),
            'primero' => isset($programados[0]) ? $programados[0]->format('d/m/Y') : null,
            'ultimo' => $programados ? end($programados)->format('d/m/Y') : null,
            'movimientos' => count($incluidos),
            'programados' => count($programados), 'fuera' => $fuera,
            'importes' => $incluidos,
            'total' => DecimalFinanciero::dinero($total),
            'motivos' => array_values(array_unique(array_column($excluidos, 'motivo'))),
        ];
    }

    /** @return iterable<array{CarbonImmutable, BigDecimal}> */
    private function programar(Concepto $concepto, CarbonImmutable $base, int $meses, bool $sinCorte = false): iterable
    {
        $monto = BigDecimal::of($concepto->simulacion_monto ?? $concepto->monto);
        if ($concepto->periodicidad === 'unico' || $concepto->fecha_real !== null) {
            yield [$this->fechaUnica($concepto, $base, $meses), $monto];

            return;
        }

        $paso = self::MESES[$concepto->periodicidad];
        $crecimiento = BigDecimal::one()->plus($concepto->simulacion_crecimiento ?? $concepto->porcentaje_crecimiento ?? '0');
        $vencido = $concepto->momento_pago === 'fin';
        $limite = $sinCorte
            ? ($concepto->periodo_fin ?? PHP_INT_MAX)
            : min($concepto->periodo_fin ?? $meses, $vencido ? $meses : $meses - 1);
        $ocurrencias = $concepto->numero_ocurrencias ?? self::MAX_MESES;
        $inicio = $concepto->periodo_inicio ?? 0;
        // En una serie vencida, 1 identifica el primer mes y 0 conserva el inicio desde la base.
        $mesPago = $vencido ? max(0, $inicio - 1) + $paso : $inicio;

        for ($n = 0; $n < $ocurrencias && $mesPago <= $limite; $n++, $mesPago += $paso) {
            yield [$base->addMonthsNoOverflow($mesPago), $monto];
            $monto = DecimalFinanciero::redondearBase($monto->multipliedBy($crecimiento));
        }
    }

    public function fechaUnica(Concepto $concepto, CarbonImmutable $base, int $horizonte): CarbonImmutable
    {
        if ($fecha = $concepto->fecha_real ?? $concepto->fecha_programada) {
            return $fecha;
        }

        $mes = $concepto->periodo_inicio ?? ($concepto->tipo_concepto === 'valor_rescate' ? $horizonte : 0);
        if ($concepto->tipo_concepto === 'inversion_inicial' && $concepto->periodo_inicio === null) {
            return $base;
        }

        return $concepto->momento_pago === 'fin' && $mes !== $horizonte
            ? $base->addMonthsNoOverflow($mes + 1)->subDay()
            : $base->addMonthsNoOverflow($mes);
    }

    private function movimiento(Concepto|AjusteFlujo $origen, CarbonImmutable $fecha, BigDecimal $monto): array
    {
        $manual = $origen instanceof AjusteFlujo;

        return [
            'fecha' => $fecha,
            'nombre' => $origen->nombre,
            'concepto' => $manual ? null : $origen,
            'ajuste' => $manual ? $origen : null,
            'origen' => $manual ? 'Manual' : 'Generado',
            'estado' => $origen->estado_dato,
            'tipo' => $origen->tipo_concepto,
            'naturaleza' => $origen->naturaleza_economica,
            'clasificacion' => $origen->clasificacion_decision,
            'observado' => $manual ? $origen->observado : $origen->fecha_real !== null,
            'recurrente' => ! $manual && $origen->periodicidad !== 'unico',
            'direccion' => Concepto::direccion($origen->tipo_concepto, $origen->direccion_otro),
            'monto' => $monto,
        ];
    }

    private function incorporar(array $movimiento, array &$periodos, array &$excluidos, CarbonImmutable $base, CarbonImmutable $fin, int $paso, int $pasoEscenario, string $decision): void
    {
        $fecha = $movimiento['fecha'];
        $movimiento['historico'] = $fecha->lt($base);
        $motivo = match (true) {
            $movimiento['clasificacion'] === 'hundido' && $decision !== 'historica' => 'Costo hundido para esta decisión',
            $movimiento['historico'] => 'Anterior a la fecha base',
            $fecha->gt($fin) => 'Fuera del horizonte',
            $movimiento['clasificacion'] === 'pendiente_clasificacion' => 'Pendiente de clasificación',
            $movimiento['naturaleza'] === 'no_monetario' => 'Dato no monetario',
            default => null,
        };

        if ($motivo !== null) {
            $excluidos[] = $movimiento + ['motivo' => $motivo];

            return;
        }

        // El período 0 contiene la base. Los demás intervalos incluyen su fecha de cierre.
        $mes = ($fecha->year - $base->year) * 12 + $fecha->month - $base->month;
        if ($fecha->gt($base->addMonthsNoOverflow($mes))) {
            $mes++;
        }

        $periodo = $mes === 0 ? 0 : intdiv($mes - 1, $paso) + 1;
        $movimiento['periodo_escenario'] = $mes === 0 ? 0 : intdiv($mes - 1, $pasoEscenario) + 1;
        $periodos[$periodo]['movimientos'][] = $movimiento;
    }

    private function cerrarPeriodos(array &$periodos): array
    {
        $totales = $this->ceros();
        foreach ($periodos as &$periodo) {
            usort($periodo['movimientos'], fn (array $a, array $b) => $a['fecha'] <=> $b['fecha']);
            foreach ($periodo['movimientos'] as $movimiento) {
                $this->sumar($periodo, $movimiento);
            }

            $periodo['neto'] = $periodo['entradas']->minus($periodo['salidas']);
            $periodo['neto_economico'] = $periodo['neto']->plus($periodo['ahorro_estimado'])->minus($periodo['oportunidad']);
            foreach (self::MONETARIOS as $campo) {
                $totales[$campo] = $totales[$campo]->plus($periodo[$campo]);
            }
            $periodo['acumulado'] = $totales['neto'];
        }
        unset($periodo);
        $totales['acumulado'] = $totales['neto'];

        return $totales;
    }

    private function sumar(array &$periodo, array $movimiento): void
    {
        $monto = $movimiento['monto'];
        if ($movimiento['naturaleza'] === 'costo_oportunidad') {
            $periodo['oportunidad'] = $periodo['oportunidad']->plus($monto);

            return;
        }

        if ($movimiento['naturaleza'] === 'ahorro_estimado') {
            $periodo['ahorro_estimado'] = $periodo['ahorro_estimado']->plus($monto);

            return;
        }

        $entrada = $movimiento['direccion'] === 'entrada';
        $campo = match ($movimiento['tipo']) {
            'ahorro' => 'ahorros',
            'inversion_inicial', 'inversion_adicional' => 'inversiones',
            'valor_rescate' => 'rescates',
            default => $entrada ? 'ingresos' : 'costos',
        };
        $direccion = $entrada ? 'entradas' : 'salidas';
        $periodo[$campo] = $periodo[$campo]->plus($monto);
        $periodo[$direccion] = $periodo[$direccion]->plus($monto);

        if ($movimiento['tipo'] === 'inversion_inicial') {
            $periodo['inicial'] = $periodo['inicial']->plus($monto);
        }
        if ($movimiento['recurrente'] && $campo === 'costos') {
            $periodo['costos_recurrentes'] = $periodo['costos_recurrentes']->plus($monto);
        }
        if ($movimiento['clasificacion'] === 'comun') {
            $periodo['comunes_netos'] = $periodo['comunes_netos']->plus($entrada ? $monto : $monto->negated());
        }
    }

    private function grafica(array $periodos): array
    {
        $magnitudes = array_map(fn (array $periodo) => $periodo['neto']->abs(), $periodos);
        $maximo = BigDecimal::max(BigDecimal::one(), ...array_values($magnitudes));

        return array_map(fn (array $periodo) => [
            'periodo' => $periodo['numero'],
            'valor' => DecimalFinanciero::dinero($periodo['neto']),
            'negativo' => $periodo['neto']->isNegative(),
            'ancho' => (string) DecimalFinanciero::dividir($periodo['neto']->abs()->multipliedBy(50), $maximo)->toScale(4, RoundingMode::HalfUp),
        ], $periodos);
    }

    private function ceros(): array
    {
        return array_fill_keys(self::MONETARIOS, BigDecimal::zero());
    }

    private function advertir(Concepto|AjusteFlujo $item, string $moneda, array &$avisos): void
    {
        if ($item->moneda !== $moneda) {
            $avisos[] = "«{$item->nombre}» está en {$item->moneda} y quedó fuera del resumen en $moneda.";
        }
        if ($item->estado_dato !== 'confirmado') {
            $avisos[] = "«{$item->nombre}» tiene un importe estimado o provisional.";
        }
        if ($item->clasificacion_decision === 'pendiente_clasificacion') {
            $avisos[] = "«{$item->nombre}» está pendiente de clasificación.";
        }
    }
}
