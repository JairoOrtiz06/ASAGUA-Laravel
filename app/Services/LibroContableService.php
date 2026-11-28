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
     * Agrupa por cuenta y calcula los saldos a partir de los movimientos del
     * diario sin modificar ningún registro.
     *
     * @return array{cuentas: array<int, array<string, mixed>>, total_debe: string, total_haber: string, total_deudor: string, total_acreedor: string}
     */
    public function balanceComprobacion(?string $fechaInicio = null, ?string $fechaFin = null): array
    {
        $grupos = [];
        foreach ($this->diario($fechaInicio, $fechaFin) as $movimiento) {
            $clave = $movimiento['id_cuenta'];
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

    /** @return array<string, mixed> */
    public function balanceGeneral(?string $fechaInicio = null, ?string $fechaFin = null): array
    {
        $cuentas = CuentaContable::query()->orderBy('codigo_cuenta')->get()->keyBy('id_cuenta');
        $secciones = [
            'AC_CORRIENTE' => [],
            'AC_NO_CORRIENTE' => [],
            'PAS_CORRIENTE' => [],
            'PAS_NO_CORRIENTE' => [],
            'PATRIMONIO' => [],
        ];

        $clasificacion = [];
        $cuentaResultado = null;
        foreach ($cuentas as $id => $cuenta) {
            $tipo = $cuenta->tipo_cuenta;
            if ($tipo === 'Patrimonio' && $cuenta->nivel === 3
                && ($cuenta->codigo_cuenta === '3104'
                    || preg_match('/^resultados? del ejercicio$/iu', trim($cuenta->nombre_cuenta)) === 1)) {
                $cuentaResultado = $cuenta;
            }
            if (! in_array($tipo, ['Activo', 'Pasivo', 'Patrimonio'], true)) {
                continue;
            }

            $mayor = $cuenta;
            $grupo = null;
            $visitados = [];
            for ($actual = $cuenta; $actual && ! isset($visitados[$actual->id_cuenta]); $actual = $cuentas->get($actual->id_cuenta_padre)) {
                $visitados[$actual->id_cuenta] = true;
                if ($actual->nivel === 3) {
                    $mayor = $actual;
                }
                if ($actual->nivel === 2) {
                    $grupo = $actual;
                    break;
                }
            }

            $prefijo = substr($cuenta->codigo_cuenta, 0, 2);
            $nombreGrupo = $grupo?->nombre_cuenta ?? '';
            $noCorriente = preg_match('/\b(no corrientes?|largo plazo)\b/iu', $nombreGrupo) === 1
                || ($grupo?->codigo_cuenta ?? $prefijo) === ($tipo === 'Activo' ? '12' : '22');

            $seccion = match ($tipo) {
                'Activo' => $noCorriente ? 'AC_NO_CORRIENTE' : 'AC_CORRIENTE',
                'Pasivo' => $noCorriente ? 'PAS_NO_CORRIENTE' : 'PAS_CORRIENTE',
                'Patrimonio' => 'PATRIMONIO',
            };
            $clasificacion[$id] = [$seccion, $mayor];
        }

        // El balance refleja los saldos acumulados hasta la fecha de corte.
        $resultado = 0;
        foreach ($this->diario(null, $fechaFin) as $movimiento) {
            $id = $movimiento['id_cuenta'];
            $neto = $this->centavos($movimiento['debe']) - $this->centavos($movimiento['haber']);
            if (isset($clasificacion[$id])) {
                [$seccion, $mayor] = $clasificacion[$id];
                $secciones[$seccion][$mayor->id_cuenta] =
                    ($secciones[$seccion][$mayor->id_cuenta] ?? 0)
                    + ($movimiento['tipo_cuenta'] === 'Activo' ? $neto : -$neto);
            } elseif (in_array($movimiento['tipo_cuenta'], ['Ingreso', 'Gasto', 'Costo'], true)) {
                $resultado -= $neto;
            }
        }

        if ($resultado !== 0) {
            $id = $cuentaResultado?->id_cuenta ?? 'resultado';
            $secciones['PATRIMONIO'][$id] = ($secciones['PATRIMONIO'][$id] ?? 0) + $resultado;
        }

        $balance = [];
        $totales = [];
        foreach ($secciones as $seccion => $saldos) {
            $totales[$seccion] = array_sum($saldos);
            $balance[$seccion] = [];
            foreach (array_filter($saldos) as $id => $saldo) {
                $cuenta = $cuentas->get($id);
                $balance[$seccion][] = [
                    'id_cuenta' => $cuenta?->id_cuenta,
                    'codigo' => $cuenta?->codigo_cuenta ?? '',
                    'cuenta' => $cuenta?->nombre_cuenta ?? 'Resultados del ejercicio',
                    'saldo' => $this->formatear($saldo),
                ];
            }
            usort($balance[$seccion], static fn (array $a, array $b): int => strcmp($a['codigo'], $b['codigo']));
        }

        $activos = $totales['AC_CORRIENTE'] + $totales['AC_NO_CORRIENTE'];
        $pasivos = $totales['PAS_CORRIENTE'] + $totales['PAS_NO_CORRIENTE'];
        $patrimonio = $totales['PATRIMONIO'];

        return $balance + [
            'total_activo_corriente' => $this->formatear($totales['AC_CORRIENTE']),
            'total_activo_no_corriente' => $this->formatear($totales['AC_NO_CORRIENTE']),
            'total_pasivo_corriente' => $this->formatear($totales['PAS_CORRIENTE']),
            'total_pasivo_no_corriente' => $this->formatear($totales['PAS_NO_CORRIENTE']),
            'total_activos' => $this->formatear($activos),
            'total_pasivos' => $this->formatear($pasivos),
            'total_patrimonio' => $this->formatear($patrimonio),
            'total_pasivos_patrimonio' => $this->formatear($pasivos + $patrimonio),
            'ecuacion_ok' => $activos === $pasivos + $patrimonio,
        ];
    }

    /**
     * Reproduces contabilidad.estado_resultados(): ingresos versus cuentas
     * de tipo Gasto, using the same ordinary and adjustment movements as the
     * general journal.
     *
     * @return array{ingresos: array<int, array{id_cuenta: int, codigo: string, cuenta: string, saldo: string}>, gastos: array<int, array{id_cuenta: int, codigo: string, cuenta: string, saldo: string}>, total_ingresos: string, total_gastos: string, utilidad_neta: string, es_utilidad: bool}
     */
    public function estadoResultados(?string $fechaInicio = null, ?string $fechaFin = null): array
    {
        $agrupadas = [];
        foreach ($this->diario($fechaInicio, $fechaFin) as $movimiento) {
            $cuenta = $movimiento['id_cuenta'];
            $agrupadas[$cuenta] ??= [
                'cuenta' => $movimiento['nombre_cuenta'],
                'codigo' => $movimiento['codigo_cuenta'],
                'tipo' => $movimiento['tipo_cuenta'],
                'debe_centavos' => 0,
                'haber_centavos' => 0,
            ];
            $agrupadas[$cuenta]['debe_centavos'] += $this->centavos($movimiento['debe']);
            $agrupadas[$cuenta]['haber_centavos'] += $this->centavos($movimiento['haber']);
        }

        $ingresos = [];
        $gastos = [];
        foreach ($agrupadas as $id => $grupo) {
            $cuenta = ['id_cuenta' => $id, 'codigo' => $grupo['codigo'], 'cuenta' => $grupo['cuenta']];
            if ($grupo['tipo'] === 'Ingreso') {
                $saldo = $grupo['haber_centavos'] - $grupo['debe_centavos'];
                if ($saldo !== 0) {
                    $ingresos[] = $cuenta + ['saldo' => $this->formatear($saldo)];
                }
            } elseif (in_array($grupo['tipo'], ['Gasto', 'Costo'], true)) {
                $saldo = $grupo['debe_centavos'] - $grupo['haber_centavos'];
                if ($saldo !== 0) {
                    $gastos[] = $cuenta + ['saldo' => $this->formatear($saldo)];
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
