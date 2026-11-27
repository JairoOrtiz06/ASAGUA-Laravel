# Evaluación financiera y económica

Desde **Ingeniería Económica → Mis proyectos → Evaluación financiera**, el contador elige escenario, alternativas, moneda, perspectiva y periodicidad de descuento. Genera una vista previa y guarda una evaluación cuando esté lista. Cada guardado crea una versión consultable desde el proyecto. Si cambian los insumos entre la vista previa y el guardado, el formulario solicita repetir la evaluación.

## Convenciones

- La fecha base y el horizonte salen del escenario o de la fecha base heredada del proyecto. La fecha original de cada movimiento se conserva. Cada importe se descuenta al cierre del período seleccionado. El flujo en la fecha base conserva el factor 1. Para evaluar cuotas mensuales en cierres mensuales, selecciona periodicidad mensual incluso si el escenario está configurado como anual.
- La TMAR proviene del escenario. El motor convierte su tasa efectiva anual a efectiva del período de descuento. Una tasa cero es válida y debe estar registrada. Una tasa sin validar aparece como advertencia.
- En la perspectiva de caja entran las partidas monetarias proyectadas u observadas pertinentes. La perspectiva económica agrega costos de oportunidad y ahorros estimados con una fuente vinculada u observaciones justificadas. Un ajuste manual usa su justificación. Los importes sin justificación se conservan en la memoria con su motivo de exclusión.
- El proyector excluye movimientos anteriores a la base, los que quedan fuera del horizonte y los costos hundidos para decisiones prospectivas o de reemplazo. Los registros no monetarios y sin clasificación también quedan fuera de los totales. Las evidencias documentan el dato y nunca se suman como pagos adicionales. Si dos conceptos presentan el mismo detalle contable, fecha, importe y dirección en la misma alternativa, se conserva una sola contribución y se advierte del posible duplicado. Los conceptos comunes registrados en el proyecto tienen prioridad en ese cotejo.
- En la comparación, cada alternativa A se contrasta con la referencia B. Se separan los comunes por período antes de restar A − B. Se usa el horizonte finito elegido y una moneda. Los reemplazos necesarios, las vidas útiles y los rescates deben estar sustentados por el escenario y los conceptos. No se asumen ciclos idénticos ni valores residuales automáticos. Los resultados se exponen separados de la viabilidad; un caso sin viabilidad confirmada queda informativo.

## Fórmulas

| Indicador | Fórmula e interpretación |
|---|---|
| VAN/VPN | `Σ FC_t × (1 + i)^(−t)`, con `FC_t = beneficios_t − costos_t`. |
| VPC | `Σ costos_t × (1 + i)^(−t)`, expresado como costo bruto positivo. Los rescates monetarios se consideran beneficios en VAN y RCB. |
| CAUE | `VPC × [i_anual (1 + i_anual)^n / ((1 + i_anual)^n − 1)]`. Si la tasa anual es cero se usa `1/n`. |
| RCB | `VP(beneficios) / VP(costos)`. Se marca como no aplicable si falta cualquiera de los dos. |
| TIR | `0 = Σ FC_t / (1 + r)^t`, con `r` efectiva por período. La TIR incremental aplica a los flujos diferenciales de A − B. |

Los importes y las tasas se calculan con **BigDecimal**. División y factores conservan al menos 40 decimales de trabajo, con precisión adaptada a la magnitud. La presentación monetaria redondea a dos decimales. Los cocientes y los porcentajes se muestran con más decimales. La instantánea guarda los valores decimales como cadenas JSON para conservar su precisión.

## Búsqueda de TIR

Se analiza el dominio de **−99 % a 1,000 % efectivo por período**. El algoritmo convierte la ecuación en un polinomio en `x = 1/(1+r)`. Una malla logarítmica de 360 divisiones propone intervalos; si hay varios cambios de signo, se examinan también candidatos estacionarios. La malla usa aritmética binaria únicamente para localizar candidatos. La bisección refina cambios de signo con decimales, hasta 100 iteraciones por intervalo y con ancho máximo de `10^−12` en la tasa. La memoria conserva el intervalo final, las iteraciones y el VAN residual de cada candidata.

Una malla finita puede omitir raíces muy próximas, particularmente raíces tangentes. Las tangencias cercanas a cero se muestran como posibles. El resultado distingue raíz única encontrada, posibles raíces múltiples, ausencia de raíz detectada en el dominio, flujos sin cambios de signo y falta de convergencia. Un valor de 0 % se muestra solamente si corresponde a una raíz encontrada.

## Instantáneas

`ie_evaluaciones` guarda proyecto, escenario, autor, fecha, perspectiva, moneda, estado y versión del motor. Su documento `JSONB` conserva configuración, alternativas, tasa original y convertida, supuestos, referencias a los movimientos, exclusiones, fuentes, indicadores y memoria por período. Las consultas históricas leen esa instantánea. Los datos de conceptos, fuentes o escenarios editados posteriormente se usan en nuevas evaluaciones.
