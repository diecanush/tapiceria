# Almacenamiento JSON (fase inicial/prototipo)

## Objetivo
Definir una estructura JSON simple para persistencia local o intercambio de datos en etapas tempranas.

## Colecciones sugeridas
- `usuarios.json`
- `clientes.json`
- `solicitudes.json`
- `presupuestos.json`
- `insumos.json`
- `movimientos_stock.json`
- `trabajos_agenda.json`

## Reglas
- Cada registro debe tener `id`, `created_at`, `updated_at`.
- Usar IDs únicos (UUID recomendado).
- Mantener referencias por `*_id` entre entidades.

## Nota
En producción, migrar a base de datos relacional para integridad y concurrencia.
