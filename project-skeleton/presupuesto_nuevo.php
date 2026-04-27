<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$clientes = read_json(data_file('clientes'));
$insumos = read_json(data_file('insumos'));
$config = read_json(data_file('config'));
$presupuestos = read_json(data_file('presupuestos'));
$categoriasInsumo = ['tela', 'gomaespuma', 'fleje', 'cierre', 'otros'];
$clientesById = [];
foreach ($clientes as $cliente) {
    $clientesById[(int) ($cliente['id'] ?? 0)] = (string) ($cliente['nombre'] ?? '');
}

function find_insumo_by_name(array $insumos, string $name): ?array
{
    $wanted = strtolower(trim($name));
    if ($wanted === '') {
        return null;
    }

    foreach ($insumos as $insumo) {
        $current = strtolower(trim((string) ($insumo['nombre'] ?? '')));
        if ($current === $wanted) {
            return $insumo;
        }
    }

    return null;
}

function find_presupuesto_by_id(array $presupuestos, int $id): ?array
{
    foreach ($presupuestos as $presupuesto) {
        if ((int) ($presupuesto['id'] ?? 0) === $id) {
            return $presupuesto;
        }
    }

    return null;
}

function export_presupuestos_csv(array $presupuestos, array $clientesById, ?int $onlyId = null): void
{
    $filename = $onlyId === null ? 'presupuestos.csv' : 'presupuesto_' . $onlyId . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'wb');
    if ($output === false) {
        exit;
    }

    fputcsv($output, ['ID', 'Fecha', 'Cliente', 'Estado', 'Detalle', 'Mano de obra', 'Materiales', 'Margen %', 'Impuesto', 'Total', 'Insumos']);

    foreach ($presupuestos as $presupuesto) {
        $id = (int) ($presupuesto['id'] ?? 0);
        if ($onlyId !== null && $id !== $onlyId) {
            continue;
        }

        $items = $presupuesto['insumos_estimados'] ?? [];
        $itemsText = [];
        foreach ($items as $item) {
            $itemsText[] = (string) ($item['nombre'] ?? 'Insumo') . ' x ' . (string) ($item['cantidad'] ?? 0) . ' (' . (string) ($item['unidad'] ?? 'unidad') . ')';
        }

        fputcsv($output, [
            $id,
            (string) ($presupuesto['fecha'] ?? ''),
            (string) ($clientesById[(int) ($presupuesto['cliente_id'] ?? 0)] ?? 'Cliente eliminado'),
            (string) ($presupuesto['estado'] ?? 'borrador'),
            (string) ($presupuesto['detalle'] ?? ''),
            (float) ($presupuesto['mano_obra'] ?? 0),
            (float) ($presupuesto['materiales'] ?? 0),
            (float) ($presupuesto['margen'] ?? 0),
            (float) ($presupuesto['impuesto'] ?? 0),
            (float) ($presupuesto['total'] ?? 0),
            implode(' | ', $itemsText),
        ]);
    }

    fclose($output);
    exit;
}

