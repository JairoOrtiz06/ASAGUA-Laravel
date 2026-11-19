# Hoja de trabajo contable

## Criterio utilizado

La hoja usa el período seleccionado y obtiene sus movimientos desde `LibroContableService::diario()`.

- **Balance de comprobación:** movimientos ordinarios, excluyendo partidas identificadas como ajustes.
- **Ajustes:** movimientos de partidas cuyo concepto tiene el formato `[AJUSTE:tipo]`.
- **Balance ajustado:** suma de las columnas de comprobación y ajustes.
- **Estado de resultados:** reutiliza `estadoResultados()` y distribuye ingresos y gastos en Debe/Haber.
- **Balance general:** reutiliza `balanceGeneral()` y distribuye activo, pasivo y patrimonio en Debe/Haber.

Las fórmulas contables no se copian en los informes finales: los servicios existentes siguen siendo la fuente para los estados de resultados y balance general. La hoja únicamente transforma sus saldos netos en columnas de presentación.

## Validaciones

Se muestran advertencias para:

- Balance de comprobación descuadrado.
- Ajustes descuadrados.
- Balance ajustado descuadrado.
- Ecuación del balance general con diferencias.
- Utilidad o pérdida del ejercicio.

La pantalla es de consulta y no contiene acciones para modificar partidas o detalles.

## Acceso

La hoja está disponible exclusivamente para el rol `Contador`, mediante [HojaTrabajoRequest.php](../app/Http/Requests/HojaTrabajoRequest.php). No se incorporan auditoría, observaciones, aprobaciones ni flujos de revisión.
