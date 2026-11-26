# Activos, depreciación y reemplazo

Abre **Ingeniería Económica → Mis proyectos → detalle de un proyecto → Activos y depreciación**. Registra el activo, guarda y elige la herramienta desde su ficha. Dejar la alternativa vacía identifica un activo compartido en el proyecto. Los conceptos asociados a ese ámbito se agregan una vez en cada alternativa evaluada y se separan en la comparación incremental de Fase 4.

## Bases y prorrateo

La base contable C es costo de adquisición más costos capitalizables registrados explícitamente. El valor residual VR se resta para fijar el máximo depreciable. La vida útil se expresa en años de servicio desde la fecha disponible para uso. El porcentaje de uso se guarda como dato del expediente y la tabla contable muestra el activo completo.

| Método | Cálculo |
|---|---|
| Línea recta | D = (C − VR) / n |
| Doble saldo decreciente | d = 2/n y Dₜ = MIN(VL inicial × d, VL inicial − VR) |
| Amortización lineal | Mismo cálculo lineal aplicado a un intangible reconocido con vida definida |

Un año completo va de un aniversario de disponibilidad al día anterior al siguiente aniversario. Si la fecha de corte se encuentra dentro del año de servicio, se incluye ese día y se multiplica la cuota anual por días transcurridos / días calendario de ese año de servicio. El corte anterior a la disponibilidad devuelve una tabla vacía. Para un activo retirado se puede elegir la fecha límite que corresponda a la política aplicable. Cuando termina la vida útil, doble saldo puede dejar saldo pendiente sobre el residual. El ajuste al residual requiere seleccionar la política, justificarla y completar el último año de servicio. Una vida indefinida conserva la memoria de parámetros y no programa cuotas.

Se usa BigDecimal con división a 40 o más decimales. Los importes de pantalla se redondean a dos decimales. El CSV y la instantánea JSONB conservan los decimales de trabajo. Los resultados se guardan como versiones nuevas con método, autor, fecha, fuentes y versión del motor.

## Conservación y reemplazo

El escenario aporta fecha base, TMAR, periodicidad y horizonte. El análisis usa **una moneda** y convierte la tasa anual a efectiva del período. Los desembolsos iniciales van en t = 0 y las cifras de operación y mantenimiento son anuales, al cierre de cada año. No hay conversión cambiaria ni costos inferidos a partir de la contabilidad.

- El historial de adquisición y el valor en libros son informativos. La opción de reemplazar recibe la venta del activo actual y paga su retiro inmediato si corresponde. La opción de conservar renuncia al importe neto. Se muestra esa oportunidad sin cargarla nuevamente como un segundo movimiento.
- Completa por separado precio, instalación, puesta en marcha, operación, mantenimiento, valor terminal y retiro. Un cero es una afirmación explícita. El VPC bruto se calcula con el motor de Fase 4. El **VPC neto** resta el VP de las recuperaciones. El **CAUE neto** aplica el factor anual de recuperación de capital a ese costo neto. La diferencia de flujos se presenta como reemplazar menos conservar.
- Los valores terminales corresponden al cierre del horizonte común. Si una opción no cubre todo el horizonte con el primer activo, reduce el horizonte del escenario o carga reposiciones documentadas. Cada reposición entra al cierre del año indicado y especifica sus importes y la cobertura del activo sucesor. No se repiten ciclos automáticamente.
- La vida económica se calcula entre edades candidatas con rescate y retiro justificados. Compara su CAUE bajo las cifras anuales del primer activo. La salida identifica el menor CAUE entre las edades informadas. La equivalencia técnica, normativa y de calidad requiere confirmación independiente.

## Evidencias y flujos

La ficha de activo admite referencias por ID a un documento fuente o detalle contable. Se consulta la cuenta y partida por relaciones existentes. Un asiento requiere respaldo del movimiento monetario. Para vincular un flujo, registra su evidencia en el activo, elige el escenario de referencia para comprobar fechas, especifica importe, fecha observada o programada y confirma. Si existe un concepto de igual fecha, importe, moneda, ámbito y dirección, debes seleccionarlo. Las repeticiones de una operación ya vinculada recuperan el concepto y una adquisición por activo queda protegida. Una fecha programada se guarda separada de una fecha observada.

La depreciación y amortización permanecen en el historial contable de la herramienta. La traslación a Fase 3 se hace mediante conceptos monetarios explícitos. El análisis de reemplazo también se conserva como cálculo y no genera movimientos automáticamente. Los datos de depreciación fiscal, amortización fiscal y efecto tributario se registran como documentación; no se calcula ISR ni escudo fiscal. Para ASAGUA se debe validar documentalmente el tratamiento aplicable.

## Ejemplos académicos

- Equipo de 1,000 USD, residual de 100 USD y vida de cinco años. Línea recta de 180 USD cada año. Doble saldo de 400, 240, 144, 86.40 y 29.60 USD. Acumulado de 900 USD y valor en libros final de 100 USD en ambos métodos.
- Intangible de 1,200 USD, residual cero y vida definida de cuatro años. Amortización lineal de 300 USD por año.
- Caso de reemplazo de las pruebas con TMAR cero y tres años. VPC neto de conservar 670 USD y de reemplazar 630 USD. CAUE neto de 223.33 y 210.00 USD/año, respectivamente.
