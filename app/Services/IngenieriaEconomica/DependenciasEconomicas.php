<?php

namespace App\Services\IngenieriaEconomica;

use App\Models\IngenieriaEconomica\CalculoActivo;
use App\Models\IngenieriaEconomica\Proyecto;

class DependenciasEconomicas
{
    public function vinculado(Proyecto $proyecto, string $tipo, int $id): bool
    {
        return $proyecto->evidenciasDocumentales()->whereHas('vinculos', fn ($q) => $q
            ->where('tipo', $tipo)->where('destino_id', $id))->exists();
    }

    public function enHistorial(Proyecto $proyecto, string $tipo, int $id): bool
    {
        $consultas = [
            [$proyecto->evaluaciones(), []],
            [$proyecto->analisisSensibilidad(), ['base']],
            [$proyecto->informesEconomicos(), ['evaluacion']],
        ];
        if (in_array($tipo, ['activo', 'escenario'], true)) {
            $consultas[] = [CalculoActivo::query()->whereIn('activo_id', $proyecto->activos()->select('id')), ['activo']];
        }

        foreach ($consultas as [$consulta, $contenedor]) {
            foreach ($consulta->select(['id', 'instantanea'])->lazyById(50) as $version) {
                $r = $version->instantanea;
                if (! is_array($r) || ! $this->reconocida($r, $contenedor) || $this->referencia($r, $tipo, $id)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function reconocida(array $r, array $contenedor): bool
    {
        return match ($contenedor[0] ?? '') {
            'activo' => isset($r['activo']['id']) && (isset($r['metodos']) || isset($r['nuevo']) || isset($r['reemplazar'])),
            'base' => isset($r['base']['escenario']['id']) && is_array($r['base']['alternativas'] ?? null),
            'evaluacion' => isset($r['evaluacion']['escenario']['id']) && is_array($r['evaluacion']['alternativas'] ?? null),
            default => isset($r['escenario']['id']) && is_array($r['alternativas'] ?? null) && is_array($r['origenes'] ?? null),
        };
    }

    private function referencia(array $r, string $tipo, int $id): bool
    {
        $capturas = [$r, $r['base'] ?? [], $r['evaluacion'] ?? [], $r['insumo_base'] ?? []];
        foreach ($r['calculos'] ?? [] as $calculo) {
            if (! is_array($calculo['datos'] ?? null)) {
                return true;
            }
            $capturas[] = $calculo['datos'];
        }
        foreach ($capturas as $e) {
            if (! is_array($e)) {
                return true;
            }
            foreach (['alternativas', 'origenes', 'insumos', 'fiscal'] as $campo) {
                if (array_key_exists($campo, $e) && ! is_array($e[$campo])) {
                    return true;
                }
            }
            $insumos = $e['insumos'] ?? [];
            if (array_key_exists('conceptos', $insumos) && ! is_array($insumos['conceptos'])) {
                return true;
            }
            $coincide = match ($tipo) {
                'proyecto' => (int) ($e['proyecto']['id'] ?? 0) === $id,
                'escenario' => (int) ($e['escenario']['id'] ?? 0) === $id,
                'alternativa' => isset($e['alternativas'][$id]) || isset($insumos['alternativas'][$id]),
                'concepto' => isset($e['origenes']['c'.$id]),
                'activo' => (int) ($e['activo']['id'] ?? 0) === $id || (int) ($e['nuevo']['id'] ?? 0) === $id,
                'evidencia' => (int) ($e['fiscal']['fuente']['id'] ?? 0) === $id,
                default => false,
            };
            if ($coincide) {
                return true;
            }
            foreach ($insumos['conceptos'] ?? [] as $concepto) {
                if (($tipo === 'concepto' && (int) ($concepto['id'] ?? 0) === $id)
                    || ($tipo === 'activo' && (int) ($concepto['activo_id'] ?? 0) === $id)) {
                    return true;
                }
            }
            if ($tipo === 'activo') {
                foreach ($e['origenes'] ?? [] as $origen) {
                    if ((int) ($origen['activo_id'] ?? 0) === $id) {
                        return true;
                    }
                }
                foreach ($e['fiscal']['alternativas'] ?? [] as $alternativa) {
                    foreach ($alternativa['periodos'] ?? [] as $periodo) {
                        if (isset($periodo['activos'][$id]) || isset($periodo['ventas'][$id])) {
                            return true;
                        }
                    }
                }
            }
        }

        foreach ($r['evidencias'] ?? [] as $evidencia) {
            if ($tipo === 'evidencia' && (int) ($evidencia['id'] ?? 0) === $id) {
                return true;
            }
            foreach ($evidencia['vinculos'] ?? [] as $vinculo) {
                if (($vinculo['tipo'] ?? null) === $tipo && (int) ($vinculo['destino_id'] ?? 0) === $id) {
                    return true;
                }
            }
        }

        return false;
    }
}
