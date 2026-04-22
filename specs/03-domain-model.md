# Modelo de Dominio

## Entidades principales
- **Usuario** (rol: admin, operario).
- **Cliente**.
- **Solicitud** (trabajo solicitado por cliente).
- **Presupuesto**.
- **ItemPresupuesto**.
- **PiezaCorte** (dimensiones y cantidad por sección del mueble).
- **Insumo**.
- **MovimientoStock**.
- **TrabajoAgenda**.
- **EstadoHistorial**.
- **ArchivoAdjunto** (fotos/documentos).

## Relaciones clave
- Un **Cliente** tiene muchas **Solicitudes**.
- Una **Solicitud** puede tener uno o más **Presupuestos**.
- Un **Presupuesto** tiene muchos **ItemPresupuesto**.
- Un **Presupuesto** tiene muchas **PiezaCorte** para estimar consumo de insumos.
- Un **Insumo** tiene muchos **MovimientoStock**.
- Un **TrabajoAgenda** se asocia a una **Solicitud** aprobada.

## Reglas de trazabilidad
- Registrar usuario/fecha de creación y modificación en entidades críticas.
