<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$clientes = read_json(data_file('clientes'));
$insumos = read_json(data_file('insumos'));
$config = read_json(data_file('config'));
$presupuestos = read_json(data_file('presupuestos'));
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

$verPresupuestoId = (int) ($_GET['ver'] ?? 0);
$presupuestoDetalle = $verPresupuestoId > 0 ? find_presupuesto_by_id($presupuestos, $verPresupuestoId) : null;

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
    <div class="inline-actions">
      <button type="button" id="agregar-insumo" class="secondary-btn">+ Agregar insumo</button>
      <button type="button" id="abrir-asistente-insumo" class="secondary-btn assistant-btn">Asistente de insumos</button>
      <a href="presupuesto_nuevo.php?export=csv" class="secondary-btn excel-btn action-link">Exportar presupuestos (Excel)</a>
    </div>
  </fieldset>

  <div><button type="submit">Crear presupuesto</button></div>
</form>

<table class="table">
  <thead><tr><th>ID</th><th>Fecha</th><th>Cliente</th><th>Estado</th><th>Mano de obra</th><th>Margen %</th><th>Materiales</th><th>Total</th><th>Detalle</th><th>Acciones</th></tr></thead>
  <tbody>
  <?php foreach ($presupuestos as $presupuesto): ?>
    <tr>
      <form method="post">
        <td>
          <?= (int) ($presupuesto['id'] ?? 0) ?>
          <input type="hidden" name="id" value="<?= (int) ($presupuesto['id'] ?? 0) ?>">
        </td>
        <td><?= h((string) ($presupuesto['fecha'] ?? '')) ?></td>
        <td>
          <select name="cliente_id" required>
            <option value="">Seleccionar...</option>
            <?php foreach ($clientes as $cliente): ?>
              <option value="<?= (int) $cliente['id'] ?>" <?= (int) $cliente['id'] === (int) ($presupuesto['cliente_id'] ?? 0) ? 'selected' : '' ?>>
                <?= h((string) $cliente['nombre']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </td>
        <td>
          <select name="estado">
            <option value="borrador" <?= ($presupuesto['estado'] ?? 'borrador') === 'borrador' ? 'selected' : '' ?>>Borrador</option>
            <option value="enviado" <?= ($presupuesto['estado'] ?? 'borrador') === 'enviado' ? 'selected' : '' ?>>Enviado</option>
            <option value="aprobado" <?= ($presupuesto['estado'] ?? 'borrador') === 'aprobado' ? 'selected' : '' ?>>Aprobado</option>
            <option value="rechazado" <?= ($presupuesto['estado'] ?? 'borrador') === 'rechazado' ? 'selected' : '' ?>>Rechazado</option>
          </select>
        </td>
        <td><input type="number" step="0.01" min="0" name="mano_obra" value="<?= (float) ($presupuesto['mano_obra'] ?? 0) ?>"></td>
        <td><input type="number" step="0.01" min="0" name="margen" value="<?= (float) ($presupuesto['margen'] ?? 0) ?>"></td>
        <td><?= money((float) ($presupuesto['materiales'] ?? 0)) ?></td>
        <td><?= money((float) ($presupuesto['total'] ?? 0)) ?></td>
        <td><input type="text" name="detalle" value="<?= h((string) ($presupuesto['detalle'] ?? '')) ?>"></td>
        <td class="actions-wrap">
          <a href="presupuesto_nuevo.php?ver=<?= (int) ($presupuesto['id'] ?? 0) ?>" class="secondary-btn info-btn action-link">Detalle</a>
          <a href="presupuesto_nuevo.php?export=csv&id=<?= (int) ($presupuesto['id'] ?? 0) ?>" class="secondary-btn excel-btn action-link">Excel</a>
          <button type="submit" name="action" value="edit" class="secondary-btn">Guardar</button>
          <button type="submit" name="action" value="delete" class="danger-btn" onclick="return confirm('¿Eliminar presupuesto?');">Borrar</button>
        </td>
      </form>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<?php if ($presupuestoDetalle !== null): ?>
  <section class="card" style="margin-top:16px;">
    <h3 style="margin-top:0;">Detalle del presupuesto #<?= (int) ($presupuestoDetalle['id'] ?? 0) ?></h3>
    <p><strong>Cliente:</strong> <?= h((string) ($clientesById[(int) ($presupuestoDetalle['cliente_id'] ?? 0)] ?? 'Cliente eliminado')) ?></p>
    <p><strong>Fecha:</strong> <?= h((string) ($presupuestoDetalle['fecha'] ?? '')) ?></p>
    <p><strong>Estado:</strong> <?= h((string) ($presupuestoDetalle['estado'] ?? 'borrador')) ?></p>
    <p><strong>Detalle:</strong> <?= h((string) ($presupuestoDetalle['detalle'] ?? '')) ?></p>
    <p><strong>Mano de obra:</strong> <?= money((float) ($presupuestoDetalle['mano_obra'] ?? 0)) ?></p>
    <p><strong>Materiales:</strong> <?= money((float) ($presupuestoDetalle['materiales'] ?? 0)) ?></p>
    <p><strong>Total:</strong> <?= money((float) ($presupuestoDetalle['total'] ?? 0)) ?></p>
    <h4>Insumos estimados</h4>
    <table class="table">
      <thead>
        <tr>
          <th>Insumo</th>
          <th>Cantidad</th>
          <th>Unidad</th>
          <th>Costo unitario</th>
          <th>Subtotal</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach (($presupuestoDetalle['insumos_estimados'] ?? []) as $item): ?>
        <tr>
          <td><?= h((string) ($item['nombre'] ?? 'Insumo')) ?></td>
          <td><?= (float) ($item['cantidad'] ?? 0) ?></td>
          <td><?= h((string) ($item['unidad'] ?? 'unidad')) ?></td>
          <td><?= money((float) ($item['costo_unitario'] ?? 0)) ?></td>
          <td><?= money((float) ($item['subtotal'] ?? 0)) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </section>
<?php endif; ?>

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

<dialog id="asistente-insumo-modal" style="border:1px solid #d1d5db;border-radius:8px;max-width:740px;width:95%;padding:14px;">
  <form method="dialog" id="asistente-insumo-form" class="dialog-form">
    <h3 style="margin:0;">Asistente de insumos</h3>
    <p class="muted" style="margin:0;">Calcula cantidad sugerida en base a piezas y tipo de insumo.</p>

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
            <option value="<?= (int) $insumo['id'] ?>"><?= h((string) $insumo['nombre']) ?> (<?= h((string) ($insumo['unidad'] ?? 'unidad')) ?>)</option>
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

    <fieldset>
      <legend>Piezas a cortar</legend>
      <div class="piece-grid-head">
        <strong>Alto (cm)</strong>
        <strong>Ancho (cm)</strong>
        <strong>Cantidad</strong>
        <span></span>
      </div>
      <div id="asistente-piezas"></div>
      <button type="button" id="agregar-pieza-asistente" class="secondary-btn">+ Agregar pieza</button>
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
  var assistantType = document.getElementById('asistente-tipo-insumo');
  var assistantInsumoId = document.getElementById('asistente-insumo-id');
  var assistantInsumoNuevo = document.getElementById('asistente-insumo-nuevo');
  var assistantCosto = document.getElementById('asistente-costo-unitario');
  var assistantPieces = document.getElementById('asistente-piezas');
  var assistantAddPiece = document.getElementById('agregar-pieza-asistente');
  var assistantCalculate = document.getElementById('calcular-asistente-insumo');
  var assistantCancel = document.getElementById('cancelar-asistente-insumo');
  var assistantResult = document.getElementById('asistente-resultado');
  var pendingRow = null;
  var lastAssistantResult = null;

  if (!container || !addButton || !assistantButton || !template) {
    return;
  }

  function addItem() {
    var node = template.content.cloneNode(true);
    container.appendChild(node);
    return container.lastElementChild;
  }

  function applyInsumoSelectionToRow(row, insumoId, insumoNuevo) {
    var hiddenInput = row.querySelector('.insumo-nuevo-hidden');
    var label = row.querySelector('.insumo-nuevo-label');
    var select = row.querySelector('.insumo-select');
    if (!hiddenInput || !label || !select) {
      return;
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

  function parseNumber(input, defaultValue) {
    var value = parseFloat(input);
    return Number.isFinite(value) ? value : defaultValue;
  }

  function addAssistantPiece(values) {
    var row = document.createElement('div');
    row.className = 'piece-grid-row';
    row.innerHTML = ''
      + '<input type="number" class="pieza-alto" min="0" step="0.01" value="' + (values && values.alto ? values.alto : 0) + '">'
      + '<input type="number" class="pieza-ancho" min="0" step="0.01" value="' + (values && values.ancho ? values.ancho : 0) + '">'
      + '<input type="number" class="pieza-cantidad" min="1" step="1" value="' + (values && values.cantidad ? values.cantidad : 1) + '">'
      + '<button type="button" class="danger-btn remove-pieza" style="width:30px;height:30px;padding:0;">X</button>';
    assistantPieces.appendChild(row);
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
      var alto = parseNumber(row.querySelector('.pieza-alto').value, 0);
      var ancho = parseNumber(row.querySelector('.pieza-ancho').value, 0);
      var cantidad = Math.max(1, Math.round(parseNumber(row.querySelector('.pieza-cantidad').value, 1)));
      if (alto <= 0 || ancho <= 0 || cantidad <= 0) {
        return;
      }
      result.push({
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
    assistantPieces.innerHTML = '';
    addAssistantPiece({ alto: 60, ancho: 60, cantidad: 1 });
    assistantType.value = 'tela';
    updateAssistantTypeFields();
    assistantResult.textContent = 'Completá los datos y presioná "Calcular".';
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

  assistantType.addEventListener('change', updateAssistantTypeFields);

  assistantAddPiece.addEventListener('click', function () {
    addAssistantPiece({ alto: 0, ancho: 0, cantidad: 1 });
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

  assistantCalculate.addEventListener('click', function () {
    calculateAssistant();
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
    applyInsumoSelectionToRow(row, selectedId, newName);
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
})();
</script>
<?php render_page_end(); ?>
