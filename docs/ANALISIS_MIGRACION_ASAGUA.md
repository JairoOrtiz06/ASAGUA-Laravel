# ASAGUA — análisis funcional y plan de migración

Fecha del análisis: 18 de noviembre de 2026  
Fuentes revisadas: `ProyectoAsagua` (Python + CustomTkinter) y `asagua` (Laravel 12).

## 1. Alcance y estado actual

El sistema original es una aplicación de escritorio contable que usa Python, CustomTkinter y PostgreSQL mediante `psycopg`. La lógica está separada en modelos/repositorios, servicios y vistas. La aplicación nueva es todavía el esqueleto de Laravel 12: conserva las migraciones estándar de usuarios, caché y trabajos, pero no contiene aún el dominio ASAGUA.

No se modificó ningún archivo Python ni se ejecutó ninguna operación sobre la base de datos original. El repositorio Python presenta cambios locales existentes —incluidos los módulos de asientos de ajuste, pruebas y cambios en algunas vistas—; este informe considera el estado de trabajo visible, no solo los dos commits existentes.

## 2. Inventario funcional

### Autenticación y sesión

- Inicio de sesión por usuario y contraseña BCrypt.
- Solo se autentican usuarios con `activo = true`.
- La sesión contiene usuario, id, nombre, rol y hora de inicio.
- Cierre de sesión y retorno a la pantalla de acceso.
- Tres roles: `Admin`, `Contador` y `Auditor`.

### Módulos comunes de consulta

- Pantalla principal con identidad ASAGUA, subtítulo “Col. Las Flores, Mazariego y Suárez”, saludo y último acceso.
- Catálogo de cuentas contables activas, con búsqueda por código/nombre en los formularios.
- Libro Diario: consulta de movimientos por fecha, totales Debe/Haber, acceso al documento fuente y exportación PDF.
- Libro Mayor: agrupación por cuenta, movimientos, total Debe, total Haber, saldo absoluto y naturaleza Deudor/Acreedor.
- Balance de Comprobación: Debe, Haber, saldo deudor y saldo acreedor por cuenta, con indicador de balance.
- Balance General: Activo corriente/no corriente, Pasivo corriente/no corriente, Patrimonio, utilidad del ejercicio y comprobación de la ecuación contable.
- Estado de Resultados: ingresos, gastos, utilidad/pérdida neta y exportación.
- Documentos Fuente: listado, previsualización y descarga de binarios.
- Reportes generados: listado de PDFs almacenados, previsualización, descarga y revisión por Auditoría.

### Funcionalidad del Contador

- Registrar partidas contables con fecha, concepto, múltiples líneas, cuenta, descripción, Debe y Haber.
- Adjuntar uno o varios documentos fuente; la interfaz limita cada archivo a 40 MB.
- Editar líneas de partidas ordinarias y conservar el documento fuente asociado al reemplazar los detalles.
- Registrar asientos de ajuste con los tipos:
  - Depreciaciones.
  - Gastos acumulados.
  - Gastos pagados por anticipado.
  - Ingresos acumulados.
  - Ingresos diferidos.
  - Ajustes de inventario.
  - Otros ajustes justificados.
- Consultar ajustes por rango y ver su detalle.
- Responder observaciones de Auditoría.

### Funcionalidad del Auditor

- Consulta de todos los libros y estados financieros.
- Consulta y descarga de documentos fuente.
- Consulta de reportes PDF generados.
- Creación de observaciones sobre reportes ajenos, con prioridad Alta/Media/Baja.
- No puede auditar sus propios reportes.

### Funcionalidad del Administrador

- Alta, edición y búsqueda de usuarios.
- Validación de email básica en la interfaz.
- Cambio de rol y contraseña.
- Desactivación lógica.
- Eliminación física explícita, que puede fallar si existen referencias contables.
- Consulta de reportes generados.

## 3. Modelo de datos observado

No existe un esquema SQL, migración ni volcado de la base original dentro de `ProyectoAsagua`. Las siguientes tablas y columnas se deducen de las consultas del código y deben confirmarse con el esquema real antes de migrar.

