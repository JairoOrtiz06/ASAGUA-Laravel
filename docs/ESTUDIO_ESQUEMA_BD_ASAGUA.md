# ASAGUA — estudio del esquema original y diseño de migraciones

Fecha: 18 de noviembre de 2026  
Alcance: análisis de solo lectura del código de `ProyectoAsagua` y del estado actual de `asagua`.

## 1. Resultado ejecutivo

El código Python utiliza siete tablas de PostgreSQL:

1. `tblUsuarios`.
2. `tblCuentasContables`.
3. `tblPartidas`.
4. `tblDetalles_partida`.
5. `tblDocumentos_fuente`.
6. `reportes`.
7. `observaciones_reportes`.

No se encontró un archivo `.sql`, dump, backup, archivo SQLite de la aplicación original, `docker-compose`, configuración de PostgreSQL adicional ni esquema en los commits del repositorio Python. La única configuración de conexión encontrada está en `app/config.py` y apunta a `localhost:5433/aurum_financeDB`.

Por tanto, el diseño siguiente distingue:

- **Confirmado:** tabla/columna/nombre utilizado directamente por una consulta o inserción.
- **Inferido:** relación, tipo probable o restricción deducida por el uso, pero no demostrada por DDL.
- **Pendiente:** dato que solo puede confirmarse leyendo metadatos de PostgreSQL o un dump autorizado.

No se crearán migraciones ni tablas hasta obtener esa evidencia.

## 2. Evidencia consultada

Se revisaron todos los módulos Python de `app/models`, `app/services` y `app/views`, las pruebas de ajustes, `app/database.py`, `app/config.py`, archivos de configuración visibles, historial Git y nombres de archivos de todos los commits. El repositorio Laravel solo contiene el esqueleto estándar de Laravel 12: `users`, `password_reset_tokens`, `sessions`, `cache`, `jobs` y `failed_jobs`.

El original contiene cambios locales no confirmados en las vistas de Contador y en los módulos de ajustes. No se alteraron esos archivos.

## 3. Tablas y columnas

### 3.1 `tblUsuarios` / `tblusuarios`

El código usa ambas capitalizaciones. En PostgreSQL, los identificadores no entrecomillados se normalizan a minúsculas, por lo que pueden referirse a la misma tabla; esto debe confirmarse con `pg_class`/`information_schema`.

| Columna | Evidencia | Tipo probable | Nulabilidad/constraint |
|---|---|---|---|
| `id_usuario` | `SELECT`, `WHERE`, `RETURNING` y joins | entero/serial/identity | **PK inferida**, exacto pendiente |
| `nombre` | login, listados, joins, alta/edición | texto/varchar | requerida en UI; DDL pendiente |
| `apellido` | búsqueda, alta/edición | texto/varchar | requerida en UI; DDL pendiente |
| `email` | búsqueda, alta/edición, unicidad comprobada por aplicación | texto/varchar | **único inferido**, no confirmado |
| `usuario` | login, búsqueda, alta/edición, unicidad comprobada por aplicación | texto/varchar | **único inferido**, no confirmado |
| `password` | login BCrypt, alta/edición | texto/varchar | requerida para alta; longitud pendiente |
| `rol` | selección y enrutamiento de dashboard | texto/varchar/enum | valores observados: `Admin`, `Contador`, `Auditor` |
| `activo` | login y desactivación | boolean | comparado con `true`; default pendiente |

Relaciones inferidas:

- `tblUsuarios.id_usuario` → `tblPartidas.id_usuario`.
- `tblUsuarios.id_usuario` → `tblDetalles_partida.id_usuario`.
- `tblUsuarios.id_usuario` → `reportes.id_usuario`.
- `tblUsuarios.id_usuario` → `observaciones_reportes.id_auditor`.
- `tblUsuarios.id_usuario` → `observaciones_reportes.id_contador`.

La aplicación permite eliminación física de usuarios y muestra un posible error si están referenciados. No hay evidencia de `ON DELETE`, `ON UPDATE` ni de una constraint formal.

### 3.2 `tblCuentasContables` / `tblcuentascontables`

