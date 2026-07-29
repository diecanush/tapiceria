<?php
declare(strict_types=1);

function presupuesto_tree_number($value): float
{
    $raw = trim((string) $value);
    if ($raw === '') {
        return 0.0;
    }
    $normalized = preg_replace('/[^0-9,.-]/', '', $raw) ?? '';
    if (strpos($normalized, ',') !== false) {
        $normalized = str_replace('.', '', $normalized);
        $normalized = str_replace(',', '.', $normalized);
    }
    return is_numeric($normalized) ? (float) $normalized : 0.0;
}

function presupuesto_tree_labor_total(array $template): float
{
    $minutes = array_sum(array_map('intval', (array) ($template['tiempos_minutos'] ?? [])));
    return round(($minutes / 60) * max(0, (float) ($template['tarifa_hora'] ?? 0)), 2);
}

function presupuesto_tree_pack_shelves(array $rectangles, float $rollWidth): float
{
    usort($rectangles, static function (array $left, array $right): int {
        return max($right['length'], $right['width']) <=> max($left['length'], $left['width']);
    });
    $shelves = [];
    foreach ($rectangles as $rectangle) {
        $best = -1;
        $increase = INF;
        foreach ($shelves as $index => $shelf) {
            if ($shelf['width'] + $rectangle['width'] > $rollWidth + 0.000001) {
                continue;
            }
            $added = max($shelf['length'], $rectangle['length']) - $shelf['length'];
            if ($added < $increase) {
                $increase = $added;
                $best = $index;
            }
        }
        if ($best < 0) {
            $shelves[] = ['width' => $rectangle['width'], 'length' => $rectangle['length']];
        } else {
            $shelves[$best]['width'] += $rectangle['width'];
            $shelves[$best]['length'] = max($shelves[$best]['length'], $rectangle['length']);
        }
    }
    return array_sum(array_column($shelves, 'length'));
}

function presupuesto_tree_linear_piece_meters(array $pieces, float $rollWidth): float
{
    $rollWidth = max(0.01, $rollWidth);
    $rectangles = [];
    $fallback = 0.0;
    foreach ($pieces as $piece) {
        $quantity = max(0, (int) ($piece['cantidad'] ?? 0));
        $height = max(0, presupuesto_tree_number($piece['alto'] ?? 0)) / 100;
        $width = max(0, presupuesto_tree_number($piece['ancho'] ?? 0)) / 100;
        $options = [['length' => $height, 'width' => $width]];
        if (($piece['rotatable'] ?? $piece['rotable'] ?? true) !== false && $height !== $width) {
            $options[] = ['length' => $width, 'width' => $height];
        }
        $valid = array_values(array_filter($options, static fn (array $option): bool => $option['width'] <= $rollWidth + 0.000001));
        if ($valid === []) {
            $fallback += ($height * $width / $rollWidth) * $quantity;
            continue;
        }
        for ($copy = 0; $copy < $quantity; $copy++) {
            $rectangles[] = ['options' => $valid];
        }
    }
    if ($rectangles === []) {
        return $fallback;
    }

    $best = INF;
    if (count($rectangles) <= 12) {
        $search = function (int $index, array $chosen) use (&$search, &$best, $rectangles, $rollWidth): void {
            if ($index === count($rectangles)) {
                $best = min($best, presupuesto_tree_pack_shelves($chosen, $rollWidth));
                return;
            }
            foreach ($rectangles[$index]['options'] as $option) {
                $search($index + 1, [...$chosen, $option]);
            }
        };
        $search(0, []);
    } else {
        $chosen = array_map(static function (array $rectangle): array {
            return array_reduce($rectangle['options'], static fn (array $shortest, array $option): array => $option['length'] < $shortest['length'] ? $option : $shortest, $rectangle['options'][0]);
        }, $rectangles);
        $best = presupuesto_tree_pack_shelves($chosen, $rollWidth);
    }
    return $best + $fallback;
}