| Tabla original | Campos observados | Relaciones / uso |
|---|---|---|
| `tblUsuarios` / `tblusuarios` | `id_usuario`, `nombre`, `apellido`, `email`, `usuario`, `password`, `rol`, `activo` | Usuario creador de partidas/reportes; auditor y contador de observaciones. |
| `tblCuentasContables` | `id_cuenta`, `codigo_cuenta`, `nombre_cuenta`, `tipo_cuenta`, `nivel`, `id_cuenta_padre`, `estado` | Catálogo jerárquico autorreferenciado; padre de cuentas y clasificación de estados. |
| `tblPartidas` | `id_partida`, `id_usuario`, `numero_partida`, `fecha`, `concepto` | Cabecera de asiento; pertenece a usuario y tiene muchos detalles. |
| `tblDetalles_partida` | `id_detalle`, `id_partida`, `id_cuenta`, `id_usuario`, `id_documento`, `descripcion`, `debe`, `haber`, `fecha_creacion` | Líneas de partida; pertenece a partida, cuenta, usuario y documento fuente opcional. |
| `tblDocumentos_fuente` | `id_documento`, `nombre_documento`, `descripcion`, `tipo_documento`, `fecha_creacion`, `archivo` | Binario adjunto a líneas de partida. |
| `reportes` | `id`, `id_usuario`, `hora`, `nombre`, `archivo`, `tiene_observaciones`, `estado_revision` | PDF generado por un usuario; puede recibir observaciones. |
| `observaciones_reportes` | `id_observacion`, `id_reporte`, `id_auditor`, `id_contador`, `comentario`, `prioridad`, `fecha_observacion`, `estado`, `fecha_vista`, `respuesta_contador`, `fecha_respuesta` | Flujo Auditor → Contador: pendiente, vista, resuelta. |

Relaciones Eloquent propuestas:

- `Usuario` hasMany `Partida`, `DetallePartida`, `Reporte`; hasMany observaciones como auditor y como contador.
- `CuentaContable` belongsTo `CuentaContable` padre, hasMany hijos, hasMany `DetallePartida`.
- `Partida` belongsTo `Usuario`; hasMany `DetallePartida`.
- `DetallePartida` belongsTo `Partida`, `CuentaContable`, `Usuario` y `DocumentoFuente` opcional.
- `DocumentoFuente` hasMany `DetallePartida`.
- `Reporte` belongsTo `Usuario`; hasMany `ObservacionReporte`.
- `ObservacionReporte` belongsTo `Reporte`, `auditor` y `contador` (`Usuario`).

Para PostgreSQL se recomienda conservar `numeric(18,2)` para importes, `date` para la fecha contable y `timestamp with time zone` para auditoría. Los nombres exactos, nulabilidad, claves foráneas, secuencias, índices y restricciones deben salir del esquema real, no de una suposición.

## 4. Reglas y operaciones contables confirmadas

### Partidas ordinarias

- Se crea una cabecera y sus detalles dentro de una transacción.
- El número se calcula como `MAX(numero_partida) + 1` cuando no se proporciona.
- Una partida contiene múltiples detalles y cada detalle referencia una cuenta.
- La interfaz de registro calcula totales Debe/Haber y no guarda si no están equilibrados.
- Los documentos se guardan como binario y se asocian a los detalles.
- La edición completa conserva el documento del primer detalle, elimina los detalles actuales y los vuelve a insertar.
- La eliminación física de una partida existe en el repositorio, aunque su exposición en la interfaz debe validarse.

### Asientos de ajuste

- Solo el rol `Contador` puede registrarlos.
- La fecha debe estar entre `periodo_inicio` y `periodo_fin`.
- El tipo debe pertenecer a la lista cerrada de siete tipos.
- El concepto es obligatorio.
- Debe existir al menos una línea.
- No se aceptan importes negativos, infinitos o inválidos.
- Una línea no puede tener Debe y Haber simultáneamente, ni ambos en cero.
- El asiento debe tener importes en ambos lados y Debe total = Haber total.
- Cada cuenta debe existir y estar activa.
- Se guarda en las tablas ordinarias `tblPartidas` y `tblDetalles_partida`, no en una tabla separada.
- El concepto se marca con `[AJUSTE:<tipo>]`.
- Un asiento marcado como ajuste no se edita; se corrige mediante otro asiento.
- El registro bloquea `tblPartidas` para serializar la numeración y usa una transacción.

### Libro Mayor y Balance de Comprobación

- El Mayor agrupa por nombre de cuenta, suma Debe y Haber y calcula `saldo = abs(debe - haber)`.
- La naturaleza es Deudor si `debe - haber >= 0`; en caso contrario, Acreedor.
- El Balance de Comprobación calcula saldo deudor si `debe - haber > 0`, y saldo acreedor si `debe - haber < 0`.
- Los importes se redondean a dos decimales.
- Las fechas del Diario usan rango inclusivo de inicio y fin; el fin se implementa como menor que el día siguiente.

### Balance General

La lógica actual depende de una jerarquía de cuentas y de IDs constantes:

