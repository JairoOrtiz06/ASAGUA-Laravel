# Análisis de sensibilidad

Abre **Ingeniería Económica → Mis proyectos → detalle del proyecto → Análisis de sensibilidad → Nuevo análisis**. Elige un escenario vigente o una evaluación guardada y pulsa **Cargar variables de la base**. Elige alternativas, perspectiva, periodicidad e indicadores. Documenta los supuestos y genera una vista previa. **Guardar versión** crea una instantánea independiente. Una evaluación guardada también tiene el acceso **Simular esta versión**.

## Modalidades

- **Una variable.** Ingresa una lista, o mínimo y máximo con paso o cantidad. Con paso explícito se generan los valores alcanzables hasta el máximo. Sin paso, se incluyen ambos extremos.
- **Optimista, base y pesimista.** La base conserva sus datos. Optimista y pesimista necesitan cambios explícitos y una justificación por configuración. Los nombres identifican supuestos, sin asignar probabilidades.
- **Matriz.** Selecciona dos alternativas y dos ejes. Cada celda muestra VPC por alternativa, diferencia de VPC y CAUE y menor costo condicionado. La línea aproximada de indiferencia interpola cambios de signo entre celdas vecinas en Y.
- **Equilibrio.** Selecciona dos alternativas y un intervalo para una variable continua. A es la alternativa comparada y B la de referencia. La búsqueda usa 16 subdivisiones iniciales y bisección hasta un ancho y residual de 0.000001 o una igualdad exacta en el cálculo. Se evalúan como máximo 120 puntos. No hallar un cruce en la malla no demuestra inexistencia matemática. Se conservan los extremos, sus diferencias y los cruces detectados.

La matriz admite hasta 100 simulaciones. Los demás análisis tienen hasta seis alternativas, con exactamente dos en matriz y equilibrio. Un presupuesto de períodos por alternativas y simulaciones limita solicitudes de horizontes largos.

## Unidades y reglas de cálculo

Las tasas absolutas se ingresan en porcentaje. Por ejemplo, 5 equivale a 5 % y se convierte a 0.05 para el motor. Una variación de 5 puntos se suma a la tasa en porcentaje; una variación relativa de 5 % multiplica el valor base por 1.05. En importes, el modo absoluto corresponde a la moneda y magnitud del concepto. El horizonte admite años enteros. Su variación reproyecta fechas y ocurrencias y respeta los límites declarados de cada concepto.

El motor reutiliza la proyección de Fase 3 y los indicadores y comparaciones de Fase 4. Los flujos conservan las convenciones de sus perspectivas. La TIR de la misma secuencia nominal se reutiliza cuando varía solo la TMAR. La base sin cambios se verifica frente a la evaluación guardada cuando coinciden perspectiva y periodicidad. Si los indicadores difieren, falla la simulación. Los resultados no aplicables se conservan como tales.

La sensibilidad relativa es el cambio porcentual del indicador dividido por el de una sola variable. Se omite con base cero, indicador no aplicable, cambio de signo del indicador o una variación de entrada inferior a 0.0000001 puntos de porcentaje relativo. El cambio porcentual del indicador puede verse por separado. El cálculo usa BigDecimal, con redondeo monetario en la presentación.

## Horas, salario y otros supuestos

Para un concepto mensual de costo de oportunidad, abre sus **Parámetros laborales**. Informa salario, jornada diaria, días laborales y horas adicionales diarias de referencia. Al variar horas o salario, el importe mensual se calcula como:

`(salario mensual × horas adicionales diarias × días laborales) / (jornada diaria × días laborales)`

El supuesto usa el mismo número de días para valorar salario y dedicación. Solo sustituye el importe del concepto elegido durante la simulación ampliada. Una base sin cambios sigue usando el importe registrado. La simulación conserva los parámetros para reproducir la derivación. El texto de las observaciones no se interpreta como fórmula. La migración temporal permite simular un costo estimado al inicio de la alternativa elegida; también se puede variar directamente un concepto futuro registrado.

## Versiones

Una vista previa no escribe resultados. El guardado añade una fila con los insumos congelados y los resultados resumidos, sin alterar conceptos, escenarios o evaluaciones. Si los parámetros cambian antes de guardar, es necesario generar otra vista previa. La memoria seleccionable reproyecta la variante desde la instantánea guardada con la versión del motor compatible.

Las evaluaciones nuevas incluyen reglas de proyección congeladas. Para evaluaciones anteriores que solo conservan su memoria, se reconstruyen los conceptos visibles y se mantienen fijos horizonte y crecimiento, porque la memoria antigua puede omitir reglas o movimientos fuera del horizonte. El sistema conserva la advertencia en la versión del análisis.

## Ejemplo de ASAGUA

Con cinco años, 60 pagos, TMAR cero y sin otros costos diferenciales, los valores mensuales del soporte ACAE de 25, 30, 33.90, 40 y 50 USD producen VPC de 1,500.00, 1,800.00, 2,034.00, 2,400.00 y 3,000.00 USD. Con 33.90 USD de soporte, salario de 500 USD y jornada de siete horas, el equilibrio teórico es `33.90 × 7 / 500 = 0.4746` horas diarias, es decir, 28.476 minutos. La simulación numérica exige el mismo calendario y duración, una valoración proporcional y ausencia de otros costos diferenciales para reproducir ese valor.

La dedicación administrativa sigue siendo una estimación. La TMAR y la viabilidad técnica de las alternativas requieren verificación documental antes de tomar una decisión.