function render_presupuesto_detalle(array $presupuestoDetalle, array $clientesById, bool $standalone = false): void
{
    $manoObra = (float) ($presupuestoDetalle['mano_obra'] ?? 0);
    $materiales = (float) ($presupuestoDetalle['materiales'] ?? 0);
    $margenPct = (float) ($presupuestoDetalle['margen'] ?? 0);
    $margenMonto = ($manoObra + $materiales) * ($margenPct / 100);

    echo '<section class="card" style="margin-top:16px;">';
    echo '<h3 style="margin-top:0;">Detalle del presupuesto #' . (int) ($presupuestoDetalle['id'] ?? 0) . '</h3>';
    if ($standalone) {
        echo '<div class="inline-actions">';
        echo '<button type="button" onclick="window.print()">Imprimir detalle</button>';
        echo '<a href="presupuesto_nuevo.php?ver=' . (int) ($presupuestoDetalle['id'] ?? 0) . '" class="secondary-btn action-link">Volver</a>';
        echo '</div>';
    }
    echo '<p><strong>Cliente:</strong> ' . h((string) ($clientesById[(int) ($presupuestoDetalle['cliente_id'] ?? 0)] ?? 'Cliente eliminado')) . '</p>';
    echo '<p><strong>Fecha:</strong> ' . h((string) ($presupuestoDetalle['fecha'] ?? '')) . '</p>';
    echo '<p><strong>Estado:</strong> ' . h((string) ($presupuestoDetalle['estado'] ?? 'borrador')) . '</p>';
    echo '<p><strong>Detalle:</strong> ' . h((string) ($presupuestoDetalle['detalle'] ?? '')) . '</p>';
    echo '<p><strong>Mano de obra:</strong> ' . money($manoObra) . '</p>';
    echo '<p><strong>Materiales:</strong> ' . money($materiales) . '</p>';
    echo '<p><strong>Margen:</strong> ' . $margenPct . '% (' . money($margenMonto) . ')</p>';
    echo '<p><strong>Total:</strong> ' . money((float) ($presupuestoDetalle['total'] ?? 0)) . '</p>';
    echo '<h4>Insumos estimados</h4>';
    echo '<table class="table">';
    echo '<thead><tr><th>Insumo</th><th>Cantidad</th><th>Unidad</th><th>Costo unitario</th><th>Subtotal</th></tr></thead><tbody>';
    foreach (($presupuestoDetalle['insumos_estimados'] ?? []) as $item) {
        echo '<tr>';
        echo '<td>' . h((string) ($item['nombre'] ?? 'Insumo')) . '</td>';
        echo '<td>' . (float) ($item['cantidad'] ?? 0) . '</td>';
        echo '<td>' . h((string) ($item['unidad'] ?? 'unidad')) . '</td>';
        echo '<td>' . money((float) ($item['costo_unitario'] ?? 0)) . '</td>';
        echo '<td>' . money((float) ($item['subtotal'] ?? 0)) . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
    echo '</section>';
}

if (($_GET['export'] ?? '') === 'csv') {
    $exportId = isset($_GET['id']) ? (int) $_GET['id'] : null;
    export_presupuestos_csv($presupuestos, $clientesById, $exportId);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? 'create');

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $presupuestos = array_values(array_filter($presupuestos, static function (array $presupuesto) use ($id): bool {
            return (int) ($presupuesto['id'] ?? 0) !== $id;
        }));
        write_json(data_file('presupuestos'), $presupuestos);
        redirect_with_message('presupuesto_nuevo.php', 'Presupuesto eliminado correctamente.');
    }

    if ($action === 'edit') {
        $id = (int) ($_POST['id'] ?? 0);
        $clienteId = (int) ($_POST['cliente_id'] ?? 0);
        $detalle = trim((string) ($_POST['detalle'] ?? ''));
        $manoObra = (float) ($_POST['mano_obra'] ?? 0);
        $margen = (float) ($_POST['margen'] ?? 0);
        $estado = trim((string) ($_POST['estado'] ?? 'borrador'));
        $impuesto = (float) ($config['impuesto'] ?? 0);

        if ($id <= 0 || $clienteId <= 0) {
            redirect_with_message('presupuesto_nuevo.php', 'Faltan datos obligatorios para editar el presupuesto.');
        }

        foreach ($presupuestos as &$presupuesto) {
            if ((int) ($presupuesto['id'] ?? 0) !== $id) {
                continue;
            }

            $materiales = (float) ($presupuesto['materiales'] ?? 0);
            $subtotal = $manoObra + $materiales;
            $recargo = $subtotal * ($margen / 100);
            $base = $subtotal + $recargo;
            $impuestos = $base * $impuesto;
            $total = $base + $impuestos;

            $presupuesto['cliente_id'] = $clienteId;
            $presupuesto['detalle'] = $detalle;
            $presupuesto['mano_obra'] = $manoObra;
            $presupuesto['margen'] = $margen;
            $presupuesto['impuesto'] = $impuesto;
            $presupuesto['estado'] = $estado === '' ? 'borrador' : $estado;
            $presupuesto['total'] = round($total, 2);
            break;
        }
        unset($presupuesto);

        write_json(data_file('presupuestos'), $presupuestos);
        redirect_with_message('presupuesto_nuevo.php', 'Presupuesto actualizado correctamente.');
    }

    $clienteId = (int) ($_POST['cliente_id'] ?? 0);
    $detalle = trim((string) ($_POST['detalle'] ?? ''));
    $manoObra = (float) ($_POST['mano_obra'] ?? 0);
    $margen = (float) ($_POST['margen'] ?? 0);
    $impuesto = (float) ($config['impuesto'] ?? 0);

    if ($clienteId <= 0) {
        redirect_with_message('presupuesto_nuevo.php', 'Debe seleccionar un cliente.');
    }

    $insumoIds = $_POST['insumo_id'] ?? [];
    $insumoCategorias = $_POST['insumo_categoria'] ?? [];
    $insumoNuevos = $_POST['insumo_nombre_nuevo'] ?? [];
    $cantidades = $_POST['cantidad'] ?? [];
    $costos = $_POST['costo_unitario'] ?? [];

    $insumosById = [];
    foreach ($insumos as $item) {
        $insumosById[(int) ($item['id'] ?? 0)] = $item;
    }

    $materiales = 0.0;
    $insumosEstimados = [];

    foreach ($insumoIds as $i => $insumoIdRaw) {
        $insumoId = (int) $insumoIdRaw;
        $categoriaInsumo = trim((string) ($insumoCategorias[$i] ?? 'otros'));
        if (!in_array($categoriaInsumo, $categoriasInsumo, true)) {
            $categoriaInsumo = 'otros';
        }
        $nombreNuevo = trim((string) ($insumoNuevos[$i] ?? ''));
        $cantidad = (float) ($cantidades[$i] ?? 0);
        $costoUnitario = (float) ($costos[$i] ?? 0);

        if ($insumoId <= 0 && $nombreNuevo !== '') {
            $exists = find_insumo_by_name($insumos, $nombreNuevo);
            if ($exists !== null) {
                $insumoId = (int) ($exists['id'] ?? 0);
            } else {
                $nuevo = [
                    'id' => next_id($insumos),
                    'nombre' => $nombreNuevo,
                    'categoria' => $categoriaInsumo,
                    'unidad' => 'unidad',
                    'precio' => $costoUnitario,
                    'stock' => 0,
                    'stock_minimo' => 0,
                ];
                $insumos[] = $nuevo;
                $insumosById[(int) $nuevo['id']] = $nuevo;
                $insumoId = (int) $nuevo['id'];
            }
        }

        if ($insumoId <= 0 || $cantidad <= 0 || $costoUnitario < 0) {
            continue;
        }

        if (!isset($insumosById[$insumoId])) {
            continue;
        }

        $insumo = $insumosById[$insumoId];
        if ($costoUnitario <= 0) {
            $costoUnitario = (float) ($insumo['precio'] ?? 0);
        }
        $subtotal = $cantidad * $costoUnitario;
        $materiales += $subtotal;

        $insumosEstimados[] = [
            'insumo_id' => $insumoId,
            'nombre' => (string) ($insumo['nombre'] ?? 'Insumo'),
            'categoria' => (string) ($insumo['categoria'] ?? 'otros'),
            'unidad' => (string) ($insumo['unidad'] ?? 'unidad'),
            'cantidad' => $cantidad,
            'costo_unitario' => round($costoUnitario, 2),
            'subtotal' => round($subtotal, 2),
        ];
    }

    write_json(data_file('insumos'), $insumos);

    $subtotal = $manoObra + $materiales;
    $recargo = $subtotal * ($margen / 100);
    $base = $subtotal + $recargo;
    $impuestos = $base * $impuesto;
    $total = $base + $impuestos;

    $presupuestos[] = [
        'id' => next_id($presupuestos),
        'cliente_id' => $clienteId,
        'detalle' => $detalle,
        'mano_obra' => $manoObra,
        'materiales' => round($materiales, 2),
        'insumos_estimados' => $insumosEstimados,
        'margen' => $margen,
        'impuesto' => $impuesto,
        'total' => round($total, 2),
        'estado' => 'borrador',
        'fecha' => date('Y-m-d'),
    ];

    write_json(data_file('presupuestos'), $presupuestos);
    redirect_with_message('presupuesto_nuevo.php', 'Presupuesto creado en estado borrador.');
}

$verPresupuestoId = (int) ($_GET['ver'] ?? 0);
$presupuestoDetalle = $verPresupuestoId > 0 ? find_presupuesto_by_id($presupuestos, $verPresupuestoId) : null;
$soloDetalle = (($_GET['solo_detalle'] ?? '') === '1');

$filtroCliente = (int) ($_GET['filtro_cliente'] ?? 0);
$filtroEstado = trim((string) ($_GET['filtro_estado'] ?? ''));
$orden = trim((string) ($_GET['orden'] ?? 'fecha'));
$direccion = trim((string) ($_GET['dir'] ?? 'desc'));
$allowedSorts = ['fecha', 'cliente', 'estado'];
if (!in_array($orden, $allowedSorts, true)) {
    $orden = 'fecha';
}
if ($direccion !== 'asc' && $direccion !== 'desc') {
    $direccion = 'desc';
}
if ($direccion !== 'asc' && $direccion !== 'desc') {
    $direccion = 'desc';
}
if ($direccion !== 'asc' && $direccion !== 'desc') {
    $direccion = 'desc';
}

$presupuestosListado = array_values(array_filter($presupuestos, static function (array $presupuesto) use ($filtroCliente, $filtroEstado): bool {
    if ($filtroCliente > 0 && (int) ($presupuesto['cliente_id'] ?? 0) !== $filtroCliente) {
        return false;
    }
    if ($filtroEstado !== '' && (string) ($presupuesto['estado'] ?? '') !== $filtroEstado) {
        return false;
    }

    return true;
}));