function presupuesto_tree_material_quantity(array $input, array $module): array
{
    $type = (string) ($input['tipo'] ?? 'otros');
    $pieces = array_values((array) ($input['piezas'] ?? []));
    $waste = max(0, presupuesto_tree_number($input['merma_pct'] ?? 0));
    $manual = presupuesto_tree_number($input['cantidad_ajustada'] ?? 0);
    $area = 0.0;
    $linear = 0.0;

    foreach ($pieces as $piece) {
        $quantity = max(0, (int) ($piece['cantidad'] ?? 0));
        $height = max(0, presupuesto_tree_number($piece['alto'] ?? 0)) / 100;
        $width = max(0, presupuesto_tree_number($piece['ancho'] ?? 0)) / 100;
        $area += $height * $width * $quantity;
        $linear += max($height, $width) * $quantity;
    }

    $base = $area;
    $detail = 'area';
    if ($type === 'tela' || $type === 'guata' || $type === 'fliselina') {
        $rollWidth = max(0.01, presupuesto_tree_number($input['ancho_util'] ?? 1.4));
        $base = presupuesto_tree_linear_piece_meters($pieces, $rollWidth);
        $detail = 'metros_lineales';
    } elseif (in_array($type, ['gomaespuma', 'madera'], true)) {
        $sheetLength = max(0.01, presupuesto_tree_number($input['placa_largo'] ?? 2));
        $sheetWidth = max(0.01, presupuesto_tree_number($input['placa_ancho'] ?? 1));
        $base = ceil(($area / ($sheetLength * $sheetWidth)) * 4) / 4;
        $detail = 'placas';
    } elseif (in_array($type, ['cierre', 'cordon'], true)) {
        $base = $linear;
        $detail = 'metros_lineales';
    } elseif ($type === 'fleje') {
        $height = max(0, presupuesto_tree_number($module['alto'] ?? 0)) / 100;
        $width = max(0, presupuesto_tree_number($module['ancho'] ?? 0)) / 100;
        $spacing = max(0.01, presupuesto_tree_number($input['separacion_cm'] ?? 10) / 100);
        $pattern = (string) ($input['patron'] ?? 'lineal');
        $direction = (string) ($input['direccion'] ?? 'ancho');
        $acrossWidth = (int) ceil($height / $spacing);
        $acrossLength = (int) ceil($width / $spacing);
        if ($pattern === 'cuadriculado') {
            $strips = $acrossWidth + $acrossLength;
            $base = ($acrossWidth * $width) + ($acrossLength * $height);
        } elseif ($direction === 'largo') {
            $strips = $acrossLength;
            $base = $strips * $height;
        } else {
            $strips = $acrossWidth;
            $base = $strips * $width;
        }
        $input['tiras'] = $strips;
        $input['grapas_estimadas'] = $strips * 2 * max(1, (int) ($input['grapas_por_extremo'] ?? 2));
        $detail = 'metros_fleje';
    } elseif (in_array($type, ['grapas', 'tachas'], true)) {
        $base = max(0, presupuesto_tree_number($input['cantidad_calculada'] ?? 0));
        $detail = 'unidades';
    }

    $calculated = round($base * (1 + ($waste / 100)), 4);
    $final = $manual > 0 ? $manual : $calculated;
    $input['cantidad_calculada'] = $calculated;
    $input['cantidad_final'] = round($final, 4);
    $input['origen_cantidad'] = $manual > 0 ? 'ajuste_manual' : $detail;
    return $input;
}

