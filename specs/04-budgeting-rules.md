# Reglas de Presupuestación

## Fórmula base
**Total presupuesto = Mano de obra + Insumos + Margen/Recargo + Impuestos (si aplica).**

## Reglas
1. Mano de obra debe ser editable por tipo de trabajo.
2. Insumos se cargan con cantidad estimada por unidad.
3. El margen se aplica sobre subtotal configurable.
4. Impuestos se aplican según condición fiscal del taller.
5. Presupuesto PDF debe incluir vigencia.
6. Cambios de estado permitidos:
   - borrador → enviado
   - enviado → aprobado/rechazado/vencido

## Estados y efectos
- **Aprobado:** habilita orden de trabajo y reserva/descuento de stock según política.
- **Rechazado/Vencido:** no impacta stock ni agenda.
