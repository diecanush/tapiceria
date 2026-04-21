<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$clientes = read_json(data_file('clientes'));
$insumos = read_json(data_file('insumos'));
$config = read_json(data_file('config'));
$presupuestos = read_json(data_file('presupuestos'));

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clienteId = (int) ($_POST['cliente_id'] ?? 0);
    $detalle = trim((string) ($_POST['detalle'] ?? ''));
    $manoObra = (float) ($_POST['mano_obra'] ?? 0);
    $margen = (float) ($_POST['margen'] ?? 0);
    $impuesto = (float) ($config['impuesto'] ?? 0);
    $insumosSeleccionados = $_POST['insumo_id'] ?? [];
    $nombresNuevos = $_POST['insumo_nombre_nuevo'] ?? [];
    $unidadesNuevas = $_POST['insumo_unidad_nueva'] ?? [];
    $cantidades = $_POST['cantidad'] ?? [];
    $costosUnitarios = $_POST['costo_unitario'] ?? [];

    if ($clienteId <= 0) {
        redirect_with_message('presupuesto_nuevo.php', 'Debe seleccionar un cliente.');
    }

    $insumosById = [];
    foreach ($insumos as $insumo) {
        $insumosById[(int) ($insumo['id'] ?? 0)] = $insumo;
    }

    $insumosEstimados = [];
    $materiales = 0.0;

    foreach ($insumosSeleccionados as $index => $insumoIdRaw) {
        $insumoId = (int) $insumoIdRaw;
        $nombreNuevo = trim((string) ($nombresNuevos[$index] ?? ''));
        $unidadNueva = trim((string) ($unidadesNuevas[$index] ?? 'unidad'));
        $cantidad = (float) ($cantidades[$index] ?? 0);
        $costoUnitario = (float) ($costosUnitarios[$index] ?? 0);

        if ($insumoId <= 0 && $nombreNuevo !== '') {
            $insumoExistente = buscar_insumo_por_nombre($insumos, $nombreNuevo);
            if ($insumoExistente !== null) {
                $insumoId = (int) ($insumoExistente['id'] ?? 0);
            } else {
                $nuevoInsumo = [
                    'id' => next_id($insumos),
                    'nombre' => $nombreNuevo,
                    'unidad' => 'unidad',
                    'unidad' => $unidadNueva === '' ? 'unidad' : $unidadNueva,
                    'stock' => 0,
                    'stock_minimo' => 0,
                ];
                $insumos[] = $nuevoInsumo;
                $insumosById[(int) $nuevoInsumo['id']] = $nuevoInsumo;
                $insumoId = (int) $nuevoInsumo['id'];
            }
        }

        $cantidad = (float) ($cantidades[$index] ?? 0);
        $costoUnitario = (float) ($costosUnitarios[$index] ?? 0);

        if ($insumoId <= 0 || $cantidad <= 0 || $costoUnitario < 0) {
            continue;
        }

        if (!isset($insumosById[$insumoId])) {
            continue;
        }

        $insumo = $insumosById[$insumoId];
        $subtotalInsumo = $cantidad * $costoUnitario;
        $materiales += $subtotalInsumo;

        $insumosEstimados[] = [
            'insumo_id' => $insumoId,
            'nombre' => (string) ($insumo['nombre'] ?? 'Insumo'),
            'unidad' => (string) ($insumo['unidad'] ?? 'unidad'),
            'cantidad' => $cantidad,
            'costo_unitario' => round($costoUnitario, 2),
            'subtotal' => round($subtotalInsumo, 2),
        ];
    }

    write_json(data_file('insumos'), $insumos);

    $insumosSeleccionados = $_POST['insumo_id'] ?? [];
    $nombresNuevos = $_POST['insumo_nombre_nuevo'] ?? [];
    $cantidades = $_POST['cantidad'] ?? [];
    $costosUnitarios = $_POST['costo_unitario'] ?? [];

    if ($clienteId <= 0) {
        redirect_with_message('presupuesto_nuevo.php', 'Debe seleccionar un cliente.');
    }

    $insumosById = [];
    foreach ($insumos as $insumo) {
        $insumosById[(int) ($insumo['id'] ?? 0)] = $insumo;
    }

    $insumosEstimados = [];
    $materiales = 0.0;

    foreach ($insumosSeleccionados as $index => $insumoIdRaw) {
        $insumoId = (int) $insumoIdRaw;
        $nombreNuevo = trim((string) ($nombresNuevos[$index] ?? ''));
        $cantidad = (float) ($cantidades[$index] ?? 0);
        $costoUnitario = (float) ($costosUnitarios[$index] ?? 0);

        if ($insumoId <= 0 && $nombreNuevo !== '') {
            $existente = buscar_insumo_por_nombre($insumos, $nombreNuevo);
            if ($existente !== null) {
                $insumoId = (int) ($existente['id'] ?? 0);
            } else {
                $nuevoInsumo = [
                    'id' => next_id($insumos),
                    'nombre' => $nombreNuevo,
                    'unidad' => 'unidad',
                    'stock' => 0,
                    'stock_minimo' => 0,
                ];
                $insumos[] = $nuevoInsumo;
                $insumosById[(int) $nuevoInsumo['id']] = $nuevoInsumo;
                $insumoId = (int) $nuevoInsumo['id'];
            }
        }

        if ($insumoId <= 0 || $cantidad <= 0 || $costoUnitario < 0) {
            continue;
        }

        if (!isset($insumosById[$insumoId])) {
            continue;
        }

        $insumo = $insumosById[$insumoId];
        $subtotalInsumo = $cantidad * $costoUnitario;
        $materiales += $subtotalInsumo;

        $insumosEstimados[] = [
            'insumo_id' => $insumoId,
            'nombre' => (string) ($insumo['nombre'] ?? 'Insumo'),
            'unidad' => (string) ($insumo['unidad'] ?? 'unidad'),
            'cantidad' => $cantidad,
            'costo_unitario' => round($costoUnitario, 2),
            'subtotal' => round($subtotalInsumo, 2),
        ];
    }

    write_json(data_file('insumos'), $insumos);

    $insumosSeleccionados = $_POST['insumo_id'] ?? [];
    $nombresNuevos = $_POST['insumo_nombre_nuevo'] ?? [];
    $cantidades = $_POST['cantidad'] ?? [];
    $costosUnitarios = $_POST['costo_unitario'] ?? [];

    if ($clienteId <= 0) {
        redirect_with_message('presupuesto_nuevo.php', 'Debe seleccionar un cliente.');
    }

    $insumosById = [];
    foreach ($insumos as $insumo) {
        $insumosById[(int) ($insumo['id'] ?? 0)] = $insumo;
    }

    $insumosEstimados = [];
    $materiales = 0.0;

    foreach ($insumosSeleccionados as $index => $insumoIdRaw) {
        $insumoId = (int) $insumoIdRaw;
        $nombreNuevo = trim((string) ($nombresNuevos[$index] ?? ''));
        $cantidad = (float) ($cantidades[$index] ?? 0);
        $costoUnitario = (float) ($costosUnitarios[$index] ?? 0);

        if ($insumoId <= 0 && $nombreNuevo !== '') {
            $existente = buscar_insumo_por_nombre($insumos, $nombreNuevo);
            if ($existente !== null) {
                $insumoId = (int) ($existente['id'] ?? 0);
            } else {
                $nuevoInsumo = [
                    'id' => next_id($insumos),
                    'nombre' => $nombreNuevo,
                    'unidad' => 'unidad',
                    'stock' => 0,
                    'stock_minimo' => 0,
                ];
                $insumos[] = $nuevoInsumo;
                $insumosById[(int) $nuevoInsumo['id']] = $nuevoInsumo;
                $insumoId = (int) $nuevoInsumo['id'];
            }
        }

        if ($insumoId <= 0 || $cantidad <= 0 || $costoUnitario < 0) {
            continue;
        }

        if (!isset($insumosById[$insumoId])) {
            continue;
        }

        $insumo = $insumosById[$insumoId];
        $subtotalInsumo = $cantidad * $costoUnitario;
        $materiales += $subtotalInsumo;

        $insumosEstimados[] = [
            'insumo_id' => $insumoId,
            'nombre' => (string) ($insumo['nombre'] ?? 'Insumo'),
            'unidad' => (string) ($insumo['unidad'] ?? 'unidad'),
            'cantidad' => $cantidad,
            'costo_unitario' => round($costoUnitario, 2),
            'subtotal' => round($subtotalInsumo, 2),
        ];
    }

    write_json(data_file('insumos'), $insumos);

    if ($clienteId <= 0) {
        redirect_with_message('presupuesto_nuevo.php', 'Debe seleccionar un cliente.');
    }

    $insumoIds = $_POST['insumo_id'] ?? [];
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
                    'unidad' => 'unidad',
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
        $subtotal = $cantidad * $costoUnitario;
        $materiales += $subtotal;

        $insumosEstimados[] = [
            'insumo_id' => $insumoId,
            'nombre' => (string) ($insumo['nombre'] ?? 'Insumo'),
            'unidad' => (string) ($insumo['unidad'] ?? 'unidad'),
            'cantidad' => $cantidad,
            'costo_unitario' => round($costoUnitario, 2),
            'subtotal' => round($subtotal, 2),
        ];
    }

    write_json(data_file('insumos'), $insumos);

    if ($clienteId <= 0) {
        redirect_with_message('presupuesto_nuevo.php', 'Debe seleccionar un cliente.');
    }

    $insumoIds = $_POST['insumo_id'] ?? [];
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
                    'unidad' => 'unidad',
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
        $subtotal = $cantidad * $costoUnitario;
        $materiales += $subtotal;

        $insumosEstimados[] = [
            'insumo_id' => $insumoId,
            'nombre' => (string) ($insumo['nombre'] ?? 'Insumo'),
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

$clientesById = [];
foreach ($clientes as $cliente) {
    $clientesById[(int) ($cliente['id'] ?? 0)] = (string) ($cliente['nombre'] ?? '');
}

render_page_start('Presupuestos');
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
    <div id="insumos-items"></div>
    <button type="button" id="agregar-insumo" class="secondary-btn">+ Agregar insumo</button>
  </fieldset>

  <div><button type="submit">Crear presupuesto</button></div>
</form>

<table class="table mobile-hidden">
<table class="table">
  <thead><tr><th>ID</th><th>Fecha</th><th>Cliente</th><th>Estado</th><th>Materiales</th><th>Total</th><th>Detalle</th></tr></thead>
  <tbody>
  <?php foreach ($presupuestos as $presupuesto): ?>
    <tr>
      <td><?= (int) ($presupuesto['id'] ?? 0) ?></td>
      <td><?= h((string) ($presupuesto['fecha'] ?? '')) ?></td>
      <td><?= h($clientesById[(int) ($presupuesto['cliente_id'] ?? 0)] ?? 'N/D') ?></td>
      <td><?= h((string) ($presupuesto['estado'] ?? '')) ?></td>
      <td><?= money((float) ($presupuesto['materiales'] ?? 0)) ?></td>
      <td><?= money((float) ($presupuesto['total'] ?? 0)) ?></td>
      <td><?= h((string) ($presupuesto['detalle'] ?? '')) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<template id="insumo-item-template">
  <article class="card insumo-item">
    <div class="insumo-item-head">
      <strong>Insumo</strong>
      <button type="button" class="danger-btn insumo-remove remove-insumo">X</button>
    </div>

    <label>Insumo existente (opcional)
      <select name="insumo_id[]">
        <option value="">Seleccionar...</option>
        <?php foreach ($insumos as $insumo): ?>
          <option value="<?= (int) $insumo['id'] ?>"><?= h((string) $insumo['nombre']) ?> (<?= h((string) ($insumo['unidad'] ?? 'unidad')) ?>)</option>
        <?php endforeach; ?>
      </select>
    </label>

    <label>Si no existe, escribí insumo nuevo
      <input type="text" name="insumo_nombre_nuevo[]" placeholder="Ej: Cinta elástica">
    </label>

    <label>Cantidad
      <input type="number" step="0.01" min="0" name="cantidad[]" value="0">
    </label>

    <label>Costo unitario
      <input type="number" step="0.01" min="0" name="costo_unitario[]" value="0">
    </label>
  </article>
</template>

<script>
(function () {
  var container = document.getElementById('insumos-items');
  var addButton = document.getElementById('agregar-insumo');
  var template = document.getElementById('insumo-item-template');

  if (!container || !addButton || !template) {
    return;
  }

  function addItem() {
    container.appendChild(template.content.cloneNode(true));
  }

  addButton.addEventListener('click', function () {
    addItem();
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

  addItem();
  addItem();
  addItem();
})();
</script>
<?php render_page_end(); ?>
