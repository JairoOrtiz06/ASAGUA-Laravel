<?php

declare(strict_types=1);

namespace App\Services\IngenieriaEconomica;

use Brick\Math\BigDecimal;

class ProcedimientoFinanciero
{
    public function __construct(
        private ConversionTasasService $tasas,
        private InteresCompuestoService $interes,
        private FactoresFinancierosService $factores,
        private SeriesUniformesService $series,
    ) {}

    public function calcular(string $herramienta, array $datos): array
    {
        $origen = $datos['periodicidad_tasa'];
        $nominal = $datos['modalidad'] === 'nominal';
        $destino = $datos['destino'] ?? $datos['periodicidad'];
        $modalidadDestino = $datos['modalidad_destino'] ?? 'efectiva';
        $mOrigen = $this->tasas->frecuencia($origen, $datos['modalidad']);
        $mDestino = $this->tasas->frecuencia($destino, $modalidadDestino);
        $periodica = $this->tasas->efectivaPeriodica((string) $datos['tasa'], $datos['modalidad'], $origen);
        $tasa = $this->tasas->convertir((string) $datos['tasa'], $datos['modalidad'], $origen, $destino);
        $pasos = [
            $nominal
                ? "i_origen = j/$mOrigen = {$datos['tasa']}/$mOrigen ≈ ".DecimalFinanciero::mostrar($periodica, 16)
                : "i_origen = {$datos['tasa']} con $mOrigen período(s) por año.",
            'i_destino = (1 + '.DecimalFinanciero::mostrar($periodica, 16).")^($mOrigen/$mDestino) − 1 ≈ ".DecimalFinanciero::mostrar($tasa, 16),
        ];

        if ($herramienta === 'tasas') {
            return $this->conversion($pasos, $tasa, $destino, $modalidadDestino, $mDestino, $nominal);
        }

        $resultado = match ($herramienta) {
            'interes-compuesto' => $this->capital($datos, $tasa),
            'factores' => $this->tablaFactores($datos, $tasa),
            'series' => $this->serie($datos, $tasa),
        };
        $resultado['pasos'] = [...$pasos, ...$resultado['pasos']];

        return $resultado;
    }

    private function conversion(array $pasos, BigDecimal $tasa, string $destino, string $modalidad, int $frecuencia, bool $origenNominal): array
    {
        $formula = 'i_destino = (1 + i_origen)^(m_origen/m_destino) − 1';
        if ($origenNominal) {
            $formula = 'i_origen = j/m_origen. '.$formula;
        }
        if ($modalidad === 'nominal') {
            $pasos[] = 'j_destino = '.$frecuencia.' × '.DecimalFinanciero::mostrar($tasa, 16);
            $formula .= '. j_destino = m_destino × i_destino';
            $tasa = $tasa->multipliedBy($frecuencia);
        }

        $periodicidad = strtolower(ConversionTasasService::PERIODOS[$destino]);

        return [
            'formula' => $formula,
            'pasos' => $pasos,
            'valor' => DecimalFinanciero::mostrar($tasa, 16).' ('.DecimalFinanciero::mostrar($tasa->multipliedBy(100), 14).' %)',
            'simbolos' => 'i es la tasa efectiva por período, j es la tasa nominal anual y m es la cantidad de períodos por año.',
            'interpretacion' => $modalidad === 'nominal'
                ? "Tasa nominal anual equivalente con capitalización $periodicidad."
                : "Tasa efectiva equivalente con periodicidad $periodicidad.",
        ];
    }

    private function capital(array $datos, BigDecimal $tasa): array
    {
        $capital = BigDecimal::of($datos['cantidad']);
        $n = (int) $datos['periodos'];
        $esFuturo = $datos['operacion'] === 'futuro';
        $i = DecimalFinanciero::mostrar($tasa, 16);
        $crecimiento = "(1 + $i)^$n";
        $valor = $esFuturo
            ? $this->interes->valorFuturo($capital, $tasa, $n)
            : $this->interes->valorPresente($capital, $tasa, $n);

        return [
            'formula' => $esFuturo ? 'F = P(1+i)^n' : 'P = F/(1+i)^n',
            'pasos' => [$esFuturo ? "F ≈ $capital × $crecimiento" : "P ≈ $capital ÷ $crecimiento"],
            'valor' => DecimalFinanciero::dinero($valor),
            'simbolos' => 'P es el capital en t = 0, F es su equivalente en t = n, i es la tasa efectiva del período y n es el número de períodos.',
            'interpretacion' => $esFuturo ? "Capital equivalente en t = $n." : 'Capital equivalente en t = 0.',
        ];
    }

