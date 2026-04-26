# Módulo de estimación de piezas por sillón (propuesta)

## 1) Objetivo

Agregar un módulo que facilite la carga para tapicería, permitiendo:

- definir dimensiones por **módulo del sillón** (asiento, respaldo, apoyabrazos, almohadón),
- generar automáticamente piezas de corte con nombre y medidas,
- acomodar piezas en tela para estimar consumo total,
- reducir errores de omisión o duplicación de piezas.

## 2) Problema a resolver

Hoy el asistente permite cargar piezas de forma manual. Esto funciona para casos simples, pero:

- obliga a pensar pieza por pieza,
- incrementa riesgo de olvidar partes por módulo,
- no ofrece control claro de completitud por cada módulo del mueble.

## 3) Alcance del MVP

### Incluye

1. **Modo de carga por módulos** en el asistente:
   - asiento, respaldo, apoyabrazos, almohadón.
2. **Generación automática de piezas** desde dimensiones:
   - alto, ancho, profundidad y cantidad por módulo.
3. **Cálculo de acomodo en tela** (nesting heurístico) y consumo total:
   - largo total de tela,
   - porcentaje de desperdicio.
4. **Croquis de piezas** con nombre y medidas.
5. **Validaciones de completitud** por módulo (alertas antes de confirmar).

### No incluye (MVP)

- optimizador exacto global de corte (se usará heurística),
- render 3D del sillón,
- simulación de costuras avanzada.

## 4) UX propuesta

## 4.1 Entrada al módulo

En el Asistente de insumos, agregar selector:

- `Modo: Piezas libres | Módulos de sillón`

## 4.2 Flujo en “Módulos de sillón”

1. Mostrar silueta simple del sillón (SVG) con módulos clickeables.
2. Al tocar un módulo, abrir panel de dimensiones:
   - ancho, alto, profundidad, cantidad.
3. Botón **Generar piezas**:
   - crea lista de piezas sugeridas con nombre.
4. Botón **Calcular tela**:
   - ubica piezas en ancho de tela,
   - devuelve largo estimado + desperdicio.
5. Mostrar **croquis de corte** con piezas etiquetadas.
6. Permitir edición manual final y confirmación.

## 4.3 Alta/baja de módulos (casos reales)

El configurador debe permitir **agregar o quitar módulos** según el trabajo:

- otomana: sin respaldo ni apoyabrazos,
- silla/butaca: respaldo + asiento, apoyabrazos opcional,
- solo almohadones: sin estructura de sillón.

Regla UX:

- cada módulo visible en “chips” con acciones `+ Agregar` / `Quitar`,
- el usuario define únicamente los módulos que aplican al trabajo actual.

## 5) Modelo de datos propuesto

## 5.1 Entidades nuevas (lógicas)

### ModuloMueble

- `id`
- `tipo` (`asiento`, `respaldo`, `apoyabrazos`, `almohadon`)
- `ancho_cm`
- `alto_cm`
- `profundidad_cm`
- `cantidad`

### PiezaGenerada

- `id`
- `modulo_id`
- `nombre_pieza` (ej: `asiento_tapa_superior`)
- `ancho_cm`
- `alto_cm`
- `cantidad`
- `insumo_categoria` (ej: `tela`)
- `rotacion_permitida` (bool)

### ResultadoNesting

- `insumo_categoria`
- `ancho_tela_cm`
- `largo_total_cm`
- `desperdicio_pct`
- `piezas_ubicadas[]` (x, y, ancho, alto, nombre)
- `piezas_no_ubicadas[]`

## 6) Reglas de generación de piezas (MVP)

Ejemplo para módulo **asiento**:

- tapa superior: `ancho x profundidad` (x1)
- tapa inferior: `ancho x profundidad` (x1)
- frente: `ancho x alto` (x1)
- fondo: `ancho x alto` (x1)
- lateral izquierdo: `profundidad x alto` (x1)
- lateral derecho: `profundidad x alto` (x1)

Ejemplo para **almohadón**:

- cara frontal: `ancho x alto` (x1)
- cara posterior: `ancho x alto` (x1)
- fuelle lateral (si aplica): calculado por perímetro
- cierre (si aplica): largo configurable

## 7) Algoritmo de acomodo en tela

## 7.1 MVP (heurístico)

Implementar estrategia por filas (“shelves”):

1. ordenar piezas por ancho/alto descendente,
2. ubicar en la primera fila donde entre,
3. crear nueva fila cuando no entra,
4. sumar altos de filas para largo total.

## 7.2 Salidas

- largo total de tela,
- piezas ubicadas (con coordenadas),
- porcentaje de desperdicio.

## 7.3 Evolución futura

Migrar a `MaxRects` o `Guillotine` para mayor aprovechamiento.

## 8) Validaciones anti-error

Antes de confirmar:

- alertar módulos incompletos (piezas obligatorias faltantes),
- alertar piezas con dimensión inválida (`<= 0`),
- alertar duplicados sospechosos (nombre + dimensión repetidos),
- mostrar checklist por módulo (OK / faltante).

Importante: las validaciones deben considerar **solo módulos activos** en ese presupuesto.

## 9) Criterios de aceptación

1. Dado un sillón con 2 apoyabrazos, al ingresar dimensiones por módulo se generan piezas para cada módulo.
2. Dada una **otomana**, se puede quitar respaldo y apoyabrazos y el sistema no exige esas piezas.
3. Dada una **silla/butaca sin apoyabrazos**, el sistema permite desactivarlos y calcula solo módulos activos.
4. Se puede crear presupuesto de **solo almohadones** sin cargar estructura de sillón.
5. El usuario visualiza un croquis con nombres y medidas de piezas.
6. El sistema calcula largo total de tela y desperdicio.
7. El usuario puede editar piezas autogeneradas antes de confirmar.
8. Si faltan piezas obligatorias de módulos activos, el sistema advierte antes de agregar al presupuesto.

## 10) Plan de implementación (iterativo)

### Fase A — Base

- agregar estructura de módulos y piezas generadas,
- agregar reglas fijas para 3-4 módulos.

### Fase B — Visual

- implementar silueta SVG clickeable,
- editor lateral por módulo.

### Fase C — Cálculo

- motor de acomodado heurístico + croquis.

### Fase D — Robustez

- validaciones de completitud,
- plantillas configurables por tipo de trabajo.