usort($presupuestosListado, static function (array $a, array $b) use ($orden, $direccion, $clientesById): int {
    if ($orden === 'cliente') {
        $valA = strtolower((string) ($clientesById[(int) ($a['cliente_id'] ?? 0)] ?? ''));
        $valB = strtolower((string) ($clientesById[(int) ($b['cliente_id'] ?? 0)] ?? ''));
    } elseif ($orden === 'estado') {
        $valA = strtolower((string) ($a['estado'] ?? ''));
        $valB = strtolower((string) ($b['estado'] ?? ''));
    } else {
        $valA = (string) ($a['fecha'] ?? '');
        $valB = (string) ($b['fecha'] ?? '');
    }

    $result = $valA <=> $valB;
    return $direccion === 'asc' ? $result : -$result;
});

$presupuestosListado = array_values(array_filter($presupuestos, static function (array $presupuesto) use ($filtroCliente, $filtroEstado): bool {
    if ($filtroCliente > 0 && (int) ($presupuesto['cliente_id'] ?? 0) !== $filtroCliente) {
        return false;
    }
    if ($filtroEstado !== '' && (string) ($presupuesto['estado'] ?? '') !== $filtroEstado) {
        return false;
    }

    return true;
}));

usort($presupuestosListado, static function (array $a, array $b) use ($orden, $direccion, $clientesById): int {
    if ($orden === 'cliente') {
        $valA = strtolower((string) ($clientesById[(int) ($a['cliente_id'] ?? 0)] ?? ''));
        $valB = strtolower((string) ($clientesById[(int) ($b['cliente_id'] ?? 0)] ?? ''));
    } elseif ($orden === 'estado') {
        $valA = strtolower((string) ($a['estado'] ?? ''));
        $valB = strtolower((string) ($b['estado'] ?? ''));
    } else {
        $valA = (string) ($a['fecha'] ?? '');
        $valB = (string) ($b['fecha'] ?? '');
    }

    $result = $valA <=> $valB;
    return $direccion === 'asc' ? $result : -$result;
});

$presupuestosListado = array_values(array_filter($presupuestos, static function (array $presupuesto) use ($filtroCliente, $filtroEstado): bool {
    if ($filtroCliente > 0 && (int) ($presupuesto['cliente_id'] ?? 0) !== $filtroCliente) {
        return false;
    }
    if ($filtroEstado !== '' && (string) ($presupuesto['estado'] ?? '') !== $filtroEstado) {
        return false;
    }

    return true;
}));

usort($presupuestosListado, static function (array $a, array $b) use ($orden, $direccion, $clientesById): int {
    if ($orden === 'cliente') {
        $valA = strtolower((string) ($clientesById[(int) ($a['cliente_id'] ?? 0)] ?? ''));
        $valB = strtolower((string) ($clientesById[(int) ($b['cliente_id'] ?? 0)] ?? ''));
    } elseif ($orden === 'estado') {
        $valA = strtolower((string) ($a['estado'] ?? ''));
        $valB = strtolower((string) ($b['estado'] ?? ''));
    } else {
        $valA = (string) ($a['fecha'] ?? '');
        $valB = (string) ($b['fecha'] ?? '');
    }

    $result = $valA <=> $valB;
    return $direccion === 'asc' ? $result : -$result;
});

render_page_start('Presupuestos');

