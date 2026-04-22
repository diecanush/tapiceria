# Reglas de Presupuestación

## Fórmula base
**Total presupuesto = Mano de obra + Insumos + Margen/Recargo + Impuestos (si aplica).**

## Reglas
1. Mano de obra debe ser editable por tipo de trabajo.
2. Insumos se cargan con cantidad estimada por unidad.
3. El presupuesto debe permitir estimar consumo de insumos desde medidas del mueble (ancho, alto, profundidad) y piezas a cortar.
4. Cada pieza a cortar debe registrar: tipo de insumo, cantidad de piezas, largo, ancho, margen de desperdicio y unidad.
5. El sistema calcula consumo estimado por insumo sumando todas las piezas y aplicando desperdicio configurable.
6. Debe mostrarse diferencia entre consumo estimado y stock disponible al momento de presupuestar.
7. Debe poder ajustarse manualmente la estimación automática antes de confirmar el presupuesto.
8. El margen se aplica sobre subtotal configurable.
9. Impuestos se aplican según condición fiscal del taller.
10. Presupuesto PDF debe incluir vigencia y detalle de cortes/consumo estimado.
11. Cambios de estado permitidos:
   - borrador → enviado
   - enviado → aprobado/rechazado/vencido

## Estados y efectos
- **Aprobado:** habilita orden de trabajo y reserva/descuento de stock según política.
- **Rechazado/Vencido:** no impacta stock ni agenda.
