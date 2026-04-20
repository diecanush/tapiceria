# Plan de trabajo: Software para Tapicería

## 1) Objetivo del sistema
Construir una aplicación que permita:
- Elaborar presupuestos de trabajos de tapicería.
- Controlar stock de insumos (telas, espumas, hilos, herrajes, pegamentos, etc.).
- Agendar solicitudes y hacer seguimiento del estado del trabajo.

## 2) Alcance funcional (MVP)

### 2.1 Presupuestos
- Alta de clientes.
- Alta de solicitudes (tipo de mueble, medidas, fotos, observaciones).
- Cálculo de presupuesto con:
  - Mano de obra.
  - Insumos y cantidades estimadas.
  - Margen/recargo.
  - Impuestos (si aplica).
- Generación de presupuesto en PDF.
- Estados del presupuesto: borrador, enviado, aprobado, rechazado, vencido.

### 2.2 Stock de insumos
- Catálogo de insumos con unidad de medida (m, kg, unidad).
- Registro de stock inicial y movimientos (ingreso, egreso, ajuste).
- Descuento automático de stock al aprobar/ejecutar trabajos.
- Alertas de stock mínimo.
- Reporte simple de consumo por período.

### 2.3 Agenda de solicitudes
- Calendario de trabajos (día/semana).
- Asignación de fechas comprometidas y prioridad.
- Estados del trabajo: pendiente, en proceso, pausado, finalizado, entregado.
- Recordatorios de vencimientos/entregas próximas.

## 3) Requerimientos no funcionales
- Multiusuario básico (admin y operario).
- Seguridad: autenticación con contraseña cifrada y control por roles.
- Respaldos automáticos diarios.
- Trazabilidad mínima (quién creó o modificó datos clave).
- Rendimiento: operaciones habituales por debajo de 2 segundos.

## 4) Modelo de datos inicial (entidades)
- Usuario
- Cliente
- Solicitud
- Presupuesto
- ÍtemPresupuesto
- Insumo
- MovimientoStock
- TrabajoAgenda
- EstadoHistorial
- ArchivoAdjunto (fotos/documentos)

## 5) Fases del proyecto

### Fase 0 — Descubrimiento (1 semana)
- Relevar proceso actual del taller.
- Definir reglas de negocio (cálculo de presupuesto, política de señas, stock mínimo).
- Priorizar funcionalidades del MVP.

**Entregable:** Documento de requerimientos + mapa de procesos.

### Fase 1 — Diseño funcional y técnico (1 semana)
- Diseñar flujos UX: crear presupuesto, aprobar, reservar stock, planificar trabajo.
- Diseñar base de datos y API.
- Definir stack tecnológico y arquitectura.

**Entregable:** Prototipo navegable + esquema de BD + backlog técnico.

### Fase 2 — Desarrollo del MVP (4 a 6 semanas)
- Sprint 1: autenticación, clientes, solicitudes.
- Sprint 2: módulo de presupuestos.
- Sprint 3: módulo de stock y movimientos.
- Sprint 4: agenda y estados de trabajo.
- Sprint 5 (si aplica): reportes básicos y ajustes finales.

**Entregable:** MVP funcional en entorno de prueba.

### Fase 3 — Pruebas y ajuste operativo (1 a 2 semanas)
- Pruebas funcionales con casos reales.
- Corrección de errores críticos.
- Carga inicial de datos (insumos y clientes).
- Capacitación breve al equipo.

**Entregable:** Versión candidata a producción + checklist de salida.

### Fase 4 — Puesta en producción y soporte inicial (2 semanas)
- Deploy productivo.
- Monitoreo de uso.
- Soporte de incidencias.
- Ajustes de baja complejidad.

**Entregable:** Sistema operando y estabilizado.

## 6) Backlog priorizado (primeras historias)
1. Como administrador, quiero crear clientes para asociar presupuestos.
2. Como tapicero, quiero registrar una solicitud con fotos para evaluar trabajo.
3. Como administrador, quiero generar un presupuesto en PDF para enviarlo al cliente.
4. Como administrador, quiero aprobar presupuesto y generar orden de trabajo.
5. Como encargado, quiero descontar insumos automáticamente del stock.
6. Como encargado, quiero ver alertas de stock mínimo para reponer a tiempo.
7. Como administrador, quiero agendar fecha de entrega para organizar el taller.
8. Como administrador, quiero ver el estado de cada trabajo para seguimiento.

## 7) KPIs para medir éxito
- Tiempo promedio de confección de presupuesto.
- Tasa de aprobación de presupuestos.
- Diferencia entre consumo estimado vs real de insumos.
- Cumplimiento de fechas de entrega.
- Cantidad de quiebres de stock por mes.

## 8) Riesgos y mitigación
- **Datos iniciales incompletos:** plan de carga y validación previa.
- **Resistencia al cambio:** capacitación corta y proceso guiado por etapas.
- **Errores en fórmulas de costos:** pruebas con presupuestos históricos.
- **Falta de disciplina en carga diaria:** interfaz simple y recordatorios.

## 9) Recomendación de implementación
- Comenzar con MVP web (desktop-first) para uso interno del taller.
- Integrar WhatsApp/email en una segunda etapa para envío automático de presupuestos/avisos.
- Revisar resultados del MVP a los 60 días y planificar mejoras.

## 10) Cronograma sugerido (estimado total: 9 a 12 semanas)
- Semana 1: descubrimiento.
- Semana 2: diseño.
- Semanas 3 a 8: desarrollo MVP.
- Semanas 9 a 10: pruebas + capacitación.
- Semanas 11 a 12: salida a producción + soporte.

---

Si querés, el siguiente paso puede ser convertir este plan en:
1) lista de tareas en formato Kanban (To Do / Doing / Done),
2) especificación funcional detallada por pantalla,
3) presupuesto de desarrollo por fases.
