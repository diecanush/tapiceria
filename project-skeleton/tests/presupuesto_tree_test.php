<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/presupuesto_tree.php';

function assert_close(float $actual, float $expected, string $message): void
{
    if (abs($actual - $expected) > 0.001) {
        throw new RuntimeException($message . ": esperado {$expected}, obtenido {$actual}");
    }
}

$supplies = [
    ['id' => 1, 'nombre' => 'Tela', 'unidad' => 'm', 'precio' => 100],
    ['id' => 2, 'nombre' => 'Fleje', 'unidad' => 'm', 'precio' => 10],
];

$payload = presupuesto_tree_calculate([
    'margen' => 10,
    'items' => [[
        'cantidad' => 2,
        'mano_obra_unitaria' => 1000,
        'modulos' => [[
            'alto' => 100,
            'ancho' => 200,
            'profundidad' => 80,
            'capas' => [[
                'tipo' => 'cobertura',
                'insumos' => [[
                    'tipo' => 'tela',
                    'insumo_id' => 1,
                    'ancho_util' => 2,
                    'merma_pct' => 0,
                    'piezas' => [['alto' => 100, 'ancho' => 200, 'cantidad' => 1]],
                ]],
            ]],
        ]],
    ]],
], $supplies);

assert_close($payload['mano_obra'], 2000, 'La mano de obra debe multiplicarse por cantidad');
assert_close($payload['materiales'], 200, 'Los materiales unitarios deben multiplicarse por cantidad');
assert_close($payload['total'], 2420, 'El margen debe aplicarse al subtotal');

$linear = presupuesto_tree_material_quantity([
    'tipo' => 'fleje',
    'separacion_cm' => 20,
    'patron' => 'lineal',
    'direccion' => 'ancho',
    'grapas_por_extremo' => 2,
], ['alto' => 100, 'ancho' => 200]);
assert_close($linear['cantidad_final'], 10, 'Fleje lineal a lo ancho');
assert_close((float) $linear['grapas_estimadas'], 20, 'Grapas lineales sólo en extremos');

$grid = presupuesto_tree_material_quantity([
    'tipo' => 'fleje',
    'separacion_cm' => 20,
    'patron' => 'cuadriculado',
    'grapas_por_extremo' => 2,
], ['alto' => 100, 'ancho' => 200]);
assert_close($grid['cantidad_final'], 20, 'Fleje cuadriculado en ambas direcciones');
assert_close((float) $grid['grapas_estimadas'], 60, 'Grapas cuadriculadas sin contar intersecciones');

$foam = presupuesto_tree_material_quantity([
    'tipo' => 'gomaespuma',
    'placa_largo' => 2,
    'placa_ancho' => 1,
    'piezas' => [['alto' => 50, 'ancho' => 100, 'cantidad' => 1]],
], []);
assert_close($foam['cantidad_final'], 0.25, 'La gomaespuma debe redondear a cuartos de placa');

echo "OK presupuesto_tree_test\n";
