# Decisiones de la estructura inicial de ASAGUA

Estas migraciones implementan una base PostgreSQL de destino para ASAGUA. No representan una lectura confirmada del DDL original: el proyecto Python no contiene esquema ni dump.

- Se mantienen las migraciones estándar de Laravel (`users`, sesiones, caché y trabajos). La entidad contable de usuarios vive provisionalmente en `tblusuarios`; el modelo `User` fue configurado para esa tabla.
- Se usan nombres físicos en minúscula (`tblusuarios`, `tblpartidas`, etc.) porque el código Python consulta identificadores PostgreSQL sin comillas. PostgreSQL normaliza esos nombres a minúsculas. Esto evita crear por accidente tablas distintas entre `tblUsuarios` y `tblusuarios`.
- Se usan claves `increments`/enteros porque el código solo demuestra identificadores numéricos y no demuestra `bigint`. Debe ajustarse al DDL real antes de cargar datos si la fuente usa otro tipo.
- Los importes son `decimal(18,2)`, nunca `float`. La precisión 18,2 es provisional y debe compararse con la definición original.
- Los textos sin longitud demostrada se crean como `text` para evitar truncamiento. Los campos con valores cortos conocidos (roles, estados, tipos) tienen longitudes conservadoras provisionales.
- Las fechas de auditoría se modelan como `timestampTz`; `tblpartidas.fecha` se modela como `date` porque el código la trata como fecha contable y la castea a `date` en consultas.
- Los binarios se modelan como `binary`/`bytea` porque Python usa `psycopg.Binary` y recupera bytes.
- Se agregan claves foráneas e índices para representar las relaciones demostradas por los `JOIN` y mejorar las consultas. Sus acciones de borrado son restrictivas para no perder información contable; deben validarse contra el comportamiento histórico antes de una carga real.
- Se agregan unicidades para `email` y `usuario` porque la interfaz exige unicidad lógica. Es una decisión de destino, no una constraint confirmada del origen.
- No se agregan columnas de auditoría Laravel (`created_at`, `updated_at`) a las entidades originales.
- No se insertan usuarios, cuentas, partidas, saldos, reportes ni observaciones ficticias.

## Ejecución pendiente

La configuración `.env` apunta a PostgreSQL, pero el entorno de trabajo no dispone de PHP, Composer, PHPUnit, Docker, Podman ni `psql`. Por ese motivo las migraciones y pruebas quedan creadas, pero no ejecutadas. Deben validarse con:

```text
php artisan migrate
php artisan test
```

en un entorno que tenga PHP 8.2+, las extensiones PostgreSQL y acceso a la base `asagua` de destino.