- Activo Corriente: id nivel 2 `7`.
- Activo No Corriente: `8`.
- Pasivo Corriente: `9`.
- Patrimonio: `10`.
- Padres de gasto: `34, 35, 38, 39, 40, 41`.
- Padres de ingreso: `36, 37`.

La implementación agrupa hojas bajo padres de nivel 3. Activos usan Debe−Haber; pasivos y patrimonio usan Haber−Debe. Los ingresos se calculan como Haber−Debe y los gastos como Debe−Haber. Un gasto cuyo padre contiene `COSTO` o es `COMPRAS` se considera costo. La utilidad es ingresos−costos−gastos y se agrega a `RESULTADOS DEL EJERCICIO`.

Hay una particularidad que debe preservarse mientras el negocio no la confirme de otra forma: el código actual deja el Pasivo No Corriente sin participación en el cálculo, aunque la interfaz lo muestra. No debe corregirse automáticamente durante la migración.

### Estado de Resultados

- Ingreso: Haber−Debe.
- Gasto: Debe−Haber.
- Se excluyen cuentas con total cero.
- La salida muestra el valor absoluto de la utilidad neta y un indicador que distingue utilidad de pérdida.

## 5. Consultas SQL y dependencias

### Patrones SQL

- Consultas parametrizadas con `psycopg` para CRUD y reportes.
- `JOIN` de partidas, detalles, cuentas, usuarios y documentos.
- `ILIKE` para búsqueda de usuarios.
- `WITH RECURSIVE` para recorrer el catálogo de cuentas.
- `ANY(array)` para validar cuentas activas.
- `COALESCE(SUM(...), 0)` para totales.
- `CURRENT_TIMESTAMP` para transiciones de observaciones.
- Inserciones con `RETURNING`.
- Bloqueo de tabla para numeración de ajustes.

### Dependencias originales

- PostgreSQL + `psycopg[binary]`.
- `pandas` y `numpy` para agregaciones y transformación de reportes.
- CustomTkinter, tkcalendar y Pillow para UI.
- ReportLab para PDF.
- PyMuPDF para vista previa/miniaturas de documentos.
- bcrypt para contraseñas.
- Matplotlib está declarado, pero no se confirmó su uso efectivo en las vistas revisadas.

La aplicación Python apunta por defecto a PostgreSQL en `localhost:5433`, base `aurum_financeDB`. El archivo de configuración contiene credenciales en texto plano: no deben copiarse a Laravel; deberán reemplazarse por variables de entorno y rotarse si son reales.

## 6. Interfaces y flujos

El flujo principal es: login → dashboard específico por rol → módulos del menú → consulta/edición/exportación. El Contador inicia cada sesión con los módulos Mayor, Balance de Comprobación, Estado de Resultados y Balance General bloqueados; al generar el Libro Diario se habilitan y consumen el mismo conjunto de movimientos filtrado durante esa sesión. Al reiniciar el filtro vuelven a bloquearse. El Auditor no usa ese bloqueo.

La reconstrucción web debe conservar el efecto funcional, pero el estado temporal no debe depender de un DataFrame en memoria de una ventana: se recomienda guardar filtros en sesión HTTP y recalcular consultas de forma determinista, con autorización en servidor en cada endpoint.

## 7. Reportes PDF

Se generan cinco tipos de PDF mediante ReportLab:

1. Libro Diario.
2. Libro Mayor.
3. Balance de Comprobación.
4. Balance General.
5. Estado de Resultados.

Todos comparten encabezado ASAGUA, subtítulo, estilos de tabla, importes monetarios y pie con número de página. El archivo se descarga localmente y también se guarda en `reportes` para revisión posterior. La migración debe decidir si conserva el BLOB en PostgreSQL o usa almacenamiento Laravel (`storage`) con metadatos; para compatibilidad inmediata puede conservar el BLOB y añadir una estrategia de almacenamiento configurable.

## 8. Arquitectura Laravel propuesta

### Modelos Eloquent

- `App\Models\User` adaptado a `tblUsuarios` o, preferiblemente, una tabla Laravel nueva durante una migración controlada.
- `CuentaContable`.
- `Partida`.
- `DetallePartida`.
- `DocumentoFuente`.
- `Reporte`.
- `ObservacionReporte`.
- Opcional: `PeriodoContable` si el negocio confirma que los períodos existen fuera del código actual.

Los modelos deben declarar explícitamente `$table`, `$primaryKey`, casts de fechas/booleanos/decimales y relaciones; no debe confiarse en las convenciones Laravel porque las tablas y claves originales son heterogéneas.

### Servicios de dominio

