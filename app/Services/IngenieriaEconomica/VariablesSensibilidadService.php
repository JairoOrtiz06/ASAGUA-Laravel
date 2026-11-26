<?php

declare(strict_types=1);

namespace App\Services\IngenieriaEconomica;

use App\Models\IngenieriaEconomica\Concepto;
use Brick\Math\BigDecimal;
use DomainException;

class VariablesSensibilidadService
{
    public const MODOS = ['absoluto' => 'Valor absoluto', 'relativo' => 'Variación relativa (%)', 'puntos' => 'Puntos porcentuales'];

    public function catalogo(array $base, array $ids, string $moneda): array
    {
        $catalogo = ['tmar' => ['nombre' => 'TMAR efectiva anual', 'unidad' => '% anual', 'base' => (string) BigDecimal::of($base['escenario']['tasa_efectiva_anual'] ?? '0')->multipliedBy(100)]];
        if (! $base['legacy']) {
            $catalogo['horizonte'] = ['nombre' => 'Horizonte', 'unidad' => 'años enteros', 'base' => (string) $base['escenario']['horizonte_anios']];
        }
        foreach ($base['insumos']['conceptos'] as $c) {
            if (($c['alternativa_id'] !== null && ! in_array((int) $c['alternativa_id'], $ids, true)) || $c['moneda'] !== $moneda
                || ($c['fecha_real'] ?? null) !== null || in_array($c['clasificacion_decision'], ['hundido', 'pendiente_clasificacion'], true)
                || $c['naturaleza_economica'] === 'no_monetario') {
                continue;
            }
            $ambito = $c['alternativa_id'] === null ? 'Común' : $base['insumos']['alternativas'][$c['alternativa_id']]['nombre'];
            $nombre = $c['nombre'].' · '.$ambito;
            $catalogo['monto:'.$c['id']] = ['nombre' => $nombre.' · '.Concepto::TIPOS[$c['tipo_concepto']], 'unidad' => $moneda.' / ocurrencia', 'base' => $c['monto']];
            if ($c['periodicidad'] !== 'unico' && ! $base['legacy'] && Concepto::direccion($c['tipo_concepto'], $c['direccion_otro'] ?? null) === 'salida') {
                $catalogo['crecimiento:'.$c['id']] = ['nombre' => 'Crecimiento · '.$nombre, 'unidad' => '% / ocurrencia', 'base' => (string) BigDecimal::of($c['porcentaje_crecimiento'] ?? '0')->multipliedBy(100)];
            }
            if ($c['periodicidad'] === 'mensual' && $c['naturaleza_economica'] === 'costo_oportunidad') {
                $catalogo['horas:'.$c['id']] = ['nombre' => 'Horas adicionales · '.$nombre, 'unidad' => 'horas/día', 'base' => null];
                $catalogo['salario:'.$c['id']] = ['nombre' => 'Salario · '.$nombre, 'unidad' => $moneda.'/mes', 'base' => null];
            }
        }
        foreach ($ids as $id) {
            $catalogo['migracion:'.$id] = [
                'nombre' => 'Migración temporal · '.$base['insumos']['alternativas'][$id]['nombre'],
                'unidad' => $moneda.' al inicio', 'base' => '0',
            ];
        }

        return $catalogo;
    }

    public function numero(mixed $valor): BigDecimal
    {
        if (! is_scalar($valor) || ! preg_match('/^[+-]?(?:\d{1,14}(?:\.\d{1,12})?|\.\d{1,12})$/D', (string) $valor)) {
            throw new DomainException('Usa números decimales con punto, hasta 14 dígitos enteros y 12 decimales.');
        }

        return BigDecimal::of((string) $valor);
    }

