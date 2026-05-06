# Especificación funcional/técnica — Presupuesto V2 por capas e insumos

## 1) Objetivo
Implementar un flujo de presupuesto orientado a tapicería que permita:
- preconfigurar módulos por tipo de mueble,
- seleccionar capa -> tipo de insumo -> insumo filtrado,
- calcular cantidades por reglas de cada tipo de insumo,
- confirmar cada bloque de insumo antes de persistir,
- guardar snapshot completo en JSON para trazabilidad.

---

## 2) Flujo de usuario (alto nivel)
1. Crear presupuesto V2.
2. Seleccionar cliente y tipo de mueble.
3. Se precargan módulos base según tipo de mueble.
4. Agregar bloques por insumo:
   - elegir capa,
   - elegir tipo de insumo permitido,
   - elegir insumo filtrado,
   - completar campos específicos del tipo,
   - confirmar módulos/piezas,
   - revisar estimación parcial,
   - presionar **Confirmar insumo**.
5. Guardar presupuesto.

Solo los bloques confirmados se persisten en `estructura_insumos_v2`.

---

## 3) Reglas de dominio

### 3.1 Tipos de mueble y módulos
Cada tipo de mueble define módulos por defecto.

Ejemplo:
- `sillon_2_cuerpos`: asiento, respaldo, brazo_izq, brazo_der, base.
- `silla`: asiento, respaldo.

### 3.2 Capas y tipos de insumo permitidos
Capas sugeridas:
- `estructura_suspension`
- `soporte_confort`
- `regularizacion_proteccion`
- `cobertura`
- `fijacion_terminacion`

Tipos de insumo:
- `tela`, `gomaespuma`, `fleje`, `guata`, `fliselina`, `adhesivo_contacto`, `grapas`, `tachas`, `cierre`, `otros`.

Regla: cada capa define qué tipos de insumo pueden seleccionarse.

### 3.3 Confirmación por bloque
Estados:
- pendiente
- confirmado
- en edición

Solo `confirmado = true` entra al cálculo final y al JSON persistido.

---

## 4) Reglas de cálculo por tipo

### 4.1 Tela
Entradas:
- ancho útil del rollo,
- piezas (alto, ancho, cantidad),
- check `rotable` por pieza,
- merma %, rendimiento.

Reglas:
- validar si cada pieza entra en ancho útil;
- si no entra y no es rotable, alertar;
- si rotable y rotada tampoco entra, alertar;
- sugerir dividir en 2+ paños más angostos;
- calcular consumo en metros lineales.

### 4.2 Gomaespuma
Entradas:
- largo/ancho placa, espesor,
- piezas y cantidades,
- merma.

Reglas:
- calcular área/volumen requerido;
- convertir a placas necesarias.

### 4.3 Fleje
Entradas:
- separación entre flejes,
- dirección de flejado,
- dimensiones del módulo.

Reglas:
- calcular número de tiras y metros totales.

### 4.4 Guata / Fliselina
Entradas:
- ancho útil,
- piezas,
- solape/merma.

Reglas:
- cálculo por m² o metros lineales según configuración.

### 4.5 Grapas / Tachas / Adhesivo
Entradas:
- rendimiento o separación de fijación,
- perímetro o área aplicable.

Reglas:
- estimación por puntos de fijación o m² cubiertos.

---

## 5) Validaciones mínimas
- Cliente obligatorio.
- Tipo de mueble obligatorio.
- Cada bloque debe tener capa, tipo de insumo e insumo válidos.
- Para confirmar bloque:
  - al menos 1 módulo activo,
  - al menos 1 pieza activa con medidas/cantidad válidas.
- Si no hay ningún bloque confirmado: error de guardado.

---

## 6) Configuración central (nueva pantalla)
Crear `config_capas_insumos.php` para administrar:
- tipos de mueble y módulos por defecto,
- capas activas,
- mapeo capa -> tipos permitidos,
- defaults por tipo de insumo (merma, rendimiento, ancho útil, separación, etc.),
- piezas por módulo.

Persistencia sugerida:
- `project-skeleton/data/config_capas_insumos.json`

---

## 7) Estructura JSON propuesta

```json
{
  "version": "1.0",
  "updated_at": "2026-05-03",
  "muebles": {
    "sillon_2_cuerpos": {
      "modulos_default": ["asiento", "respaldo", "brazo_izq", "brazo_der", "base"]
    }
  },
  "capas": {
    "cobertura": {
      "tipos_insumo_permitidos": ["tela", "cierre", "guata", "fliselina"]
    },
    "estructura_suspension": {
      "tipos_insumo_permitidos": ["fleje", "grapas", "tachas"]
    }
  },
  "tipos_insumo": {
    "tela": {
      "defaults": {
        "merma_pct": 10,
        "rendimiento": 1,
        "ancho_util_m": 1.4,
        "permite_rotacion": true
      }
    }
  }
}
```

---

## 8) Criterios de aceptación
1. Al elegir tipo de mueble se precargan módulos.
2. Al elegir capa se filtran tipos de insumo permitidos.
3. Al elegir tipo se filtran insumos.
4. El formulario muestra campos según tipo de insumo.
5. El cálculo parcial refleja reglas del tipo.
6. El check `rotable` afecta validaciones de ancho en tela.
7. Solo bloques confirmados se guardan en JSON.
8. El presupuesto guarda versión/configuración usada.

---

## 9) Plan de implementación por etapas

### Etapa 1
- Documento y JSON base de configuración.
- Integrar tipo de mueble + módulos default en V2.

### Etapa 2
- Filtro capa -> tipo -> insumo.
- UI dinámica por tipo de insumo.

### Etapa 3
- Motor de cálculo por tipo (tela/gomaespuma/fleje primero).
- Alertas de corte y sugerencia de división.

### Etapa 4
- Persistencia de snapshot de reglas/configuración.
- Ajustes UX y validaciones finales.
