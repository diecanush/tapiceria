<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$insumos = read_json(data_file('insumos'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim((string) ($_POST['nombre'] ?? ''));
    $unidad = trim((string) ($_POST['unidad'] ?? 'unidad'));
    $stock = (float) ($_POST['stock'] ?? 0);
    $stockMinimo = (float) ($_POST['stock_minimo'] ?? 0);

    if ($nombre === '') {
        redirect_with_message('insumos.php', 'El nombre del insumo es obligatorio.');
    }

    $insumos[] = [
        'id' => next_id($insumos),
        'nombre' => $nombre,
        'unidad' => $unidad,
        'stock' => $stock,
        'stock_minimo' => $stockMinimo,
    ];

    write_json(data_file('insumos'), $insumos);
    redirect_with_message('insumos.php', 'Insumo agregado correctamente.');
}

render_page_start('Insumos');
?>
<form method="post" class="form-grid">
  <label>Nombre
    <input type="text" name="nombre" required>
  </label>
  <label>Unidad
    <input type="text" name="unidad" value="unidad">
  </label>
  <label>Stock inicial
    <input type="number" step="0.01" name="stock" value="0">
  </label>
  <label>Stock mínimo
    <input type="number" step="0.01" name="stock_minimo" value="0">
  </label>
  <div><button type="submit">Agregar insumo</button></div>
</form>

<table class="table">
  <thead><tr><th>ID</th><th>Nombre</th><th>Unidad</th><th>Stock</th><th>Mínimo</th><th>Estado</th></tr></thead>
  <tbody>
  <?php foreach ($insumos as $insumo):
      $stockActual = (float) ($insumo['stock'] ?? 0);
      $minimo = (float) ($insumo['stock_minimo'] ?? 0);
      $estado = $stockActual <= $minimo ? '⚠ Bajo' : 'OK';
      ?>
    <tr>
      <td><?= (int) $insumo['id'] ?></td>
      <td><?= h((string) $insumo['nombre']) ?></td>
      <td><?= h((string) $insumo['unidad']) ?></td>
      <td><?= $stockActual ?></td>
      <td><?= $minimo ?></td>
      <td><?= $estado ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php render_page_end();
