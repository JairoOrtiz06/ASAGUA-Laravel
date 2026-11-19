# Migración del estado de resultados

## Comportamiento trasladado

- Se reutilizan los movimientos del libro diario, por lo que se incluyen partidas ordinarias y de ajuste.
- Las cuentas de tipo `Ingreso` se calculan como `Haber - Debe`.
- Las cuentas de tipo `Gasto` se calculan como `Debe - Haber`.
- Las cuentas se agrupan por nombre y solo se presentan saldos distintos de cero.
- La utilidad o pérdida se calcula como `total_ingresos - total_gastos`.
- `utilidad_neta` se presenta como importe absoluto y `es_utilidad` indica si el resultado original es mayor o igual a cero, igual que Python.
- `RESULTADOS DEL EJERCICIO` no se incluye en ingresos ni gastos porque en el catálogo original es una cuenta de patrimonio.

El código Python no devuelve una colección separada de costos: todas las cuentas de tipo `Gasto` aparecen en la sección de gastos. La clasificación especial de costos solo se utiliza en el balance general y no se inventó una separación adicional.

## Archivos

- `app/Services/LibroContableService.php`: método `estadoResultados()` y formato monetario con soporte para importes negativos.
- `app/Http/Controllers/EstadoResultadosController.php`.
- `resources/views/balances/resultados.blade.php`.
- `routes/web.php` y enlaces de navegación.
- `tests/Feature/EstadoResultadosTest.php`.

La consulta es de solo lectura y está protegida para `Contador` y `Auditor`. No se crearon migraciones ni se modificaron registros contables.

La exportación PDF existente en Python queda pendiente de una decisión sobre la biblioteca PDF de Laravel, ya que el proyecto Laravel no contiene actualmente un generador PDF instalado.
