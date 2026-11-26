<?php

namespace App\Services\IngenieriaEconomica;

use App\Models\CuentaContable;
use App\Models\DetallePartida;
use App\Models\DocumentoFuente;
use App\Models\IngenieriaEconomica\EvidenciaDocumental;
use App\Models\IngenieriaEconomica\Proyecto;
use App\Models\Partida;
use Illuminate\Contracts\Pagination\Paginator;

class ReferenciaDocumentalService
{
    public const CONTABLES = ['cuenta', 'partida', 'detalle', 'documento'];

    public function resolver(Proyecto $proyecto, string $tipo, int $id): ?array
    {
        $item = $this->consulta($proyecto, $tipo)?->find($id);

        return $item ? $this->resumen($tipo, $item) : null;
    }

    public function opciones(Proyecto $proyecto, string $tipo, string $busqueda): ?Paginator
    {
        $consulta = $this->consulta($proyecto, $tipo);
        if (! $consulta) {
            return null;
        }
        if ($busqueda !== '') {
            $patron = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $busqueda).'%';
            $consulta->where(function ($q) use ($tipo, $patron, $busqueda) {
                match ($tipo) {
                    'cuenta' => $q->whereLike('codigo_cuenta', $patron)->orWhereLike('nombre_cuenta', $patron),
                    'partida' => $q->whereLike('concepto', $patron),
                    'detalle' => $q->whereLike('descripcion', $patron)
                        ->orWhereHas('partida', fn ($p) => $p->whereLike('concepto', $patron))
                        ->orWhereHas('cuenta', fn ($c) => $c->whereLike('nombre_cuenta', $patron)->orWhereLike('codigo_cuenta', $patron))
                        ->orWhereHas('documento', fn ($d) => $d->whereLike('nombre_documento', $patron)),
                    'documento' => $q->whereLike('nombre_documento', $patron),
                    'evaluacion' => $q->whereLike('instantanea->escenario->nombre', $patron),
                    'informe' => $q->whereLike('folio', $patron)->orWhereLike('instantanea->evaluacion->proyecto->nombre', $patron),
                    default => $q->whereLike('nombre', $patron),
                };
                if (ctype_digit($busqueda) && strlen($busqueda) <= 9) {
                    $q->orWhere($tipo === 'partida' ? 'numero_partida' : $q->getModel()->getKeyName(), (int) $busqueda);
                    if ($tipo === 'detalle') {
                        $q->orWhereHas('partida', fn ($p) => $p->where('numero_partida', (int) $busqueda));
                    }
                }
            });
        }

        $pagina = $consulta->orderByDesc($consulta->getModel()->getKeyName())->simplePaginate(12, ['*'], 'pagina');
        $pagina->setCollection($pagina->getCollection()->map(fn ($item) => $this->resumen($tipo, $item)));

