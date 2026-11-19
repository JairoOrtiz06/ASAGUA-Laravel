# Migración del balance general

## Reglas trasladadas desde Python

- Las cuentas se clasifican por la jerarquía activa del catálogo y los identificadores originales: 7 activo corriente, 8 activo no corriente, 9 pasivo corriente y 10 patrimonio.
- Activos: `Debe - Haber`.
- Pasivos y patrimonio: `Haber - Debe`.
- Ingresos: `Haber - Debe`.
- Gastos: `Debe - Haber`.
- Los gastos cuyo padre de nivel 3 contiene `COSTO` o es `COMPRAS` se acumulan como costos.
- La utilidad del ejercicio es `ingresos - costos - gastos` y se agrega a `RESULTADOS DEL EJERCICIO` si esa cuenta existe.
- Solo se presentan cuentas con saldo distinto de cero; los totales se calculan sobre todas las cuentas inicializadas.
- Las partidas ordinarias y de ajuste se toman conjuntamente del diario.

## Pasivo no corriente

El código Python inicializa y muestra la subsección de pasivo no corriente en la vista, pero `balance_general()` devuelve `PAS_NO_CORRIENTE` vacío y calcula `total_pasivos` únicamente con pasivo corriente. Laravel reproduce esa particularidad y la muestra explícitamente en la interfaz como una sección que no participa en el total. No se corrigió ni se incorporó al cálculo porque sería un cambio de regla contable original.

## Archivos principales

- `app/Services/LibroContableService.php`: método `balanceGeneral()` reutilizando el diario.
- `app/Http/Controllers/BalanceGeneralController.php`
- `resources/views/balances/general.blade.php`
- `tests/Feature/BalanceGeneralTest.php`

El módulo es de solo lectura y está disponible para `Contador` y `Auditor`; no modifica partidas, detalles ni cuentas y no crea migraciones nuevas.
