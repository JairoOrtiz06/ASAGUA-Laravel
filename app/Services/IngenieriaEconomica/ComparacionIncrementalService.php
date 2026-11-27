<?php

declare(strict_types=1);

namespace App\Services\IngenieriaEconomica;

use Brick\Math\BigDecimal;

class ComparacionIncrementalService
{
    public function __construct(private TirService $tir) {}

    public function interpretar(array $evaluacion): array
    {
        $estados = $candidatas = [];
        $preliminar = ! $evaluacion['equivalencia'] || $evaluacion['escenario']['estado_tasa'] !== 'validada';

        foreach ($evaluacion['alternativas'] as $id => $alternativa) {
            $incluidos = [];
            foreach ($alternativa['periodos'] as $periodo) {
                foreach ($periodo['movimientos'] as $movimiento) {
                    if ($movimiento['motivo'] === null) {
                        $incluidos[] = $movimiento;
                    }
                }
            }

            $costoCeroDocumentado = collect($incluidos)->contains(function (array $m) use ($evaluacion): bool {
                return $m['direccion'] === 'salida' && $m['estado'] === 'confirmado' && BigDecimal::of($m['monto'])->isZero()
                    && collect($evaluacion['origenes'][$m['origen']]['fuentes'] ?? [])->contains(
                        fn (array $f) => ($f['utilizado'] ?? $f['observado'] ?? null) !== null
                            && BigDecimal::of($f['utilizado'] ?? $f['observado'])->isZero()
                    );
            });
            $avisos = $alternativa['avisos'];
            $sinMovimientos = $incluidos === [];
            $costoCero = BigDecimal::of($alternativa['indicadores']['vpc'])->isZero();
            $insuficiente = $sinMovimientos || ($costoCero && ! $costoCeroDocumentado)
                || collect($avisos)->contains(fn (string $aviso) => str_contains($aviso, 'quedó fuera del resumen')
                    || str_contains($aviso, 'pendiente de clasificación') || str_starts_with($aviso, 'Justifica la valoración'));
            $tecnica = $this->restriccionTecnica([$alternativa['viabilidad']], $evaluacion['equivalencia']);
            $inviable = $alternativa['viabilidad'] === 'no_viable';
            $etiquetas = [];
            if ($tecnica) {
                $etiquetas[] = $tecnica;
            } else {
                $etiquetas[] = 'Viable y comparable.';
            }
            if ($sinMovimientos) {
                $etiquetas[] = 'Sin movimientos incluidos en esta perspectiva. El cero calculado no acredita ausencia de costos.';
            }
            if ($insuficiente) {
                $etiquetas[] = 'Información económica insuficiente para ordenar esta alternativa por costo.';
            } elseif ($costoCero) {
                $etiquetas[] = 'Costo cero documentado en los flujos incluidos y en su fuente.';
            }
            $estados[$id] = ['etiquetas' => $etiquetas, 'insuficiente' => $insuficiente];

            if (! $inviable) {
                $preliminar = $preliminar || $tecnica !== null || $insuficiente || $avisos !== [];
                if (! $insuficiente) {
                    $candidatas[$id] = $alternativa;
                }
            }
        }

        $conclusiones = [];
        if (count($candidatas) >= 2) {
            foreach (['vpc' => 'VPC', 'caue' => 'CAUE'] as $clave => $titulo) {
                $valores = array_map(fn ($a) => BigDecimal::of($a['indicadores'][$clave]), $candidatas);
                $minimo = BigDecimal::min(...array_values($valores));
                $nombres = array_column(array_intersect_key($candidatas, array_filter($valores, fn ($v) => $v->isEqualTo($minimo))), 'nombre');
                $unidad = $evaluacion['moneda'].($clave === 'caue' ? '/año' : '');
                $conclusiones[] = 'Menor '.$titulo.' bajo los supuestos registrados: '.implode(', ', $nombres).' · '
                    .DecimalFinanciero::dinero($minimo).' '.$unidad.'.';
                foreach ($candidatas as $id => $alternativa) {
                    $diferencia = $valores[$id]->minus($minimo);
                    if ($diferencia->isPositive()) {
                        $conclusiones[] = $alternativa['nombre'].' supera el menor '.$titulo.' por '
                            .DecimalFinanciero::dinero($diferencia).' '.$unidad.'.';
                    }
                }
            }
        } else {
            $conclusiones[] = 'Se requieren al menos dos alternativas con información económica suficiente para identificar el menor costo.';
        }

        if ($preliminar) {
            $conclusiones[] = 'Comparación preliminar. La recomendación queda pendiente de confirmar los supuestos, la TMAR y la comparabilidad que correspondan.';
        } elseif (count($candidatas) >= 2) {
            $conclusiones[] = 'El menor VPC y CAUE identifican la opción de menor costo entre los servicios comparables. Si los beneficios difieren, revisa también el VAN incremental.';
        }

        $pares = [];
        foreach ($evaluacion['comparaciones'] as $par) {
            $a = $par['id_a'];
            $b = $par['id_b'];
            $tecnica = $this->restriccionTecnica([
                $evaluacion['alternativas'][$a]['viabilidad'], $evaluacion['alternativas'][$b]['viabilidad'],
            ], $evaluacion['equivalencia']);
            $pares[$a][$b] = $tecnica ?? match (true) {
                $estados[$a]['insuficiente'] || $estados[$b]['insuficiente'] => 'Información económica insuficiente. La diferencia matemática se conserva para consulta.',
                $preliminar => 'Comparación preliminar bajo los supuestos registrados.',
                default => $par['criterio'],
            };
        }

        return ['alternativas' => $estados, 'conclusiones' => $conclusiones, 'pares' => $pares, 'candidatas' => array_keys($candidatas), 'preliminar' => $preliminar];
    }

