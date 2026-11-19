<?php

namespace App\Services;

use App\Models\CuentaContable;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LibroContableService
{
    public function diario(?string $fechaInicio = null, ?string $fechaFin = null): Collection
    {
        $query = $this->movimientosQuery()
            // Ordinary details may not have fecha_creacion. The Python
            // movement query uses the partida date for the accounting books.
            ->selectRaw('p.fecha AS fecha, p.numero_partida, p.concepto, '
                .'d.id_detalle, d.descripcion, c.id_cuenta, c.codigo_cuenta, '
                .'c.nombre_cuenta, c.tipo_cuenta, d.debe, d.haber, '
                .'d.id_documento, d.fecha_creacion')
            ->orderBy('p.fecha')
            ->orderBy('p.numero_partida')
            ->orderBy('d.id_detalle');

        $this->applyDateFilters($query, $fechaInicio, $fechaFin);

        return $query->get()->map(fn (object $row): array => $this->normalizarMovimiento($row));
    }

    /** @return array{cuenta: CuentaContable|null, movimientos: Collection, total_debe: string, total_haber: string, saldo: string, naturaleza: string} */
    public function mayor(int $cuentaId, ?string $fechaInicio = null, ?string $fechaFin = null): array
    {
        $cuenta = CuentaContable::query()->find($cuentaId);
        $query = $this->movimientosQuery()
            ->where('d.id_cuenta', $cuentaId)
            ->selectRaw('p.fecha AS fecha, p.numero_partida, p.concepto, '
                .'d.id_detalle, d.descripcion, c.id_cuenta, c.codigo_cuenta, '
                .'c.nombre_cuenta, c.tipo_cuenta, d.debe, d.haber, '
                .'d.id_documento, d.fecha_creacion')
            ->orderBy('p.fecha')
            ->orderBy('p.numero_partida')
            ->orderBy('d.id_detalle');

        $this->applyDateFilters($query, $fechaInicio, $fechaFin);
        $movimientos = $query->get()->map(fn (object $row): array => $this->normalizarMovimiento($row));

        $debe = array_sum(array_map(fn (array $movimiento): int => $this->centavos($movimiento['debe']), $movimientos->all()));
        $haber = array_sum(array_map(fn (array $movimiento): int => $this->centavos($movimiento['haber']), $movimientos->all()));
        $diferencia = $debe - $haber;

        return [
            'cuenta' => $cuenta,
            'movimientos' => $movimientos,
            'total_debe' => $this->formatear($debe),
            'total_haber' => $this->formatear($haber),
            'saldo' => $this->formatear(abs($diferencia)),
            'naturaleza' => $diferencia >= 0 ? 'Deudor' : 'Acreedor',
        ];
    }

    public function cuentasParaMayor(): Collection
    {
        return CuentaContable::query()->orderBy('codigo_cuenta')->get();
    }

    /**
     * Reproduces contabilidad.balance_comprobacion(): agrupa por código y
     * nombre de cuenta, y calcula los saldos a partir de los movimientos del
     * diario sin modificar ningún registro.
     *
     * @return array{cuentas: array<int, array<string, mixed>>, total_debe: string, total_haber: string, total_deudor: string, total_acreedor: string}
     */
    public function balanceComprobacion(?string $fechaInicio = null, ?string $fechaFin = null): array
    {
        $grupos = [];
        foreach ($this->diario($fechaInicio, $fechaFin) as $movimiento) {
            $clave = $movimiento['codigo_cuenta'].'\0'.$movimiento['nombre_cuenta'];
            $grupos[$clave] ??= [
                'cuenta' => $movimiento['nombre_cuenta'],
                'codigo' => $movimiento['codigo_cuenta'],
                'debe_centavos' => 0,
                'haber_centavos' => 0,
                'conceptos' => [],
            ];
            $grupos[$clave]['debe_centavos'] += $this->centavos($movimiento['debe']);
            $grupos[$clave]['haber_centavos'] += $this->centavos($movimiento['haber']);
            if ($movimiento['concepto'] !== '' && ! in_array($movimiento['concepto'], $grupos[$clave]['conceptos'], true)) {
                $grupos[$clave]['conceptos'][] = $movimiento['concepto'];
            }
        }

        usort($grupos, static fn (array $a, array $b): int =>
            [$a['codigo'], $a['cuenta']] <=> [$b['codigo'], $b['cuenta']]);

        $totalDebe = $totalHaber = $totalDeudor = $totalAcreedor = 0;
        $cuentas = array_map(function (array $grupo) use (&$totalDebe, &$totalHaber, &$totalDeudor, &$totalAcreedor): array {
            $saldo = $grupo['debe_centavos'] - $grupo['haber_centavos'];
            $deudor = max($saldo, 0);
            $acreedor = max(-$saldo, 0);
            $totalDebe += $grupo['debe_centavos'];
            $totalHaber += $grupo['haber_centavos'];
            $totalDeudor += $deudor;
            $totalAcreedor += $acreedor;

            return [
                'cuenta' => $grupo['cuenta'],
                'codigo' => $grupo['codigo'],
                'conceptos' => $grupo['conceptos'],
                'debe' => $this->formatear($grupo['debe_centavos']),
                'haber' => $this->formatear($grupo['haber_centavos']),
                'saldo_deudor' => $this->formatear($deudor),
                'saldo_acreedor' => $this->formatear($acreedor),
            ];
        }, $grupos);

        return [
            'cuentas' => $cuentas,
            'total_debe' => $this->formatear($totalDebe),
            'total_haber' => $this->formatear($totalHaber),
            'total_deudor' => $this->formatear($totalDeudor),
            'total_acreedor' => $this->formatear($totalAcreedor),
        ];
    }

    /**
     * Reproduces contabilidad.balance_general(), including the original
     * exclusion of non-current liabilities from the calculated total.
     *
     * @return array<string, mixed>
     */
    public function balanceGeneral(?string $fechaInicio = null, ?string $fechaFin = null): array
    {
        $cuentas = CuentaContable::query()
            ->where('estado', true)
            ->orderBy('codigo_cuenta')
            ->get()
            ->keyBy('id_cuenta');

        $hijos = function (int $idPadre) use ($cuentas): array {
            return $cuentas->filter(fn (CuentaContable $cuenta): bool =>
                (int) $cuenta->id_cuenta_padre === $idPadre)
                ->sortBy('codigo_cuenta')
                ->pluck('nombre_cuenta')
                ->values()
                ->all();
        };

        $totales = [
            'AC_CORRIENTE' => array_fill_keys($hijos(7), 0),
            'AC_NO_CORRIENTE' => array_fill_keys($hijos(8), 0),
            'PAS_CORRIENTE' => array_fill_keys($hijos(9), 0),
            'PATRIMONIO' => array_fill_keys($hijos(10), 0),
        ];

        $padresNivel3 = [];
        foreach ($cuentas as $id => $cuenta) {
            $actual = $cuenta;
            $visitados = [];
            while ($actual && (int) $actual->nivel !== 3) {
                if (isset($visitados[$actual->id_cuenta])) {
                    $actual = null;
                    break;
                }
                $visitados[$actual->id_cuenta] = true;
                $actual = $cuentas->get($actual->id_cuenta_padre);
            }
            $padresNivel3[$id] = $actual?->nombre_cuenta;
        }

        $agrupadas = [];
        foreach ($this->diario($fechaInicio, $fechaFin) as $movimiento) {
            $clave = (string) $movimiento['nombre_cuenta'];
            $agrupadas[$clave] ??= [
                'tipo' => $movimiento['tipo_cuenta'],
                'padre_nivel3' => $padresNivel3[$movimiento['id_cuenta']] ?? null,
                'debe_centavos' => 0,
                'haber_centavos' => 0,
            ];
            $agrupadas[$clave]['debe_centavos'] += $this->centavos($movimiento['debe']);
            $agrupadas[$clave]['haber_centavos'] += $this->centavos($movimiento['haber']);
        }

        $totalIngresos = $totalCostos = $totalGastos = 0;
        foreach ($agrupadas as $grupo) {
            if ($grupo['debe_centavos'] === 0 && $grupo['haber_centavos'] === 0) {
                continue;
            }

            $padre = $grupo['padre_nivel3'];
            $tipo = $grupo['tipo'];
            if ($tipo === 'Activo') {
                if (array_key_exists($padre, $totales['AC_CORRIENTE'])) {
                    $totales['AC_CORRIENTE'][$padre] += $grupo['debe_centavos'] - $grupo['haber_centavos'];
                } elseif (array_key_exists($padre, $totales['AC_NO_CORRIENTE'])) {
                    $totales['AC_NO_CORRIENTE'][$padre] += $grupo['debe_centavos'] - $grupo['haber_centavos'];
                }
            } elseif ($tipo === 'Pasivo') {
                // Pasivo no corriente is intentionally omitted, as in Python.
                if (array_key_exists($padre, $totales['PAS_CORRIENTE'])) {
                    $totales['PAS_CORRIENTE'][$padre] += $grupo['haber_centavos'] - $grupo['debe_centavos'];
                }
            } elseif ($tipo === 'Patrimonio') {
                if (array_key_exists($padre, $totales['PATRIMONIO'])) {
                    $totales['PATRIMONIO'][$padre] += $grupo['haber_centavos'] - $grupo['debe_centavos'];
                }
            } elseif ($tipo === 'Ingreso') {
                $totalIngresos += $grupo['haber_centavos'] - $grupo['debe_centavos'];
            } elseif ($tipo === 'Gasto') {
                $saldo = $grupo['debe_centavos'] - $grupo['haber_centavos'];
                if ($padre !== null && (str_contains($padre, 'COSTO') || $padre === 'COMPRAS')) {
                    $totalCostos += $saldo;
                } else {
                    $totalGastos += $saldo;
                }
            }
        }

        $utilidad = $totalIngresos - $totalCostos - $totalGastos;
        if ($utilidad !== 0 && array_key_exists('RESULTADOS DEL EJERCICIO', $totales['PATRIMONIO'])) {
            $totales['PATRIMONIO']['RESULTADOS DEL EJERCICIO'] += $utilidad;
        }

        $conSaldo = function (array $items): array {
            $resultado = [];
            foreach ($items as $nombre => $importe) {
                if (abs($importe) > 0) {
                    $resultado[] = ['cuenta' => $nombre, 'saldo' => $this->formatear($importe)];
                }
            }
            return $resultado;
        };

        $acCorriente = array_sum($totales['AC_CORRIENTE']);
        $acNoCorriente = array_sum($totales['AC_NO_CORRIENTE']);
        $pasCorriente = array_sum($totales['PAS_CORRIENTE']);
        $patrimonio = array_sum($totales['PATRIMONIO']);
        $activos = $acCorriente + $acNoCorriente;
        $pasivoPatrimonio = $pasCorriente + $patrimonio;

        return [
            'AC_CORRIENTE' => $conSaldo($totales['AC_CORRIENTE']),
            'AC_NO_CORRIENTE' => $conSaldo($totales['AC_NO_CORRIENTE']),
            'PAS_CORRIENTE' => $conSaldo($totales['PAS_CORRIENTE']),
            'PAS_NO_CORRIENTE' => [],
            'PATRIMONIO' => $conSaldo($totales['PATRIMONIO']),
            'total_activo_corriente' => $this->formatear($acCorriente),
            'total_activo_no_corriente' => $this->formatear($acNoCorriente),
            'total_pasivo_corriente' => $this->formatear($pasCorriente),
            'total_activos' => $this->formatear($activos),
            'total_pasivos' => $this->formatear($pasCorriente),
            'total_patrimonio' => $this->formatear($patrimonio),
            'total_pasivos_patrimonio' => $this->formatear($pasivoPatrimonio),
            'ecuacion_ok' => abs($activos - $pasivoPatrimonio) < 1,
        ];
    }

    /**
     * Reproduces contabilidad.estado_resultados(): ingresos versus cuentas
     * de tipo Gasto, using the same ordinary and adjustment movements as the
     * general journal.
     *
     * @return array{ingresos: array<int, array{cuenta: string, saldo: string}>, gastos: array<int, array{cuenta: string, saldo: string}>, total_ingresos: string, total_gastos: string, utilidad_neta: string, es_utilidad: bool}
     */
    public function estadoResultados(?string $fechaInicio = null, ?string $fechaFin = null): array
    {
        $agrupadas = [];
        foreach ($this->diario($fechaInicio, $fechaFin) as $movimiento) {
            $cuenta = (string) $movimiento['nombre_cuenta'];
            $agrupadas[$cuenta] ??= [
                'tipo' => $movimiento['tipo_cuenta'],
                'debe_centavos' => 0,
                'haber_centavos' => 0,
            ];
            $agrupadas[$cuenta]['debe_centavos'] += $this->centavos($movimiento['debe']);
            $agrupadas[$cuenta]['haber_centavos'] += $this->centavos($movimiento['haber']);
        }

        $ingresos = [];
        $gastos = [];
        foreach ($agrupadas as $cuenta => $grupo) {
            if ($grupo['tipo'] === 'Ingreso') {
                $saldo = $grupo['haber_centavos'] - $grupo['debe_centavos'];
                if ($saldo !== 0) {
                    $ingresos[] = ['cuenta' => $cuenta, 'saldo' => $this->formatear($saldo)];
                }
            } elseif ($grupo['tipo'] === 'Gasto') {
                $saldo = $grupo['debe_centavos'] - $grupo['haber_centavos'];
                if ($saldo !== 0) {
                    $gastos[] = ['cuenta' => $cuenta, 'saldo' => $this->formatear($saldo)];
                }
            }
        }

        usort($ingresos, static fn (array $a, array $b): int => $a['cuenta'] <=> $b['cuenta']);
        usort($gastos, static fn (array $a, array $b): int => $a['cuenta'] <=> $b['cuenta']);

        $totalIngresos = array_sum(array_map(fn (array $fila): int => $this->centavos($fila['saldo']), $ingresos));
        $totalGastos = array_sum(array_map(fn (array $fila): int => $this->centavos($fila['saldo']), $gastos));
        $resultado = $totalIngresos - $totalGastos;

        return [
            'ingresos' => $ingresos,
            'gastos' => $gastos,
            'total_ingresos' => $this->formatear($totalIngresos),
            'total_gastos' => $this->formatear($totalGastos),
            'utilidad_neta' => $this->formatear(abs($resultado)),
            'es_utilidad' => $resultado >= 0,
        ];
    }

    private function movimientosQuery(): \Illuminate\Database\Query\Builder
    {
        return DB::table('tbldetalles_partida AS d')
            ->join('tblpartidas AS p', 'p.id_partida', '=', 'd.id_partida')
            ->join('tblcuentascontables AS c', 'c.id_cuenta', '=', 'd.id_cuenta');
    }

    private function applyDateFilters(\Illuminate\Database\Query\Builder $query, ?string $inicio, ?string $fin): void
    {
        if ($inicio !== null && $inicio !== '') {
            $query->whereDate('p.fecha', '>=', $inicio);
        }
        if ($fin !== null && $fin !== '') {
            $query->whereDate('p.fecha', '<=', $fin);
        }
    }

    /** @return array<string, mixed> */
    private function normalizarMovimiento(object $row): array
    {
        $concepto = (string) ($row->concepto ?? '');
        preg_match('/^\[AJUSTE:([^\]]+)\]\s*(.*)$/', $concepto, $ajuste);

        return [
            'id_detalle' => (int) $row->id_detalle,
            'id_cuenta' => (int) $row->id_cuenta,
            'numero_partida' => (int) $row->numero_partida,
            // A DATE can be returned by SQLite as Y-m-d 00:00:00. Normalize
            // only this output value; ordering and stored data remain intact.
            'fecha' => Carbon::parse((string) $row->fecha)->format('Y-m-d'),
            'fecha_creacion' => $row->fecha_creacion,
            'concepto' => $concepto,
            'concepto_visible' => $ajuste ? ($ajuste[2] ?: $concepto) : $concepto,
            'es_ajuste' => $ajuste !== [],
            'tipo_ajuste' => $ajuste[1] ?? null,
            'descripcion' => $row->descripcion,
            'codigo_cuenta' => $row->codigo_cuenta,
            'nombre_cuenta' => $row->nombre_cuenta,
            'tipo_cuenta' => $row->tipo_cuenta,
            'debe' => $this->formatear($this->centavos($row->debe)),
            'haber' => $this->formatear($this->centavos($row->haber)),
            'id_documento' => $row->id_documento,
        ];
    }

    private function centavos(mixed $importe): int
    {
        $valor = trim((string) ($importe ?? '0'));
        $negativo = str_starts_with($valor, '-');
        $valor = ltrim($valor, '+-');
        [$entero, $decimal] = array_pad(explode('.', $valor, 2), 2, '');
        $centavos = ((int) $entero * 100) + (int) str_pad(substr($decimal, 0, 2), 2, '0');

        return $negativo ? -$centavos : $centavos;
    }

    private function formatear(int $centavos): string
    {
        $signo = $centavos < 0 ? '-' : '';
        $absoluto = abs($centavos);

        return $signo.intdiv($absoluto, 100).'.'.str_pad((string) ($absoluto % 100), 2, '0', STR_PAD_LEFT);
    }
}
