<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/presupuesto_tree.php';

function normalize_module_type(mixed $value): string
{
    $value = trim((string) $value);
    return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
}

function normalize_module_catalog(array $values): array
{
    $normalized = [];
    foreach ($values as $value) {
        $value = normalize_module_type($value);
        if ($value !== '' && !in_array($value, $normalized, true)) {
            $normalized[] = $value;
        }
    }
    return $normalized;
}

$clientes = read_json(data_file('clientes'));
$insumos = read_json(data_file('insumos'));
$manoObraValores = read_json(data_file('mano_obra_valores'));
$presupuestos = read_json(data_file('presupuestos'));
$configCapas = read_json(data_file('config_capas_insumos'));
$configCapasChanged = false;
if (isset($configCapas['muebles']) && is_array($configCapas['muebles'])) {
foreach ($configCapas['muebles'] as &$furnitureConfig) {
    foreach ((array) ($furnitureConfig['modulos_default'] ?? []) as &$defaultModule) {
        if (is_array($defaultModule)) {
            $normalizedType = normalize_module_type($defaultModule['tipo'] ?? $defaultModule['nombre'] ?? '');
            if (($defaultModule['tipo'] ?? '') !== $normalizedType) {
                $defaultModule['tipo'] = $normalizedType;
                $configCapasChanged = true;
            }
        } elseif (normalize_module_type($defaultModule) !== (string) $defaultModule) {
            $defaultModule = normalize_module_type($defaultModule);
            $configCapasChanged = true;
        }
    }
    unset($defaultModule);
}
unset($furnitureConfig);
}
if ($configCapasChanged) {
    write_json(data_file('config_capas_insumos'), $configCapas);
}
$presupuestosChanged = false;
foreach ($presupuestos as &$budget) {
    if (!isset($budget['items']) || !is_array($budget['items'])) {
        continue;
    }
    foreach ($budget['items'] as &$item) {
        if (!isset($item['modulos']) || !is_array($item['modulos'])) {
            continue;
        }
        foreach ($item['modulos'] as &$module) {
            $normalizedType = normalize_module_type($module['tipo'] ?? '');
            if (($module['tipo'] ?? '') !== $normalizedType) {
                $module['tipo'] = $normalizedType;
                $presupuestosChanged = true;
            }
        }
        unset($module);
    }
    unset($item);
}
unset($budget);
if ($presupuestosChanged) {
    write_json(data_file('presupuestos'), $presupuestos);
}
$configApp = read_json(data_file('config'));
$configApp['presupuesto_personalizados'] = (array) ($configApp['presupuesto_personalizados'] ?? []);
$catalogTypes = ['muebles', 'trabajos', 'dificultades', 'capas', 'modulos', 'tipos_insumo'];
foreach ($catalogTypes as $catalogType) {
    $configApp['presupuesto_personalizados'][$catalogType] = array_values((array) ($configApp['presupuesto_personalizados'][$catalogType] ?? []));
}
$configApp['presupuesto_personalizados']['modulos'] = normalize_module_catalog($configApp['presupuesto_personalizados']['modulos']);
$configApp['presupuesto_personalizados']['tipos_insumo'] = normalize_module_catalog($configApp['presupuesto_personalizados']['tipos_insumo']);
foreach ($presupuestos as $budget) {
    foreach ((array) ($budget['items'] ?? []) as $item) {
        foreach (['muebles' => $item['tipo_mueble'] ?? '', 'trabajos' => $item['trabajo_tipo'] ?? '', 'dificultades' => $item['complejidad'] ?? ''] as $catalogType => $value) {
            $value = trim((string) $value);
            if ($value !== '' && !in_array($value, $configApp['presupuesto_personalizados'][$catalogType], true)) {
                $configApp['presupuesto_personalizados'][$catalogType][] = $value;
            }
        }
        foreach ((array) ($item['modulos'] ?? []) as $module) {
            $moduleName = normalize_module_type($module['tipo'] ?? '');
            if ($moduleName !== '' && !in_array($moduleName, $configApp['presupuesto_personalizados']['modulos'], true)) {
                $configApp['presupuesto_personalizados']['modulos'][] = $moduleName;
            }
            foreach ((array) ($module['capas'] ?? []) as $layer) {
                $value = trim((string) ($layer['tipo'] ?? ''));
                if ($value !== '' && !in_array($value, $configApp['presupuesto_personalizados']['capas'], true)) {
                    $configApp['presupuesto_personalizados']['capas'][] = $value;
                }
            }
        }
    }
}
write_json(data_file('config'), $configApp);
$catalogosGlobales = $configApp['presupuesto_personalizados'];
$clientNames = [];
foreach ($clientes as $client) {
    $clientNames[(int) ($client['id'] ?? 0)] = (string) ($client['nombre'] ?? '');
}

function budget_supply_category(array $supply): string
{
    $category = mb_strtolower(trim((string) ($supply['categoria'] ?? 'otros')));
    $name = mb_strtolower((string) ($supply['nombre'] ?? ''));
    $rules = [
        'gomaespuma' => ['gomaespuma', 'goma espuma', 'espuma', 'alta densidad'],
        'madera' => ['madera', 'fenolico', 'fenólico', 'mdf', 'aglomerado', 'terciado', 'multilaminado'],
        'tornillos' => ['tornillo', 'tirafondo'],
        'guata' => ['guata', 'vellon', 'vellón'],
        'fliselina' => ['fliselina', 'friselina'],
        'fleje' => ['fleje', 'cincha'],
        'grapas' => ['grapa'],
        'tachas' => ['tacha'],
        'cierre' => ['cierre', 'cremallera', 'deslizador', 'tiracierre'],
        'adhesivo_contacto' => ['adhesivo', 'pegamento', 'cemento de contacto'],
    ];
    foreach ($rules as $type => $tokens) {
        foreach ($tokens as $token) {
            if (str_contains($name, $token)) {
                return $type;
            }
        }
    }
    return $category === '' ? 'otros' : $category;
}

usort($insumos, static function (array $a, array $b): int {
    return strcasecmp((string) ($a['nombre'] ?? ''), (string) ($b['nombre'] ?? ''));
});

function budget_find(array $rows, int $id): ?array
{
    foreach ($rows as $row) {
        if ((int) ($row['id'] ?? 0) === $id) {
            return $row;
        }
    }
    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? 'save');
    $id = (int) ($_POST['id'] ?? 0);
    $isAutosave = $action === 'autosave';

    if ($action === 'catalog_save') {
        $catalogType = (string) ($_POST['catalog_type'] ?? '');
        $value = trim((string) ($_POST['value'] ?? ''));
        $allowedTypes = ['muebles', 'trabajos', 'dificultades', 'capas', 'modulos', 'tipos_insumo'];
        if (!in_array($catalogType, $allowedTypes, true) || $value === '') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Datos de catálogo incompletos.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if (in_array($catalogType, ['modulos', 'tipos_insumo'], true)) {
            $value = normalize_module_type($value);
        }
        $configApp['presupuesto_personalizados'] = (array) ($configApp['presupuesto_personalizados'] ?? []);
        $catalogValues = array_merge((array) ($configApp['presupuesto_personalizados'][$catalogType] ?? []), [$value]);
        $configApp['presupuesto_personalizados'][$catalogType] = $catalogType === 'modulos'
            ? normalize_module_catalog($catalogValues)
            : array_values(array_unique($catalogValues));
        write_json(data_file('config'), $configApp);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true, 'catalog' => $configApp['presupuesto_personalizados']], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'supply_save') {
        $supplyId = (int) ($_POST['supply_id'] ?? 0);
        $name = trim((string) ($_POST['nombre'] ?? ''));
        $unit = trim((string) ($_POST['unidad'] ?? 'unidad')) ?: 'unidad';
        $category = normalize_module_type($_POST['categoria'] ?? 'otros') ?: 'otros';
        $allowedCategories = ['tela', 'gomaespuma', 'madera', 'guata', 'fliselina', 'fleje', 'grapas', 'tachas', 'tornillos', 'cierre', 'cordon', 'adhesivo_contacto', 'otros'];
        $customCategories = (array) ($configApp['presupuesto_personalizados']['tipos_insumo'] ?? []);
        if (!in_array($category, $allowedCategories, true) && !in_array($category, $customCategories, true)) {
            $customCategories[] = $category;
            $configApp['presupuesto_personalizados']['tipos_insumo'] = normalize_module_catalog($customCategories);
            write_json(data_file('config'), $configApp);
        }
        if ($name === '') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'El nombre del insumo es obligatorio.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $supply = null;
        if ($supplyId > 0) {
            foreach ($insumos as &$row) {
                if ((int) ($row['id'] ?? 0) === $supplyId) {
                    $row['nombre'] = $name;
                    $row['unidad'] = $unit;
                    $row['categoria'] = $category;
                    $row['precio'] = max(0, (float) ($_POST['precio'] ?? 0));
                    $row['stock'] = max(0, (float) ($row['stock'] ?? 0));
                    $row['stock_minimo'] = max(0, (float) ($row['stock_minimo'] ?? 0));
                    $supply = $row;
                    break;
                }
            }
            unset($row);
        } else {
            $supply = [
                'id' => next_id($insumos),
                'nombre' => $name,
                'unidad' => $unit,
                'categoria' => $category,
                'precio' => max(0, (float) ($_POST['precio'] ?? 0)),
                'stock' => max(0, (float) ($_POST['stock'] ?? 0)),
                'stock_minimo' => max(0, (float) ($_POST['stock_minimo'] ?? 0)),
            ];
            $insumos[] = $supply;
        }
        if ($supply === null) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'No se encontró el insumo.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        write_json(data_file('insumos'), $insumos);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true, 'supply' => $supply], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'labor_save') {
        $laborId = (int) ($_POST['labor_id'] ?? 0);
        $mueble = trim((string) ($_POST['mueble_tipo'] ?? ''));
        $work = trim((string) ($_POST['trabajo_tipo'] ?? ''));
        $complexity = trim((string) ($_POST['complejidad'] ?? 'complejidad_1')) ?: 'complejidad_1';
        $allowedTasks = ['desarme', 'soporte_elastico', 'confort_gomaespuma', 'confort_guata', 'cobertura_corte_tela', 'cobertura_confeccion_tela', 'terminacion'];
        $times = [];
        foreach ($allowedTasks as $task) {
            $times[$task] = max(0, (int) ($_POST['tiempo_' . $task] ?? 0));
        }
        if ($mueble === '' || $work === '') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Indicá el mueble y el tipo de trabajo.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $labor = null;
        $laborSnapshot = [
            'id' => $laborId,
            'mueble_tipo' => $mueble,
            'trabajo_tipo' => $work,
            'complejidad' => $complexity,
            'tarifa_hora' => max(0, (float) ($_POST['tarifa_hora'] ?? 0)),
            'tiempos_minutos' => $times,
            'activo' => true,
        ];
        foreach ($manoObraValores as &$row) {
            if ($laborId > 0 && (int) ($row['id'] ?? 0) === $laborId) {
                $laborSnapshot['id'] = $laborId;
                $row = $laborSnapshot;
                $labor = $row;
                break;
            }
        }
        unset($row);
        if ($labor === null) {
            $laborSnapshot['id'] = next_id($manoObraValores);
            $labor = $laborSnapshot;
            $manoObraValores[] = $labor;
        }
        write_json(data_file('mano_obra_valores'), $manoObraValores);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true, 'labor' => $labor], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'delete') {
        $presupuestos = array_values(array_filter($presupuestos, static function (array $row) use ($id): bool {
            return (int) ($row['id'] ?? 0) !== $id;
        }));
        write_json(data_file('presupuestos'), $presupuestos);
        redirect_with_message('presupuesto_nuevo_v2.php', 'Presupuesto eliminado.');
    }
    if ($action === 'duplicate') {
        $copy = budget_find($presupuestos, $id);
        if ($copy === null) {
            redirect_with_message('presupuesto_nuevo_v2.php', 'No se encontró el presupuesto.');
        }
        $copy['id'] = next_id($presupuestos);
        $copy['fecha'] = date('Y-m-d');
        $copy['estado'] = 'borrador';
        $copy['audit'] = ['created_at' => date('c'), 'flow' => 'presupuesto_tree_duplicate'];
        $presupuestos[] = $copy;
        write_json(data_file('presupuestos'), $presupuestos);
        redirect_with_message('presupuesto_nuevo_v2.php?editar_v2=' . $copy['id'], 'Presupuesto duplicado.');
    }

    $decoded = json_decode((string) ($_POST['presupuesto_payload'] ?? ''), true);
    if (!is_array($decoded) || (int) ($decoded['cliente_id'] ?? 0) <= 0 || empty($decoded['items'])) {
        if ($isAutosave) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'reason' => 'incomplete'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        redirect_with_message('presupuesto_nuevo_v2.php', 'Seleccioná un cliente y agregá al menos un mueble.');
    }
    foreach ($decoded['items'] as &$item) {
        if (!isset($item['modulos']) || !is_array($item['modulos'])) {
            continue;
        }
        foreach ($item['modulos'] as &$module) {
            $module['tipo'] = normalize_module_type($module['tipo'] ?? '');
        }
        unset($module);
    }
    unset($item);
    $customCatalog = (array) ($decoded['catalogos_personalizados'] ?? []);
    $configApp['presupuesto_personalizados'] = (array) ($configApp['presupuesto_personalizados'] ?? []);
    foreach (['muebles', 'trabajos', 'dificultades', 'capas', 'modulos', 'tipos_insumo'] as $catalogType) {
        $customValues = in_array($catalogType, ['modulos', 'tipos_insumo'], true)
            ? normalize_module_catalog((array) ($customCatalog[$catalogType] ?? []))
            : (array) ($customCatalog[$catalogType] ?? []);
        $configApp['presupuesto_personalizados'][$catalogType] = in_array($catalogType, ['modulos', 'tipos_insumo'], true)
            ? normalize_module_catalog(array_merge((array) ($configApp['presupuesto_personalizados'][$catalogType] ?? []), $customValues))
            : array_values(array_unique(array_merge((array) ($configApp['presupuesto_personalizados'][$catalogType] ?? []), $customValues)));
    }
    write_json(data_file('config'), $configApp);
    $existing = $id > 0 ? budget_find($presupuestos, $id) : null;
    $payload = presupuesto_tree_calculate($decoded, $insumos);
    $payload = array_replace($existing ?? [], $payload);
    $payload['id'] = $existing !== null ? $id : next_id($presupuestos);
    $payload['estado'] = $action === 'finalize' ? 'finalizado' : (string) ($existing['estado'] ?? 'borrador');
    $payload['fecha'] = (string) ($payload['fecha'] ?? date('Y-m-d'));
    $payload['version_flujo'] = 'presupuesto_jerarquico_v3';
    $payload['config_capas_version'] = (string) ($configCapas['version'] ?? 'manual');
    $payload['config_capas_snapshot'] = $configCapas;
    $payload['audit'] = array_replace((array) ($existing['audit'] ?? []), [
        $existing === null ? 'created_at' : 'updated_at' => date('c'),
        'flow' => $existing === null ? 'presupuesto_tree_create' : 'presupuesto_tree_update',
    ]);
    $updated = false;
    foreach ($presupuestos as &$row) {
        if ((int) ($row['id'] ?? 0) === $payload['id']) {
            $row = $payload;
            $updated = true;
            break;
        }
    }
    unset($row);
    if (!$updated) {
        $presupuestos[] = $payload;
    }
    write_json(data_file('presupuestos'), $presupuestos);
    if ($isAutosave) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true, 'id' => (int) $payload['id']], JSON_UNESCAPED_UNICODE);
        exit;
    }
    redirect_with_message('presupuesto_nuevo_v2.php?editar_v2=' . $payload['id'], $action === 'finalize' ? 'Presupuesto finalizado.' : 'Borrador guardado.');
}

