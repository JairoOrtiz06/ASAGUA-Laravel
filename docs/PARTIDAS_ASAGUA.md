# Migración del registro de partidas

## Funcionalidad trasladada

El flujo implementado conserva el comportamiento observado en `ProyectoAsagua`:

- El contador captura fecha, concepto y una lista de detalles.
- Cada detalle selecciona una cuenta activa y registra Debe o Haber.
- Una partida requiere al menos un detalle válido.
- El total del Debe y el total del Haber deben ser mayores que cero y exactamente iguales.
- Si la descripción queda vacía, se utiliza el nombre de la cuenta, igual que en el formulario Python.
- El concepto vacío se guarda como `Asiento contable`, que es el valor por defecto del flujo original.
- La partida y todos sus detalles se guardan dentro de una transacción.
- La consulta muestra partidas, sus detalles, cuentas, usuario registrador y totales.

## Decisiones y límites

- Las altas se restringen provisionalmente al rol `Contador`, mientras `Contador` y `Auditor` pueden consultar. Es consistente con los paneles Python observados; la autorización de ajustes, reportes y documentos queda para sus tareas respectivas.
- El detalle ordinario conserva `id_documento = NULL` y `fecha_creacion = NULL`, porque el flujo Python ordinario no los asigna. La vinculación de documentos fuente se implementará junto con ese módulo.
- Los importes se validan como cadenas decimales y se comparan en centavos enteros; no se utiliza `float`.
- El número de partida sigue siendo `MAX(numero_partida) + 1`. En PostgreSQL se bloquea la tabla durante la transacción para evitar duplicados concurrentes. Es una protección adicional de persistencia, sin cambiar la regla de numeración observada.
- Las migraciones actuales usan SQLite en la configuración de pruebas y PostgreSQL en producción. El bloqueo de tabla solo se ejecuta para el driver PostgreSQL.

## Archivos principales

- `app/Http/Requests/PartidaRequest.php`: autorización y validación HTTP.
- `app/Services/PartidaService.php`: reglas contables, normalización monetaria y transacción.
- `app/Http/Controllers/PartidaController.php`: listado, alta y consulta.
- `resources/views/partidas/`: formulario, listado, detalle y fila dinámica.
- `tests/Feature/PartidaTest.php`: altas válidas, descuadre, ausencia de detalles, cuentas inválidas, asociación y ausencia de registros parciales.

## Pendiente de validar en entorno real

La ejecución de PHP/Artisan debe realizarse desde Docker, porque el entorno de trabajo actual no tiene el binario `php`. También debe verificarse en PostgreSQL que el tipo devuelto por `SUM(decimal)` se renderiza con la precisión esperada y que los nombres de las restricciones coinciden con el esquema desplegado.