if ($soloDetalle) {
    if ($presupuestoDetalle === null) {
        echo '<p class="flash">No se encontró el presupuesto solicitado.</p>';
    } else {
        render_presupuesto_detalle($presupuestoDetalle, $clientesById, true);
    }
    render_page_end();
    exit;
}
?>
<form method="post" class="form-grid">
  <label>Cliente
    <select name="cliente_id" required>
      <option value="">Seleccionar...</option>
      <?php foreach ($clientes as $cliente): ?>
        <option value="<?= (int) $cliente['id'] ?>"><?= h((string) $cliente['nombre']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>

  <label>Detalle
    <input type="text" name="detalle" placeholder="Ej: Retapizado de sillón 2 cuerpos">
  </label>

  <label>Mano de obra
    <input type="number" step="0.01" name="mano_obra" required>
  </label>

  <label>Margen (%)
    <input type="number" step="0.01" name="margen" value="30" required>
  </label>

  <fieldset style="grid-column: 1 / -1;">
    <legend>Estimación de insumos</legend>
    <p class="muted">3 renglones iniciales. Botón + para agregar y X para eliminar.</p>
    <div class="insumo-row insumo-row-head">
      <strong>Categoría</strong>
      <strong>Insumo existente</strong>
      <strong>Cantidad</strong>
      <strong>Costo unitario</strong>
      <span></span>
    </div>
    <div id="insumos-items"></div>
    <div class="inline-actions">
      <button type="button" id="agregar-insumo" class="secondary-btn">+ Agregar insumo</button>
      <button type="button" id="abrir-asistente-insumo" class="secondary-btn assistant-btn">Asistente de insumos</button>
      <a href="presupuesto_nuevo.php?export=csv" class="secondary-btn excel-btn action-link">Exportar presupuestos (Excel)</a>
    </div>
  </fieldset>

  <div><button type="submit">Crear presupuesto</button></div>
</form>

<form method="get" class="form-grid card" style="margin-top:12px;">
  <label>Filtrar por cliente
    <select name="filtro_cliente">
      <option value="0">Todos</option>
      <?php foreach ($clientes as $cliente): ?>
        <option value="<?= (int) $cliente['id'] ?>" <?= (int) $cliente['id'] === $filtroCliente ? 'selected' : '' ?>><?= h((string) $cliente['nombre']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Filtrar por estado
    <select name="filtro_estado">
      <option value="">Todos</option>
      <option value="borrador" <?= $filtroEstado === 'borrador' ? 'selected' : '' ?>>Borrador</option>
      <option value="enviado" <?= $filtroEstado === 'enviado' ? 'selected' : '' ?>>Enviado</option>
      <option value="aprobado" <?= $filtroEstado === 'aprobado' ? 'selected' : '' ?>>Aprobado</option>
      <option value="rechazado" <?= $filtroEstado === 'rechazado' ? 'selected' : '' ?>>Rechazado</option>
    </select>
  </label>
  <label>Ordenar por
    <select name="orden">
      <option value="fecha" <?= $orden === 'fecha' ? 'selected' : '' ?>>Fecha</option>
      <option value="cliente" <?= $orden === 'cliente' ? 'selected' : '' ?>>Cliente</option>
      <option value="estado" <?= $orden === 'estado' ? 'selected' : '' ?>>Estado</option>
    </select>
  </label>
  <label>Dirección
    <select name="dir">
      <option value="desc" <?= $direccion === 'desc' ? 'selected' : '' ?>>Descendente</option>
      <option value="asc" <?= $direccion === 'asc' ? 'selected' : '' ?>>Ascendente</option>
    </select>
  </label>
  <div class="inline-actions">
    <button type="submit" class="secondary-btn">Aplicar</button>
    <a href="presupuesto_nuevo.php" class="secondary-btn action-link">Limpiar</a>
  </div>
</form>

<section class="presupuestos-cards">
<?php foreach ($presupuestosListado as $presupuesto): ?>
  <article class="card presupuesto-card">
    <form method="post" class="presupuesto-card-form">
      <input type="hidden" name="id" value="<?= (int) ($presupuesto['id'] ?? 0) ?>">
      <div class="presupuesto-card-head">
        <strong>#<?= (int) ($presupuesto['id'] ?? 0) ?></strong>
        <span class="muted"><?= h((string) ($presupuesto['fecha'] ?? '')) ?></span>
      </div>

      <div class="presupuesto-card-grid">
        <label>Cliente
          <select name="cliente_id" required>
            <option value="">Seleccionar...</option>
            <?php foreach ($clientes as $cliente): ?>
              <option value="<?= (int) $cliente['id'] ?>" <?= (int) $cliente['id'] === (int) ($presupuesto['cliente_id'] ?? 0) ? 'selected' : '' ?>>
                <?= h((string) $cliente['nombre']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>

        <label>Estado
          <select name="estado">
            <option value="borrador" <?= ($presupuesto['estado'] ?? 'borrador') === 'borrador' ? 'selected' : '' ?>>Borrador</option>
            <option value="enviado" <?= ($presupuesto['estado'] ?? 'borrador') === 'enviado' ? 'selected' : '' ?>>Enviado</option>
            <option value="aprobado" <?= ($presupuesto['estado'] ?? 'borrador') === 'aprobado' ? 'selected' : '' ?>>Aprobado</option>
            <option value="rechazado" <?= ($presupuesto['estado'] ?? 'borrador') === 'rechazado' ? 'selected' : '' ?>>Rechazado</option>
          </select>
        </label>

        <label>Mano de obra
          <input type="number" step="0.01" min="0" name="mano_obra" value="<?= (float) ($presupuesto['mano_obra'] ?? 0) ?>">
        </label>

        <label>Margen %
          <input type="number" step="0.01" min="0" name="margen" value="<?= (float) ($presupuesto['margen'] ?? 0) ?>">
        </label>

        <label class="wide">Detalle
          <input type="text" name="detalle" value="<?= h((string) ($presupuesto['detalle'] ?? '')) ?>">
        </label>

        <div>
          <small class="muted">Materiales</small>
          <div><strong><?= money((float) ($presupuesto['materiales'] ?? 0)) ?></strong></div>
        </div>

        <div>
          <small class="muted">Total</small>
          <div><strong><?= money((float) ($presupuesto['total'] ?? 0)) ?></strong></div>
        </div>
      </div>

      <div class="actions-wrap presupuesto-card-actions">
        <a href="presupuesto_nuevo.php?ver=<?= (int) ($presupuesto['id'] ?? 0) ?>" class="secondary-btn info-btn action-link">Detalle</a>
        <a href="presupuesto_nuevo.php?ver=<?= (int) ($presupuesto['id'] ?? 0) ?>&solo_detalle=1" class="secondary-btn action-link" target="_blank" rel="noopener">Imprimir</a>
        <a href="presupuesto_nuevo.php?export=csv&id=<?= (int) ($presupuesto['id'] ?? 0) ?>" class="secondary-btn excel-btn action-link">Excel</a>
        <button type="submit" name="action" value="edit" class="secondary-btn">Guardar</button>
        <button type="submit" name="action" value="delete" class="danger-btn" onclick="return confirm('¿Eliminar presupuesto?');">Borrar</button>
      </div>
    </form>
  </article>
<?php endforeach; ?>
</section>

<?php if ($presupuestoDetalle !== null): ?>
  <?php render_presupuesto_detalle($presupuestoDetalle, $clientesById); ?>
<?php endif; ?>

<template id="insumo-item-template">
  <div class="insumo-row insumo-item">
    <select name="insumo_categoria[]" class="insumo-categoria">
      <option value="todas" selected>Todas</option>
      <?php foreach ($categoriasInsumo as $categoria): ?>
        <option value="<?= h($categoria) ?>"><?= h(ucfirst($categoria)) ?></option>
      <?php endforeach; ?>
    </select>
    <div>
      <select name="insumo_id[]" class="insumo-select">
        <option value="">Seleccionar...</option>
        <option value="__new__">+ Cargar insumo nuevo...</option>
        <?php foreach ($insumos as $insumo): ?>
          <option
            value="<?= (int) $insumo['id'] ?>"
            data-categoria="<?= h((string) ($insumo['categoria'] ?? 'otros')) ?>"
            data-precio="<?= (float) ($insumo['precio'] ?? 0) ?>"
          ><?= h((string) $insumo['nombre']) ?> (<?= h((string) ($insumo['unidad'] ?? 'unidad')) ?>)</option>
        <?php endforeach; ?>
      </select>
      <input type="hidden" name="insumo_nombre_nuevo[]" class="insumo-nuevo-hidden" value="">
      <small class="muted insumo-nuevo-label"></small>
    </div>

    <input type="number" step="0.01" min="0" name="cantidad[]" value="0" style="width:100%;">
    <input type="number" step="0.01" min="0" name="costo_unitario[]" value="0" style="width:100%;">
    <button type="button" class="danger-btn insumo-remove remove-insumo" aria-label="Eliminar insumo" style="width:30px;height:30px;padding:0;line-height:1;justify-self:center;">X</button>
  </div>
</template>

<dialog id="nuevo-insumo-modal" style="border:1px solid #d1d5db;border-radius:8px;max-width:420px;width:92%;padding:14px;">
  <form method="dialog" id="nuevo-insumo-form">
    <h3 style="margin-top:0;">Nuevo insumo</h3>
    <label>Nombre
      <input type="text" id="nuevo-insumo-nombre" placeholder="Ej: Cinta elástica" required>
    </label>
    <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:10px;">
      <button type="button" id="cancelar-nuevo-insumo" class="secondary-btn" style="margin-top:0;">Cancelar</button>
      <button type="submit">Guardar</button>
    </div>
  </form>
</dialog>

<dialog id="asistente-insumo-modal" style="border:1px solid #d1d5db;border-radius:8px;max-width:740px;width:95%;padding:14px;">
  <form method="dialog" id="asistente-insumo-form" class="dialog-form">
    <h3 style="margin:0;">Asistente de insumos</h3>
    <p class="muted" style="margin:0;">Primero definí el mueble y sus módulos. Después calculá cada tipo de insumo usando el checklist de piezas.</p>

    <div class="form-grid">
      <label>Tipo de mueble
        <select id="asistente-tipo-mueble">
          <option value="sillon_2_cuerpos">Sillón 2 cuerpos</option>
          <option value="sillon_3_cuerpos">Sillón 3 cuerpos</option>
          <option value="otomana">Otomana</option>
          <option value="silla">Silla / Butaca</option>
          <option value="personalizado">Personalizado</option>
        </select>
      </label>
    </div>

    <fieldset id="asistente-modulos-box">
      <legend>Módulos del mueble</legend>
      <p class="muted">Activá solo los módulos que aplican y cargá medidas. Ej: una otomana no usa respaldo.</p>
      <div class="piece-grid-head assistant-module-head" style="grid-template-columns:70px 120px repeat(4,minmax(80px,1fr)) 36px;">
        <strong>Activo</strong>
        <strong>Módulo</strong>
        <strong>Ancho</strong>
        <strong>Alto</strong>
        <strong>Prof.</strong>
        <strong>Cant.</strong>
        <span></span>
      </div>
      <div id="asistente-modulos"></div>
      <div class="inline-actions">
        <button type="button" id="agregar-modulo-asistente" class="secondary-btn">+ Agregar módulo</button>
        <button type="button" id="generar-piezas-modulos" class="secondary-btn">Generar checklist de piezas</button>
      </div>
    </fieldset>

    <fieldset>
      <legend>Checklist de piezas</legend>
      <div class="piece-grid-head assistant-piece-head" style="grid-template-columns:120px 130px repeat(3,minmax(70px,1fr)) 70px 36px;">
        <strong>Módulo</strong>
        <strong>Pieza</strong>
        <strong>Alto</strong>
        <strong>Ancho</strong>
        <strong>Cant.</strong>
        <strong>Usar</strong>
        <span></span>
      </div>
      <div id="asistente-piezas"></div>
      <button type="button" id="agregar-pieza-asistente" class="secondary-btn">+ Agregar pieza manual</button>
    </fieldset>

    <fieldset>
      <legend>Insumo a calcular</legend>
      <div class="form-grid">
        <label>Tipo de insumo
          <select id="asistente-tipo-insumo">
            <option value="tela">Tela</option>
            <option value="gomaespuma">Gomaespuma</option>
            <option value="fleje">Fleje</option>
            <option value="cierre">Cierre</option>
          </select>
        </label>

        <label>Insumo existente (opcional)
          <select id="asistente-insumo-id">
            <option value="">Seleccionar...</option>
            <?php foreach ($insumos as $insumo): ?>
              <option
                value="<?= (int) $insumo['id'] ?>"
                data-categoria="<?= h((string) ($insumo['categoria'] ?? 'otros')) ?>"
                data-precio="<?= (float) ($insumo['precio'] ?? 0) ?>"
              ><?= h((string) $insumo['nombre']) ?> (<?= h((string) ($insumo['unidad'] ?? 'unidad')) ?>)</option>
            <?php endforeach; ?>
          </select>
        </label>

        <label>Nombre de insumo nuevo (opcional)
          <input type="text" id="asistente-insumo-nuevo" placeholder="Ej: Tela chenille beige">
        </label>

        <label>Costo unitario sugerido
          <input type="number" id="asistente-costo-unitario" min="0" step="0.01" value="0">
        </label>
      </div>
    </fieldset>

    <fieldset>
      <legend>Parámetros por tipo</legend>
      <div class="form-grid">
        <label data-param-for="tela">Ancho de tela (cm)
          <input type="number" id="asistente-tela-ancho" min="1" step="0.01" value="140">
        </label>
        <label data-param-for="tela gomaespuma">Desperdicio (%)
          <input type="number" id="asistente-desperdicio" min="0" step="0.01" value="10">
        </label>

        <label data-param-for="gomaespuma">Ancho de plancha (cm)
          <input type="number" id="asistente-plancha-ancho" min="1" step="0.01" value="200">
        </label>
        <label data-param-for="gomaespuma">Largo de plancha (cm)
          <input type="number" id="asistente-plancha-largo" min="1" step="0.01" value="100">
        </label>

        <label data-param-for="fleje">Separación entre flejes (cm)
          <input type="number" id="asistente-fleje-separacion" min="1" step="0.01" value="8">
        </label>

        <label data-param-for="cierre">Margen por cierre (cm)
          <input type="number" id="asistente-cierre-margen" min="0" step="0.01" value="5">
        </label>
      </div>
    </fieldset>

    <div id="asistente-resultado" class="assistant-result"></div>

    <div class="dialog-actions">
      <button type="button" id="cancelar-asistente-insumo" class="secondary-btn" style="margin-top:0;">Cancelar</button>
      <button type="button" id="calcular-asistente-insumo" class="secondary-btn" style="margin-top:0;">Calcular</button>
      <button type="submit">Agregar renglón</button>
    </div>
  </form>
</dialog>

<script>
(function () {
  var container = document.getElementById('insumos-items');
  var addButton = document.getElementById('agregar-insumo');
  var assistantButton = document.getElementById('abrir-asistente-insumo');
  var template = document.getElementById('insumo-item-template');
  var modal = document.getElementById('nuevo-insumo-modal');
  var modalForm = document.getElementById('nuevo-insumo-form');
  var modalInput = document.getElementById('nuevo-insumo-nombre');
  var modalCancel = document.getElementById('cancelar-nuevo-insumo');
  var assistantModal = document.getElementById('asistente-insumo-modal');
  var assistantForm = document.getElementById('asistente-insumo-form');
  var assistantFurnitureType = document.getElementById('asistente-tipo-mueble');
  var assistantType = document.getElementById('asistente-tipo-insumo');
  var assistantInsumoId = document.getElementById('asistente-insumo-id');
  var assistantInsumoNuevo = document.getElementById('asistente-insumo-nuevo');
  var assistantCosto = document.getElementById('asistente-costo-unitario');
  var assistantPieces = document.getElementById('asistente-piezas');
  var assistantAddPiece = document.getElementById('agregar-pieza-asistente');
  var assistantModules = document.getElementById('asistente-modulos');
  var assistantAddModule = document.getElementById('agregar-modulo-asistente');
  var assistantGenerateFromModules = document.getElementById('generar-piezas-modulos');
  var assistantCalculate = document.getElementById('calcular-asistente-insumo');
  var assistantCancel = document.getElementById('cancelar-asistente-insumo');
  var assistantResult = document.getElementById('asistente-resultado');
  var pendingRow = null;
  var lastAssistantResult = null;

  if (!container || !addButton || !assistantButton || !template || !assistantFurnitureType) {
    return;
  }

  function addItem() {
    var node = template.content.cloneNode(true);
    container.appendChild(node);
    var row = container.lastElementChild;
    applyCategoryFilter(row);
    return row;
  }

  function applyInsumoSelectionToRow(row, insumoId, insumoNuevo, categoria) {
    var hiddenInput = row.querySelector('.insumo-nuevo-hidden');
    var label = row.querySelector('.insumo-nuevo-label');
    var select = row.querySelector('.insumo-select');
    var categoriaSelect = row.querySelector('.insumo-categoria');
    if (!hiddenInput || !label || !select) {
      return;
    }

    if (categoriaSelect && categoria) {
      categoriaSelect.value = categoria;
      applyCategoryFilter(row);
    }

    if (insumoId !== '') {
      select.value = insumoId;
      hiddenInput.value = '';
      label.textContent = '';
      return;
    }

    if (insumoNuevo !== '') {
      hiddenInput.value = insumoNuevo;
      label.textContent = 'Nuevo: ' + insumoNuevo;
      select.value = '';
      return;
    }

    select.value = '';
    hiddenInput.value = '';
    label.textContent = '';
  }

  function applyCategoryFilter(row) {
    var categoriaSelect = row.querySelector('.insumo-categoria');
    var insumoSelect = row.querySelector('.insumo-select');
    if (!categoriaSelect || !insumoSelect) {
      return;
    }

    var categoria = categoriaSelect.value || 'todas';
    Array.prototype.slice.call(insumoSelect.options).forEach(function (option) {
      if (option.value === '' || option.value === '__new__') {
        option.hidden = false;
        return;
      }
      var optionCategory = option.getAttribute('data-categoria') || 'otros';
      option.hidden = categoria !== 'todas' && optionCategory !== categoria;
    });

    if (insumoSelect.value !== '' && insumoSelect.value !== '__new__') {
      var selected = insumoSelect.options[insumoSelect.selectedIndex];
      if (selected && selected.hidden) {
        insumoSelect.value = '';
      }
    }
  }

  function parseNumber(input, defaultValue) {
    var value = parseFloat(input);
    return Number.isFinite(value) ? value : defaultValue;
  }

  function addAssistantPiece(values) {
    var row = document.createElement('div');
    row.className = 'piece-grid-row';
    row.style.gridTemplateColumns = '120px 130px repeat(3,minmax(70px,1fr)) 70px 36px';
    row.innerHTML = ''
      + '<input type="text" class="pieza-modulo" value="' + (values && values.modulo ? values.modulo : 'manual') + '">'
      + '<input type="text" class="pieza-nombre" value="' + (values && values.nombre ? values.nombre : 'pieza') + '">'
      + '<input type="number" class="pieza-alto" min="0" step="0.01" value="' + (values && values.alto ? values.alto : 0) + '">'
      + '<input type="number" class="pieza-ancho" min="0" step="0.01" value="' + (values && values.ancho ? values.ancho : 0) + '">'
      + '<input type="number" class="pieza-cantidad" min="1" step="1" value="' + (values && values.cantidad ? values.cantidad : 1) + '">'
      + '<label style="display:flex;justify-content:center;"><input type="checkbox" class="pieza-usar" ' + (values && values.usar === false ? '' : 'checked') + '></label>'
      + '<button type="button" class="danger-btn remove-pieza" style="width:30px;height:30px;padding:0;">X</button>';
    assistantPieces.appendChild(row);
  }

  function addAssistantModule(values) {
    var row = document.createElement('div');
    row.className = 'piece-grid-row module-row';
    row.style.gridTemplateColumns = '70px 120px repeat(4,minmax(80px,1fr)) 36px';
    row.innerHTML = ''
      + '<label style="display:flex;justify-content:center;"><input type="checkbox" class="modulo-activo" ' + (values && values.activo === false ? '' : 'checked') + '></label>'
      + '<select class="modulo-tipo">'
      + '  <option value="asiento">Asiento</option>'
      + '  <option value="respaldo">Respaldo</option>'
      + '  <option value="apoyabrazos">Apoyabrazos</option>'
      + '  <option value="almohadon">Almohadón</option>'
      + '</select>'
      + '<input type="number" class="modulo-ancho" min="0" step="0.01" value="' + (values && values.ancho ? values.ancho : 0) + '">'
      + '<input type="number" class="modulo-alto" min="0" step="0.01" value="' + (values && values.alto ? values.alto : 0) + '">'
      + '<input type="number" class="modulo-profundidad" min="0" step="0.01" value="' + (values && values.profundidad ? values.profundidad : 0) + '">'
      + '<input type="number" class="modulo-cantidad" min="1" step="1" value="' + (values && values.cantidad ? values.cantidad : 1) + '">'
      + '<button type="button" class="danger-btn remove-modulo" style="width:30px;height:30px;padding:0;">X</button>';
    assistantModules.appendChild(row);
    if (values && values.tipo) {
      row.querySelector('.modulo-tipo').value = values.tipo;
    }
  }

  function getFurnitureModules(type) {
    if (type === 'sillon_3_cuerpos') {
      return [
        { tipo: 'asiento', ancho: 180, alto: 18, profundidad: 60, cantidad: 1, activo: true },
        { tipo: 'respaldo', ancho: 180, alto: 55, profundidad: 18, cantidad: 1, activo: true },
        { tipo: 'apoyabrazos', ancho: 60, alto: 55, profundidad: 80, cantidad: 2, activo: true }
      ];
    }
    if (type === 'otomana') {
      return [
        { tipo: 'asiento', ancho: 70, alto: 18, profundidad: 70, cantidad: 1, activo: true },
        { tipo: 'respaldo', ancho: 70, alto: 40, profundidad: 15, cantidad: 1, activo: false },
        { tipo: 'apoyabrazos', ancho: 70, alto: 40, profundidad: 18, cantidad: 2, activo: false }
      ];
    }
    if (type === 'silla') {
      return [
        { tipo: 'asiento', ancho: 45, alto: 10, profundidad: 45, cantidad: 1, activo: true },
        { tipo: 'respaldo', ancho: 45, alto: 50, profundidad: 8, cantidad: 1, activo: true },
        { tipo: 'apoyabrazos', ancho: 45, alto: 40, profundidad: 8, cantidad: 2, activo: false }
      ];
    }
    if (type === 'personalizado') {
      return [{ tipo: 'asiento', ancho: 0, alto: 0, profundidad: 0, cantidad: 1, activo: true }];
    }
    return [
      { tipo: 'asiento', ancho: 140, alto: 18, profundidad: 60, cantidad: 1, activo: true },
      { tipo: 'respaldo', ancho: 140, alto: 55, profundidad: 18, cantidad: 1, activo: true },
      { tipo: 'apoyabrazos', ancho: 60, alto: 55, profundidad: 80, cantidad: 2, activo: true }
    ];
  }

  function loadModulesByFurniture() {
    assistantModules.innerHTML = '';
    getFurnitureModules(assistantFurnitureType.value).forEach(function (module) {
      addAssistantModule(module);
    });
  }

  function moduleToPieces(module) {
    var pieces = [];
    var repeat = module.cantidad;
    if (module.tipo === 'asiento') {
      pieces.push({ modulo: 'asiento', nombre: 'tapa', alto: module.profundidad, ancho: module.ancho, cantidad: 2 * repeat });
      pieces.push({ modulo: 'asiento', nombre: 'frente_fondo', alto: module.alto, ancho: module.ancho, cantidad: 2 * repeat });
      pieces.push({ modulo: 'asiento', nombre: 'laterales', alto: module.alto, ancho: module.profundidad, cantidad: 2 * repeat });
    } else if (module.tipo === 'respaldo') {
      pieces.push({ modulo: 'respaldo', nombre: 'frente_dorso', alto: module.alto, ancho: module.ancho, cantidad: 2 * repeat });
      pieces.push({ modulo: 'respaldo', nombre: 'laterales', alto: module.alto, ancho: module.profundidad, cantidad: 2 * repeat });
      pieces.push({ modulo: 'respaldo', nombre: 'tapa', alto: module.profundidad, ancho: module.ancho, cantidad: 1 * repeat });
    } else if (module.tipo === 'apoyabrazos') {
      pieces.push({ modulo: 'apoyabrazos', nombre: 'caras', alto: module.profundidad, ancho: module.ancho, cantidad: 2 * repeat });
      pieces.push({ modulo: 'apoyabrazos', nombre: 'frente_fondo', alto: module.alto, ancho: module.profundidad, cantidad: 2 * repeat });
      pieces.push({ modulo: 'apoyabrazos', nombre: 'tapa', alto: module.alto, ancho: module.ancho, cantidad: 1 * repeat });
    } else if (module.tipo === 'almohadon') {
      pieces.push({ modulo: 'almohadon', nombre: 'caras', alto: module.alto, ancho: module.ancho, cantidad: 2 * repeat });
      if (module.profundidad > 0) {
        pieces.push({ modulo: 'almohadon', nombre: 'fuelle', alto: (module.alto * 2) + (module.ancho * 2), ancho: module.profundidad, cantidad: 1 * repeat });
      }
    }
    return pieces;
  }

  function updateAssistantTypeFields() {
    var type = assistantType.value;
    document.querySelectorAll('[data-param-for]').forEach(function (node) {
      var modes = node.getAttribute('data-param-for').split(/\s+/);
      node.style.display = modes.indexOf(type) >= 0 ? '' : 'none';
    });
  }

  function readPieces() {
    var rows = Array.prototype.slice.call(assistantPieces.querySelectorAll('.piece-grid-row'));
    var result = [];
    rows.forEach(function (row) {
      var usar = row.querySelector('.pieza-usar').checked;
      var alto = parseNumber(row.querySelector('.pieza-alto').value, 0);
      var ancho = parseNumber(row.querySelector('.pieza-ancho').value, 0);
      var cantidad = Math.max(1, Math.round(parseNumber(row.querySelector('.pieza-cantidad').value, 1)));
      if (!usar || alto <= 0 || ancho <= 0 || cantidad <= 0) {
        return;
      }
      result.push({
        modulo: row.querySelector('.pieza-modulo').value || 'modulo',
        nombre: row.querySelector('.pieza-nombre').value || 'pieza',
        alto: alto,
        ancho: ancho,
        cantidad: cantidad
      });
    });
    return result;
  }

  function expandPieces(pieces) {
    var expanded = [];
    pieces.forEach(function (piece) {
      for (var i = 0; i < piece.cantidad; i += 1) {
        expanded.push({ alto: piece.alto, ancho: piece.ancho });
      }
    });
    return expanded;
  }

  function computeTela(pieces) {
    var width = parseNumber(document.getElementById('asistente-tela-ancho').value, 140);
    var waste = Math.max(0, parseNumber(document.getElementById('asistente-desperdicio').value, 0));
    var expanded = expandPieces(pieces).sort(function (a, b) {
      return b.ancho - a.ancho;
    });
    if (expanded.length === 0) {
      return null;
    }
    var rows = [];
    expanded.forEach(function (piece) {
      var placed = false;
      for (var i = 0; i < rows.length; i += 1) {
        if (rows[i].used + piece.ancho <= width) {
          rows[i].used += piece.ancho;
          rows[i].height = Math.max(rows[i].height, piece.alto);
          placed = true;
          break;
        }
      }
      if (!placed) {
        rows.push({ used: piece.ancho, height: piece.alto });
      }
    });
    var largoCm = rows.reduce(function (acc, row) {
      return acc + row.height;
    }, 0);
    var totalM = (largoCm / 100) * (1 + (waste / 100));
    return {
      cantidad: Math.round(totalM * 100) / 100,
      detalle: 'Largo estimado: ' + (Math.round(largoCm) / 100).toFixed(2) + ' m. Filas usadas: ' + rows.length + '.',
      unidad: 'm'
    };
  }

  function computeGomaespuma(pieces) {
    var boardWidth = parseNumber(document.getElementById('asistente-plancha-ancho').value, 200);
    var boardLength = parseNumber(document.getElementById('asistente-plancha-largo').value, 100);
    var waste = Math.max(0, parseNumber(document.getElementById('asistente-desperdicio').value, 0));
    var areaBoards = boardWidth * boardLength;
    if (areaBoards <= 0) {
      return null;
    }
    var totalArea = pieces.reduce(function (acc, piece) {
      return acc + (piece.alto * piece.ancho * piece.cantidad);
    }, 0);
    var withWaste = totalArea * (1 + waste / 100);
    var boards = Math.ceil(withWaste / areaBoards);
    return {
      cantidad: boards,
      detalle: 'Área total con desperdicio: ' + Math.round(withWaste) + ' cm².',
      unidad: 'planchas'
    };
  }

  function computeFleje(pieces) {
    var separation = Math.max(1, parseNumber(document.getElementById('asistente-fleje-separacion').value, 8));
    var totalCm = 0;
    pieces.forEach(function (piece) {
      var flejesPorPieza = Math.max(1, Math.floor(piece.ancho / separation) + 1);
      totalCm += flejesPorPieza * piece.alto * piece.cantidad;
    });
    return {
      cantidad: Math.round((totalCm / 100) * 100) / 100,
      detalle: 'Separación usada: ' + separation + ' cm.',
      unidad: 'm'
    };
  }

  function computeCierre(pieces) {
    var margin = Math.max(0, parseNumber(document.getElementById('asistente-cierre-margen').value, 5));
    var totalCm = pieces.reduce(function (acc, piece) {
      var perimetro = 2 * (piece.alto + piece.ancho);
      return acc + ((perimetro + margin) * piece.cantidad);
    }, 0);
    return {
      cantidad: Math.round((totalCm / 100) * 100) / 100,
      detalle: 'Incluye margen adicional de ' + margin + ' cm por pieza.',
      unidad: 'm'
    };
  }

  function calculateAssistant() {
    var type = assistantType.value;
    var pieces = readPieces();
    if (pieces.length === 0) {
      assistantResult.textContent = 'Cargá al menos una pieza válida para calcular.';
      lastAssistantResult = null;
      return null;
    }

    var calculated = null;
    if (type === 'tela') {
      calculated = computeTela(pieces);
    } else if (type === 'gomaespuma') {
      calculated = computeGomaespuma(pieces);
    } else if (type === 'fleje') {
      calculated = computeFleje(pieces);
    } else if (type === 'cierre') {
      calculated = computeCierre(pieces);
    }

    if (!calculated || calculated.cantidad <= 0) {
      assistantResult.textContent = 'No se pudo calcular con los valores actuales.';
      lastAssistantResult = null;
      return null;
    }

    lastAssistantResult = calculated;
    assistantResult.textContent = 'Cantidad sugerida: ' + calculated.cantidad + ' ' + calculated.unidad + ' | ' + calculated.detalle;
    return calculated;
  }

  addButton.addEventListener('click', function () {
    addItem();
  });

  assistantButton.addEventListener('click', function () {
    assistantInsumoId.value = '';
    assistantInsumoNuevo.value = '';
    assistantCosto.value = '0';
    assistantFurnitureType.value = 'sillon_2_cuerpos';
    loadModulesByFurniture();
    assistantPieces.innerHTML = '';
    assistantType.value = 'tela';
    updateAssistantTypeFields();
    assistantResult.textContent = 'Generá piezas desde módulos y elegí cuáles usar para este insumo.';
    lastAssistantResult = null;
    if (typeof assistantModal.showModal === 'function') {
      assistantModal.showModal();
    }
  });

  container.addEventListener('click', function (event) {
    if (!event.target.classList.contains('remove-insumo')) {
      return;
    }

    var item = event.target.closest('.insumo-item');
    if (item) {
      item.remove();
    }
  });

  container.addEventListener('change', function (event) {
    if (event.target.classList.contains('insumo-categoria')) {
      var categoryRow = event.target.closest('.insumo-item');
      if (categoryRow) {
        applyCategoryFilter(categoryRow);
      }
      return;
    }

    if (!event.target.classList.contains('insumo-select')) {
      return;
    }

    var row = event.target.closest('.insumo-item');
    var hiddenInput = row.querySelector('.insumo-nuevo-hidden');
    var label = row.querySelector('.insumo-nuevo-label');
    var costoInput = row.querySelector('input[name="costo_unitario[]"]');

    if (event.target.value === '__new__') {
      pendingRow = row;
      modalInput.value = '';
      if (typeof modal.showModal === 'function') {
        modal.showModal();
      }
      return;
    }

    if (event.target.value !== '') {
      var selected = event.target.options[event.target.selectedIndex];
      var precioBase = parseNumber(selected.getAttribute('data-precio'), 0);
      if (costoInput && precioBase > 0) {
        costoInput.value = precioBase.toFixed(2);
      }
    }

    hiddenInput.value = '';
    label.textContent = '';
  });

  modalForm.addEventListener('submit', function (event) {
    event.preventDefault();
    if (!pendingRow) {
      modal.close();
      return;
    }

    var nombre = modalInput.value.trim();
    if (nombre === '') {
      return;
    }

    var hiddenInput = pendingRow.querySelector('.insumo-nuevo-hidden');
    var label = pendingRow.querySelector('.insumo-nuevo-label');
    var select = pendingRow.querySelector('.insumo-select');

    hiddenInput.value = nombre;
    label.textContent = 'Nuevo: ' + nombre;
    select.value = '';
    modal.close();
    pendingRow = null;
  });

  modalCancel.addEventListener('click', function () {
    if (pendingRow) {
      var select = pendingRow.querySelector('.insumo-select');
      select.value = '';
      pendingRow = null;
    }
    modal.close();
  });

  assistantType.addEventListener('change', updateAssistantTypeFields);
  assistantFurnitureType.addEventListener('change', loadModulesByFurniture);

  assistantAddPiece.addEventListener('click', function () {
    addAssistantPiece({ modulo: 'manual', nombre: 'pieza_manual', alto: 0, ancho: 0, cantidad: 1 });
  });

  assistantAddModule.addEventListener('click', function () {
    addAssistantModule({ tipo: 'asiento', ancho: 0, alto: 0, profundidad: 0, cantidad: 1, activo: true });
  });

  assistantPieces.addEventListener('click', function (event) {
    if (!event.target.classList.contains('remove-pieza')) {
      return;
    }
    var row = event.target.closest('.piece-grid-row');
    if (row) {
      row.remove();
    }
  });

  assistantModules.addEventListener('click', function (event) {
    if (!event.target.classList.contains('remove-modulo')) {
      return;
    }
    var row = event.target.closest('.module-row');
    if (row) {
      row.remove();
    }
  });

  assistantGenerateFromModules.addEventListener('click', function () {
    var rows = Array.prototype.slice.call(assistantModules.querySelectorAll('.module-row'));
    if (rows.length === 0) {
      assistantResult.textContent = 'Agregá al menos un módulo para generar piezas.';
      return;
    }
    assistantPieces.innerHTML = '';
    rows.forEach(function (row) {
      var module = {
        activo: row.querySelector('.modulo-activo').checked,
        tipo: row.querySelector('.modulo-tipo').value,
        ancho: parseNumber(row.querySelector('.modulo-ancho').value, 0),
        alto: parseNumber(row.querySelector('.modulo-alto').value, 0),
        profundidad: parseNumber(row.querySelector('.modulo-profundidad').value, 0),
        cantidad: Math.max(1, Math.round(parseNumber(row.querySelector('.modulo-cantidad').value, 1)))
      };
      if (!module.activo) {
        return;
      }
      moduleToPieces(module).forEach(function (piece) {
        if (piece.alto > 0 && piece.ancho > 0 && piece.cantidad > 0) {
          addAssistantPiece(piece);
        }
      });
    });
    assistantResult.textContent = 'Piezas generadas desde módulos. Revisá y presioná "Calcular".';
  });

  assistantCalculate.addEventListener('click', function () {
    calculateAssistant();
  });

  assistantInsumoId.addEventListener('change', function () {
    if (assistantInsumoId.value === '') {
      return;
    }
    var selected = assistantInsumoId.options[assistantInsumoId.selectedIndex];
    var precio = parseNumber(selected.getAttribute('data-precio'), 0);
    if (precio > 0) {
      assistantCosto.value = precio.toFixed(2);
    }
  });

  assistantCancel.addEventListener('click', function () {
    assistantModal.close();
  });

  assistantForm.addEventListener('submit', function (event) {
    event.preventDefault();
    var calculation = calculateAssistant();
    if (!calculation) {
      return;
    }

    var selectedId = assistantInsumoId.value;
    var newName = assistantInsumoNuevo.value.trim();
    if (selectedId === '' && newName === '') {
      assistantResult.textContent = 'Seleccioná un insumo existente o cargá un nombre de insumo nuevo.';
      return;
    }

    var row = addItem();
    var categoryByType = {
      tela: 'tela',
      gomaespuma: 'gomaespuma',
      fleje: 'fleje',
      cierre: 'cierre'
    };
    var categoria = categoryByType[assistantType.value] || 'otros';
    applyInsumoSelectionToRow(row, selectedId, newName, categoria);
    var cantidadInput = row.querySelector('input[name="cantidad[]"]');
    var costoInput = row.querySelector('input[name="costo_unitario[]"]');
    if (cantidadInput) {
      cantidadInput.value = calculation.cantidad;
    }
    if (costoInput) {
      costoInput.value = parseNumber(assistantCosto.value, 0).toFixed(2);
    }

    assistantModal.close();
  });

  addItem();
  addItem();
  addItem();
  updateAssistantTypeFields();
  loadModulesByFurniture();
})();
</script>
<?php render_page_end(); ?>