$budgets = $presupuestos;
$editId = (int) ($_GET['editar_v2'] ?? 0);
$viewId = (int) ($_GET['ver_v2'] ?? 0);
$editing = budget_find($budgets, $editId);
$viewing = budget_find($budgets, $viewId);
$editing = $editing ? presupuesto_tree_from_legacy($editing) : null;
$viewing = $viewing ? presupuesto_tree_from_legacy($viewing) : null;
$initial = $editing ?? ['cliente_id' => 0, 'detalle' => '', 'fecha' => date('Y-m-d'), 'margen' => 30, 'items' => []];
$laborTemplates = [];
foreach ($manoObraValores as $template) {
    if (!(bool) ($template['activo'] ?? true)) {
        continue;
    }
    $laborTemplates[] = [
        'id' => (int) ($template['id'] ?? 0),
        'mueble_tipo' => (string) ($template['mueble_tipo'] ?? ''),
        'trabajo_tipo' => (string) ($template['trabajo_tipo'] ?? ''),
        'complejidad' => (string) ($template['complejidad'] ?? ''),
        'costo' => presupuesto_tree_labor_total($template),
        'snapshot' => $template,
    ];
}

render_page_start('Presupuestos por muebles');
?>
<style>
.budget-grid,.item-grid,.module-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;align-items:end}
.tree{border-left:3px solid #d4dae2;margin:12px 0 12px 12px;padding-left:14px}.tree.item{border-color:#47739f}.tree.module{border-color:#708f58}.tree.layer{border-color:#aa7c4f}
.node-title{display:flex;align-items:center;gap:8px}.node-title h3,.node-title h4{margin:0}.node-actions{display:flex;gap:6px;flex-wrap:wrap;margin-left:auto}.node-fields{display:grid;grid-template-columns:repeat(5,minmax(90px,1fr));gap:7px;align-items:end;flex:1}.node-fields label{font-size:12px;color:#56616f}.node-fields input,.node-fields select{width:100%;min-width:0}.level-heading{display:flex;align-items:center;gap:8px;margin:14px 0 8px}.level-heading h2,.level-heading h3,.level-heading h4{margin:0}.add-level{width:34px;height:34px;padding:0;font-size:20px;line-height:1}.tree-details>summary{cursor:pointer;list-style:none}.tree-details>summary::-webkit-details-marker{display:none}.collapse-arrow{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;font-size:25px;line-height:1;color:#365a7c;transition:transform .15s ease}.tree-details[open]>summary .collapse-arrow{transform:rotate(90deg)}.tree-content{padding-top:10px}
.insumo-row{display:grid;grid-template-columns:2fr 1fr 1fr auto;gap:8px;align-items:center;padding:7px 0;border-bottom:1px solid #eee}
.tree.layer .node-fields{grid-template-columns:minmax(220px,300px) minmax(0,1fr)}.layer-inputs-summary{display:flex;align-items:center;gap:6px;flex-wrap:wrap;overflow:visible;min-width:0;padding-top:0}.layer-inputs-summary>span{font-size:12px;color:#56616f;flex:0 0 auto}.summary-input-button,.summary-add-input{width:auto!important;flex:0 0 auto;white-space:nowrap}.summary-input-button{padding:5px 9px;font-size:12px}.summary-add-input{width:28px!important;height:28px;font-size:18px}
.summary-card{position:sticky;bottom:8px;z-index:5;background:#fff;box-shadow:0 4px 18px #0002}
.modal-bg{display:none;position:fixed;inset:0;background:#0008;z-index:30;align-items:center;justify-content:center}.modal-bg.open{display:flex}
.modal-card{background:#fff;width:min(900px,94vw);max-height:92vh;overflow:auto;padding:18px;border-radius:8px}.piece-head,.piece-row{display:grid;grid-template-columns:minmax(120px,1fr) 140px repeat(3,90px) 55px 42px;gap:7px;margin:6px 0}.piece-head{font-size:12px;font-weight:700;color:#56616f}.piece-edges{grid-column:1/-1;display:grid;grid-template-columns:repeat(4,1fr);gap:6px;padding:6px;background:#f7f9fb;border:1px solid #e1e6eb}.piece-edges label{font-size:11px}.piece-edges select{width:100%}.calculation-preview{margin:12px 0;padding:12px;border:1px solid #b9c9dc;border-radius:6px;background:#f4f8fc;font-weight:700}
.budget-history-wrap{overflow-x:auto}.budget-history{min-width:940px}.budget-history th,.budget-history td{white-space:nowrap}.budget-history .history-description{white-space:normal;min-width:150px}.budget-history .history-actions{display:flex;flex-wrap:nowrap;gap:5px;align-items:center}.budget-history .history-actions .action-link,.budget-history .history-actions button{width:auto;margin:0;padding:6px 8px;line-height:1.2}.budget-history .history-actions .danger-btn{margin-left:0}
.detail-tree{margin:8px 0;border-left:3px solid #d4dae2;padding-left:10px}.detail-item{border-color:#47739f}.detail-module{border-color:#708f58}.detail-layer{border-color:#aa7c4f}.detail-tree>summary{cursor:pointer;padding:7px 4px;font-weight:700}.detail-children{padding:2px 0 4px}.detail-supply{margin:8px 0;padding:9px;background:#f7f9fb;border:1px solid #d9e1ea;border-radius:5px}.detail-supply>summary{cursor:pointer;font-weight:700}.detail-supply-meta{display:flex;flex-wrap:wrap;gap:6px 18px;padding:8px 0;color:#3f4d5d}.detail-pieces{width:100%;border-collapse:collapse;margin-top:7px;font-size:13px}.detail-pieces th,.detail-pieces td{border:1px solid #d9e1ea;padding:5px 6px;text-align:left}.detail-pieces th{background:#edf2f7}.detail-empty{color:#667788;font-style:italic;margin:7px 0}
.detail-header{display:flex;align-items:center;justify-content:space-between;gap:12px}.detail-header h3{margin:0}.detail-totals{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:8px;margin:12px 0;padding:10px;background:#f4f8fc;border:1px solid #b9c9dc;border-radius:6px}.detail-totals strong{display:block;font-size:12px;color:#56616f}.detail-totals span{font-weight:700}.detail-view>p:nth-of-type(2){display:none}
.budget-print-view{display:none}.budget-print-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.budget-print-view h1{margin:0 0 12px}.budget-print-view table{width:100%;border-collapse:collapse;margin-top:18px}.budget-print-view th,.budget-print-view td{border:1px solid #333;padding:8px;text-align:left}.budget-print-view th{background:#edf2f7}.budget-print-view .money{text-align:right;white-space:nowrap}.budget-print-view tfoot td{font-weight:700}
@media print{ @page{margin:12mm} body{background:#fff!important;color:#000!important}.topbar,.container>h2,.detail-history,.no-print{display:none!important}.container{max-width:none!important;margin:0!important;padding:0!important}.detail-view{display:block!important;margin:0!important;padding:0!important;border:0!important;box-shadow:none!important}.detail-header button{display:none!important}.detail-view>p:nth-of-type(2){display:none}.detail-totals{grid-template-columns:repeat(5,1fr);background:#fff;border:1px solid #999}.detail-tree,.detail-supply{break-inside:avoid}.detail-tree>summary,.detail-supply>summary{color:#000}.detail-pieces{font-size:10px}body.print-budget .detail-view{display:none!important}body.print-budget .budget-print-view{display:block!important}body.print-budget .budget-print-view h1{font-size:22px}}
@media(max-width:800px){.budget-grid,.item-grid,.module-grid,.node-fields,.insumo-row,.piece-row{grid-template-columns:1fr 1fr}.tree.layer .node-fields{grid-template-columns:1fr}}
</style>

<?php if ($viewing !== null): ?>
<section class="card detail-view">
  <div class="detail-header"><h3>Presupuesto #<?= (int) ($viewing['id'] ?? 0) ?></h3><div class="budget-print-actions no-print"><button type="button" class="secondary-btn" onclick="window.printBudget('detail')">Imprimir detalle</button><button type="button" class="secondary-btn" onclick="window.printBudget('budget')">Imprimir presupuesto</button></div></div>
  <?php
  $viewLabor = (float) ($viewing['mano_obra'] ?? 0);
  $viewMaterials = (float) ($viewing['materiales'] ?? 0);
  $viewSubtotal = $viewLabor + $viewMaterials;
  $viewMarginPercent = (float) ($viewing['margen'] ?? 0);
  $viewMarginAmount = (float) ($viewing['total'] ?? 0) - $viewSubtotal;
  $detailInputCost = static function (array $input): float {
      if (array_key_exists('costo_unitario_total', $input)) {
          return (float) $input['costo_unitario_total'];
      }
      $quantity = (float) ($input['cantidad_final'] ?? $input['cantidad_calculada'] ?? 0);
      return $quantity * (float) ($input['costo_unitario'] ?? 0);
  };
  $detailKey = static fn (mixed $value): string => strtolower(str_replace(['_', '-', ' '], '', (string) $value));
  $legacyCutPieces = (array) ($viewing['piezas_corte'] ?? []);
  $viewTotal = (float) ($viewing['total'] ?? 0);
  $viewItemMaterials = static function (array $item) use ($detailInputCost): float {
      if (array_key_exists('materiales_unitarios', $item)) {
          return (float) $item['materiales_unitarios'];
      }
      $materials = 0.0;
      foreach ((array) ($item['modulos'] ?? []) as $module) {
          foreach ((array) ($module['capas'] ?? []) as $layer) {
              foreach ((array) ($layer['insumos'] ?? []) as $input) {
                  $materials += $detailInputCost((array) $input);
              }
          }
      }
      return $materials;
  };
  ?>
  <div class="detail-totals"><div><strong>Mano de obra</strong><span><?= money($viewLabor) ?></span></div><div><strong>Materiales</strong><span><?= money($viewMaterials) ?></span></div><div><strong>Subtotal</strong><span><?= money($viewSubtotal) ?></span></div><div><strong>Margen (<?= h((string) $viewMarginPercent) ?>%)</strong><span><?= money($viewMarginAmount) ?></span></div><div><strong>Total</strong><span><?= money($viewTotal) ?></span></div></div>
  <p><?= h((string) ($viewing['detalle'] ?? '')) ?> · <?= h((string) ($viewing['fecha'] ?? '')) ?></p>
  <p><strong>Mano de obra:</strong> <?= money($viewLabor) ?> · <strong>Materiales:</strong> <?= money($viewMaterials) ?> · <strong>Subtotal:</strong> <?= money($viewSubtotal) ?> · <strong>Margen (<?= h((string) $viewMarginPercent) ?>%):</strong> <?= money($viewMarginAmount) ?> · <strong>Total:</strong> <?= money((float) ($viewing['total'] ?? 0)) ?></p>
  <?php foreach ((array) ($viewing['items'] ?? []) as $item): ?>
    <?php
    $itemQuantity = max(1, (int) ($item['cantidad'] ?? 1));
    $itemModules = (array) ($item['modulos'] ?? []);
    ?>
    <details class="detail-tree detail-item">
      <summary><?= h((string) ($item['tipo_mueble'] ?? 'Mueble')) ?> &times; <?= $itemQuantity ?> &middot; <?= money((float) ($item['subtotal_total'] ?? 0)) ?></summary>
      <div class="detail-children">
        <?php if (!$itemModules): ?>
          <p class="detail-empty">Sin modulos cargados.</p>
        <?php endif; ?>
        <?php foreach ($itemModules as $module): ?>
          <?php
          $moduleQuantity = max(1, (int) ($module['cantidad'] ?? 1));
          $moduleLayers = (array) ($module['capas'] ?? []);
          $moduleKey = $detailKey($module['tipo'] ?? '');
          $moduleLegacyPieces = array_values(array_filter($legacyCutPieces, static function (array $piece) use ($detailKey, $moduleKey): bool {
              $legacyKey = $detailKey($piece['modulo'] ?? '');
              return $legacyKey === $moduleKey
                  || (str_contains($moduleKey, 'brazo') && str_contains($legacyKey, 'apoyabraz'))
                  || (str_contains($moduleKey, 'lateral') && str_contains($legacyKey, 'lateral'));
          }));
          $moduleCost = 0.0;
          foreach ($moduleLayers as $moduleLayer) {
              foreach ((array) ($moduleLayer['insumos'] ?? []) as $moduleInput) {
                  $moduleCost += $detailInputCost((array) $moduleInput);
              }
          }
          ?>
          <details class="detail-tree detail-module">
            <summary>Modulo: <?= h((string) ($module['tipo'] ?? 'Sin tipo')) ?> &middot; Cantidad: <?= $moduleQuantity ?> &middot; Medidas: <?= (float) ($module['alto'] ?? 0) ?> &times; <?= (float) ($module['ancho'] ?? 0) ?> &times; <?= (float) ($module['profundidad'] ?? 0) ?> cm &middot; Materiales: <?= money($moduleCost) ?></summary>
            <div class="detail-children">
              <?php if (!$moduleLayers): ?>
                <p class="detail-empty">Sin capas cargadas.</p>
              <?php endif; ?>
              <?php foreach ($moduleLayers as $layer): ?>
                <?php
                $layerInputs = (array) ($layer['insumos'] ?? []);
                $layerCost = 0.0;
                foreach ($layerInputs as $layerInput) {
                    $layerCost += $detailInputCost((array) $layerInput);
                }
                ?>
                <details class="detail-tree detail-layer">
                  <summary>Capa: <?= h((string) ($layer['tipo'] ?? 'Sin tipo')) ?> &middot; Insumos: <?= count($layerInputs) ?> &middot; Costo: <?= money($layerCost) ?></summary>
                  <div class="detail-children">
                    <?php if (!$layerInputs): ?>
                      <p class="detail-empty">Sin insumos cargados.</p>
                    <?php endif; ?>
                    <?php foreach ($layerInputs as $input): ?>
                      <?php
                      $inputTotalQuantity = (float) ($input['cantidad_final'] ?? $input['cantidad_calculada'] ?? 0);
                      $inputModuleCount = max(1, (int) ($input['cantidad_modulos'] ?? $moduleQuantity));
                      $inputPerModuleQuantity = array_key_exists('cantidad_por_modulo', $input)
                          ? (float) $input['cantidad_por_modulo']
                          : ($inputModuleCount > 1 ? $inputTotalQuantity / $inputModuleCount : $inputTotalQuantity);
                      $inputUnit = (string) ($input['unidad'] ?? '');
                      $inputUnitCost = (float) ($input['costo_unitario'] ?? 0);
                      $inputTotalCost = $detailInputCost($input);
                      $inputPieces = is_array($input['piezas'] ?? null) ? array_values($input['piezas']) : [];
                      if (!$inputPieces && $moduleLegacyPieces) {
                          $inputType = $detailKey($input['tipo'] ?? '');
                          $inputPieces = array_values(array_filter($moduleLegacyPieces, static fn (array $piece): bool => $detailKey($piece['insumo_tipo'] ?? '') === $inputType));
                      }
                      ?>
                      <details class="detail-supply">
                        <summary><?= h((string) ($input['nombre'] ?? 'Insumo')) ?> &middot; <?= money($inputTotalCost) ?></summary>
                        <div class="detail-supply-meta">
                          <span><strong>Tipo:</strong> <?= h((string) ($input['tipo'] ?? '')) ?></span>
                          <span><strong>Por modulo:</strong> <?= $inputPerModuleQuantity ?> <?= h($inputUnit) ?></span>
                          <span><strong>Total:</strong> <?= $inputTotalQuantity ?> <?= h($inputUnit) ?></span>
                          <span><strong>Costo unitario:</strong> <?= money($inputUnitCost) ?></span>
                          <?php if (array_key_exists('origen_cantidad', $input)): ?>
                            <span><strong>Origen:</strong> <?= h((string) $input['origen_cantidad']) ?></span>
                          <?php endif; ?>
                          <?php if (array_key_exists('tiras', $input)): ?>
                            <span><strong>Tiras:</strong> <?= (float) $input['tiras'] ?></span>
                          <?php endif; ?>
                          <?php if (array_key_exists('grapas_estimadas', $input)): ?>
                            <span><strong>Grapas:</strong> <?= (float) $input['grapas_estimadas'] ?></span>
                          <?php endif; ?>
                          <?php if (array_key_exists('merma_pct', $input)): ?>
                            <span><strong>Merma:</strong> <?= (float) $input['merma_pct'] ?>%</span>
                          <?php endif; ?>
                          <?php if (!empty($input['motivo_ajuste'])): ?>
                            <span><strong>Ajuste:</strong> <?= h((string) $input['motivo_ajuste']) ?></span>
                          <?php endif; ?>
                        </div>
                        <?php if ($inputPieces): ?>
                          <table class="detail-pieces">
                            <thead><tr><th>Pieza</th><th>Cara</th><th>Alto</th><th>Ancho</th><th>Cantidad</th><th>Rotar</th><th>Bordes</th></tr></thead>
                            <tbody>
                            <?php foreach ($inputPieces as $piece): ?>
                              <?php
                              $pieceEdges = (array) ($piece['bordes'] ?? []);
                              $edgeLabels = ['sup' => 'Sup', 'der' => 'Der', 'inf' => 'Inf', 'izq' => 'Izq'];
                              $edgeText = [];
                              foreach ($edgeLabels as $edgeKey => $edgeLabel) {
                                  if (!empty($pieceEdges[$edgeKey])) {
                                      $edgeText[] = $edgeLabel . ': ' . (string) $pieceEdges[$edgeKey];
                                  }
                              }
                              ?>
                              <tr><td><?= h((string) ($piece['pieza'] ?? 'Pieza')) ?></td><td><?= h((string) ($piece['cara'] ?? '')) ?></td><td><?= (float) ($piece['alto'] ?? 0) ?> cm</td><td><?= (float) ($piece['ancho'] ?? 0) ?> cm</td><td><?= (float) ($piece['cantidad'] ?? 1) ?></td><td><?= !empty($piece['rotatable']) ? 'Si' : 'No' ?></td><td><?= h(implode(' / ', $edgeText)) ?></td></tr>
                            <?php endforeach; ?>
                            </tbody>
                          </table>
                        <?php endif; ?>
                      </details>
                    <?php endforeach; ?>
                  </div>
                </details>
              <?php endforeach; ?>
            </div>
          </details>
        <?php endforeach; ?>
      </div>
    </details>

 <?php if (false): ?>
  <?php foreach ([] as $_legacyItem): ?>
    <details><summary><?= h((string) ($item['tipo_mueble'] ?? 'Mueble')) ?> × <?= (int) ($item['cantidad'] ?? 1) ?> · <?= money((float) ($item['subtotal_total'] ?? 0)) ?></summary>
    <?php foreach ((array) ($item['modulos'] ?? []) as $module): ?><div style="margin-left:16px"><strong><?= h((string) ($module['tipo'] ?? 'Módulo')) ?></strong>: <?= (float) ($module['alto'] ?? 0) ?> × <?= (float) ($module['ancho'] ?? 0) ?> × <?= (float) ($module['profundidad'] ?? 0) ?> cm</div><?php endforeach; ?>
    </details>
  <?php endforeach; ?>
  <?php endif; ?>
  <?php endforeach; ?>
</section>
<?php endif; ?>

<?php if ($viewing !== null): ?>
<section class="budget-print-view">
  <?php $viewClientId = (int) ($viewing['cliente_id'] ?? 0); ?>
  <h1>Presupuesto</h1>
  <p><strong>Cliente:</strong> <?= h($clientNames[$viewClientId] ?? ($viewClientId > 0 ? 'Cliente #' . $viewClientId : 'Sin cliente')) ?></p>
  <table>
    <thead><tr><th>Mueble</th><th>Trabajo</th><th>Cantidad</th><th class="money">Mano de obra</th><th class="money">Materiales</th></tr></thead>
    <tbody>
    <?php foreach ((array) ($viewing['items'] ?? []) as $printItem): ?>
      <?php
      $printQuantity = max(1, (int) ($printItem['cantidad'] ?? 1));
      $printLabor = (float) ($printItem['mano_obra_unitaria'] ?? 0) * $printQuantity;
      $printMaterials = $viewItemMaterials((array) $printItem) * $printQuantity;
      ?>
      <tr><td><?= h((string) ($printItem['tipo_mueble'] ?? 'Mueble')) ?></td><td><?= h((string) ($printItem['trabajo_tipo'] ?? '')) ?></td><td><?= $printQuantity ?></td><td class="money"><?= money($printLabor) ?></td><td class="money"><?= money($printMaterials) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot><tr><td colspan="3">Totales</td><td class="money"><?= money($viewLabor) ?></td><td class="money"><?= money($viewMaterials) ?></td></tr></tfoot>
  </table>
</section>
<?php endif; ?>

<?php if ($viewing !== null): ?>
<script>
(() => {
  let detailStates = [];
  window.printBudget = (mode) => {
    document.body.classList.toggle('print-budget', mode === 'budget');
    window.print();
  };
  const prepareDetailPrint = () => {
    if (document.body.classList.contains('print-budget')) return;
    detailStates = [...document.querySelectorAll('.detail-view details')].map(detail => ({detail, open: detail.open}));
    detailStates.forEach(({detail}) => { detail.open = true; });
  };
  const restoreDetailPrint = () => {
    detailStates.forEach(({detail, open}) => { detail.open = open; });
    detailStates = [];
    document.body.classList.remove('print-budget');
  };
  window.addEventListener('beforeprint', prepareDetailPrint);
  window.addEventListener('afterprint', restoreDetailPrint);
})();
</script>
<?php endif; ?>

<section class="card detail-history">
  <h3>Historial</h3>
  <div class="budget-history-wrap">
  <table class="table budget-history"><thead><tr><th>ID</th><th>Fecha</th><th>Cliente</th><th>Descripción</th><th>Estado</th><th>Total</th><th>Acciones</th></tr></thead><tbody>
  <?php foreach (array_slice(array_reverse($budgets), 0, 12) as $row): ?>
    <?php
    $clientId = (int) ($row['cliente_id'] ?? 0);
    $clientName = $clientNames[$clientId] ?? ($clientId > 0 ? 'Cliente #' . $clientId : 'Sin cliente');
    ?>
    <tr><td>#<?= (int) ($row['id'] ?? 0) ?></td><td><?= h((string) ($row['fecha'] ?? '')) ?></td><td><?= h($clientName) ?></td><td class="history-description"><?= h((string) ($row['detalle'] ?? '')) ?></td><td><?= h((string) ($row['estado'] ?? 'borrador')) ?></td><td><?= money((float) ($row['total'] ?? 0)) ?></td>
    <td><form method="post" class="history-actions"><input type="hidden" name="id" value="<?= (int) ($row['id'] ?? 0) ?>"><a class="secondary-btn action-link" href="?ver_v2=<?= (int) ($row['id'] ?? 0) ?>">Detalle</a><a class="secondary-btn action-link" href="?editar_v2=<?= (int) ($row['id'] ?? 0) ?>">Editar</a><button class="secondary-btn" name="action" value="duplicate">Duplicar</button><button class="danger-btn" name="action" value="delete" onclick="return confirm('¿Eliminar presupuesto?')">Eliminar</button></form></td></tr>
  <?php endforeach; ?>
  </tbody></table>
  </div>
</section>

<form method="post" id="budget-form">
  <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
  <input type="hidden" name="action" id="save-action" value="save">
  <input type="hidden" name="presupuesto_payload" id="payload">
  <section class="card">
    <h2><?= $editing ? 'Editar presupuesto #' . (int) $editing['id'] : 'Nuevo presupuesto' ?></h2>
    <div class="budget-grid">
      <label>Cliente<select id="cliente" required><option value="">Seleccionar...</option><?php foreach ($clientes as $client): ?><option value="<?= (int) $client['id'] ?>"><?= h((string) $client['nombre']) ?></option><?php endforeach; ?></select></label>
      <label>Descripción<input id="detalle" type="text"></label>
      <label>Fecha<input id="fecha" type="date"></label>
      <label>Margen %<input id="margen" type="number" min="0" step=".01"></label>
    </div>
  </section>
  <div class="level-heading"><h2>Muebles</h2><button type="button" id="add-item" class="add-level" title="Agregar mueble" aria-label="Agregar mueble">+</button></div>
  <div id="items"></div>
  <section class="card summary-card"><strong id="summary"></strong><div class="inline-actions" style="margin-top:8px"><button type="submit">Guardar borrador</button><button type="submit" class="secondary-btn" id="finalize">Finalizar</button></div><small id="autosave-status" class="muted" aria-live="polite"></small></section>
</form>

<div class="modal-bg" id="modal"><div class="modal-card">
  <h3 id="modal-title">Agregar insumo</h3>
  <div class="form-grid">
    <label>Tipo<select id="mi-type"></select></label>
    <label>Insumo<select id="mi-supply"><option value="">Seleccionar...</option><?php foreach ($insumos as $supply): ?><option value="<?= (int) $supply['id'] ?>" data-price="<?= (float) ($supply['precio'] ?? 0) ?>" data-unit="<?= h((string) ($supply['unidad'] ?? 'unidad')) ?>" data-category="<?= h(budget_supply_category($supply)) ?>"><?= h((string) ($supply['nombre'] ?? 'Insumo')) ?></option><?php endforeach; ?></select><span class="inline-actions"><button type="button" class="secondary-btn" id="new-supply">Nuevo</button><button type="button" class="secondary-btn" id="edit-supply">Modificar</button></span></label>
    <label>Merma %<input id="mi-waste" type="number" min="0" step=".01" value="10"></label>
    <label>Costo unitario<input id="mi-cost" type="number" min="0" step=".01"></label>
    <label data-for="tela guata fliselina">Ancho útil (m)<input id="mi-roll-width" type="number" min=".01" step=".01" value="1.4"></label>
    <label data-for="gomaespuma madera">Largo placa (m)<input id="mi-sheet-length" type="number" min=".01" step=".01" value="2"></label>
    <label data-for="gomaespuma madera">Ancho placa (m)<input id="mi-sheet-width" type="number" min=".01" step=".01" value="1"></label>
    <label data-for="fleje">Patrón<select id="mi-pattern"><option value="lineal">Lineal</option><option value="cuadriculado">Cuadriculado</option></select></label>
    <label data-for="fleje" id="direction-label">Dirección<select id="mi-direction"><option value="ancho">A lo ancho</option><option value="largo">A lo largo</option></select></label>
    <label data-for="fleje">Separación (cm)<input id="mi-spacing" type="number" min="1" value="10"></label>
    <label data-for="fleje">Grapas por extremo<input id="mi-staples" type="number" min="1" value="2"></label>
    <label>Cantidad ajustada<input id="mi-adjusted" type="number" min="0" step=".01"></label>
    <label>Motivo del ajuste<input id="mi-reason" type="text"></label>
  </div>
  <section id="pieces-section"><h4>Piezas a cortar</h4><p class="muted">Elegí la cara a tapizar para proponer las medidas. Podés modificarlas. Indicá si la pieza puede rotar 90°.</p><div class="piece-head"><span>Pieza</span><span>Cara</span><span>Alto (cm)</span><span>Ancho (cm)</span><span>Cantidad</span><span>Rotar</span><span></span></div><div id="pieces"></div><button type="button" class="secondary-btn" id="add-piece">+ Pieza</button><button type="button" class="secondary-btn" id="apply-piece-rules">Recalcular medidas</button></section>
  <div id="calculation-preview" class="calculation-preview">Completá los datos para calcular el consumo.</div>
  <div class="inline-actions" style="margin-top:12px"><button type="button" id="save-input">Aceptar</button><button type="button" class="secondary-btn" id="cancel-input">Cancelar</button></div>
</div></div>

<div class="modal-bg" id="catalog-modal"><div class="modal-card">
  <h3 id="catalog-title">Nuevo registro</h3>
  <form id="supply-catalog-form" class="form-grid">
    <input type="hidden" id="catalog-supply-id">
    <label>Modificar existente<select id="catalog-existing-supply"><option value="">Nuevo insumo</option><?php foreach ($insumos as $supply): ?><option value="<?= (int) ($supply['id'] ?? 0) ?>"><?= h((string) ($supply['nombre'] ?? 'Insumo')) ?></option><?php endforeach; ?></select></label>
    <label>Nombre<input type="text" id="catalog-supply-name" required></label>
    <label>Unidad<input type="text" id="catalog-supply-unit" value="unidad"></label>
    <label>Categoría<select id="catalog-supply-category"><option value="tela">Tela</option><option value="gomaespuma">Gomaespuma</option><option value="madera">Madera</option><option value="guata">Guata</option><option value="fliselina">Fliselina</option><option value="fleje">Fleje</option><option value="grapas">Grapas</option><option value="tachas">Tachas</option><option value="tornillos">Tornillos</option><option value="cierre">Cierre</option><option value="cordon">Cordón</option><option value="adhesivo_contacto">Adhesivo</option><option value="otros">Otros</option></select></label>
    <label>Precio unitario<input type="number" min="0" step="0.01" id="catalog-supply-price"></label>
    <label>Stock inicial<input type="number" min="0" step="0.01" id="catalog-supply-stock" value="0"></label>
    <label>Stock mínimo<input type="number" min="0" step="0.01" id="catalog-supply-min" value="0"></label>
    <div class="inline-actions" style="grid-column:1/-1"><button type="submit">Guardar insumo</button><button type="button" class="secondary-btn" id="close-catalog">Cancelar</button></div>
  </form>
  <form id="labor-catalog-form" class="form-grid" style="display:none">
    <input type="hidden" id="catalog-labor-id">
    <label>Mueble<input type="text" id="catalog-labor-furniture" list="catalog-furniture-list" required></label>
    <label>Tipo de trabajo<input type="text" id="catalog-labor-work" required></label>
    <label>Complejidad<select id="catalog-labor-complexity"><option value="complejidad_1">Complejidad 1</option><option value="complejidad_2">Complejidad 2</option><option value="complejidad_3">Complejidad 3</option><option value="especial">Especial</option></select></label>
    <label>Tarifa por hora<input type="number" min="0" step="0.01" id="catalog-labor-rate" value="0"></label>
    <div id="catalog-labor-tasks" class="form-grid" style="grid-column:1/-1"></div>
    <div class="inline-actions" style="grid-column:1/-1"><button type="submit">Guardar mano de obra</button><button type="button" class="secondary-btn" id="close-catalog-labor">Cancelar</button></div>
  </form>
</div></div>

<script type="text/plain" id="legacy-editor-script">
(() => {
const initial=<?= json_encode($initial, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
const config=<?= json_encode($configCapas, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
let labor=<?= json_encode($laborTemplates, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
let supplyCatalog=<?= json_encode($insumos, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
const globalCustomCatalog=<?= json_encode($catalogosGlobales, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
const fallbackTypes=['tela','gomaespuma','madera','guata','fliselina','fleje','grapas','tachas','tornillos','cierre','cordon','adhesivo_contacto','otros'];
	let state=structuredClone(initial),ctx=null,autosaveTimer=null,autosaveBusy=false,autosaveAgain=false;
	const $=(s,r=document)=>r.querySelector(s), esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
	const uid=p=>p+'-'+Date.now().toString(36)+Math.random().toString(36).slice(2,6), money=v=>Number(v||0).toLocaleString('es-AR',{style:'currency',currency:'ARS'});
	function syncHeader(){state.cliente_id=Number($('#cliente').value||0);state.detalle=$('#detalle').value;state.fecha=$('#fecha').value;state.margen=Number($('#margen').value||0)}
	function setAutosaveStatus(text){const host=$('#autosave-status');if(host)host.textContent=text}
	function autosave(){
		syncHeader();
		if(!state.cliente_id||!state.items.length){setAutosaveStatus('Completá cliente y al menos un mueble para activar el autoguardado.');return}
		if(autosaveBusy){autosaveAgain=true;return}
		autosaveBusy=true;autosaveAgain=false;setAutosaveStatus('Guardando automáticamente...');
		const body=new FormData();body.set('action','autosave');body.set('id',String(state.id||0));body.set('presupuesto_payload',JSON.stringify(state));
		fetch(window.location.href,{method:'POST',body,credentials:'same-origin',headers:{'Accept':'application/json'}}).then(response=>response.json()).then(result=>{
			if(!result.ok){setAutosaveStatus('Autoguardado pendiente: faltan datos obligatorios.');return}
			if(result.id){state.id=Number(result.id);const idField=$("input[name='id']");if(idField)idField.value=state.id}
			setAutosaveStatus('Guardado automáticamente a las '+new Date().toLocaleTimeString('es-AR',{hour:'2-digit',minute:'2-digit'}));
		}).catch(()=>setAutosaveStatus('No se pudo autoguardar. Revisá la conexión y guardá manualmente.')).finally(()=>{autosaveBusy=false;if(autosaveAgain)queueAutosave()})
	}
	function queueAutosave(){clearTimeout(autosaveTimer);setAutosaveStatus('Cambios pendientes de guardar...');autosaveTimer=setTimeout(autosave,800)}
	function persistCatalogValue(type,value){const body=new FormData();body.set('action','catalog_save');body.set('catalog_type',type);body.set('value',value);fetch(window.location.href,{method:'POST',body,credentials:'same-origin',headers:{'Accept':'application/json'}}).then(response=>response.json()).then(result=>{if(result.ok&&result.catalog){Object.keys(result.catalog).forEach(key=>{state.catalogos_personalizados[key]=[...new Set([...(state.catalogos_personalizados[key]||[]),...(result.catalog[key]||[])])]})}}).catch(()=>setAutosaveStatus('El valor se agregó al presupuesto, pero no se pudo actualizar el catálogo global.'))}
let furniture=Object.keys(config.muebles||{}), layers=Object.keys(config.capas||{});
state.catalogos_personalizados=state.catalogos_personalizados||{muebles:[],trabajos:[],dificultades:[],capas:[],modulos:[]};
['muebles','trabajos','dificultades','capas','modulos'].forEach(key=>{state.catalogos_personalizados[key]=[...new Set([...(globalCustomCatalog[key]||[]),...(state.catalogos_personalizados[key]||[])])]});
furniture=[...new Set([...furniture,...(state.catalogos_personalizados.muebles||[])])];
layers=[...new Set([...layers,...(state.catalogos_personalizados.capas||[])])];
function normalizeModuleType(value){return String(value||'').trim().replace(/\s+/g,'_').toLowerCase()}
function moduleLabel(value){const label=String(value||'').replaceAll('_',' ');return label?label.charAt(0).toUpperCase()+label.slice(1):label}
let moduleCatalog=[...new Set([...(state.catalogos_personalizados.modulos||[]),...Object.values(config.muebles||{}).flatMap(entry=>(entry.modulos_default||[]).map(value=>typeof value==='string'?value:(value.tipo||value.nombre||'')))].map(normalizeModuleType).filter(Boolean))];
const opts=(values,current)=>values.map(v=>`<option value="${esc(v)}" ${v===current?'selected':''}>${esc(v.replaceAll('_',' '))}</option>`).join('');
const addOption='<option value="__add__">＋ Agregar...</option>';
function moduleOptions(current){return '<option value="">Seleccionar...</option>'+moduleCatalog.map(v=>`<option value="${esc(v)}" ${v===current?'selected':''}>${esc(moduleLabel(v))}</option>`).join('')+addOption}
function newModule(type='modulo',quantity=1){return{id:uid('mod'),tipo:normalizeModuleType(type)||'modulo',alto:0,ancho:0,profundidad:0,cantidad:Math.max(1,Number(quantity||1)),capas:[]}}
function defaultModulesForFurniture(type){const defaults=config.muebles?.[type]?.modulos_default||[];return defaults.map(entry=>typeof entry==='string'?newModule(entry):newModule(entry.tipo||entry.nombre||'modulo',entry.cantidad||1))}
function newItem(){return{id:uid('item'),tipo_mueble:'',trabajo_tipo:'',complejidad:'',cantidad:1,mano_obra_unitaria:0,mano_obra_plantilla_id:null,mano_obra_snapshot:null,modulos:[]}}
function unique(values){return [...new Set(values.filter(Boolean))]}
function workOptions(item){const values=unique([...labor.map(x=>x.trabajo_tipo),...(state.catalogos_personalizados.trabajos||[]),item.trabajo_tipo]);return '<option value="">Seleccionar...</option>'+opts(values,item.trabajo_tipo)+addOption}
		function difficultyOptions(item){const values=unique([...labor.map(x=>x.complejidad),...(state.catalogos_personalizados.dificultades||[]),item.complejidad]);return '<option value="">Seleccionar...</option>'+opts(values,item.complejidad)+addOption}
	const laborTasks=['desarme','soporte_elastico','confort_gomaespuma','confort_guata','cobertura_corte_tela','cobertura_confeccion_tela','terminacion'];
	const laborTaskLabels={desarme:'Desarme',soporte_elastico:'Soporte elástico',confort_gomaespuma:'Confort gomaespuma',confort_guata:'Confort guata',cobertura_corte_tela:'Corte tela',cobertura_confeccion_tela:'Confección tela',terminacion:'Terminación'};
	function laborTemplate(record){const snapshot=record.snapshot||record,minutes=snapshot.tiempos_minutos||{};const total=laborTasks.reduce((sum,key)=>sum+Number(minutes[key]||0),0);return{...record,id:Number(record.id||snapshot.id||0),mueble_tipo:record.mueble_tipo||snapshot.mueble_tipo||'',trabajo_tipo:record.trabajo_tipo||snapshot.trabajo_tipo||'',complejidad:record.complejidad||snapshot.complejidad||'complejidad_1',costo:record.costo??(total/60*Number(snapshot.tarifa_hora||0)),snapshot}}
	function openLaborCatalog(ii){const item=state.items[ii],selected=labor.find(x=>x.mueble_tipo===item.tipo_mueble&&x.trabajo_tipo===item.trabajo_tipo&&x.complejidad===item.complejidad);$('#catalog-title').textContent=selected?'Modificar mano de obra':'Nueva mano de obra';$('#supply-catalog-form').style.display='none';$('#labor-catalog-form').style.display='grid';$('#catalog-labor-id').value=selected?.id||'';$('#catalog-labor-furniture').value=item.tipo_mueble||'';$('#catalog-labor-work').value=item.trabajo_tipo||'';$('#catalog-labor-complexity').value=item.complejidad||'complejidad_1';const raw=selected?.snapshot||{};$('#catalog-labor-rate').value=raw.tarifa_hora??0;$('#catalog-labor-tasks').innerHTML=laborTasks.map(task=>`<label>${laborTaskLabels[task]}<input type="number" min="0" step="1" data-labor-task="${task}" value="${Number(raw.tiempos_minutos?.[task]||0)}"></label>`).join('');$('#catalog-modal').classList.add('open');$('#catalog-modal').dataset.laborItem=String(ii)}
	function fillSupplyCatalog(id=0){const current=supplyCatalog.find(x=>Number(x.id)===Number(id));$('#catalog-supply-id').value=current?.id||'';$('#catalog-supply-name').value=current?.nombre||'';$('#catalog-supply-unit').value=current?.unidad||'unidad';$('#catalog-supply-category').value=current?.categoria||$('#mi-type').value||'otros';$('#catalog-supply-price').value=current?.precio??0;$('#catalog-supply-stock').value=current?.stock??0;$('#catalog-supply-min').value=current?.stock_minimo??0;$('#catalog-title').textContent=current?'Modificar insumo':'Nuevo insumo'}
	function openSupplyCatalog(id=0){$('#supply-catalog-form').style.display='grid';$('#labor-catalog-form').style.display='none';$('#catalog-existing-supply').value=id?String(id):'';fillSupplyCatalog(id);$('#catalog-modal').classList.add('open')}
	function refreshSupplyOptions(selectedId=''){const select=$('#mi-supply');select.innerHTML='<option value="">Seleccionar...</option>'+supplyCatalog.map(s=>`<option value="${Number(s.id)}" data-price="${Number(s.precio||0)}" data-unit="${esc(s.unidad||'unidad')}" data-category="${esc(s.categoria||'otros')}">${esc(s.nombre||'Insumo')}</option>`).join('');const catalog=$('#catalog-existing-supply');if(catalog)catalog.innerHTML='<option value="">Nuevo insumo</option>'+supplyCatalog.map(s=>`<option value="${Number(s.id)}">${esc(s.nombre||'Insumo')}</option>`).join('');filterSupplies(selectedId)}
function applyLaborTemplate(item){const template=labor.find(x=>x.mueble_tipo===item.tipo_mueble&&x.trabajo_tipo===item.trabajo_tipo&&x.complejidad===item.complejidad);item.mano_obra_plantilla_id=template?.id||null;item.mano_obra_snapshot=template?.snapshot||null;if(template)item.mano_obra_unitaria=template.costo}
function addLayerOptions(){document.querySelectorAll('.layer-type').forEach(select=>{if(![...select.options].some(option=>option.value==='__add__'))select.insertAdjacentHTML('beforeend',addOption)})}
function draw(keep=null){
 $('#cliente').value=state.cliente_id||'';$('#detalle').value=state.detalle||'';$('#fecha').value=state.fecha||'';$('#margen').value=state.margen??30;
 $('#items').innerHTML=(state.items||[]).map((item,ii)=>`<section class="card tree item" data-item="${ii}"><details class="tree-details"><summary><span class="node-title"><span class="collapse-arrow" aria-hidden="true">›</span><div class="node-fields" onclick="event.stopPropagation()"><label>Mueble<select class="item-type"><option value="">Seleccionar...</option>${opts(furniture,item.tipo_mueble)}${addOption}</select></label><label>Trabajo<select class="item-work">${workOptions(item)}</select></label><label>Dificultad<select class="item-difficulty">${difficultyOptions(item)}</select></label><label>Cantidad<input class="item-qty" type="number" min="1" value="${Number(item.cantidad||1)}"></label><label>Mano de obra<input class="item-cost" type="number" min="0" step=".01" value="${Number(item.mano_obra_unitaria||0)}"></label></div><span class="node-actions"><button type="button" class="secondary-btn manage-labor">Plantilla</button><button type="button" class="danger-btn remove-item">Eliminar</button></span></span></summary><div class="tree-content">
 <div class="level-heading"><h3>Módulos</h3><button type="button" class="add-level add-module" title="Agregar módulo" aria-label="Agregar módulo">+</button></div>${(item.modulos||[]).map((m,mi)=>drawModule(m,mi)).join('')}</div></details></section>`).join('');bind();summary()
}
function drawModule(m,mi){return`<div class="tree module" data-module="${mi}"><details class="tree-details"><summary><span class="node-title"><span class="collapse-arrow" aria-hidden="true">›</span><div class="node-fields" onclick="event.stopPropagation()"><label>Tipo<select class="module-type">${moduleOptions(m.tipo)}</select></label><label>Alto<input class="module-h" type="number" min="0" value="${Number(m.alto||0)}"></label><label>Ancho<input class="module-w" type="number" min="0" value="${Number(m.ancho||0)}"></label><label>Profundidad<input class="module-d" type="number" min="0" value="${Number(m.profundidad||0)}"></label><label>Cantidad<input class="module-qty" type="number" min="1" step="1" value="${Math.max(1,Number(m.cantidad||1))}"></label></div><span class="node-actions"><button type="button" class="danger-btn remove-module">Eliminar</button></span></span></summary><div class="tree-content"><div class="level-heading"><h4>Capas</h4><button type="button" class="add-level add-layer" title="Agregar capa" aria-label="Agregar capa">+</button></div>${(m.capas||[]).map((l,li)=>drawLayer(l,li)).join('')}</div></details></div>`}
function drawLayer(l,li){return`<div class="tree layer" data-layer="${li}"><details class="tree-details"><summary><span class="node-title"><span class="collapse-arrow" aria-hidden="true">›</span><div class="node-fields" onclick="event.stopPropagation()"><label>Tipo de capa<select class="layer-type">${opts(layers,l.tipo)}</select></label><label>Insumos<strong>${(l.insumos||[]).length}</strong></label></div><span class="node-actions"><button type="button" class="danger-btn remove-layer">Eliminar</button></span></span></summary><div class="tree-content"><div class="level-heading"><h4>Insumos</h4><button type="button" class="add-level add-input" title="Agregar insumo" aria-label="Agregar insumo">+</button></div>${(l.insumos||[]).map((x,xi)=>`<div class="insumo-row" data-input="${xi}"><span><strong>${esc(x.nombre||'Insumo')}</strong><br><small>${esc(x.tipo||'')}${x.tipo==='fleje'?' · '+Number(x.tiras||0)+' tiras · '+Number(x.grapas_estimadas||0)+' grapas':''}</small></span><span>${Number(x.cantidad_final||x.cantidad_ajustada||0).toFixed(2)} ${esc(x.unidad||'')}</span><span>${money(x.costo_unitario_total||0)}</span><span><button type="button" class="secondary-btn edit-input">Editar</button> <button type="button" class="danger-btn remove-input">×</button></span></div>`).join('')}</div></details></div>`}
function drawLayer(l,li){const buttons=(l.insumos||[]).map((x,xi)=>`<button type="button" class="secondary-btn summary-input-button" data-input-index="${xi}" onclick="event.stopPropagation()">${esc(x.nombre||'Insumo')} · ${money(x.costo_unitario_total||0)}</button>`).join('');return`<div class="tree layer" data-layer="${li}"><details class="tree-details"><summary><span class="node-title"><span class="collapse-arrow" aria-hidden="true">›</span><div class="node-fields" onclick="event.stopPropagation()"><label>Tipo de capa<select class="layer-type">${opts(layers,l.tipo)}</select></label><div class="layer-inputs-summary"><span>Insumos</span>${buttons}<button type="button" class="add-level summary-add-input" title="Agregar insumo" aria-label="Agregar insumo" onclick="event.stopPropagation()">+</button></div></div><span class="node-actions"><button type="button" class="danger-btn remove-layer">Eliminar</button></span></span></summary><div class="tree-content">${(l.insumos||[]).map((x,xi)=>`<div class="insumo-row" data-input="${xi}"><span><strong>${esc(x.nombre||'Insumo')}</strong><br><small>${esc(x.tipo||'')}${x.tipo==='fleje'?' · '+Number(x.tiras||0)+' tiras · '+Number(x.grapas_estimadas||0)+' grapas':''}</small></span><span>${Number(x.cantidad_final||x.cantidad_ajustada||0).toFixed(2)} ${esc(x.unidad||'')}</span><span>${money(x.costo_unitario_total||0)}</span><span><button type="button" class="secondary-btn edit-input">Editar</button> <button type="button" class="danger-btn remove-input">×</button></span></div>`).join('')}</div></details></div>`}
function restoreOpenBranches(keep){if(!keep)return;const itemDetails=document.querySelector(`[data-item="${keep.ii}"]>.tree-details`),moduleDetails=document.querySelector(`[data-item="${keep.ii}"] [data-module="${keep.mi}"]>.tree-details`),layerDetails=document.querySelector(`[data-item="${keep.ii}"] [data-module="${keep.mi}"] [data-layer="${keep.li}"]>.tree-details`);if(itemDetails)itemDetails.open=true;if(moduleDetails)moduleDetails.open=true;if(layerDetails)layerDetails.open=true}
function captureOpenBranches(){return[...document.querySelectorAll('.tree-details[open]')].map(details=>{const item=details.closest('[data-item]'),module=details.closest('[data-module]'),layer=details.closest('[data-layer]');return{ii:item?.dataset.item,mi:module?.dataset.module,li:layer?.dataset.layer}})}
function restoreCapturedBranches(branches){(branches||[]).forEach(branch=>{const item=document.querySelector(`[data-item="${branch.ii}"]`),module=branch.mi===undefined?null:item?.querySelector(`[data-module="${branch.mi}"]`),layer=branch.li===undefined?null:module?.querySelector(`[data-layer="${branch.li}"]`);const details=layer?.querySelector(':scope > .tree-details')||module?.querySelector(':scope > .tree-details')||item?.querySelector(':scope > .tree-details');if(details)details.open=true})}
function indexes(el){return[Number(el.closest('[data-item]')?.dataset.item),Number(el.closest('[data-module]')?.dataset.module),Number(el.closest('[data-layer]')?.dataset.layer),Number(el.closest('[data-input]')?.dataset.input)]}
	function bind(){
	 addLayerOptions();
	 document.querySelectorAll('[data-item]').forEach(n=>{const ii=Number(n.dataset.item),item=state.items[ii];$('.remove-item',n).onclick=()=>{state.items.splice(ii,1);draw();queueAutosave()};$('.add-module',n).onclick=()=>{item.modulos.push(newModule());draw();queueAutosave()};$('.manage-labor',n).onclick=()=>openLaborCatalog(ii);$('.item-type',n).onchange=e=>{if(e.target.value==='__add__'){const value=prompt('Nombre del nuevo tipo de mueble:');if(!value?.trim()){draw();return}const custom=value.trim().replace(/\s+/g,'_');if(!furniture.includes(custom))furniture.push(custom);if(!state.catalogos_personalizados.muebles.includes(custom))state.catalogos_personalizados.muebles.push(custom);e.target.value=custom}item.tipo_mueble=e.target.value;item.trabajo_tipo='';item.complejidad='';item.mano_obra_plantilla_id=null;item.mano_obra_snapshot=null;item.mano_obra_unitaria=0;draw();queueAutosave()};$('.item-work',n).onchange=e=>{if(e.target.value==='__add__'){const value=prompt('Nombre del nuevo tipo de trabajo:');if(!value?.trim()){draw();return}const custom=value.trim();if(!state.catalogos_personalizados.trabajos.includes(custom))state.catalogos_personalizados.trabajos.push(custom);e.target.value=custom}item.trabajo_tipo=e.target.value;item.complejidad='';item.mano_obra_plantilla_id=null;item.mano_obra_snapshot=null;draw();queueAutosave()};$('.item-difficulty',n).onchange=e=>{if(e.target.value==='__add__'){const value=prompt('Nombre del nuevo nivel de dificultad:');if(!value?.trim()){draw();return}const custom=value.trim();if(!state.catalogos_personalizados.dificultades.includes(custom))state.catalogos_personalizados.dificultades.push(custom);e.target.value=custom}item.complejidad=e.target.value;applyLaborTemplate(item);draw();queueAutosave()};$('.item-qty',n).oninput=e=>{item.cantidad=Math.max(1,Number(e.target.value||1));summary();queueAutosave()};$('.item-cost',n).oninput=e=>{item.mano_obra_unitaria=Number(e.target.value||0);summary();queueAutosave()}});
	 document.querySelectorAll('[data-module]').forEach(n=>{const[ii,mi]=indexes(n),m=state.items[ii].modulos[mi];$('.remove-module',n).onclick=()=>{state.items[ii].modulos.splice(mi,1);draw();queueAutosave()};$('.add-layer',n).onclick=()=>{m.capas.push({id:uid('layer'),tipo:layers[0]||'estructura',insumos:[]});draw();restoreOpenBranches({ii,mi,li:m.capas.length-1});queueAutosave()};$('.module-type',n).onchange=e=>{if(e.target.value==='__add__'){const value=prompt('Nombre del nuevo tipo de módulo:');if(!value?.trim()){draw();return}const custom=normalizeModuleType(value);if(!moduleCatalog.includes(custom))moduleCatalog.push(custom);if(!state.catalogos_personalizados.modulos.includes(custom))state.catalogos_personalizados.modulos.push(custom);persistCatalogValue('modulos',custom);e.target.value=custom}m.tipo=normalizeModuleType(e.target.value);queueAutosave()};[['.module-h','alto'],['.module-w','ancho'],['.module-d','profundidad'],['.module-qty','cantidad']].forEach(([s,k])=>$(s,n).oninput=e=>{m[k]=Math.max(1,Number(e.target.value||1));queueAutosave()})});
	 document.querySelectorAll('[data-layer]').forEach(n=>{const[ii,mi,li]=indexes(n);$('.add-input',n).onclick=()=>{autosave();openModal(ii,mi,li,-1)}});
	 document.querySelectorAll('[data-input]').forEach(n=>{const[ii,mi,li,xi]=indexes(n);$('.edit-input',n).onclick=()=>{autosave();openModal(ii,mi,li,xi)};$('.remove-input',n).onclick=()=>{state.items[ii].modulos[mi].capas[li].insumos.splice(xi,1);draw();queueAutosave()}});
}
function summary(){let work=0,materials=0;(state.items||[]).forEach(i=>{const q=Math.max(1,Number(i.cantidad||1));work+=Number(i.mano_obra_unitaria||0)*q;(i.modulos||[]).forEach(m=>(m.capas||[]).forEach(l=>(l.insumos||[]).forEach(x=>materials+=Number(x.costo_unitario_total||0)*q)))});const subtotal=work+materials,marginPercent=Math.max(0,Number(state.margen||0)),marginAmount=subtotal*marginPercent/100,total=subtotal+marginAmount;$('#summary').textContent=`Mano de obra: ${money(work)} · Materiales: ${money(materials)} · Subtotal: ${money(subtotal)} · Margen (${marginPercent}%): ${money(marginAmount)} · Total estimado: ${money(total)}`}
summary=function(){let work=0,materials=0;(state.items||[]).forEach(i=>{const q=Math.max(1,Number(i.cantidad||1));work+=Number(i.mano_obra_unitaria||0)*q;(i.modulos||[]).forEach(m=>(m.capas||[]).forEach(l=>(l.insumos||[]).forEach(x=>materials+=Number(x.costo_unitario_total||0)*Math.max(1,Number(m.cantidad||1))*q)))});const subtotal=work+materials,marginPercent=Math.max(0,Number(state.margen||0)),marginAmount=subtotal*marginPercent/100,total=subtotal+marginAmount;$('#summary').textContent=`Mano de obra: ${money(work)} · Materiales: ${money(materials)} · Subtotal: ${money(subtotal)} · Margen (${marginPercent}%): ${money(marginAmount)} · Total estimado: ${money(total)}`}
function foamThickness(name){const match=String(name||'').match(/(\d+(?:[.,]\d+)?)\s*cm/i);return match?Number(match[1].replace(',','.')):0}
function edgeDefaults(type){const value=['tela','guata','fliselina','cierre','cordon'].includes(type)?'ambos':'ninguno';return{superior:value,derecho:value,inferior:value,izquierdo:value}}
function edgeAllowance(value){return value==='fijacion'?5:value==='costura'?2:value==='ambos'?7:0}
function rawPieceDimensions(pieza,type,module,supplyName=''){const name=String(pieza||'pieza').toLowerCase();const h=Math.max(0,Number(module?.alto||0)),w=Math.max(0,Number(module?.ancho||0)),d=Math.max(0,Number(module?.profundidad||0));let a=w,b=d;if(name.includes('lateral')||name.includes('lado')){a=h;b=d}else if(name.includes('frente')||name.includes('trasera')||name.includes('dorso')){a=h;b=w}else if(name.includes('superior')||name.includes('inferior')||name.includes('asiento')){a=w;b=d}if(type==='gomaespuma'){const thickness=foamThickness(supplyName);if(thickness>1&&thickness<5){a=Math.max(0,a-5);b=Math.max(0,b-5)}}return{alto:a,ancho:b}}
function dimensionsWithEdges(base,bordes){return{alto:Math.max(0,base.alto+edgeAllowance(bordes.superior)+edgeAllowance(bordes.inferior)),ancho:Math.max(0,base.ancho+edgeAllowance(bordes.izquierdo)+edgeAllowance(bordes.derecho))}}
function defaultPiece(pieza,type,module,supplyName='',bordes=null){const edges=bordes||edgeDefaults(type),base=rawPieceDimensions(pieza,type,module,supplyName),dimensions=dimensionsWithEdges(base,edges);return{...dimensions,bordes:edges,rotatable:true}}
function edgeSelect(className,value){return`<select class="${className}"><option value="ninguno" ${value==='ninguno'?'selected':''}>Ninguno</option><option value="costura" ${value==='costura'?'selected':''}>Costura +2</option><option value="fijacion" ${value==='fijacion'?'selected':''}>Fijación +5</option><option value="ambos" ${value==='ambos'?'selected':''}>Ambos +7</option></select>`}
function pieceRow(p={},type=$('#mi-type')?.value||'',module=ctx?state.items[ctx.ii].modulos[ctx.mi]:null){const hasDimensions=Number(p.alto||0)>0&&Number(p.ancho||0)>0;const defaults=defaultPiece(p.pieza,type,module,$('#mi-supply')?.selectedOptions[0]?.textContent||'',p.bordes);const row={...defaults,...p,alto:hasDimensions?Number(p.alto):defaults.alto,ancho:hasDimensions?Number(p.ancho):defaults.ancho,bordes:p.bordes||defaults.bordes,rotatable:p.rotatable??p.rotable??true};const r=document.createElement('div');r.className='piece-row';r.dataset.manual=hasDimensions?'1':'0';r.innerHTML=`<input class="p-name" placeholder="Pieza" value="${esc(row.pieza||'pieza')}"><input class="p-h" type="number" min="0" placeholder="Alto cm" value="${Number(row.alto||0)}"><input class="p-w" type="number" min="0" placeholder="Ancho cm" value="${Number(row.ancho||0)}"><input class="p-q" type="number" min="1" value="${Number(row.cantidad||1)}"><label style="text-align:center"><input class="p-rotate" type="checkbox" ${row.rotatable!==false?'checked':''} aria-label="Permitir rotación 90 grados"></label><button type="button" class="danger-btn">×</button><div class="piece-edges"><label>Sup. ${edgeSelect('p-edge-top',row.bordes.superior)}</label><label>Der. ${edgeSelect('p-edge-right',row.bordes.derecho)}</label><label>Inf. ${edgeSelect('p-edge-bottom',row.bordes.inferior)}</label><label>Izq. ${edgeSelect('p-edge-left',row.bordes.izquierdo)}</label></div>`;$('button',r).onclick=()=>r.remove();$('.p-name',r).oninput=()=>{if(r.dataset.manual==='0'){const d=defaultPiece($('.p-name',r).value,type,module,$('#mi-supply')?.selectedOptions[0]?.textContent||'',row.bordes);$('.p-h',r).value=d.alto;$('.p-w',r).value=d.ancho}};['.p-h','.p-w'].forEach(selector=>$(selector,r).oninput=()=>{r.dataset.manual='1'});return r}
function filterSupplies(selectedId=''){const type=$('#mi-type').value,select=$('#mi-supply');[...select.options].forEach(option=>{if(!option.value)return;option.hidden=option.dataset.category!==type});const selected=[...select.options].find(option=>option.value===String(selectedId)&&!option.hidden);select.value=selected?selected.value:'';if(!select.value)$('#mi-cost').value=''}
function fields(selectedId=''){const type=$('#mi-type').value;document.querySelectorAll('[data-for]').forEach(x=>x.style.display=x.dataset.for.split(' ').includes(type)?'':'none');$('#direction-label').style.display=type==='fleje'&&$('#mi-pattern').value==='lineal'?'':'none';$('#pieces-section').style.display=['tela','gomaespuma','madera','guata','fliselina','cierre','cordon'].includes(type)?'':'none';filterSupplies(selectedId);previewCalculation()}
function linearPieceMeters(pieces,rollWidth){return pieces.reduce((total,p)=>{const q=Math.max(0,Number(p.cantidad||0)),a=Math.max(0,Number(p.alto||0))/100,b=Math.max(0,Number(p.ancho||0))/100,rotatable=p.rotatable!==false&&p.rotable!==false;const candidates=[{length:a,width:b}];if(rotatable)candidates.push({length:b,width:a});const valid=candidates.filter(option=>option.width<=rollWidth);if(valid.length)return total+Math.min(...valid.map(option=>option.length))*q;return total+(a*b/Math.max(.01,rollWidth))*q},0)}
function calculateInput(input,module){const type=input.tipo,waste=Math.max(0,Number(input.merma_pct||0)),manual=Math.max(0,Number(input.cantidad_ajustada||0));let base=0,strips=0,staples=0,origin='calculado';if(type==='fleje'){const height=Math.max(0,Number(module.alto||0))/100,width=Math.max(0,Number(module.ancho||0))/100,spacing=Math.max(.01,Number(input.separacion_cm||10)/100);const acrossWidth=Math.ceil(height/spacing),acrossLength=Math.ceil(width/spacing);if(input.patron==='cuadriculado'){strips=acrossWidth+acrossLength;base=acrossWidth*width+acrossLength*height}else if(input.direccion==='largo'){strips=acrossLength;base=strips*height}else{strips=acrossWidth;base=strips*width}staples=strips*2*Math.max(1,Number(input.grapas_por_extremo||2));origin='metros de fleje'}else{let area=0,linear=0;(input.piezas||[]).forEach(p=>{const q=Math.max(0,Number(p.cantidad||0)),h=Math.max(0,Number(p.alto||0))/100,w=Math.max(0,Number(p.ancho||0))/100;area+=h*w*q;linear+=Math.max(h,w)*q});base=area;if(['tela','guata','fliselina'].includes(type)){base=area/Math.max(.01,Number(input.ancho_util||1.4));origin='metros lineales'}else if(['gomaespuma','madera'].includes(type)){base=Math.ceil((area/(Math.max(.01,Number(input.placa_largo||2))*Math.max(.01,Number(input.placa_ancho||1))))*4)/4;origin='placas'}else if(['cierre','cordon'].includes(type)){base=linear;origin='metros lineales'}}const calculated=base*(1+waste/100),finalQty=manual>0?manual:calculated,cost=finalQty*Math.max(0,Number(input.costo_unitario||0));return{cantidad_calculada:Number(calculated.toFixed(4)),cantidad_final:Number(finalQty.toFixed(4)),costo_unitario_total:Number(cost.toFixed(2)),tiras:strips,grapas_estimadas:staples,origen_cantidad:manual>0?'ajuste manual':origin}}
const calculateInputLegacy=calculateInput;calculateInput=function(input,module){const result=calculateInputLegacy(input,module);if(['tela','guata','fliselina'].includes(input.tipo)&&!Number(input.cantidad_ajustada||0))result.cantidad_calculada=Number((linearPieceMeters(input.piezas,Math.max(.01,Number(input.ancho_util||1.4)))*(1+Math.max(0,Number(input.merma_pct||0))/100)).toFixed(4)),result.cantidad_final=result.cantidad_calculada,result.costo_unitario_total=Number((result.cantidad_final*Math.max(0,Number(input.costo_unitario||0))).toFixed(2));return result}
function modalInput(){return{tipo:$('#mi-type').value,costo_unitario:Number($('#mi-cost').value||0),merma_pct:Number($('#mi-waste').value||0),ancho_util:Number($('#mi-roll-width').value||0),placa_largo:Number($('#mi-sheet-length').value||0),placa_ancho:Number($('#mi-sheet-width').value||0),patron:$('#mi-pattern').value,direccion:$('#mi-direction').value,separacion_cm:Number($('#mi-spacing').value||0),grapas_por_extremo:Number($('#mi-staples').value||2),cantidad_ajustada:Number($('#mi-adjusted').value||0),piezas:[...document.querySelectorAll('.piece-row')].map(r=>({pieza:$('.p-name',r).value||'pieza',alto:Number($('.p-h',r).value||0),ancho:Number($('.p-w',r).value||0),cantidad:Number($('.p-q',r).value||1),rotatable:$('.p-rotate',r).checked,bordes:{superior:$('.p-edge-top',r).value,derecho:$('.p-edge-right',r).value,inferior:$('.p-edge-bottom',r).value,izquierdo:$('.p-edge-left',r).value}})).filter(p=>p.alto>0&&p.ancho>0)}}
function previewCalculation(){if(!ctx)return;const input=modalInput(),module=state.items[ctx.ii].modulos[ctx.mi],result=calculateInput(input,module),host=$('#calculation-preview');if(input.tipo==='fleje'&&(!Number(module.alto)||!Number(module.ancho))){host.textContent='Completá el alto y el ancho del módulo para calcular el fleje.';return}const unitLabel=['gomaespuma','madera'].includes(input.tipo)?' placas':'';host.textContent=input.tipo==='fleje'?('Consumo: '+result.cantidad_final.toFixed(2)+' m de cinta · '+result.tiras+' tiras · '+result.grapas_estimadas+' grapas · Costo: '+money(result.costo_unitario_total)):('Cantidad: '+result.cantidad_final.toFixed(2)+unitLabel+' · Costo: '+money(result.costo_unitario_total))}
function previewCalculation(){if(!ctx)return;const host=$('#calculation-preview');if(!Number($('#mi-supply').value||0)){host.textContent='Seleccioná un insumo para ver el consumo.';return}const input=modalInput(),module=state.items[ctx.ii].modulos[ctx.mi],result=calculateInput(input,module);if(input.tipo==='fleje'&&(!Number(module.alto)||!Number(module.ancho))){host.textContent='Completá el alto y el ancho del módulo para calcular el fleje.';return}const unitLabel=['gomaespuma','madera'].includes(input.tipo)?' placas':'';host.textContent=input.tipo==='fleje'?('Consumo: '+result.cantidad_final.toFixed(2)+' m de cinta · '+result.tiras+' tiras · '+result.grapas_estimadas+' grapas · Costo: '+money(result.costo_unitario_total)):('Cantidad: '+result.cantidad_final.toFixed(2)+unitLabel+' · Costo: '+money(result.costo_unitario_total))}
function openModal(ii,mi,li,xi){ctx={ii,mi,li,xi};const layer=state.items[ii].modulos[mi].capas[li],x=xi>=0?layer.insumos[xi]:{};$('#modal-title').textContent=xi>=0?'Editar insumo':'Agregar insumo';$('#mi-type').innerHTML=opts(config.capas?.[layer.tipo]?.tipos_insumo_permitidos||fallbackTypes,x.tipo||'');$('#mi-supply').value=x.insumo_id||'';$('#mi-waste').value=x.merma_pct??10;$('#mi-cost').value=x.costo_unitario??0;$('#mi-roll-width').value=x.ancho_util??1.4;$('#mi-sheet-length').value=x.placa_largo??2;$('#mi-sheet-width').value=x.placa_ancho??1;$('#mi-pattern').value=x.patron??'lineal';$('#mi-direction').value=x.direccion??'ancho';$('#mi-spacing').value=x.separacion_cm??10;$('#mi-staples').value=x.grapas_por_extremo??2;$('#mi-adjusted').value=x.cantidad_ajustada??'';$('#mi-reason').value=x.motivo_ajuste??'';$('#pieces').innerHTML='';(x.piezas?.length?x.piezas:[{}]).forEach(p=>$('#pieces').append(pieceRow(p)));fields(x.insumo_id||'');$('#modal').classList.add('open')}
		$('#mi-type').onchange=()=>{fields();applyPieceRules()};$('#mi-pattern').onchange=()=>{fields();previewCalculation()};$('#mi-supply').onchange=e=>{$('#mi-cost').value=e.target.selectedOptions[0]?.dataset.price||0;applyPieceRules();previewCalculation()};$('#add-piece').onclick=()=>{$('#pieces').append(pieceRow({},$('#mi-type').value,ctx?state.items[ctx.ii].modulos[ctx.mi]:null));previewCalculation()};$('#apply-piece-rules').onclick=()=>{applyPieceRules(true);previewCalculation()};$('#modal').addEventListener('input',previewCalculation);$('#modal').addEventListener('change',e=>{if(e.target.matches('.piece-edges select')){const row=e.target.closest('.piece-row');row.bordes={superior:$('.p-edge-top',row).value,derecho:$('.p-edge-right',row).value,inferior:$('.p-edge-bottom',row).value,izquierdo:$('.p-edge-left',row).value};row.dataset.manual='0';applyPieceRules(false)}previewCalculation()});$('#cancel-input').onclick=()=>$('#modal').classList.remove('open');
	$('#new-supply').onclick=()=>openSupplyCatalog();$('#edit-supply').onclick=()=>{const id=Number($('#mi-supply').value||0);if(!id){alert('Seleccioná un insumo para modificarlo.');return}openSupplyCatalog(id)};$('#catalog-existing-supply').onchange=e=>fillSupplyCatalog(Number(e.target.value||0));
	$('#close-catalog').onclick=()=>$('#catalog-modal').classList.remove('open');$('#close-catalog-labor').onclick=()=>$('#catalog-modal').classList.remove('open');
	$('#supply-catalog-form').onsubmit=e=>{e.preventDefault();const body=new FormData();body.set('action','supply_save');body.set('supply_id',$('#catalog-supply-id').value);body.set('nombre',$('#catalog-supply-name').value);body.set('unidad',$('#catalog-supply-unit').value);body.set('categoria',$('#catalog-supply-category').value);body.set('precio',$('#catalog-supply-price').value);body.set('stock',$('#catalog-supply-stock').value);body.set('stock_minimo',$('#catalog-supply-min').value);fetch(window.location.href,{method:'POST',body,credentials:'same-origin',headers:{'Accept':'application/json'}}).then(r=>r.json()).then(result=>{if(!result.ok){alert(result.error||'No se pudo guardar el insumo.');return}const saved=result.supply;const index=supplyCatalog.findIndex(x=>Number(x.id)===Number(saved.id));if(index>=0)supplyCatalog[index]=saved;else supplyCatalog.push(saved);refreshSupplyOptions(saved.id);$('#mi-supply').value=String(saved.id);$('#mi-cost').value=Number(saved.precio||0);fields(saved.id);$('#catalog-modal').classList.remove('open')}).catch(()=>alert('No se pudo guardar el insumo. Revisá la conexión.'))};
	$('#labor-catalog-form').onsubmit=e=>{e.preventDefault();const body=new FormData();body.set('action','labor_save');body.set('labor_id',$('#catalog-labor-id').value);body.set('mueble_tipo',$('#catalog-labor-furniture').value);body.set('trabajo_tipo',$('#catalog-labor-work').value);body.set('complejidad',$('#catalog-labor-complexity').value);body.set('tarifa_hora',$('#catalog-labor-rate').value);document.querySelectorAll('[data-labor-task]').forEach(input=>body.set('tiempo_'+input.dataset.laborTask,input.value));fetch(window.location.href,{method:'POST',body,credentials:'same-origin',headers:{'Accept':'application/json'}}).then(r=>r.json()).then(result=>{if(!result.ok){alert(result.error||'No se pudo guardar la mano de obra.');return}const saved=laborTemplate(result.labor),index=labor.findIndex(x=>Number(x.id)===Number(saved.id));if(index>=0)labor[index]=saved;else labor.push(saved);const ii=Number($('#catalog-modal').dataset.laborItem),item=state.items[ii];item.tipo_mueble=saved.mueble_tipo;item.trabajo_tipo=saved.trabajo_tipo;item.complejidad=saved.complejidad;applyLaborTemplate(item);$('#catalog-modal').classList.remove('open');draw();queueAutosave()}).catch(()=>alert('No se pudo guardar la mano de obra. Revisá la conexión.'))};
$('#save-input').onclick=()=>{const{ii,mi,li,xi}=ctx,sel=$('#mi-supply'),id=Number(sel.value);if(!id){alert('Seleccioná un insumo.');return}const adjusted=Number($('#mi-adjusted').value||0),reason=$('#mi-reason').value;if(adjusted>0&&!reason.trim()){alert('Indicá el motivo del ajuste.');return}const module=state.items[ii].modulos[mi];if($('#mi-type').value==='fleje'&&(!Number(module.alto)||!Number(module.ancho))){alert('Para calcular el fleje, completá el alto y el ancho del módulo.');return}const input=modalInput(),result=calculateInput(input,module);const x={id:xi>=0?state.items[ii].modulos[mi].capas[li].insumos[xi].id:uid('input'),...input,...result,insumo_id:id,nombre:sel.selectedOptions[0].textContent,unidad:sel.selectedOptions[0].dataset.unit,motivo_ajuste:reason};const l=state.items[ii].modulos[mi].capas[li];if(xi>=0)l.insumos[xi]=x;else l.insumos.push(x);$('#modal').classList.remove('open');draw()};
$('#add-item').onclick=()=>{state.items.push(newItem());draw(true)};['cliente','detalle','fecha','margen'].forEach(id=>$('#'+id).oninput=e=>{state[id==='cliente'?'cliente_id':id]=id==='cliente'?Number(e.target.value):id==='margen'?Number(e.target.value):e.target.value;summary()});$('#finalize').onclick=()=>$('#save-action').value='finalize';
$('#budget-form').onsubmit=e=>{state.cliente_id=Number($('#cliente').value);state.detalle=$('#detalle').value;state.fecha=$('#fecha').value;state.margen=Number($('#margen').value||0);if(!state.cliente_id||!state.items.length){e.preventDefault();alert('Seleccioná cliente y agregá al menos un mueble.');return}$('#payload').value=JSON.stringify(state)};
	function applyPieceRules(force=false){if(!ctx)return;const type=$('#mi-type').value,module=state.items[ctx.ii].modulos[ctx.mi],supplyName=$('#mi-supply').selectedOptions[0]?.textContent||'';document.querySelectorAll('.piece-row').forEach(row=>{if(!force&&row.dataset.manual==='1')return;const bordes={superior:$('.p-edge-top',row).value,derecho:$('.p-edge-right',row).value,inferior:$('.p-edge-bottom',row).value,izquierdo:$('.p-edge-left',row).value},d=defaultPiece($('.p-name',row).value,type,module,supplyName,bordes);$('.p-h',row).value=d.alto;$('.p-w',row).value=d.ancho;row.dataset.manual='0'})}
		// Captura cambios de controles dinámicos y botones que reconstruyen el árbol.
	document.addEventListener('input',e=>{if(e.target.closest('#budget-form'))queueAutosave()});
	document.addEventListener('change',e=>{if(e.target.closest('#budget-form'))queueAutosave()});
	document.addEventListener('change',e=>{if(!e.target.matches('.item-type'))return;const itemNode=e.target.closest('[data-item]'),item=itemNode?state.items[Number(itemNode.dataset.item)]:null;if(item&&item.modulos.length===0){item.modulos=defaultModulesForFurniture(item.tipo_mueble);draw();queueAutosave()}});
	document.addEventListener('change',e=>{if(!e.target.matches('.layer-type'))return;const layerNode=e.target.closest('[data-layer]'),[ii,mi,li]=indexes(layerNode),layer=state.items[ii]?.modulos?.[mi]?.capas?.[li];if(!layer)return;let value=e.target.value;if(value==='__add__'){const custom=prompt('Nombre de la nueva capa:');if(!custom?.trim()){draw();return}value=custom.trim().replace(/\s+/g,'_');if(!layers.includes(value))layers.push(value);if(!state.catalogos_personalizados.capas.includes(value))state.catalogos_personalizados.capas.push(value)}layer.tipo=value;draw();queueAutosave()});
	document.addEventListener('click',e=>{const removeLayer=e.target.closest('.remove-layer');if(!removeLayer)return;const layerNode=removeLayer.closest('[data-layer]'),[ii,mi,li]=indexes(layerNode),module=state.items[ii]?.modulos?.[mi];if(!module)return;module.capas.splice(li,1);draw();queueAutosave();e.preventDefault();e.stopPropagation()});
	document.addEventListener('click',e=>{const summaryInput=e.target.closest('.summary-input-button,.summary-add-input');if(!summaryInput)return;const layerNode=summaryInput.closest('[data-layer]'),[ii,mi,li]=indexes(layerNode);openModal(ii,mi,li,summaryInput.classList.contains('summary-add-input')?-1:Number(summaryInput.dataset.inputIndex))},true);
	document.addEventListener('click',e=>{if(e.target.closest('#save-input'))setTimeout(()=>restoreOpenBranches(ctx),0);if(e.target.closest('#add-item,.add-module,.add-layer,.add-input,.remove-item,.remove-module,.remove-layer,.remove-input,#save-input'))setTimeout(queueAutosave,0)});
	state.items=state.items||[];state.items.forEach(item=>{item.trabajo_tipo=item.trabajo_tipo||item.mano_obra_snapshot?.trabajo_tipo||'';item.complejidad=item.complejidad||item.mano_obra_snapshot?.complejidad||''});const renderTree=draw;draw=function(resetOpen=false){const openBranches=resetOpen?[]:captureOpenBranches();renderTree();setTimeout(()=>restoreCapturedBranches(openBranches),0)};draw();
})();
</script>
<script>
window.presupuestoV2Data = {
  initial: <?= json_encode($initial, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>,
  config: <?= json_encode($configCapas, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>,
  labor: <?= json_encode($laborTemplates, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>,
  supplyCatalog: <?= json_encode($insumos, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>,
  globalCustomCatalog: <?= json_encode($catalogosGlobales, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>
};
</script>
<script src="assets/js/presupuesto_editor_v2_component_utf8.js?v=<?= filemtime(__DIR__ . '/assets/js/presupuesto_editor_v2_component_utf8.js') ?>"></script>
<?php render_page_end(); ?>
