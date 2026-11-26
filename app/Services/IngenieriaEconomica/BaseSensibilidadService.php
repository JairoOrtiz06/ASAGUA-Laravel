<?php

declare(strict_types=1);

namespace App\Services\IngenieriaEconomica;

use App\Models\IngenieriaEconomica\Escenario;
use App\Models\IngenieriaEconomica\Evaluacion;
use App\Models\IngenieriaEconomica\Proyecto;
use Brick\Math\BigDecimal;
use DomainException;

class BaseSensibilidadService
{
    public function __construct(private ProyeccionFlujosService $proyeccion) {}

    public function capturar(Proyecto $proyecto, Escenario $escenario, ?Evaluacion $evaluacion, array $ids, string $moneda): array
    {
        if (! $evaluacion) {
            return [
                'proyecto' => $proyecto->getAttributes(), 'escenario' => $escenario->getAttributes(),
                'insumos' => $this->proyeccion->insumos($proyecto, $escenario, $ids),
                'legacy' => false, 'advertencias' => [], 'comparar' => null,
            ];
        }

        $r = $evaluacion->instantanea;
        if (isset($r['fiscal'])) {
            throw new DomainException('La sensibilidad de una evaluación fiscal requiere volver a documentar las bases tributarias de cada simulación.');
        }
        if ((int) $evaluacion->proyecto_id !== (int) $proyecto->id || (int) $evaluacion->escenario_id !== (int) $escenario->id
            || $r['moneda'] !== $moneda || array_diff($ids, array_keys($r['alternativas']))) {
            throw new DomainException('La evaluación guardada debe cubrir el escenario, alternativas y moneda elegidos.');
        }
        $e = $r['escenario'];
        $legacy = ! isset($r['insumos']);

        return [
            'proyecto' => $r['proyecto'] + ['fecha_base' => $e['base']],
            'escenario' => [
                'id' => $e['id'], 'proyecto_id' => $proyecto->id, 'nombre' => $e['nombre'],
                'fecha_base' => $e['base'], 'horizonte_anios' => $e['horizonte'], 'periodicidad' => $e['periodicidad_escenario'],
                'tasa_efectiva_anual' => $e['anual'], 'tasa_original' => $e['original'],
                'modalidad_tasa' => $e['modalidad_tasa'], 'periodicidad_tasa' => $e['periodicidad_tasa'],
                'capitalizaciones_anuales' => $e['capitalizaciones_anuales'], 'estado_tasa' => $e['estado_tasa'], 'fuente_tasa' => $e['fuente_tasa'],
            ],
            'insumos' => $r['insumos'] ?? $this->reconstruir($r, (int) $proyecto->id),
            'legacy' => $legacy,
            'advertencias' => $legacy ? ['La evaluación anterior conserva solo los conceptos que aparecen en su memoria. Horizonte y crecimiento quedan fijos para esa versión.'] : [],
            'comparar' => [
                'perspectiva' => $r['perspectiva'], 'periodicidad' => $e['periodicidad'],
                'indicadores' => array_map(fn ($a) => $a['indicadores'], array_intersect_key($r['alternativas'], array_flip($ids))),
                'avisos' => array_map(fn ($a) => $a['avisos'], array_intersect_key($r['alternativas'], array_flip($ids))),
            ],
        ];
    }

    public function comprobar(array $base, array $resultado): void
    {
        $referencia = $base['comparar'];
        if (! $referencia || $referencia['perspectiva'] !== $resultado['perspectiva']
            || $referencia['periodicidad'] !== $resultado['escenario']['periodicidad']) {
            return;
        }
        foreach ($resultado['alternativas'] as $id => $alt) {
            $guardados = $referencia['indicadores'][$id];
            foreach (['van', 'vpc', 'caue', 'rcb'] as $clave) {
                $actual = $alt['indicadores'][$clave];
                $anterior = $guardados[$clave];
                if (($actual === null || $anterior === null) ? $actual !== $anterior : ! BigDecimal::of($actual)->isEqualTo($anterior)) {
                    throw new DomainException('La simulación base no reproduce los indicadores de la evaluación guardada. Revisa la versión de sus datos.');
                }
            }

            $tirActual = $alt['indicadores']['tir'];
            $tirAnterior = $guardados['tir'];
            if ($tirActual['estado'] !== $tirAnterior['estado']
                || array_column($tirActual['raices'], 'tasa') !== array_column($tirAnterior['raices'], 'tasa')) {
                throw new DomainException('La TIR base no coincide con la evaluación guardada.');
            }
        }
    }

    private function reconstruir(array $r, int $proyecto): array
    {
        $alternativas = $conceptos = $ajustes = [];
        foreach ($r['alternativas'] as $id => $a) {
            $alternativas[$id] = [
                'id' => $id, 'proyecto_id' => $proyecto, 'nombre' => $a['nombre'],
                'viabilidad_tecnica' => $a['viabilidad'], 'observaciones' => $a['observaciones'],
            ];
            $movimientos = $a['excluidos'];
            foreach ($a['periodos'] as $periodo) {
                array_push($movimientos, ...$periodo['movimientos']);
            }
            foreach ($movimientos as $m) {
                $clave = $m['origen'];
                $origen = $r['origenes'][$clave];
                $numero = (int) substr($clave, 1);
                $datos = [
                    'id' => $numero, 'alternativa_id' => $m['clasificacion'] === 'comun' ? null : $id,
                    'nombre' => $origen['nombre'], 'moneda' => $r['moneda'], 'monto' => $origen['monto'],
                    'tipo_concepto' => $m['tipo'], 'naturaleza_economica' => $m['naturaleza'],
                    'clasificacion_decision' => $m['clasificacion'], 'estado_dato' => $m['estado'],
                    'direccion_otro' => $m['direccion'],
                ];
                if (str_starts_with($clave, 'a')) {
                    $ajustes[$numero] = $datos + [
                        'fecha_flujo' => $m['fecha'], 'observado' => $m['observado'],
                        'justificacion' => $origen['observaciones'], 'id_usuario' => $origen['id_usuario'],
                    ];

                    continue;
                }
                $conceptos[$numero] ??= $datos + [
                    'proyecto_id' => $proyecto, 'descripcion' => $origen['descripcion'], 'observaciones' => $origen['observaciones'],
                    'periodicidad' => $origen['periodicidad'], 'momento_pago' => $origen['momento_pago'],
                    'periodo_inicio' => $origen['inicio'], 'periodo_fin' => $origen['fin'], 'numero_ocurrencias' => $origen['ocurrencias'],
                    'porcentaje_crecimiento' => $origen['crecimiento'], 'activo_id' => $origen['activo_id'] ?? null,
                    'activo_operacion' => $origen['activo_operacion'] ?? null,
                    'fecha_real' => $m['observado'] ? $m['fecha'] : null,
                    'fecha_programada' => ! $m['observado'] && $origen['periodicidad'] === 'unico' ? $m['fecha'] : null,
                    'fuentes' => array_map(fn ($f) => $f + ['importe_observado' => $f['observado'], 'importe_utilizado' => $f['utilizado']], $origen['fuentes']),
                ];
            }
        }
        ksort($conceptos);
        ksort($ajustes);

        return ['alternativas' => $alternativas, 'conceptos' => array_values($conceptos), 'ajustes' => array_values($ajustes)];
    }
}