    public function aplicar(array $base, array $catalogo, array $cambios, array $oportunidad, string $moneda): array
    {
        $insumos = $base['insumos'];
        $escenario = $base['escenario'];
        $conceptos = array_column($insumos['conceptos'], null, 'id');
        $tasa = null;
        $variaciones = $usados = $laborales = [];
        foreach ($cambios as $cambio) {
            $variable = $cambio['variable'];
            [$tipo, $id] = array_pad(explode(':', $variable, 2), 2, null);
            $def = $catalogo[$variable] ?? throw new DomainException('La variable seleccionada no está disponible para esta base.');
            if (isset($usados[$variable])) {
                throw new DomainException('Una simulación solo puede variar una vez cada variable.');
            }
            $usados[$variable] = true;
            if (in_array($tipo, ['horas', 'salario'], true)) {
                if (isset($usados['monto:'.$id])) {
                    throw new DomainException('El importe de oportunidad se modifica mediante valor directo o parámetros laborales.');
                }
                $laborales[$id] ??= $this->parametros($oportunidad[$id] ?? []);
                $def['base'] = $oportunidad[$id][$tipo];
            } elseif ($tipo === 'monto' && isset($laborales[$id])) {
                throw new DomainException('El importe de oportunidad se modifica mediante valor directo o parámetros laborales.');
            }

            $original = $this->numero($def['base']);
            $entrada = $this->numero($cambio['valor'] ?? null);
            $nuevo = match ($cambio['modo'] ?? null) {
                'absoluto' => $entrada,
                'relativo' => $original->multipliedBy(BigDecimal::one()->plus($entrada->withPointMovedLeft(2))),
                'puntos' => in_array($tipo, ['tmar', 'crecimiento'], true)
                    ? $original->plus($entrada) : throw new DomainException('Los puntos porcentuales se aplican a tasas y crecimiento.'),
                default => throw new DomainException('Selecciona una modalidad de variación válida.'),
            };
            $this->validar($tipo, $nuevo);
            $variaciones[] = $def + [
                'variable' => $variable, 'nuevo' => (string) $nuevo, 'entrada' => (string) $entrada, 'modo' => $cambio['modo'],
                'cambio_pct' => $original->isZero() ? null : (string) DecimalFinanciero::dividir($nuevo->minus($original)->multipliedBy(100), $original),
            ];

            switch ($tipo) {
                case 'tmar':
                    $tasa = (string) $nuevo->withPointMovedLeft(2);
                    break;
                case 'horizonte':
                    $escenario['horizonte_anios'] = $nuevo->toInt();
                    break;
                case 'monto':
                    $conceptos[$id]['simulacion_monto'] = (string) $nuevo;
                    break;
                case 'crecimiento':
                    $conceptos[$id]['simulacion_crecimiento'] = (string) $nuevo->withPointMovedLeft(2);
                    break;
                case 'migracion':
                    $conceptos[-(int) $id] = [
                        'id' => -(int) $id, 'alternativa_id' => (int) $id,
                        'nombre' => 'Costo de migración supuesto', 'monto' => '0', 'simulacion_monto' => (string) $nuevo,
                        'moneda' => $moneda, 'tipo_concepto' => 'inversion_adicional', 'periodicidad' => 'unico',
                        'periodo_inicio' => 0, 'momento_pago' => 'inicio',
                        'clasificacion_decision' => 'diferencial', 'naturaleza_economica' => 'desembolso_real',
                        'estado_dato' => 'estimado', 'observaciones' => 'Supuesto temporal de la simulación.', 'fuentes' => [],
                    ];
                    break;
                default:
                    $laborales[$id][$tipo] = $nuevo;
                    break;
            }
        }

        $memoriaLaboral = [];
        foreach ($laborales as $id => $p) {
            if ($p['horas']->isGreaterThan($p['jornada'])) {
                throw new DomainException('Las horas adicionales no pueden superar la jornada diaria parametrizada.');
            }
            $horasMes = $p['jornada']->multipliedBy($p['dias']);
            $importe = DecimalFinanciero::dividir($p['salario']->multipliedBy($p['horas'])->multipliedBy($p['dias']), $horasMes);
            $conceptos[$id]['simulacion_monto'] = (string) $importe;
            $memoriaLaboral[$id] = ['formula' => '(salario mensual × horas adicionales diarias × días) / (jornada diaria × días)',
                'parametros' => array_map('strval', $p), 'costo_mensual' => (string) $importe];
        }
        $insumos['conceptos'] = array_values($conceptos);

        return compact('insumos', 'escenario', 'tasa', 'variaciones', 'memoriaLaboral');
    }

    private function parametros(array $valores): array
    {
        $p = [];
        foreach (['salario', 'jornada', 'dias', 'horas'] as $campo) {
            if (! isset($valores[$campo])) {
                throw new DomainException('Completa salario mensual, jornada, días y horas adicionales del concepto de oportunidad.');
            }
            $p[$campo] = $this->numero($valores[$campo]);
        }
        if ($p['salario']->isNegative() || $p['jornada']->isLessThanOrEqualTo(0) || $p['jornada']->isGreaterThan(24)
            || $p['dias']->strippedOfTrailingZeros()->getScale() !== 0 || $p['dias']->isLessThan(1) || $p['dias']->isGreaterThan(31) || $p['horas']->isNegative() || $p['horas']->isGreaterThan($p['jornada'])) {
            throw new DomainException('Corrige los parámetros laborales. Jornada hasta 24 horas, días entre 1 y 31 y horas adicionales dentro de la jornada.');
        }

        return $p;
    }

    private function validar(string $tipo, BigDecimal $valor): void
    {
        $valido = match ($tipo) {
            'tmar' => $valor->isGreaterThan(-100) && $valor->isLessThan('10000000000'),
            'crecimiento' => $valor->isGreaterThanOrEqualTo(-100) && $valor->isLessThanOrEqualTo(100),
            'horizonte' => $valor->strippedOfTrailingZeros()->getScale() === 0 && $valor->isGreaterThanOrEqualTo(1) && $valor->isLessThanOrEqualTo(ProyeccionFlujosService::MAX_ANIOS),
            'horas' => ! $valor->isNegative() && $valor->isLessThanOrEqualTo(24),
            default => ! $valor->isNegative() && $valor->isLessThan('100000000000000'),
        };
        if (! $valido) {
            throw new DomainException('El valor simulado para '.$tipo.' queda fuera de su rango válido.');
        }
    }
}