function presupuesto_tree_calculate(array $payload, array $supplies): array
{
    $suppliesById = [];
    foreach ($supplies as $supply) {
        $suppliesById[(int) ($supply['id'] ?? 0)] = $supply;
    }

    $materials = 0.0;
    $labor = 0.0;
    $items = [];
    foreach ((array) ($payload['items'] ?? []) as $itemIndex => $item) {
        $quantity = max(1, (int) ($item['cantidad'] ?? 1));
        $unitLabor = max(0, presupuesto_tree_number($item['mano_obra_unitaria'] ?? 0));
        $itemMaterials = 0.0;
        $modules = [];
        foreach ((array) ($item['modulos'] ?? []) as $moduleIndex => $module) {
            $moduleQuantity = max(1, (int) ($module['cantidad'] ?? 1));
            $layers = [];
            foreach ((array) ($module['capas'] ?? []) as $layerIndex => $layer) {
                $inputs = [];
                foreach ((array) ($layer['insumos'] ?? []) as $inputIndex => $input) {
                    $supplyId = (int) ($input['insumo_id'] ?? 0);
                    if ($supplyId <= 0 || !isset($suppliesById[$supplyId])) {
                        continue;
                    }
                    $input = presupuesto_tree_material_quantity($input, $module);
                    $unitCost = max(0, presupuesto_tree_number($input['costo_unitario'] ?? $suppliesById[$supplyId]['precio'] ?? 0));
                    $input['cantidad_por_modulo'] = $input['cantidad_final'];
                    $input['cantidad_modulos'] = $moduleQuantity;
                    $input['cantidad_final'] = round($input['cantidad_final'] * $moduleQuantity, 4);
                    $cost = round($input['cantidad_final'] * $unitCost, 2);
                    $input['id'] = (string) ($input['id'] ?? 'insumo-' . $itemIndex . '-' . $moduleIndex . '-' . $layerIndex . '-' . $inputIndex);
                    $input['nombre'] = (string) ($suppliesById[$supplyId]['nombre'] ?? 'Insumo');
                    $input['unidad'] = (string) ($suppliesById[$supplyId]['unidad'] ?? 'unidad');
                    $input['costo_unitario'] = $unitCost;
                    $input['costo_unitario_total'] = $cost;
                    $inputs[] = $input;
                    $itemMaterials += $cost;
                }
                $layer['insumos'] = $inputs;
                $layers[] = $layer;
            }
            $module['cantidad'] = $moduleQuantity;
            $module['capas'] = $layers;
            $modules[] = $module;
        }
        $item['cantidad'] = $quantity;
        $item['mano_obra_unitaria'] = $unitLabor;
        $item['modulos'] = $modules;
        $item['materiales_unitarios'] = round($itemMaterials, 2);
        $item['subtotal_unitario'] = round($unitLabor + $itemMaterials, 2);
        $item['subtotal_total'] = round(($unitLabor + $itemMaterials) * $quantity, 2);
        $materials += $itemMaterials * $quantity;
        $labor += $unitLabor * $quantity;
        $items[] = $item;
    }

    $margin = max(0, presupuesto_tree_number($payload['margen'] ?? 30));
    $subtotal = $materials + $labor;
    $payload['items'] = $items;
    $payload['mano_obra'] = round($labor, 2);
    $payload['materiales'] = round($materials, 2);
    $payload['margen'] = $margin;
    $payload['total'] = round($subtotal * (1 + ($margin / 100)), 2);
    return $payload;
}

