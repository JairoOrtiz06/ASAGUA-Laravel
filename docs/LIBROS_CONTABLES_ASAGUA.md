# Migración de libros contables

## Diario general

El diario consulta las líneas de `tbldetalles_partida` junto con sus partidas y cuentas. Incluye partidas ordinarias y ajustes, identifica estos últimos por el marcador `[AJUSTE:<tipo>]`, muestra Debe/Haber, cuenta, descripción y documento asociado, y ordena por fecha de partida, número de partida e identificador de detalle.

La consulta Python de `obtener_diario()` muestra `d.fecha_creacion`; sin embargo, los detalles ordinarios del flujo Python pueden dejar ese campo nulo. Laravel presenta `p.fecha` como fecha contable para que todas las partidas mantengan orden cronológico; los detalles de ajuste siguen conservando `fecha_creacion` en la base de datos.

La salida de `LibroContableService` normaliza la fecha contable al formato `Y-m-d`. Esto evita que el driver de pruebas SQLite agregue `00:00:00` a una columna `DATE` y reproduce la salida de fecha de PostgreSQL/Python, sin modificar el valor almacenado ni el ordenamiento.

## Libro mayor

El mayor filtra una cuenta específica y utiliza la misma base de movimientos que el diario: fecha de partida, número, concepto, detalle, Debe y Haber. El cálculo conserva la lógica Python:

- `saldo = abs(total_debe - total_haber)`.
- Si `total_debe - total_haber >= 0`, la naturaleza es `Deudor`.
- En caso contrario, es `Acreedor`.

Los importes se suman como centavos enteros en Laravel, evitando `float` y manteniendo dos decimales.

## Permisos y persistencia

`Contador` y `Auditor` pueden consultar ambos libros; `Admin` no tiene acceso, igual que los dashboards originales. La generación es exclusivamente de lectura: no inserta ni modifica partidas, detalles, cuentas o ajustes.

Archivos principales:

- `app/Services/LibroContableService.php`
- `app/Http/Controllers/LibroContableController.php`
- `app/Http/Requests/LibroRequest.php`
- `resources/views/libros/`
- `tests/Feature/LibroContableTest.php`