        return $pagina;
    }

    private function consulta(Proyecto $proyecto, string $tipo)
    {
        return match ($tipo) {
            'proyecto' => Proyecto::query()->whereKey($proyecto->id),
            'alternativa' => $proyecto->alternativas(),
            'escenario' => $proyecto->escenarios(),
            'concepto' => $proyecto->conceptos()->with('alternativa:id,nombre'),
            'activo' => $proyecto->activos()->with('alternativa:id,nombre'),
            'evaluacion' => $proyecto->evaluaciones()->select(['id', 'created_at', 'instantanea->escenario->nombre as escenario_historico']),
            'sensibilidad' => $proyecto->analisisSensibilidad()->select(['id','nombre','created_at']),
            'informe' => $proyecto->informesEconomicos()->select(['id','folio','created_at','instantanea->evaluacion->proyecto->nombre as proyecto_historico']),
            'cuenta' => CuentaContable::query(),
            'partida' => Partida::query()->withSum('detalles as debe_total', 'debe')->withSum('detalles as haber_total', 'haber'),
            'detalle' => DetallePartida::with([
                'partida:id_partida,numero_partida,fecha,concepto', 'cuenta:id_cuenta,codigo_cuenta,nombre_cuenta',
                'documento:id_documento,nombre_documento',
            ]),
            'documento' => DocumentoFuente::query()->select(['id_documento','nombre_documento','fecha_creacion']),
            default => null,
        };
    }

    private function resumen(string $tipo, $item): array
    {
        $fecha = $item->created_at?->toDateString();

        return ['id' => $item->getKey()] + match ($tipo) {
            'cuenta' => ['etiqueta' => $item->codigo_cuenta.' · '.$item->nombre_cuenta, 'cuenta' => $item->codigo_cuenta],
            'partida' => ['etiqueta' => 'Partida '.$item->numero_partida.' · '.$item->concepto, 'fecha' => $item->fecha?->toDateString(),
                'concepto' => $item->concepto, 'debe' => $item->debe_total, 'haber' => $item->haber_total],
            'detalle' => [
                'etiqueta' => 'Detalle de partida '.$item->partida?->numero_partida.' · '.$item->cuenta?->nombre_cuenta,
                'partida' => $item->partida?->numero_partida, 'fecha' => $item->partida?->fecha?->toDateString(),
                'concepto' => $item->descripcion ?: $item->partida?->concepto,
                'cuenta' => $item->cuenta?->codigo_cuenta.' · '.$item->cuenta?->nombre_cuenta,
                'debe' => $item->debe, 'haber' => $item->haber, 'documento' => $item->documento?->nombre_documento,
            ],
            'documento' => ['etiqueta' => $item->nombre_documento, 'fecha' => $item->fecha_creacion?->toDateString(), 'documento' => $item->nombre_documento],
            'concepto' => ['etiqueta' => $item->nombre, 'alternativa' => $item->alternativa?->nombre ?? 'Compartido',
                'importe' => $item->monto.' '.$item->moneda, 'periodicidad' => $item->periodicidad],
            'activo' => ['etiqueta' => $item->nombre, 'alternativa' => $item->alternativa?->nombre ?? 'Compartido',
                'importe' => $item->costo_adquisicion.' '.$item->moneda],
            'escenario' => ['etiqueta' => $item->nombre, 'fecha' => $item->fecha_base?->toDateString(), 'estado' => $item->estado_tasa],
            'evaluacion' => ['etiqueta' => 'Evaluación #'.$item->id.' · '.($item->escenario_historico ?? 'Escenario'), 'fecha' => $fecha],
            'informe' => ['etiqueta' => 'Informe #'.$item->id.' · '.($item->proyecto_historico ?? $item->folio), 'fecha' => $fecha],
            default => ['etiqueta' => $item->nombre, 'fecha' => $fecha],
        };
    }

    public function faltaRespaldo(Proyecto $proyecto, EvidenciaDocumental $doc, ArchivoDocumentalService $archivos, bool $conciliado = false): ?string
    {
        if ($archivos->evidenciaDisponible($doc->archivo, $doc->sha256)) {
            return null;
        }
        if ($doc->vinculos->contains(fn ($v) => $v->tipo === 'documento'
            && (! $conciliado || $v->conciliacion === 'conciliado')
            && $this->resolver($proyecto, 'documento', $v->destino_id) !== null)) {
            return null;
        }

        return ($doc->archivo ? 'El archivo adjunto no se pudo verificar.' : 'Falta archivo adjunto.')
            .' Vincula un documento contable verificable'.($conciliado ? ' y registra su conciliación documental.' : '.');
    }

    public function fuentesPrevias(Proyecto $proyecto, bool $contables = false): array
    {
        $respaldos = fn ($q) => $q->when($contables, fn ($fuentes) => $fuentes
            ->where(fn ($f) => $f->whereNotNull('id_documento')->orWhereNotNull('id_detalle')));

        return [
            'conceptos' => $proyecto->conceptos()->withWhereHas('fuentes', $respaldos)->orderBy('id')->paginate(15, pageName: 'citas_conceptos')->withQueryString(),
            'activos' => $proyecto->activos()->withWhereHas('fuentes', $respaldos)->orderBy('id')->paginate(15, pageName: 'citas_activos')->withQueryString(),
        ];
    }
}