| Columna | Evidencia | Tipo probable | Nulabilidad/constraint |
|---|---|---|---|
| `id_cuenta` | selección, joins, `ANY`, `RETURNING` no usado | entero/serial/identity | **PK inferida**, pendiente |
| `codigo_cuenta` | orden, búsqueda visual, reportes | texto/varchar | posible único, no confirmado |
| `nombre_cuenta` | búsquedas, agrupaciones, reportes | texto/varchar | no confirmado |
| `tipo_cuenta` | cálculos de estados | texto/varchar | valores observados: `Activo`, `Pasivo`, `Patrimonio`, `Ingreso`, `Gasto` |
| `nivel` | CTE recursiva y clasificación | entero | valores 3 o mayores usados; sin constraint confirmada |
| `id_cuenta_padre` | CTE autorreferente y clasificación | entero nullable | **FK autorreferente inferida**; nullable probable para raíces |
| `estado` | filtros de cuenta activa | boolean | usado como `true`; default pendiente |

Relación inferida: `id_cuenta_padre` referencia `id_cuenta` dentro de la misma tabla. La jerarquía se recorre con `WITH RECURSIVE`.

La lógica contable usa además IDs concretos de nivel 2 (`7`, `8`, `9`, `10`) y padres de gastos/ingresos (`34`–`41`). Esos valores no son restricciones de base de datos y no deben convertirse en seeders sin validar el catálogo real.

### 3.3 `tblPartidas`

| Columna | Evidencia | Tipo probable | Nulabilidad/constraint |
|---|---|---|---|
| `id_partida` | `RETURNING`, filtros y joins | entero/serial/identity | **PK inferida**, pendiente |
| `id_usuario` | inserción, join con usuarios | entero | **FK inferida** a usuarios |
| `numero_partida` | `MAX()+1`, búsqueda, orden, reportes | entero | posible único, no confirmado |
| `fecha` | inserción y filtros; casteo a `date` | date o timestamp | precisión original pendiente |
| `concepto` | inserción, edición, reportes y marca de ajuste | texto/varchar | requerida funcionalmente |

Relaciones inferidas:

- Una partida pertenece a un usuario.
- Una partida tiene muchos detalles.
- Los ajustes son partidas ordinarias cuyo `concepto` comienza con `[AJUSTE:`; no existe tabla de ajustes en el código.

La numeración ordinaria usa `MAX(numero_partida)+1` sin bloqueo visible. La numeración de ajustes bloquea `tblPartidas` con `SHARE ROW EXCLUSIVE`. No se ha demostrado que `numero_partida` tenga índice único.

### 3.4 `tblDetalles_partida`

| Columna | Evidencia | Tipo probable | Nulabilidad/constraint |
|---|---|---|---|
| `id_detalle` | listados, edición, borrado y orden | entero/serial/identity | **PK inferida**, pendiente |
| `id_partida` | inserción, joins, borrado por partida | entero | **FK inferida** a partidas |
| `id_cuenta` | inserción, joins y validación activa | entero | **FK inferida** a cuentas |
| `id_usuario` | inserción/actualización y joins indirectos | entero nullable posible | **FK inferida** a usuarios; nulabilidad pendiente |
| `id_documento` | inserción, vista de documento | entero nullable | **FK inferida** a documentos; se inserta `None` |
| `descripcion` | inserción, edición y reportes | texto/varchar | no confirmado |
| `debe` | sumas, validación y reportes | numeric/decimal | precisión/scale pendiente; funcionalmente no negativo |
| `haber` | sumas, validación y reportes | numeric/decimal | precisión/scale pendiente; funcionalmente no negativo |
| `fecha_creacion` | selección y filtros del Diario; se inserta explícitamente en ajustes | date/timestamp | default/nulabilidad pendiente |

Reglas de aplicación confirmadas: cada línea no debe tener Debe y Haber positivos a la vez; una línea no puede estar en cero; la partida completa debe cuadrar. No hay evidencia de `CHECK` equivalentes en la base.

Detalle importante: el registro ordinario inserta la línea sin incluir `fecha_creacion`, mientras que el ajuste sí la incluye. Por ello, el DDL podría tener un default, permitir null, o existir una diferencia de comportamiento entre flujos. Debe verificarse antes de definir la migración.

### 3.5 `tblDocumentos_fuente`

| Columna | Evidencia | Tipo probable | Nulabilidad/constraint |
|---|---|---|---|
| `id_documento` | `RETURNING`, consultas y borrado | entero/serial/identity | **PK inferida**, pendiente |
| `nombre_documento` | inserción y listado | texto/varchar | no confirmado |
| `descripcion` | inserción y listado | texto/varchar | puede recibir texto fijo |
| `tipo_documento` | inserción y listado | texto/varchar | valores observados `PDF` u otra extensión/tipo |
| `fecha_creacion` | inserción y listado | date/timestamp | se permite `None` en repositorio |
| `archivo` | `psycopg.Binary`, recuperación como bytes | `bytea` muy probable | puede ser nullable; confirmado solo por uso |

