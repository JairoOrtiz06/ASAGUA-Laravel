# Migración de partidas de ajuste

## Funcionalidad trasladada

El módulo conserva el flujo de `ProyectoAsagua` y utiliza las tablas existentes `tblpartidas` y `tbldetalles_partida`:

- Tipos: `Depreciaciones`, `Gastos acumulados`, `Gastos pagados por anticipado`, `Ingresos acumulados`, `Ingresos diferidos`, `Ajustes de inventario` y `Otros ajustes justificados`.
- Validación de período: inicio menor o igual al fin y fecha del ajuste dentro del período.
- Validación de detalles, cuentas activas, importes no negativos, Debe/Haber excluyentes por línea e igualdad exacta de totales.
- Identificación mediante el concepto `[AJUSTE:<tipo>] <concepto>`, igual que el sistema Python.
- `fecha_creacion` de cada detalle se guarda con la fecha del ajuste; `id_documento` permanece `NULL`, como en el flujo original.
- Registro de cabecera y detalles mediante la transacción común de `PartidaService`, incluyendo bloqueo de numeración PostgreSQL.

## Permisos

El código Python restringe toda la vista de ajustes al rol `Contador`. La aplicación Laravel conserva esa decisión: solo `Contador` puede consultar, registrar y ver ajustes. `Admin` y `Auditor` reciben respuesta prohibida en este módulo; sus consultas contables generales permanecen separadas.

## Archivos principales

- `app/Services/AjusteService.php`: tipos, período, clasificación y preparación del ajuste.
- `app/Http/Requests/AjusteRequest.php`: autorización y validación HTTP.
- `app/Http/Controllers/AjusteController.php`: registro y consulta.
- `app/Models/Partida.php`: scope y lectura de clasificación `[AJUSTE:...]`.
- `resources/views/ajustes/`: formulario, listado, detalle y fila dinámica.
- `tests/Feature/AjusteTest.php`: pruebas de equilibrio, período, cuentas, permisos, transacción y separación de partidas.

No se agregaron tablas ni migraciones nuevas, porque el sistema original persiste ajustes en las mismas tablas de partidas ordinarias.
