# Catálogo de cuentas — Tarea 3

- El código Python confirma consulta del catálogo activo ordenado por `codigo_cuenta`, búsqueda exacta por `nombre_cuenta`, jerarquía mediante `id_cuenta_padre` y uso de `tipo_cuenta`, `nivel` y `estado` en reportes.
- No se encontró en Python una vista o repositorio CRUD de cuentas ni una regla explícita que asigne la mutación a un rol.
- Como decisión provisional de la migración, los tres roles autenticados pueden consultar cuentas activas; la consulta también permite filtrar inactivas o todas. Solo `Admin` puede registrar, editar o eliminar.
- No se agregaron listas cerradas de tipos, categorías ni niveles. El formulario conserva esos valores como campos capturados por el catálogo autorizado.
- La edición impide que una cuenta sea su propio padre o que cree un ciclo jerárquico.
- La eliminación se rechaza si la cuenta tiene subcuentas o detalles contables. La FK de la migración también protege la integridad como segunda barrera.
- No se insertaron cuentas ficticias.
- Las pruebas automatizadas están en `tests/Feature/CuentaContableTest.php`, pero no pudieron ejecutarse porque el entorno actual no tiene PHP. Deben ejecutarse desde Docker o un entorno PHP 8.2+ con PostgreSQL/SQLite de pruebas.