function presupuesto_tree_from_legacy(array $budget): array
{
    if (isset($budget['items']) && is_array($budget['items'])) {
        return $budget;
    }

    // El formato clásico guardaba insumos y piezas en listas planas. Lo
    // convertimos solo para mostrar/editar en V2; el registro original no se
    // modifica hasta que el usuario lo guarde explícitamente.
    if (!isset($budget['estructura_insumos_v2']) && isset($budget['insumos_estimados'])) {
        $pieces = [];
        foreach ((array) ($budget['piezas_corte'] ?? []) as $piece) {
            $pieces[] = [
                'modulo' => (string) ($piece['modulo'] ?? 'modulo'),
                'insumo_tipo' => (string) ($piece['insumo_tipo'] ?? 'otros'),
                'pieza' => (string) ($piece['pieza'] ?? 'pieza'),
                'alto' => (float) ($piece['alto'] ?? 0),
                'ancho' => (float) ($piece['ancho'] ?? 0),
                'cantidad' => (int) ($piece['cantidad'] ?? 1),
            ];
        }
        $inputs = [];
        foreach ((array) $budget['insumos_estimados'] as $legacyInput) {
            $inputType = (string) ($legacyInput['categoria'] ?? 'otros');
            $inputPieces = array_values(array_filter(
                $pieces,
                static fn (array $piece): bool => (string) ($piece['insumo_tipo'] ?? '') === $inputType
            ));
            foreach ($inputPieces as &$inputPiece) {
                unset($inputPiece['modulo']);
            }
            unset($inputPiece);
            $inputs[] = [
                'insumo_id' => (int) ($legacyInput['insumo_id'] ?? 0),
                'tipo' => $inputType,
                'piezas' => $inputPieces,
                'merma_pct' => 0,
                'cantidad_ajustada' => (float) ($legacyInput['cantidad'] ?? 0),
                'costo_unitario' => (float) ($legacyInput['costo_unitario'] ?? 0),
                'motivo_ajuste' => 'Importado del formato clásico',
            ];
        }
        $budget['items'] = [[
            'id' => 'legacy-item',
            'tipo_mueble' => (string) ($budget['mueble_tipo'] ?? 'personalizado'),
            'complejidad' => 'importada',
            'cantidad' => 1,
            'mano_obra_unitaria' => (float) ($budget['mano_obra'] ?? 0),
            'mano_obra_plantilla_id' => $budget['mano_obra_plantilla_id'] ?? null,
            'mano_obra_snapshot' => $budget['mano_obra_plantilla_snapshot'] ?? null,
            'modulos' => [[
                'id' => 'legacy-modulo',
                'tipo' => 'Importado',
                'alto' => 0,
                'ancho' => 0,
                'profundidad' => 0,
                'capas' => [[
                    'id' => 'legacy-capa',
                    'tipo' => 'importada',
                    'insumos' => $inputs,
                ]],
            ]],
        ]];
        return $budget;
    }

    $modules = [];
    foreach ((array) ($budget['estructura_insumos_v2'] ?? []) as $legacyInput) {
        foreach ((array) ($legacyInput['modulos'] ?? []) as $legacyModule) {
            $name = (string) ($legacyModule['modulo'] ?? 'modulo');
            if (!isset($modules[$name])) {
                $modules[$name] = [
                    'id' => 'legacy-' . $name,
                    'tipo' => $name,
                    'alto' => 0,
                    'ancho' => 0,
                    'profundidad' => 0,
                    'capas' => [],
                ];
            }
            $layer = (string) ($legacyInput['capa'] ?? 'capa');
            if (!isset($modules[$name]['capas'][$layer])) {
                $modules[$name]['capas'][$layer] = ['id' => 'legacy-' . $name . '-' . $layer, 'tipo' => $layer, 'insumos' => []];
            }
            $modules[$name]['capas'][$layer]['insumos'][] = [
                'insumo_id' => (int) ($legacyInput['insumo']['id'] ?? 0),
                'tipo' => (string) ($legacyInput['tipo_insumo'] ?? 'otros'),
                'piezas' => (array) ($legacyModule['piezas'] ?? []),
                'merma_pct' => (float) ($legacyInput['parametros_calculo']['merma_pct'] ?? 0),
                'cantidad_ajustada' => (float) ($legacyInput['totales']['cantidad_total'] ?? 0),
                'costo_unitario' => (float) ($legacyInput['insumo']['costo_unitario'] ?? 0),
                'motivo_ajuste' => 'Importado del formato V2 anterior',
            ];
        }
    }
    foreach ($modules as &$module) {
        $module['capas'] = array_values($module['capas']);
    }
    unset($module);
    $budget['items'] = [[
        'id' => 'legacy-item',
        'tipo_mueble' => (string) ($budget['mueble_tipo'] ?? 'personalizado'),
        'complejidad' => 'importada',
        'cantidad' => 1,
        'mano_obra_unitaria' => (float) ($budget['mano_obra'] ?? 0),
        'mano_obra_plantilla_id' => $budget['mano_obra_plantilla_id'] ?? null,
        'mano_obra_snapshot' => $budget['mano_obra_plantilla_snapshot'] ?? null,
        'modulos' => array_values($modules),
    ]];
    return $budget;
}
