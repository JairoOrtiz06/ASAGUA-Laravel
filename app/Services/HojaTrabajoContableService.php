<?php

namespace App\Services;

class HojaTrabajoContableService
{
    public function __construct(private readonly LibroContableService $libros)
    {
    }

    /** @return array<string, mixed> */
    public function generar(?string $fechaInicio = null, ?string $fechaFin = null): array
    {
        $filas = [];

        foreach ($this->libros->diario($fechaInicio, $fechaFin) as $movimiento) {
            $clave = $movimiento['codigo_cuenta'].'\0'.$movimiento['nombre_cuenta'];
            $filas[$clave] ??= $this->filaBase(
                (string) $movimiento['codigo_cuenta'],
                (string) $movimiento['nombre_cuenta'],
                (string) $movimiento['tipo_cuenta'],
            );

            $columna = $movimiento['es_ajuste'] ? 'ajustes' : 'balance_comprobacion';
            $filas[$clave][$columna]['debe'] += $this->centavos((string) $movimiento['debe']);
            $filas[$clave][$columna]['haber'] += $this->centavos((string) $movimiento['haber']);
        }

        foreach ($filas as &$fila) {
            $fila['balance_ajustado']['debe'] =
                $fila['balance_comprobacion']['debe'] + $fila['ajustes']['debe'];
            $fila['balance_ajustado']['haber'] =
                $fila['balance_comprobacion']['haber'] + $fila['ajustes']['haber'];
        }
        unset($fila);

        $estado = $this->libros->estadoResultados($fechaInicio, $fechaFin);
        foreach ($estado['ingresos'] as $item) {
            $this->agregarResultado($filas, $item['cuenta'], 'Ingreso', $item['saldo'], false);
        }
        foreach ($estado['gastos'] as $item) {
            $this->agregarResultado($filas, $item['cuenta'], 'Gasto', $item['saldo'], true);
        }

        $balance = $this->libros->balanceGeneral($fechaInicio, $fechaFin);
        foreach (['AC_CORRIENTE', 'AC_NO_CORRIENTE'] as $seccion) {
            foreach ($balance[$seccion] as $item) {
                $this->agregarBalanceGeneral($filas, $item['cuenta'], 'Activo', $item['saldo'], true);
            }
        }
        foreach (['PAS_CORRIENTE', 'PATRIMONIO'] as $seccion) {
            foreach ($balance[$seccion] as $item) {
                $this->agregarBalanceGeneral($filas, $item['cuenta'], $seccion === 'PATRIMONIO' ? 'Patrimonio' : 'Pasivo', $item['saldo'], false);
            }
        }

        $filas = array_values($filas);
        usort($filas, static fn (array $a, array $b): int =>
            [$a['codigo'], $a['cuenta']] <=> [$b['codigo'], $b['cuenta']]);

        $this->formatearFilas($filas);

        $totales = [
            'balance_comprobacion' => $this->totales($filas, 'balance_comprobacion'),
            'ajustes' => $this->totales($filas, 'ajustes'),
            'balance_ajustado' => $this->totales($filas, 'balance_ajustado'),
            'estado_resultados' => $this->totales($filas, 'estado_resultados'),
            'balance_general' => $this->totales($filas, 'balance_general'),
        ];

        return [
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
            'filas' => $filas,
            'totales' => $totales,
            'utilidad_neta' => $estado['utilidad_neta'],
            'es_utilidad' => $estado['es_utilidad'],
            'balance_comprobacion_cuadra' => $this->cuadra($totales['balance_comprobacion']),
            'ajustes_cuadran' => $this->cuadra($totales['ajustes']),
            'balance_ajustado_cuadra' => $this->cuadra($totales['balance_ajustado']),
            'balance_general_cuadra' => (bool) $balance['ecuacion_ok'],
        ];
    }

