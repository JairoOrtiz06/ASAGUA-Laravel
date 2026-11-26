# Conceptos y proyección de flujos

## Registro

En **Ingeniería Económica → Mis proyectos**, abre un proyecto y registra conceptos desde **Conceptos** en cada alternativa o desde **Conceptos comunes** en el proyecto. Una alternativa y un escenario del mismo proyecto son suficientes para consultar una proyección. Selecciona varias alternativas en **Proyectar y comparar flujos** para verlas en la misma moneda y horizonte. Los filtros de períodos limitan el detalle visible y conservan el acumulado calculado desde la fecha base.

El ámbito de un concepto queda definido al crearlo. Los conceptos comunes se registran una vez en el proyecto y se incorporan una vez por alternativa al proyectar. La comparación indica el neto común incluido en cada alternativa. Un concepto propio puede ser diferencial, hundido o quedar pendiente de clasificación.

Las evidencias guardan referencia, fecha, importe observado, importe utilizado y justificación. Se puede vincular un documento fuente o el ID de un detalle de partida existente. La integración solo consulta esos registros y no trata el Debe ni el Haber como desembolso. Un asiento requiere evidencia adicional para acreditar pago. Los dos importes de evidencia están expresados en la moneda del concepto.

## Fechas e importes

- El escenario puede indicar una fecha base propia. Si queda vacía, hereda la del proyecto.
- Los meses programados se cuentan desde esa fecha. El mes 0 comienza en la fecha base. Los límites mensuales, trimestrales y anuales siguen sus aniversarios, con ajuste al último día si el número de día no existe.
- Un pago en la fecha base va al período 0. Cada período posterior comienza el día siguiente al cierre anterior e incluye su propia fecha de cierre. Los pagos recurrentes al inicio conservan la fecha de apertura del tramo. Un pago único programado al final de un mes conserva su fecha un día antes del siguiente aniversario mensual.
- Una inversión inicial única sin fecha ni mes programado va al período 0. Un rescate único en las mismas condiciones va al cierre. Para programar una inversión al inicio del cuarto año se indica el mes 36. Las fechas observadas se conservan en movimientos únicos.
- Las recurrencias tienen un intervalo de 1, 3, 6 o 12 meses. En una serie vencida, el inicio 1, 0 o vacío abre el primer tramo en la fecha base y se paga al cumplirse el intervalo. Un inicio vencido posterior desplaza ese tramo. El mes de pago es inclusivo, incluido el cierre del horizonte. Las series anticipadas mantienen el inicio como desplazamiento desde la base y terminan antes del cierre. Un máximo de ocurrencias puede detener la serie antes. El primer pago usa el monto ingresado y el crecimiento se aplica por ocurrencia.
- El servicio conserva la fecha de cada movimiento y su período original. La agrupación de visualización suma importes por período sin desplazar las fechas ni aplicar tasas de interés.
- Los registros anteriores a la base se muestran fuera de los totales. En decisiones prospectivas y de reemplazo se excluyen los hundidos. En un proyecto histórico pueden entrar si su fecha pertenece al horizonte. Los conceptos pendientes de clasificación y no monetarios aparecen fuera de los totales. Los costos de oportunidad y ahorros estimados se muestran aparte de la caja. La caja usa **entradas − salidas**.
- Se usa una moneda por consulta, sin conversión cambiaria. Los conceptos en otra moneda quedan fuera del resumen y generan un aviso. La proyección admite hasta 100 años y hasta 10 alternativas por consulta. El servicio opera con decimales y redondea importes a centavos al presentar.

## Regeneración y persistencia

`ie_conceptos` guarda las reglas de programación y `ie_fuentes` sus evidencias. Los movimientos generados se derivan al consultar y llevan una referencia al concepto y su evidencia. `ie_flujos` guarda únicamente ajustes manuales de una alternativa y escenario, con fecha, autor y justificación. Una consulta no inserta, duplica ni sobrescribe ajustes. Los ajustes se agregan a través de su formulario y se eliminan mediante una acción explícita. No se publica una instantánea de evaluación en esta fase.

## Ejemplo manual para ASAGUA

Configura un proyecto de cinco años y las alternativas ACAE y Hacienda. Registra apoyo mensual de **33.90 USD** como costo operativo de ACAE. Con doce cargos completos, el total anual es **406.80 USD**. El gasto extraordinario de **135.60 USD** se puede registrar como único con fecha observada **13/05/2026**. Para una evaluación prospectiva con base posterior al gasto, se verá en el detalle histórico fuera de caja; clasifícalo como hundido si corresponde a la decisión.

Registra el internet compartido una sola vez. Si el salario existente es común pero la persona realiza otras tareas, documenta la porción atribuible antes de asignarle un importe. La hora diaria estimada de Hacienda se puede documentar como dato no monetario con monto cero hasta contar con una valoración. Si se valora y no implica un pago adicional, úsala como costo de oportunidad. Un ahorro de talonarios compartido se registra una vez y solo con un importe sustentado. La TMAR trimestral puede documentarse como provisional desde el escenario, pero no interviene en la proyección sin descuento.
