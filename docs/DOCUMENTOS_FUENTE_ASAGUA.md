# Migración del módulo de documentos fuente

## Evidencia del sistema Python

`documento_fuente.py` guarda el binario directamente en `tbldocumentos_fuente.archivo` mediante `psycopg.Binary`, consulta metadatos sin cargar el binario en el listado y recupera el contenido por `id_documento`. La vista permite visualizar y descargar el archivo. El registro ordinario de partidas acepta hasta 40 MB y guarda varios documentos, pero asigna únicamente el primer `id_documento` a todos los detalles; los documentos restantes quedan almacenados sin relación.

## Implementación Laravel

- Se conserva `tbldocumentos_fuente.archivo` como binario PostgreSQL; no se migra automáticamente a archivos externos ni se modifica el esquema.
- `DocumentoFuenteService` registra metadatos, binario y asociación dentro de una transacción.
- La asociación se realiza mediante `tbldetalles_partida.id_documento`, que es la única relación confirmada por el esquema.
- Se permite registrar documentos sin archivo, igual que el modelo Python; los documentos sin binario se visualizan pero no se pueden descargar.
- Se limita el archivo a 40 MB, igual que la interfaz Python.
- Los usuarios `Contador` pueden registrar y asociar documentos. `Contador` y `Auditor` pueden consultarlos y descargarlos, conforme a los dashboards originales.
- El contenido se entrega mediante una ruta autenticada; no se expone el binario en una URL pública de almacenamiento.

## Limitación de asociación

El esquema actual permite una sola FK `id_documento` por detalle, no una colección de documentos. Laravel conserva todos los documentos registrados, pero rechaza asociar un segundo documento al mismo detalle para evitar sobrescribir silenciosamente la relación existente. Una asociación de múltiples archivos por detalle requeriría una tabla pivote, que no se crea en esta tarea porque no forma parte del modelo confirmado.

## Archivos principales

- `app/Services/DocumentoFuenteService.php`
- `app/Http/Requests/DocumentoFuenteRequest.php`
- `app/Http/Controllers/DocumentoFuenteController.php`
- `resources/views/documentos/`
- `tests/Feature/DocumentoFuenteTest.php`