- `AutenticacionService`: compatibilidad BCrypt y usuario activo.
- `PartidaService`: validación, numeración segura, persistencia transaccional, adjuntos y edición.
- `AjusteContableService`: tipos, período, líneas, equilibrio, cuentas activas y marca `[AJUSTE:...]`.
- `CatalogoContableService`: jerarquía, cuentas activas y clasificación.
- `LibroDiarioService`: filtros y movimientos.
- `LibroMayorService`: agregaciones por cuenta.
- `BalanceComprobacionService`.
- `BalanceGeneralService`: réplica exacta inicial de la lógica actual, incluida la exclusión observada del pasivo no corriente hasta validación.
- `EstadoResultadosService`.
- `ReportePdfService`: contratos independientes por reporte; implementar con una librería PHP compatible con el formato requerido, por ejemplo Dompdf o TCPDF, después de comparar resultados.
- `DocumentoFuenteService`: validación de tamaño/tipo, almacenamiento, vista y descarga.
- `ObservacionReporteService`: creación, estados, respuesta y actualización atómica del reporte.

### Controladores

- `Auth\AuthenticatedSessionController` o equivalente para login/logout.
- `DashboardController`.
- `PartidaController` y `DetallePartidaController`.
- `AjusteContableController`.
- `CatalogoContableController`.
- `LibroDiarioController`, `LibroMayorController`, `BalanceComprobacionController`, `BalanceGeneralController`, `EstadoResultadosController`.
- `DocumentoFuenteController`.
- `ReporteController` y `ReporteDownloadController`.
- `ObservacionReporteController`.
- `UsuarioController`.

Cada controlador debe ser delgado: autorización, validación Form Request, llamada al servicio y respuesta Blade/descarga.

### Autorización

Definir middleware/policies server-side:

- `role:Admin`: gestión de usuarios y reportes administrativos.
- `role:Contador`: registrar/editar partidas ordinarias, ajustes y responder observaciones.
- `role:Auditor`: consultar información y crear observaciones.
- `ReportePolicy`: impedir que un usuario audite su propio reporte.
- `PartidaPolicy`: bloquear edición de ajustes y controlar quién puede modificar partidas.
- `DocumentoFuentePolicy`: restringir descarga a usuarios autenticados con rol permitido.

No debe dependerse únicamente de ocultar enlaces en Blade.

### Vistas Blade

Usar un layout autenticado con sidebar ASAGUA y componentes reutilizables para filtros, tablas, importes, alertas, estados y exportación. Vistas mínimas:

- `auth/login`, `dashboard/index`.
- `partidas/create`, `partidas/index`, `partidas/show`, `partidas/edit`.
- `ajustes/index`, `ajustes/create`, `ajustes/show`.
- `catalogo/index`.
- `libros/diario`, `libros/mayor`.
- `reportes/balance-comprobacion`, `reportes/balance-general`, `reportes/estado-resultados`, `reportes/index`, `reportes/show`.
- `documentos/index`, `documentos/show`.
- `observaciones/index`, `observaciones/show`.
- `usuarios/index`, `usuarios/create`, `usuarios/edit`.

El formulario de partida necesita líneas dinámicas, selección de cuenta por búsqueda, cálculo cliente/servidor de totales, subida múltiple de documentos y mensajes de validación. El servidor siempre debe recalcular y comprobar el equilibrio.

### Rutas propuestas

Usar grupos `auth`, `role` y nombres REST, por ejemplo:

```text
GET    /dashboard
resource /partidas
resource /ajustes
GET    /catalogo-cuentas
GET    /libros/diario
GET    /libros/mayor
GET    /reportes/balance-comprobacion
GET    /reportes/balance-general
GET    /reportes/estado-resultados
resource /documentos-fuente
resource /reportes
resource /observaciones
resource /usuarios
GET    /reportes/{reporte}/pdf
```

Las rutas de exportación deben devolver `Content-Type: application/pdf`, registrar el reporte y evitar duplicados accidentales mediante una decisión explícita de negocio.

### Migraciones

Hay dos estrategias posibles, que no deben mezclarse sin decisión:

1. **Compatibilidad directa:** modelos y migraciones que reflejen exactamente las tablas existentes, con `Schema::connection('pgsql')`, claves y nombres originales.
2. **Esquema Laravel normalizado:** nuevas tablas snake_case, migración ETL controlada y período de coexistencia.

Para la primera etapa recomiendo compatibilidad directa para no perder datos ni alterar la base original. Las migraciones Laravel deben crear el esquema nuevo en una base de destino; nunca deben ejecutarse contra la base original sin respaldo, plan de reversión y autorización explícita.