    /** @return array<string, mixed> */
    private function filaBase(string $codigo, string $cuenta, string $tipo): array
    {
        return [
            'codigo' => $codigo,
            'cuenta' => $cuenta,
            'tipo' => $tipo,
            'balance_comprobacion' => ['debe' => 0, 'haber' => 0],
            'ajustes' => ['debe' => 0, 'haber' => 0],
            'balance_ajustado' => ['debe' => 0, 'haber' => 0],
            'estado_resultados' => ['debe' => 0, 'haber' => 0],
            'balance_general' => ['debe' => 0, 'haber' => 0],
        ];
    }

    /** @param array<string, array<string, mixed>> $filas */
    private function agregarResultado(array &$filas, string $cuenta, string $tipo, string $saldo, bool $deudora): void
    {
        $clave = $this->buscarClave($filas, $cuenta);
        if ($clave === null) {
            $clave = '0\0'.$cuenta;
            $filas[$clave] = $this->filaBase('', $cuenta, $tipo);
        }

        $importe = $this->centavos($saldo);
        if (($deudora && $importe >= 0) || (! $deudora && $importe < 0)) {
            $filas[$clave]['estado_resultados']['debe'] += abs($importe);
        } else {
            $filas[$clave]['estado_resultados']['haber'] += abs($importe);
        }
    }

    /** @param array<string, array<string, mixed>> $filas */
    private function agregarBalanceGeneral(array &$filas, string $cuenta, string $tipo, string $saldo, bool $deudora): void
    {
        $clave = $this->buscarClave($filas, $cuenta);
        if ($clave === null) {
            $clave = '0\0'.$cuenta;
            $filas[$clave] = $this->filaBase('', $cuenta, $tipo);
        }

        $importe = $this->centavos($saldo);
        if (($deudora && $importe >= 0) || (! $deudora && $importe < 0)) {
            $filas[$clave]['balance_general']['debe'] += abs($importe);
        } else {
            $filas[$clave]['balance_general']['haber'] += abs($importe);
        }
    }

    /** @param array<string, array<string, mixed>> $filas */
    private function buscarClave(array $filas, string $cuenta): ?string
    {
        foreach ($filas as $clave => $fila) {
            if ($fila['cuenta'] === $cuenta) {
                return $clave;
            }
        }

        return null;
    }

    /** @param array<int, array<string, mixed>> $filas */
    private function formatearFilas(array &$filas): void
    {
        foreach ($filas as &$fila) {
            foreach (['balance_comprobacion', 'ajustes', 'balance_ajustado', 'estado_resultados', 'balance_general'] as $seccion) {
                $fila[$seccion]['debe'] = $this->formatear($fila[$seccion]['debe']);
                $fila[$seccion]['haber'] = $this->formatear($fila[$seccion]['haber']);
            }
        }
        unset($fila);
    }

    /** @param array<int, array<string, mixed>> $filas */
    private function totales(array $filas, string $seccion): array
    {
        $debe = $haber = 0;
        foreach ($filas as $fila) {
            $debe += $this->centavos((string) $fila[$seccion]['debe']);
            $haber += $this->centavos((string) $fila[$seccion]['haber']);
        }

        return ['debe' => $this->formatear($debe), 'haber' => $this->formatear($haber)];
    }

    /** @param array{debe: string, haber: string} $totales */
    private function cuadra(array $totales): bool
    {
        return $this->centavos($totales['debe']) === $this->centavos($totales['haber']);
    }

    private function centavos(string $importe): int
    {
        $valor = trim($importe);
        $negativo = str_starts_with($valor, '-');
        $valor = ltrim($valor, '+-');
        [$entero, $decimal] = array_pad(explode('.', $valor, 2), 2, '');
        $resultado = ((int) $entero * 100) + (int) str_pad(substr($decimal, 0, 2), 2, '0');

        return $negativo ? -$resultado : $resultado;
    }

    private function formatear(int $centavos): string
    {
        $signo = $centavos < 0 ? '-' : '';
        $absoluto = abs($centavos);

        return $signo.intdiv($absoluto, 100).'.'.str_pad((string) ($absoluto % 100), 2, '0', STR_PAD_LEFT);
    }
}