    private function tablaFactores(array $datos, BigDecimal $tasa): array
    {
        $n = (int) $datos['periodos'];
        $i = DecimalFinanciero::mostrar($tasa, 16);
        $factores = $this->factores->calcular($tasa, $n);
        $formulas = $tasa->isZero() ? FactoresFinancierosService::LIMITES_TASA_CERO : FactoresFinancierosService::FORMULAS;
        $tabla = [];
        foreach ($factores as $codigo => $factor) {
            $expresion = strtr($formulas[$codigo], ['i' => "($i)", 'n' => (string) $n]);
            $tabla[$codigo] = [
                'nombre' => FactoresFinancierosService::NOMBRES[$codigo],
                'formula' => $formulas[$codigo],
                'sustitucion' => $expresion,
                'valor' => DecimalFinanciero::mostrar($factor, 16),
            ];
        }

        $resultado = [
            'formula' => 'Resultado equivalente = cantidad × factor',
            'pasos' => ["i ≈ $i y n = $n. Se sustituyen en la fórmula de cada factor."],
            'simbolos' => 'F es valor futuro, P es valor presente, A es pago uniforme, i es tasa efectiva por período y n es número de períodos.',
            'interpretacion' => 'Los factores con A corresponden a pagos al final de cada período.',
            'tabla' => $tabla,
        ];
        if (isset($datos['factor'])) {
            $codigo = $datos['factor'];
            $cantidad = BigDecimal::of($datos['cantidad']);
            $resultado['pasos'][] = "$cantidad × $codigo ≈ $cantidad × ".$tabla[$codigo]['valor'];
            $resultado['valor'] = DecimalFinanciero::dinero($cantidad->multipliedBy($factores[$codigo]));
        }

        return $resultado;
    }

    private function serie(array $datos, BigDecimal $tasa): array
    {
        $operacion = $datos['operacion'];
        $base = $datos['valor_base'] ?? $operacion;
        $primero = $this->series->primerPago($datos['tipo_serie'], isset($datos['primer_pago']) ? (int) $datos['primer_pago'] : null);
        $cantidad = BigDecimal::of($datos['cantidad']);
        $n = $operacion === 'periodos'
            ? $this->series->periodos($base, $cantidad, BigDecimal::of($datos['objetivo']), $tasa, $primero)
            : (int) $datos['periodos'];

        $simbolo = $base === 'presente' ? 'P' : 'F';
        $i = DecimalFinanciero::mostrar($tasa, 16);
        $traslado = match (true) {
            $primero === 0 => ' × (1+i)',
            $primero > 1 && $base === 'presente' => ' ÷ (1+i)^(k−1)',
            default => '',
        };
        $factorFormula = $tasa->isZero()
            ? 'K = n'
            : "K = ($simbolo/A, i, n)$traslado; $simbolo/A = ".FactoresFinancierosService::FORMULAS["$simbolo/A"];
        $formula = match ($operacion) {
            'anualidad' => "A = $simbolo/K",
            'periodos' => "$simbolo/A = K(n), donde $simbolo/A aquí es el valor objetivo dividido por el pago",
            default => "$simbolo = A × K",
        };
        $pasos = ["Primer pago en t = $primero. Los pagos siguientes se separan por un período."];
        if ($operacion === 'periodos') {
            $pasos[] = "Se busca un entero n entre 1 y ".DecimalFinanciero::MAX_PERIODOS." tal que $cantidad × K(n) = {$datos['objetivo']}.";
        }
        $simbolos = 'P es el valor en t = 0, F es el valor en la fecha futura indicada, A es el importe de cada pago, k es el primer período de pago, i es la tasa efectiva y K es el factor de la serie.';
        if ($n === null) {
            return [
                'formula' => "$factorFormula. $formula",
                'pasos' => $pasos,
                'valor' => 'Sin solución entera en el rango admitido',
                'simbolos' => $simbolos,
                'interpretacion' => 'Con estos datos, ningún número entero de pagos del rango iguala el valor objetivo.',
            ];
        }

        $ultimo = $primero + $n - 1;
        $fechaFutura = $primero === 0 ? $n : $ultimo;
        $fecha = $base === 'presente' ? 0 : $fechaFutura;
        $factor = $this->series->factor($base, $tasa, $n, $primero);
        $factorMostrado = DecimalFinanciero::mostrar($factor, 16);
        $pasos[] = "Último pago en t = $ultimo. Valuación en t = $fecha.";
        $nTexto = (string) $n;
        $sustitucion = $tasa->isZero()
            ? $nTexto
            : strtr(FactoresFinancierosService::FORMULAS["$simbolo/A"], ['i' => "($i)", 'n' => $nTexto]).match (true) {
                $primero === 0 => " × (1 + $i)",
                $primero > 1 && $base === 'presente' => " ÷ (1 + $i)^".($primero - 1),
                default => '',
            };
        $pasos[] = "K ≈ ($sustitucion) ≈ $factorMostrado.";
        if ($operacion !== 'periodos') {
            $pasos[] = $operacion === 'anualidad' ? "A ≈ $cantidad ÷ $factorMostrado" : "$simbolo ≈ $cantidad × $factorMostrado";
        }
        $valor = match ($operacion) {
            'anualidad' => DecimalFinanciero::dinero(DecimalFinanciero::dividir($cantidad, $factor)),
            'periodos' => "$n ".($n === 1 ? 'pago' : 'pagos'),
            default => DecimalFinanciero::dinero($cantidad->multipliedBy($factor)),
        };

        return [
            'formula' => "$factorFormula. $formula",
            'pasos' => $pasos,
            'valor' => $valor,
            'simbolos' => $simbolos,
            'interpretacion' => $operacion === 'anualidad'
                ? 'Importe uniforme de cada pago para alcanzar el valor indicado.'
                : "Equivalencia de la serie en t = $fecha.",
        ];
    }
}
