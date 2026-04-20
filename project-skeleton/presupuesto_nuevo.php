<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$clientes = read_json(data_file('clientes'));
$config = read_json(data_file('config'));
$presupuestos = read_json(data_file('presupuestos'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clienteId = (int) ($_POST['cliente_id'] ?? 0);
    $detalle = trim((string) ($_POST['detalle'] ?? ''));
    $manoObra = (float) ($_POST['mano_obra'] ?? 0);
    $materiales = (float) ($_POST['materiales'] ?? 0);
    $margen = (float) ($_POST['margen'] ?? 0);
    $impuesto = (float) ($config['impuesto'] ?? 0);

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
        'materiales' => $materiales,
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
  <label>Materiales
    <input type="number" step="0.01" name="materiales" required>
  </label>
  <label>Margen (%)
    <input type="number" step="0.01" name="margen" value="30" required>
  </label>
  <div><button type="submit">Crear presupuesto</button></div>
</form>

<table class="table">
  <thead><tr><th>ID</th><th>Fecha</th><th>Cliente</th><th>Estado</th><th>Total</th><th>Detalle</th></tr></thead>
  <tbody>
  <?php foreach ($presupuestos as $presupuesto): ?>
    <tr>
      <td><?= (int) $presupuesto['id'] ?></td>
      <td><?= h((string) ($presupuesto['fecha'] ?? '')) ?></td>
      <td><?= h($clientesById[(int) ($presupuesto['cliente_id'] ?? 0)] ?? 'N/D') ?></td>
      <td><?= h((string) $presupuesto['estado']) ?></td>
      <td><?= money((float) ($presupuesto['total'] ?? 0)) ?></td>
      <td><?= h((string) ($presupuesto['detalle'] ?? '')) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php render_page_end();
require_once __DIR__ . '/includes/bootstrap.php';
render_header('Nuevo presupuesto');
