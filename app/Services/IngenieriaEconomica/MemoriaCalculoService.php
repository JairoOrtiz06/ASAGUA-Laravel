<?php

declare(strict_types=1);

namespace App\Services\IngenieriaEconomica;

use Brick\Math\BigDecimal;

class MemoriaCalculoService
{
    public const NOMBRES = [
        'van' => 'Valor actual neto (VAN/VPN)',
        'vpc' => 'Valor presente de costos (VPC)',
        'caue' => 'Costo anual uniforme equivalente (CAUE)',
        'rcb' => 'Relación beneficio/costo (RCB)',
        'tir' => 'Tasa interna de retorno (TIR)',
    ];

    public const ESTADOS_TIR = [
        'unica' => 'TIR calculable · raíz única detectada',
        'posibles_multiples' => 'Posibles raíces múltiples',
        'sin_raiz' => 'Sin raíz detectada en el dominio analizado',
        'insuficiente' => 'TIR no calculable para estos flujos · no aplicable por su estructura',
        'no_convergencia' => 'El método no convergió en todos los intervalos candidatos',
    ];

    public static function estadoTir(array $tir): string
    {
        return count($tir['raices']) > 1
            ? 'Múltiples raíces detectadas'
            : self::ESTADOS_TIR[$tir['estado']];
    }

    public function describir(array $alternativa, array $escenario): array
    {
        $i = $alternativa['indicadores'];
        $beneficios = $this->importe($i['vp_beneficios']);
        $costos = $this->importe($i['vpc']);
        $ap = DecimalFinanciero::mostrar(BigDecimal::of($i['factor_ap']), 16);
        $anual = DecimalFinanciero::mostrar(BigDecimal::of($escenario['anual']), 16);
        $n = $escenario['horizonte'];
        $factor = BigDecimal::of($escenario['anual'])->isZero()
            ? "A/P = 1/$n"
            : "A/P = ($anual × (1 + $anual)^$n) / ((1 + $anual)^$n − 1)";
        $tir = $i['tir'];

        return [
            'van' => [
                'formula' => 'VAN = Σ FCₜ × P/F, con P/F = (1+i)⁻ᵗ',
                'variables' => 'FCₜ es beneficio menos costo en t. i es la tasa efectiva de cada período. El factor de t = 0 es 1.',
                'sustitucion' => "VP de beneficios − VP de costos ≈ $beneficios − $costos",
                'resultado' => $this->importe($i['van']),
                'interpretacion' => BigDecimal::of($i['vp_beneficios'])->isZero()
                    ? 'Con flujos de solo costos, un VAN negativo expresa el costo neto en la fecha base. Usar VPC y CAUE para comparar servicios equivalentes.'
                    : 'Valor presente de beneficios menos costos a la TMAR aplicada. Su signo indica si el valor neto calculado supera, iguala o queda por debajo de cero.',
                'columnas' => ['neto' => 'Flujo neto', 'factor' => 'Factor P/F', 'vp' => 'Valor presente'],
            ],
            'vpc' => [
                'formula' => 'VPC = Σ Cₜ × (1+i)⁻ᵗ',
                'variables' => 'Cₜ es costo positivo en el período t. i es la tasa efectiva del período.',
                'sustitucion' => 'Suma de los valores presentes positivos de la columna VP de costos.',
                'resultado' => $costos,
                'interpretacion' => 'Suma de los costos incluidos descontados a la fecha base. Para servicios equivalentes, un menor VPC indica menor costo presente.',
                'columnas' => ['costos' => 'Costo', 'factor' => 'Factor P/F', 'vp_costos' => 'VP de costos'],
            ],
            'caue' => [
                'formula' => 'CAUE = VPC × A/P, con A/P = '.FactoresFinancierosService::FORMULAS['A/P'].' o 1/n si i = 0',
                'variables' => 'i es la tasa efectiva anual. n es el horizonte de análisis en años. VPC es costo positivo.',
                'sustitucion' => "$factor ≈ $ap. CAUE ≈ $costos × $ap",
                'resultado' => $this->importe($i['caue']),
                'interpretacion' => 'Costo anual equivalente de los costos incluidos. Permite comparar servicios equivalentes sobre una base anual. Los rescates monetarios se reflejan como beneficios en el VAN y la RCB.',
                'columnas' => ['costos' => 'Costo', 'factor' => 'Factor P/F', 'vp_costos' => 'Contribución al VPC'],
            ],
            'rcb' => [
                'formula' => 'RCB = VP(Beneficios) / VP(Costos)',
                'variables' => 'Se descuentan beneficios y costos brutos por separado con la misma tasa.',
                'sustitucion' => "VP de beneficios ÷ VP de costos ≈ $beneficios ÷ $costos",
                'resultado' => $i['rcb'] === null ? 'No aplicable con los datos actuales' : DecimalFinanciero::mostrar(BigDecimal::of($i['rcb'])),
                'interpretacion' => $i['rcb'] === null
                    ? 'La RCB requiere beneficios cuantificados y un valor presente de costos positivo. Si faltan movimientos pertinentes, los datos son insuficientes para interpretarla.'
                    : 'Unidades de beneficio presente por cada unidad de costo presente.',
                'columnas' => ['beneficios' => 'Beneficio', 'costos' => 'Costo', 'factor' => 'Factor P/F', 'vp_beneficios' => 'VP beneficios', 'vp_costos' => 'VP costos'],
            ],
            'tir' => [
                'formula' => '0 = Σ FCₜ/(1+r)ᵗ',
                'variables' => 'r es una tasa efectiva de la periodicidad evaluada. Los flujos tienen signo y t = 0 se conserva.',
                'sustitucion' => "Búsqueda en [{$tir['dominio'][0]}, {$tir['dominio'][1]}], tolerancia {$tir['tolerancia']} en la tasa y hasta {$tir['max_iteraciones']} iteraciones por intervalo.",
                'resultado' => self::estadoTir($tir),
                'interpretacion' => 'La búsqueda por malla y bisección puede omitir raíces muy próximas. Los candidatos tangentes y posibles raíces múltiples se informan. Una TIR individual no ordena alternativas de solo costos.',
                'columnas' => ['neto' => 'FC usado en la búsqueda'],
            ],
        ];
    }

    public function importe(string $valor): string
    {
        return DecimalFinanciero::dinero(BigDecimal::of($valor));
    }
}
