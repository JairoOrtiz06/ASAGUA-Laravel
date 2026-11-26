<?php

namespace Tests\Unit\IngenieriaEconomica;

use App\Services\IngenieriaEconomica\PuntoEquilibrioService;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\TestCase;

class PuntoEquilibrioTest extends TestCase
{
    public function test_detecta_dos_cruces_y_descarta_funcion_sin_raiz(): void
    {
        $servicio = new PuntoEquilibrioService;
        $doble = $servicio->buscar(BigDecimal::of(0), BigDecimal::of(3),
            fn ($x) => $x->minus('0.7')->multipliedBy($x->minus('1.8')));
        $this->assertCount(2, $doble['cruces']);
        $this->assertEqualsWithDelta(0.7, (float) $doble['cruces'][0]['valor'], 1e-6);
        $this->assertEqualsWithDelta(1.8, (float) $doble['cruces'][1]['valor'], 1e-6);
        $sinRaiz = $servicio->buscar(BigDecimal::of(0), BigDecimal::of(3), fn ($x) => $x->power(2)->plus(1));
        $this->assertSame([], $sinRaiz['cruces']);
    }
}