## 9. Información faltante que bloquea una reconstrucción fiel

1. Esquema completo de PostgreSQL: tipos, defaults, índices, constraints, triggers, secuencias y claves foráneas.
2. Volumen y datos de prueba anonimizados para comparar resultados.
3. Definición oficial de períodos contables; el código recibe fechas, pero no identifica su origen.
4. Significado oficial de tipos de cuenta y catálogo completo; los IDs 7, 8, 9, 10 y 34–41 no deben asumirse universales.
5. Confirmación de si Pasivo No Corriente debe excluirse o si es un defecto heredado.
6. Reglas oficiales para numeración: unicidad, reinicio por año/período y concurrencia.
7. Regla de permisos para editar/eliminar partidas y si debe existir auditoría de cambios.
8. Política de conservación de documentos y reportes, tamaño máximo real y tipos MIME permitidos.
9. Formatos PDF aprobados, logo oficial y requisitos de firma, folio o numeración.
10. Flujo completo de estados de reportes: el código muestra `SIN_REVISAR`, `CON_OBSERVACIONES`, `APROBADO` y `RECHAZADO`, pero no expone todas las acciones para cada transición.
11. Política de contraseñas, recuperación, expiración y rotación de credenciales.
12. Confirmación de si la base `aurum_financeDB` y el puerto 5433 son definitivos o solo valores locales.
13. Definición de despliegue: servidor web, colas, almacenamiento de archivos, backups y usuarios PostgreSQL.
14. Casos de prueba contables de aceptación para cada reporte y para ajustes.

## 10. Plan de migración por etapas

### Etapa 0 — Descubrimiento y congelamiento

- Obtener esquema, dump de estructura y copia de datos anonimizados.
- Documentar catálogo, períodos, permisos y ejemplos contables aprobados.
- Congelar una versión de referencia del original sin modificarlo.
- Definir criterios de igualdad para cada consulta y PDF.

### Etapa 1 — Base Laravel y PostgreSQL de destino

- Configurar `.env` para PostgreSQL sin credenciales embebidas.
- Definir conexión, timezone, almacenamiento y sesiones.
- Crear migraciones de compatibilidad y modelos con claves originales.
- Crear seeders solo para datos técnicos, no para inventar catálogo o saldos.

### Etapa 2 — Autenticación, roles y layout

- Migrar usuarios con compatibilidad BCrypt.
- Implementar login/logout, middleware, policies y dashboard por rol.
- Construir layout Blade, menú y navegación.

### Etapa 3 — Catálogo, partidas y documentos

- Implementar cuentas jerárquicas.
- Registrar, consultar y editar partidas ordinarias.
- Persistir adjuntos con la política aprobada.
- Añadir transacciones, validaciones, numeración segura y pruebas de concurrencia.

### Etapa 4 — Ajustes y observaciones

- Implementar ajustes como partidas ordinarias marcadas, sin tabla paralela.
- Bloquear su edición y exigir asiento correctivo.
- Implementar reportes almacenados, observaciones, estados y respuesta del Contador.

### Etapa 5 — Libros y estados financieros

- Implementar Diario y filtros.
- Replicar Mayor, Balance de Comprobación, Balance General y Estado de Resultados con pruebas de comparación contra casos del original.
- Mantener temporalmente las particularidades heredadas hasta recibir decisión funcional.

### Etapa 6 — PDFs, descargas y paridad visual

- Seleccionar librería PDF PHP comparando paginación, moneda, tablas y encabezados.
- Generar y guardar reportes.
- Validar visualmente cada formato y permisos de descarga.

### Etapa 7 — Migración de datos y coexistencia

- Ejecutar una carga de prueba en PostgreSQL de destino.
- Comparar conteos, sumas Debe/Haber, saldos por cuenta y reportes.
- Ejecutar una ventana de coexistencia o solo lectura del original.
- Preparar corte, respaldo, reconciliación y plan de reversión.

### Etapa 8 — Aceptación y operación

- Pruebas por rol, seguridad, archivos, concurrencia y regresión contable.
- Capacitación y manual breve.
- Monitoreo, backups, logs y soporte.

## 11. Criterio de finalización

La migración estará lista cuando cada módulo del inventario tenga una ruta y vista equivalente, cada regla confirmada tenga prueba automatizada, los saldos coincidan con el sistema original para un conjunto de períodos de aceptación, los PDFs sean aprobados y la autorización se aplique en servidor. Las diferencias heredadas —por ejemplo, el tratamiento del Pasivo No Corriente— deben quedar aprobadas explícitamente antes de corregirse o declararse parte de la compatibilidad.