Una partida puede guardar varios documentos, pero el código solo conserva `id_doc` del primer documento en los detalles. Los documentos adicionales se insertan, aunque no se ve una relación que los conecte a la partida si no quedan referenciados por algún detalle. Esto es un posible problema de pérdida lógica de adjuntos y debe resolverse en el modelo destino.

### 3.6 `reportes`

| Columna | Evidencia | Tipo probable | Nulabilidad/constraint |
|---|---|---|---|
| `id` | `RETURNING`, filtros y joins | entero/serial/identity | **PK inferida**, pendiente |
| `id_usuario` | inserción y join | entero | **FK inferida** a usuarios |
| `hora` | listado | timestamp | probablemente default de creación; pendiente |
| `nombre` | inserción y listado | texto/varchar | no confirmado |
| `archivo` | `psycopg.Binary`, descarga | `bytea` muy probable | no confirmado |
| `tiene_observaciones` | `COALESCE`, actualización | boolean | default funcional `false` |
| `estado_revision` | `COALESCE`, actualización y etiquetas | texto/varchar/enum | valores observados `SIN_REVISAR`, `CON_OBSERVACIONES`, `APROBADO`, `RECHAZADO` |

Relación inferida: un reporte pertenece al usuario que lo generó y tiene muchas observaciones.

### 3.7 `observaciones_reportes`

| Columna | Evidencia | Tipo probable | Nulabilidad/constraint |
|---|---|---|---|
| `id_observacion` | `RETURNING`, filtros y actualización | entero/serial/identity | **PK inferida**, pendiente |
| `id_reporte` | inserción, join, conteo | entero | **FK inferida** a `reportes.id` |
| `id_auditor` | inserción y join | entero | **FK inferida** a usuarios |
| `id_contador` | inserción y join | entero | **FK inferida** a usuarios |
| `comentario` | inserción y respuesta visual | texto/text | requerido funcionalmente |
| `prioridad` | inserción y etiquetas | texto/varchar/enum | `ALTA`, `MEDIA`, `BAJA` |
| `fecha_observacion` | listado e historial | timestamp | probablemente default; pendiente |
| `estado` | transición `PENDIENTE` → `VISTA` → `RESUELTA` | texto/varchar/enum | default funcional `PENDIENTE` |
| `fecha_vista` | actualización al marcar vista | timestamp nullable | nullable probable |
| `respuesta_contador` | respuesta | texto nullable | nullable probable |
| `fecha_respuesta` | actualización al responder | timestamp nullable | nullable probable |

Relaciones inferidas: reporte y dos relaciones distintas hacia usuarios, una como auditor y otra como contador. La aplicación actualiza también el estado agregado del reporte al crear una observación.

## 4. Claves, restricciones e índices

### Confirmado por el código

- Las columnas `id_usuario`, `id_cuenta`, `id_partida`, `id_detalle`, `id_documento`, `id` e `id_observacion` son tratadas como identificadores.
- Las inserciones usan `RETURNING` sobre esos identificadores.
- Existen referencias lógicas entre las tablas porque los `JOIN` y los valores insertados las conectan.
- `id_cuenta_padre` es una referencia lógica autorreferente.
- Los campos binarios aceptan bytes PostgreSQL, compatible con `bytea`.
- Las operaciones se ejecutan en transacciones de conexión, y los fallos de una inserción abortan la transacción por el context manager.

### Inferido, no confirmado

- Las claves primarias son los campos `id_*`/`id` indicados.
- Los campos `id_*` son `integer`, `bigint`, `serial` o `identity`.
- Existen claves foráneas reales y qué acción `ON DELETE` tienen.
- `usuario`, `email`, `codigo_cuenta` y `numero_partida` son únicos.
- Existen índices sobre columnas filtradas, de unión o de orden.
- Hay defaults para fechas, booleanos, identificadores y estados.
- Hay `CHECK` para importes, roles, estados o equilibrio.

### No demostrado

- DDL, nombres de secuencias, schemas distintos de `public`, triggers, views, funciones, materialized views, policies RLS, particiones o extensiones.
- Precisión/escala de importes, límites de texto y tamaño de `bytea`.
- Si las tablas se crearon con identificadores entrecomillados sensibles a mayúsculas.

## 5. Propuesta de migraciones Laravel 12

La propuesta es para una **base de destino PostgreSQL separada**. No se debe ejecutar contra la base original sin un plan de corte aprobado.

### Decisión recomendada: esquema de compatibilidad inicial

