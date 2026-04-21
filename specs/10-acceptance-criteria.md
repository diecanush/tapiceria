# Criterios de Aceptación

## Presupuestos
- Se puede crear cliente y solicitud asociada.
- Se puede calcular total con mano de obra + insumos + margen + impuestos.
- Se pueden registrar medidas del mueble y piezas a cortar por presupuesto.
- El sistema calcula automáticamente consumo estimado de cada insumo según piezas y desperdicio.
- Se puede editar manualmente la estimación automática de insumos antes de confirmar.
- Se puede exportar presupuesto PDF.
- Cambios de estado respetan flujo definido.

## Stock
- Se puede dar de alta insumo con unidad y stock mínimo.
- Se registran movimientos con trazabilidad.
- El sistema alerta faltantes al llegar al mínimo.

## Agenda
- Se puede planificar trabajo con fecha comprometida.
- Se puede cambiar estado de trabajo durante ejecución.
- Se visualizan entregas próximas.

## No funcional
- Login operativo con rol admin/operario.
- Operaciones comunes responden en menos de 2 segundos en condiciones normales.
