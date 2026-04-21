<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$clientes = read_json(data_file('clientes'));
$insumos = read_json(data_file('insumos'));
$config = read_json(data_file('config'));
$presupuestos = read_json(data_file('presupuestos'));

function buscar_insumo_por_nombre(array $insumos, string $nombre): ?array
{
    $buscado = strtolower(trim($nombre));
    if ($buscado === '') {
        return null;
    }

    foreach ($insumos as $insumo) {
        $actual = strtolower(trim((string) ($insumo['nombre'] ?? '')));
        if ($actual === $buscado) {
    foreach ($insumos as $insumo) {
        $actual = strtolower(trim((string) ($insumo['nombre'] ?? '')));
        if ($actual !== '' && $actual === $buscado) {
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
    $clientesById[(int) $cliente['id']] = (string) $cliente['nombre'];
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
    <p class="muted">3 renglones iniciales. Con + agregás uno. Con X eliminás un renglón.</p>
    <div id="insumos-items"></div>
    <button type="button" id="agregar-insumo" class="secondary-btn">+ Agregar insumo</button>
  </fieldset>

  <fieldset style="grid-column: 1 / -1;">
    <legend>Estimación de insumos</legend>
    <p class="muted">Se cargan 3 renglones iniciales. Con + agregás uno más y con X quitás el que no quieras.</p>
    <div id="insumos-items"></div>
    <button type="button" id="agregar-insumo" class="secondary-btn">+ Agregar insumo</button>
    <p class="muted">Usá + para agregar filas, X para quitar, y si no existe el insumo escribilo para guardarlo.</p>
    <div id="insumos-items"></div>
    <button type="button" id="agregar-insumo" class="secondary-btn">+ Agregar insumo</button>
    <p class="muted">Completá solo las filas necesarias. Materiales se calcula automáticamente.</p>
    <table class="table">
      <thead><tr><th>Insumo</th><th>Cantidad</th><th>Costo unitario</th></tr></thead>
      <tbody>
      <?php for ($i = 0; $i < 3; $i++): ?>
        <tr>
          <td>
            <select name="insumo_id[]">
              <option value="">Seleccionar...</option>
              <?php foreach ($insumos as $insumo): ?>
                <option value="<?= (int) $insumo['id'] ?>"><?= h((string) $insumo['nombre']) ?> (<?= h((string) ($insumo['unidad'] ?? 'unidad')) ?>)</option>
              <?php endforeach; ?>
            </select>
          </td>
          <td><input type="number" step="0.01" min="0" name="cantidad[]" value="0"></td>
          <td><input type="number" step="0.01" min="0" name="costo_unitario[]" value="0"></td>
        </tr>
      <?php endfor; ?>
      </tbody>
    </table>
  </fieldset>
  <div><button type="submit">Crear presupuesto</button></div>
</form>

<table class="table mobile-hidden">
<table class="table">
  <thead><tr><th>ID</th><th>Fecha</th><th>Cliente</th><th>Estado</th><th>Materiales</th><th>Total</th><th>Detalle</th></tr></thead>
  <tbody>
  <?php foreach ($presupuestos as $presupuesto): ?>
    <tr>
      <td><?= (int) $presupuesto['id'] ?></td>
      <td><?= h((string) ($presupuesto['fecha'] ?? '')) ?></td>
      <td><?= h($clientesById[(int) ($presupuesto['cliente_id'] ?? 0)] ?? 'N/D') ?></td>
      <td><?= h((string) $presupuesto['estado']) ?></td>
      <td><?= money((float) ($presupuesto['materiales'] ?? 0)) ?></td>
      <td><?= money((float) ($presupuesto['total'] ?? 0)) ?></td>
      <td><?= h((string) ($presupuesto['detalle'] ?? '')) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<div class="mobile-cards">
  <?php foreach ($presupuestos as $presupuesto): ?>
    <article class="card">
      <h3>Presupuesto #<?= (int) $presupuesto['id'] ?></h3>
      <p><strong>Fecha:</strong> <?= h((string) ($presupuesto['fecha'] ?? '')) ?></p>
      <p><strong>Cliente:</strong> <?= h($clientesById[(int) ($presupuesto['cliente_id'] ?? 0)] ?? 'N/D') ?></p>
      <p><strong>Estado:</strong> <?= h((string) $presupuesto['estado']) ?></p>
      <p><strong>Materiales:</strong> <?= money((float) ($presupuesto['materiales'] ?? 0)) ?></p>
      <p><strong>Total:</strong> <?= money((float) ($presupuesto['total'] ?? 0)) ?></p>
      <p><strong>Detalle:</strong> <?= h((string) ($presupuesto['detalle'] ?? '')) ?></p>
    </article>
  <?php endforeach; ?>
</div>

<template id="insumo-item-template">
  <article class="card insumo-item">
    <div class="insumo-item-head">
      <strong>Insumo</strong>
      <button type="button" class="danger-btn insumo-remove remove-insumo">X</button>
    </div>

    <label>Insumo existente (opcional)
      <button type="button" class="danger-btn remove-insumo">X</button>
    </div>
    <button type="button" class="danger-btn remove-insumo">X</button>
    <label>Insumo existente
      <select name="insumo_id[]">
        <option value="">Seleccionar...</option>
        <?php foreach ($insumos as $insumo): ?>
          <option value="<?= (int) $insumo['id'] ?>"><?= h((string) $insumo['nombre']) ?> (<?= h((string) ($insumo['unidad'] ?? 'unidad')) ?>)</option>
        <?php endforeach; ?>
      </select>
    </label>

    <label>Si no existe, escribí el insumo nuevo
      <input type="text" name="insumo_nombre_nuevo[]" placeholder="Ej: Cinta elástica">
    </label>

    <label>Cantidad
      <input type="number" step="0.01" min="0" name="cantidad[]" value="0">
    </label>

    <p class="muted">Si no está en la lista, escribilo abajo y se guarda automáticamente con unidad "unidad".</p>
    <label>Si no existe, escribí el nuevo insumo
      <input type="text" name="insumo_nombre_nuevo[]" placeholder="Ej: Cinta elástica">
    </label>
    <p class="muted">Si no está en la lista, escribilo abajo y se guarda automáticamente.</p>
    <p class="muted">Si no existe, cargalo abajo y se guarda automáticamente.</p>
    <label>Nuevo insumo (opcional)
      <input type="text" name="insumo_nombre_nuevo[]" placeholder="Ej: Cinta elástica">
    </label>
    <label>Unidad del nuevo insumo
      <input type="text" name="insumo_unidad_nueva[]" value="unidad">
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
    var node = template.content.cloneNode(true);
    container.appendChild(node);
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

  container.innerHTML = '';
  addItem();
  addItem();
  addItem();
})();
  (function () {
    var container = document.getElementById('insumos-items');
    var addButton = document.getElementById('agregar-insumo');
    var template = document.getElementById('insumo-item-template');

    function addItem() {
      var node = template.content.cloneNode(true);
      container.appendChild(node);
    }

    addButton.addEventListener('click', function () {
      addItem();
    });

    container.addEventListener('click', function (event) {
      if (!event.target.classList.contains('remove-insumo')) {
        return;
      }

      var items = container.querySelectorAll('.insumo-item');
      if (items.length <= 1) {
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
<?php render_page_end();
