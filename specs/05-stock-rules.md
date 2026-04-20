# Reglas de Stock

## Unidades
- Metro (m), kilogramo (kg), unidad (u).

## Tipos de movimientos
- **Ingreso:** suma stock.
- **Egreso:** resta stock.
- **Ajuste:** corrige diferencia inventario/sistema.

## Reglas operativas
1. No permitir egreso que deje stock negativo (salvo permiso admin explícito).
2. Cada movimiento debe guardar motivo y usuario.
3. Al aprobar o ejecutar trabajo, descontar insumos según configuración.
4. Definir stock mínimo por insumo.
5. Generar alerta visual cuando stock actual ≤ stock mínimo.

## Reportes mínimos
- Consumo por período.
- Top insumos con mayor rotación.