    public function restriccionTecnica(array $viabilidades, bool $equivalencia): ?string
    {
        return match (true) {
            in_array('no_viable', $viabilidades, true) => 'Opción técnicamente inviable. Se conservan las cifras para consulta y se excluye la opción de la recomendación.',
            in_array('pendiente', $viabilidades, true) => 'La viabilidad técnica está pendiente. La diferencia de costos es informativa.',
            ! $equivalencia => 'Comparabilidad pendiente de confirmar. Revisa equivalencia de servicio, horizonte y condiciones aplicables.',
            default => null,
        };
    }

    public function comparar(array $alternativas, int $referencia, bool $equivalenciaConfirmada): array
    {
        $base = $alternativas[$referencia];
        $comparaciones = [];
        foreach ($alternativas as $id => $alternativa) {
            if ($id === $referencia) {
                continue;
            }

            $netos = $filas = [];
            $total = BigDecimal::zero();
            foreach ($alternativa['periodos'] as $numero => $a) {
                $b = $base['periodos'][$numero];
                $propioA = BigDecimal::of($a['neto'])->minus($a['neto_comun']);
                $propioB = BigDecimal::of($b['neto'])->minus($b['neto_comun']);
                $neto = $propioA->minus($propioB);
                $vp = $neto->multipliedBy($a['factor']);
                $total = $total->plus($vp);
                $netos[] = $neto;
                $filas[$numero] = [
                    'desde' => $a['desde'], 'hasta' => $a['hasta'],
                    'a' => (string) $propioA, 'b' => (string) $propioB,
                    'comun_a' => $a['neto_comun'], 'comun_b' => $b['neto_comun'],
                    'neto' => (string) $neto, 'factor' => $a['factor'], 'vp' => (string) $vp,
                ];
            }

            $soloCostos = BigDecimal::of($alternativa['indicadores']['vp_beneficios'])->isZero()
                && BigDecimal::of($base['indicadores']['vp_beneficios'])->isZero();
            $viabilidades = [$alternativa['viabilidad'], $base['viabilidad']];
            $criterio = $this->restriccionTecnica($viabilidades, $equivalenciaConfirmada) ?? ($soloCostos
                ? 'Para servicios equivalentes, menor VPC y CAUE indican menor costo en el horizonte común.'
                : 'Un VAN incremental positivo indica mayor valor presente para A bajo los supuestos registrados.');

            $comparaciones[] = [
                'id_a' => $id, 'id_b' => $referencia, 'van' => (string) $total,
                'diferencia_vpc' => (string) BigDecimal::of($alternativa['indicadores']['vpc'])->minus($base['indicadores']['vpc']),
                'diferencia_caue' => (string) BigDecimal::of($alternativa['indicadores']['caue'])->minus($base['indicadores']['caue']),
                'criterio' => $criterio, 'solo_costos' => $soloCostos,
                'periodos' => $filas, 'tir' => $this->tir->resolver($netos),
            ];
        }

        return $comparaciones;
    }
}
