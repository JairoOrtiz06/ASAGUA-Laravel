<?php

namespace App\Http\Requests\IngenieriaEconomica;

use App\Models\IngenieriaEconomica\EvidenciaDocumental;
use App\Models\IngenieriaEconomica\VinculoDocumental;
use Illuminate\Validation\Rule;

class EvidenciaDocumentalRequest extends ConfiguracionRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('proyecto'));
    }

    public function rules(): array
    {
        $reglasArchivo = ['file','max:'.max(1, (int) config('ingenieria_documental.max_kib')), 'mimetypes:application/pdf,image/png,image/jpeg'];

        return match (true) {
            $this->routeIs('*.evidencias.desvincular') => ['nota' => ['required', 'string', 'max:4000']],
            $this->routeIs('*.evidencias.adjuntar') => ['archivo' => ['required', ...$reglasArchivo]],
            $this->routeIs('*.evidencias.estado') => [
                'estado' => ['required', Rule::in(['revisado','validado','pendiente'])],
                'nota' => ['required','string','max:4000'],
            ],
            $this->routeIs('*.evidencias.vincular') => [
                'tipo_vinculo' => ['required', Rule::in(array_keys(VinculoDocumental::TIPOS))],
                'destino_id' => ['required', 'integer', 'min:1'],
                'conciliacion' => ['nullable', Rule::in(array_keys(VinculoDocumental::CONCILIACIONES))],
                'nota' => ['required','string','max:4000'],
            ],
            default => [
                'titulo' => ['required','string','max:160'],
                'tipo' => ['required', Rule::in(array_keys(EvidenciaDocumental::TIPOS))],
                'calidad' => ['required', Rule::in(array_keys(EvidenciaDocumental::CALIDADES))],
                'procedencia' => ['required','string','max:250'],
                'fecha_documento' => ['required','date_format:Y-m-d'],
                'observaciones' => ['nullable','string','max:4000'],
                'archivo' => ['nullable', ...$reglasArchivo],
            ],
        };
    }
}
