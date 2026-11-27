# Tasas y equivalencias de Ingeniería Económica

Las tasas se ingresan como fracciones decimales con hasta 12 decimales. Una tasa efectiva trimestral de 0.25 % se escribe como `0.0025`. Una tasa nominal anual requiere capitalización mensual, trimestral o semestral. Para calcular capitales y series, el motor convierte la tasa cotizada a una tasa efectiva con la misma periodicidad que los períodos del cálculo.

## Precisión y persistencia

El motor usa `Brick\Math\BigDecimal`, presente en las dependencias del proyecto. Los productos y potencias enteras operan sobre decimales. Las raíces trabajan a 40 decimales y las divisiones usan 40 o más decimales para conservar precisión en cocientes pequeños. Las divisiones redondean al par. La TMAR efectiva anual se guarda redondeada a 12 decimales en `ie_escenarios`. Las tasas y factores se muestran con hasta 16 decimales, y los importes con dos decimales. La presentación y la persistencia redondean mitades alejándose de cero. Una tasa o factor muy pequeño se puede mostrar en notación científica.

Las calculadoras independientes no guardan operaciones. Al guardar un escenario, Laravel calcula su tasa efectiva anual desde la tasa original, la modalidad y la periodicidad. El estado validado requiere una fuente. Guardar un escenario actualiza sus parámetros actuales. Los resultados históricos de evaluación se deberán conservar con sus propios parámetros en las fases que los publiquen.

## Fórmulas

- Tasa efectiva destino: `i_d = (1 + i_o)^(m_o/m_d) − 1`.
- Tasa nominal anual: `i_o = j/m_o`, `TEA = (1 + j/m_o)^m_o − 1` y `j_d = m_d × i_d` para un destino nominal.
- Capital: `F = P(1+i)^n` y `P = F/(1+i)^n`.
- Con `g = (1+i)^n`, los factores son `F/P = g`, `P/F = 1/g`, `P/A = (g−1)/(ig)`, `A/P = ig/(g−1)`, `F/A = (g−1)/i` y `A/F = i/(g−1)`.
- Con tasa cero, esos factores son, respectivamente, `1`, `1`, `n`, `1/n`, `n` y `1/n`.

## Fechas de las series

| Serie | Primer pago | Último pago | Fecha de valor futuro |
|---|---|---|---|
| Vencida | `t = 1` | `t = n` | `t = n` |
| Anticipada | `t = 0` | `t = n−1` | `t = n` |
| Diferida | `t = k`, con `k ≥ 2` | `t = k+n−1` | `t = k+n−1` |

El valor presente se mide en `t = 0`. Una serie anticipada multiplica el factor vencido por `1+i`. Para el valor presente de una diferida, el factor vencido se divide por `(1+i)^(k−1)`. El valor futuro diferido se mide al último pago y usa el factor vencido. La anualidad se obtiene dividiendo el importe objetivo por el factor correspondiente.

Para despejar el número de pagos se busca una igualdad en los enteros de 1 a 600 mediante búsqueda binaria y comparación cruzada de productos decimales. El importe objetivo debe igualar el resultado con la tasa efectiva de trabajo. Un objetivo dado como importe redondeado a centavos puede quedar entre dos soluciones enteras. La herramienta informa cuando no existe solución entera en el rango.

Las calculadoras admiten hasta 600 períodos y cantidades entre 0 y 10^12. El interés compuesto permite cero períodos. Con una tasa nominal se valida que `j/m > −1`. Con una tasa efectiva se exige `i > −1`. La tasa efectiva anual de un escenario debe caber en `NUMERIC(20,12)` y seguir siendo mayor que −1 después de redondear.