Para no perder datos, la primera versión debe conservar nombres y claves originales (`tblUsuarios`, `tblCuentasContables`, etc.) y configurar cada modelo Eloquent con `$table` y `$primaryKey` explícitos. El esquema Laravel estándar `users` no debe coexistir como fuente de autenticación salvo que se defina una migración de transformación posterior.

Orden propuesto:

1. `create_tbl_usuarios`.
2. `create_tbl_cuentas_contables`.
3. `create_tbl_documentos_fuente`.
4. `create_tbl_partidas`.
5. `create_tbl_detalles_partida`.
6. `create_reportes`.
7. `create_observaciones_reportes`.
8. Índices y constraints verificadas después de cargar una copia de prueba.

La migración real debe usar tipos solo después de confirmar el DDL. El siguiente mapa es diseño, no una orden de crear columnas:

| Destino propuesto | Tipo Laravel/PostgreSQL a validar |
|---|---|
| Identificadores | `bigInteger` o tipo exacto del origen; no asumir `id()` |
| Texto corto | `string(length)` con longitud tomada del origen |
| Texto largo | `text` si el origen lo confirma |
| Fechas contables | `date` si la columna no conserva hora; si no, `timestampTz` |
| Fechas de auditoría | `timestampTz` |
| Importes | `decimal(precision, scale)` igual al origen |
| Booleanos | `boolean` |
| Binarios | `binary`/`bytea`, según capacidad y política de archivos |

### Constraints que se pueden proponer después de confirmar compatibilidad

- FK de detalles a partidas, cuentas, usuarios y documentos.
- FK de partidas/reportes a usuarios.
- FK de observaciones a reportes y usuarios con nombres explícitos para las dos relaciones de usuario.
- FK autorreferente del catálogo.
- Índices para `id_partida`, `id_cuenta`, `id_usuario`, `id_documento`, `id_reporte`, estados y fechas.
- Unique de `usuario`, `email`, `codigo_cuenta` o `numero_partida` solo si el esquema real y los datos existentes lo soportan.
- Checks de `debe >= 0`, `haber >= 0` y no simultaneidad únicamente después de revisar datos históricos; el equilibrio de una partida requiere trigger o servicio, porque no es un `CHECK` simple entre filas.

### Alternativa posterior: esquema Laravel normalizado

Después de una migración estable se podría transformar a nombres `snake_case` (`users`, `accounts`, `journal_entries`, etc.). No se recomienda hacerlo en la primera etapa: aumenta el riesgo de romper referencias, BLOBs, numeración, historial y compatibilidad con datos antiguos.

## 6. Compatibilidad con Laravel y PostgreSQL

1. **Convenciones Eloquent:** Laravel espera `id`, `created_at`, `updated_at` y nombres plurales. El origen usa claves y nombres diferentes; todos deben declararse explícitamente.
2. **Modelo `User`:** el modelo actual apunta a `users`, `name`, `email` y `password`; debe adaptarse o crear un proveedor/modelo para `tblUsuarios`. `usuario` es el identificador de login real.
3. **Hash BCrypt:** el hash original debe conservarse. No se debe re-hashear un hash existente con el cast `hashed`; hay que verificar compatibilidad y cambiarlo solo en una autenticación exitosa o con un proceso controlado.
4. **Mayúsculas:** las consultas Python no entrecomillan identificadores. Laravel Query Builder puede generar nombres exactos; deben evitarse comillas que hagan distinta `tblUsuarios` de `tblusuarios`.
5. **`MAX()+1`:** produce carreras concurrentes. En Laravel debe sustituirse por una secuencia/identity o una estrategia transaccional compatible, pero la numeración resultante debe validarse con el negocio.
6. **Transacciones incompletas:** la creación ordinaria guarda varios adjuntos pero referencia solo el primero. El diseño destino debe preservar todos, posiblemente con una tabla pivote `partida_documento`, pero esa tabla sería una mejora nueva y requiere aprobación.
7. **Importes:** Python mezcla `float`, `numpy` y `Decimal`; Laravel debe operar con `decimal`/strings para evitar errores de redondeo.
8. **Rangos de fecha:** el Python usa fecha final inclusiva mediante `< fin + 1 día` en algunos reportes; debe mantenerse el mismo contrato.
9. **CTE recursivas:** PostgreSQL las soporta; Eloquent no debe ocultar esta lógica en relaciones ingenuas. Usar Query Builder/SQL encapsulado en servicios cuando sea necesario.
10. **BLOBs:** PostgreSQL `bytea` puede hacer crecer la base; la alternativa Storage requiere conservar checksum, MIME, nombre y relación. No migrar a archivos externos sin una política de recuperación.
11. **Estados libres:** roles, prioridades y estados se almacenan como texto según el código. Convertirlos a enums/checks puede rechazar valores históricos no conocidos.
12. **Eliminación física:** añadir FK restrictivas puede cambiar el comportamiento actual. Primero hay que inventariar referencias y decidir si se conserva eliminación física o se vuelve lógica.
13. **Sesión del Libro Diario:** el Python mantiene filtros/DataFrame en memoria; en Laravel debe persistirse el filtro en sesión o recalcularse, sin confiar en el cliente.

