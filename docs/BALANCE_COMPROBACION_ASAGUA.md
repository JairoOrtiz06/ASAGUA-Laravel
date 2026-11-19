# Migración del balance de comprobación

## Reglas trasladadas

El módulo reutiliza `LibroContableService::diario()` y agrupa sus movimientos por `codigo_cuenta` y `nombre_cuenta`, como `contabilidad.balance_comprobacion()` en Python. Solo participan cuentas que tienen al menos un detalle en los movimientos filtrados; no se agregan cuentas del catálogo sin movimientos.

Para cada cuenta:

- `Debe` y `Haber` son las sumas de todos sus detalles.
- `saldo_deudor = Debe - Haber` si la diferencia es positiva; en otro caso es `0.00`.
- `saldo_acreedor = Haber - Debe` si la diferencia es negativa; en otro caso es `0.00`.

Por ejemplo, una cuenta con Debe `20.00` y Haber `100.00` produce saldo deudor `0.00` y saldo acreedor `80.00`; no se conserva el Debe bruto como saldo deudor. Esta clasificación reproduce literalmente la fórmula Python.

Los totales se calculan sobre las filas agrupadas y se comparan con la misma lógica del formulario Python. La vista también muestra los conceptos de las partidas agrupadas para conservar la trazabilidad de movimientos ordinarios y de ajuste. Los importes se calculan en centavos enteros, sin `float`.

Las partidas ordinarias y las partidas con marcador `[AJUSTE:<tipo>]` participan conjuntamente. Las consultas son de solo lectura y no alteran partidas, detalles ni saldos almacenados.

## Archivos principales

- `app/Services/LibroContableService.php`: método `balanceComprobacion()` reutilizando el diario.
- `app/Http/Controllers/BalanceComprobacionController.php`
- `app/Http/Requests/LibroRequest.php`: filtros y autorización.
- `resources/views/balances/comprobacion.blade.php`
- `tests/Feature/BalanceComprobacionTest.php`

`Contador` y `Auditor` pueden consultar el balance; `Admin` no tiene acceso, conforme a los dashboards Python. No se crearon migraciones ni se modificó el libro mayor.
