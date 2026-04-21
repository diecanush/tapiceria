<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$clientes = read_json(data_file('clientes'));
$insumos = read_json(data_file('insumos'));
$config = read_json(data_file('config'));
$presupuestos = read_json(data_file('presupuestos'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clienteId = (int) ($_POST['cliente_id'] ?? 0);
    $detalle = trim((string) ($_POST['detalle'] ?? ''));
    $manoObra = (float) ($_POST['mano_obra'] ?? 0);
    $margen = (float) ($_POST['margen'] ?? 0);
    $impuesto = (float) ($config['impuesto'] ?? 0);
    $insumosSeleccionados = $_POST['insumo_id'] ?? [];
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
<?php render_page_end();
