<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$insumos = read_json(data_file('insumos'));
$categorias = ['tela', 'gomaespuma', 'fleje', 'cierre', 'otros'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? 'create');

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $insumos = array_values(array_filter($insumos, static function (array $insumo) use ($id): bool {
            return (int) ($insumo['id'] ?? 0) !== $id;
        }));
        write_json(data_file('insumos'), $insumos);
        redirect_with_message('insumos.php', 'Insumo eliminado correctamente.');
    }

    if ($action === 'edit') {
        $id = (int) ($_POST['id'] ?? 0);
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $unidad = trim((string) ($_POST['unidad'] ?? 'unidad'));
        $categoria = trim((string) ($_POST['categoria'] ?? 'otros'));
        if (!in_array($categoria, $categorias, true)) {
            $categoria = 'otros';
        }
        $precio = (float) ($_POST['precio'] ?? 0);
        $stock = (float) ($_POST['stock'] ?? 0);
        $stockMinimo = (float) ($_POST['stock_minimo'] ?? 0);

        if ($id <= 0 || $nombre === '') {
            redirect_with_message('insumos.php', 'Debe indicar ID y nombre para editar.');
        }

        foreach ($insumos as &$insumo) {
            if ((int) ($insumo['id'] ?? 0) !== $id) {
                continue;
            }

            $insumo['nombre'] = $nombre;
            $insumo['unidad'] = $unidad;
            $insumo['categoria'] = $categoria;
            $insumo['precio'] = $precio;
            $insumo['stock'] = $stock;
            $insumo['stock_minimo'] = $stockMinimo;
            break;
        }
        unset($insumo);

        write_json(data_file('insumos'), $insumos);
        redirect_with_message('insumos.php', 'Insumo actualizado correctamente.');
    }

    $nombre = trim((string) ($_POST['nombre'] ?? ''));
    $unidad = trim((string) ($_POST['unidad'] ?? 'unidad'));
    $categoria = trim((string) ($_POST['categoria'] ?? 'otros'));
    if (!in_array($categoria, $categorias, true)) {
        $categoria = 'otros';
    }
    $precio = (float) ($_POST['precio'] ?? 0);
    $stock = (float) ($_POST['stock'] ?? 0);
    $stockMinimo = (float) ($_POST['stock_minimo'] ?? 0);

    if ($nombre === '') {
        redirect_with_message('insumos.php', 'El nombre del insumo es obligatorio.');
    }

    $insumos[] = [
        'id' => next_id($insumos),
        'nombre' => $nombre,
        'unidad' => $unidad,
        'categoria' => $categoria,
        'precio' => $precio,
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
  <label>Categoría
    <select name="categoria">
      <?php foreach ($categorias as $categoria): ?>
        <option value="<?= h($categoria) ?>"><?= h(ucfirst($categoria)) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Precio unitario
    <input type="number" step="0.01" min="0" name="precio" value="0">
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
  <thead><tr><th>ID</th><th>Nombre</th><th>Categoría</th><th>Unidad</th><th>Precio</th><th>Stock</th><th>Mínimo</th><th>Estado</th><th>Acciones</th></tr></thead>
  <tbody>
  <?php foreach ($insumos as $insumo):
      $stockActual = (float) ($insumo['stock'] ?? 0);
      $minimo = (float) ($insumo['stock_minimo'] ?? 0);
      $categoriaActual = (string) ($insumo['categoria'] ?? 'otros');
      if (!in_array($categoriaActual, $categorias, true)) {
          $categoriaActual = 'otros';
      }
      $precioActual = (float) ($insumo['precio'] ?? 0);
      $estado = $stockActual <= $minimo ? '⚠ Bajo' : 'OK';
      ?>
    <tr>
      <form method="post">
        <td>
          <?= (int) $insumo['id'] ?>
          <input type="hidden" name="id" value="<?= (int) $insumo['id'] ?>">
        </td>
        <td><input type="text" name="nombre" value="<?= h((string) $insumo['nombre']) ?>" required></td>
        <td>
          <select name="categoria">
            <?php foreach ($categorias as $categoria): ?>
              <option value="<?= h($categoria) ?>" <?= $categoria === $categoriaActual ? 'selected' : '' ?>><?= h(ucfirst($categoria)) ?></option>
            <?php endforeach; ?>
          </select>
        </td>
        <td><input type="text" name="unidad" value="<?= h((string) $insumo['unidad']) ?>"></td>
        <td><input type="number" step="0.01" min="0" name="precio" value="<?= $precioActual ?>"></td>
        <td><input type="number" step="0.01" name="stock" value="<?= $stockActual ?>"></td>
        <td><input type="number" step="0.01" name="stock_minimo" value="<?= $minimo ?>"></td>
        <td><?= $estado ?></td>
        <td style="display:flex;gap:6px;">
          <button type="submit" name="action" value="edit" class="secondary-btn">Guardar</button>
          <button type="submit" name="action" value="delete" class="danger-btn" onclick="return confirm('¿Eliminar insumo?');">Borrar</button>
        </td>
      </form>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php render_page_end(); ?>