## 7. Estrategia de traslado de datos

### Fase A — extracción no destructiva

Solicitar al administrador de PostgreSQL un dump de esquema y datos, o una extracción consistente con `pg_dump --schema-only` y `pg_dump --data-only` hacia archivos entregados fuera del repositorio. Si no se puede entregar el dump completo, solicitar al menos:

- `information_schema.columns`.
- `table_constraints`, `key_column_usage` y `referential_constraints`.
- `pg_indexes`.
- `pg_sequences`.
- Conteo y sumas de cada tabla.
- Muestras anonimizadas de datos y catálogo.

Estas operaciones deben ejecutarse por el responsable de la base con permisos de solo lectura; este análisis no las ejecutó.

### Fase B — staging y mapeo

1. Crear una base PostgreSQL de staging separada.
2. Cargar el esquema original sin modificar la fuente.
3. Verificar conteos por tabla y rangos de IDs.
4. Verificar que no haya partidas sin usuario, detalles sin partida/cuenta, documentos huérfanos o observaciones sin reporte/usuario.
5. Comparar sumas globales de `debe` y `haber` y saldos por cuenta.
6. Registrar una tabla de mapeo de IDs solo si se renombra el esquema.

### Fase C — carga conservadora

En la primera migración, conservar IDs y valores originales. Cargar en este orden:

1. Usuarios.
2. Cuentas contables por nivel raíz a hoja.
3. Documentos fuente.
4. Partidas.
5. Detalles de partida.
6. Reportes.
7. Observaciones.

Desactivar temporalmente solo las validaciones nuevas que no existan en el origen durante la carga histórica; después reportar las excepciones. No alterar importes, fechas, conceptos `[AJUSTE:...]`, estados ni hashes.

### Fase D — reconciliación

Comparar entre origen y destino:

- Conteo de filas por tabla.
- IDs mínimos/máximos y ausencia de duplicados.
- Suma total Debe/Haber.
- Suma por período, partida y cuenta.
- Conteo de ajustes.
- Conteo y tamaño total de documentos/PDFs.
- Estados y observaciones por reporte.
- Resultados de Libro Diario, Mayor, Balance de Comprobación, Balance General y Estado de Resultados para períodos de aceptación.

Solo después de reconciliar se deben activar constraints nuevas que no estaban demostradas en origen y habilitar escritura de usuarios.

## 8. Información pendiente de confirmar

### Esquema

- DDL completo y schema PostgreSQL real.
- Tipos exactos, longitudes, precisión, defaults y nulabilidad.
- Claves primarias y secuencias.
- Claves foráneas y acciones `ON DELETE`/`ON UPDATE`.
- Índices, constraints, triggers, views y funciones.
- Si `tblUsuarios` y `tblusuarios` son el mismo objeto no entrecomillado.

### Datos

- Cantidad de filas y tamaño de BLOBs.
- Datos huérfanos o inconsistentes existentes.
- Regla histórica de numeración de partidas.
- Si `fecha_creacion` de detalles tiene default y qué precisión conserva.
- Si adjuntos adicionales sin `id_documento` de detalle deben conservarse.

### Negocio

- Si `numero_partida` es global, anual o por período.
- Si `codigo_cuenta` y usuarios deben ser únicos.
- Si el Pasivo No Corriente excluido por el código es una regla válida o un defecto heredado.
- Si deben crearse `periodos_contables` y una relación formal entre período y partida.
- Política de eliminación física y auditoría de cambios.
- Política de almacenamiento de BLOBs frente a filesystem/S3.
- Estados oficiales y transiciones permitidas de reportes.

## 9. Conclusión

Se puede diseñar una base Laravel compatible sin inventar el dominio: siete tablas, relaciones lógicas y campos usados están documentados por el código. No es responsable generar migraciones ejecutables todavía porque faltan DDL, tipos, constraints e índices reales. El siguiente paso correcto es obtener un esquema autorizado de solo lectura, cargarlo en staging y comparar datos; solo entonces convertir esta propuesta en migraciones Laravel 12 versionadas.
