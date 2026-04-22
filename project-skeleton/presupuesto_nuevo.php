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
    <div class="insumo-row insumo-row-head" style="display:grid;grid-template-columns:minmax(260px,2fr) 110px 130px 36px;gap:8px;align-items:center;min-width:560px;">
      <strong>Insumo existente</strong>
      <strong>Cantidad</strong>
      <strong>Costo unitario</strong>
      <span></span>
    </div>
    <div id="insumos-items" style="overflow-x:auto;padding-bottom:4px;"></div>
    <button type="button" id="agregar-insumo" class="secondary-btn">+ Agregar insumo</button>
  </fieldset>

  <div><button type="submit">Crear presupuesto</button></div>
</form>

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
  <div class="insumo-row insumo-item" style="display:grid;grid-template-columns:minmax(260px,2fr) 110px 130px 36px;gap:8px;align-items:center;min-width:560px;margin-bottom:8px;">
    <div>
      <select name="insumo_id[]" class="insumo-select">
        <option value="">Seleccionar...</option>
        <option value="__new__">+ Cargar insumo nuevo...</option>
        <?php foreach ($insumos as $insumo): ?>
          <option value="<?= (int) $insumo['id'] ?>"><?= h((string) $insumo['nombre']) ?> (<?= h((string) ($insumo['unidad'] ?? 'unidad')) ?>)</option>
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

<script>
(function () {
  var container = document.getElementById('insumos-items');
  var addButton = document.getElementById('agregar-insumo');
  var template = document.getElementById('insumo-item-template');
  var modal = document.getElementById('nuevo-insumo-modal');
  var modalForm = document.getElementById('nuevo-insumo-form');
  var modalInput = document.getElementById('nuevo-insumo-nombre');
  var modalCancel = document.getElementById('cancelar-nuevo-insumo');
  var pendingRow = null;

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

  container.addEventListener('change', function (event) {
    if (!event.target.classList.contains('insumo-select')) {
      return;
    }

    var row = event.target.closest('.insumo-item');
    var hiddenInput = row.querySelector('.insumo-nuevo-hidden');
    var label = row.querySelector('.insumo-nuevo-label');

    if (event.target.value === '__new__') {
      pendingRow = row;
      modalInput.value = '';
      if (typeof modal.showModal === 'function') {
        modal.showModal();
      }
      return;
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

  addItem();
  addItem();
  addItem();
})();
</script>
<?php render_page_end(); ?>
