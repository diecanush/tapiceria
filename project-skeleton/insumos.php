<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$insumos = read_json(data_file('insumos'));
$categorias = ['tela', 'gomaespuma', 'fleje', 'cierre', 'otros'];

function parse_ars_number($value): float
{
    $raw = trim((string) $value);
    if ($raw === '') {
        return 0.0;
    }

    $normalized = preg_replace('/[^0-9,.-]/', '', $raw) ?? '';
    if (strpos($normalized, ',') !== false) {
        $normalized = str_replace('.', '', $normalized);
        $normalized = str_replace(',', '.', $normalized);
    } elseif (preg_match('/^-?\d{1,3}(?:\.\d{3})+$/', $normalized) === 1) {
        $normalized = str_replace('.', '', $normalized);
    } else {
        $normalized = str_replace(',', '', $normalized);
    }

    return is_numeric($normalized) ? (float) $normalized : 0.0;
}

function format_ars_input(float $value): string
{
    if ($value <= 0) {
        return '';
    }

    return number_format($value, 2, ',', '.');
}

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
        $precio = parse_ars_number($_POST['precio'] ?? 0);
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
    $precio = parse_ars_number($_POST['precio'] ?? 0);
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
<form method="post" class="form-grid" id="insumo-create-form">
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
    <input type="text" inputmode="decimal" name="precio" value="" placeholder="Ej: 12.500,50">
  </label>
  <label>Stock inicial
    <input type="number" step="0.01" name="stock" value="0">
  </label>
  <label>Stock mínimo
    <input type="number" step="0.01" name="stock_minimo" value="0">
  </label>
</form>

<div class="insumos-toolbar">
  <button type="submit" form="insumo-create-form">Agregar insumo</button>
  <div class="insumos-filtros" aria-label="Filtros de insumos">
    <label>Filtrar por nombre
      <input type="search" id="filtro-insumo-nombre" placeholder="Buscar insumo...">
    </label>
    <label>Filtrar por categoría
      <select id="filtro-insumo-categoria">
        <option value="">Todas</option>
        <?php foreach ($categorias as $categoria): ?>
          <option value="<?= h($categoria) ?>"><?= h(ucfirst($categoria)) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
  </div>
</div>

<table class="table insumos-table" id="insumos-table">
  <colgroup>
    <col class="insumos-col-id">
    <col class="insumos-col-nombre">
    <col class="insumos-col-categoria">
    <col class="insumos-col-unidad">
    <col class="insumos-col-precio">
    <col class="insumos-col-stock">
    <col class="insumos-col-minimo">
    <col class="insumos-col-estado">
    <col class="insumos-col-acciones">
  </colgroup>
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
    <tr data-insumo-nombre="<?= h(mb_strtolower((string) ($insumo['nombre'] ?? ''))) ?>" data-insumo-categoria="<?= h($categoriaActual) ?>">
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
        <td><input type="text" inputmode="decimal" name="precio" value="<?= format_ars_input($precioActual) ?>" placeholder="Ej: 12.500,50"></td>
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
<script>
(function () {
  const nombreInput = document.getElementById('filtro-insumo-nombre');
  const categoriaSelect = document.getElementById('filtro-insumo-categoria');
  const rows = Array.from(document.querySelectorAll('#insumos-table tbody tr[data-insumo-nombre]'));

  function normalize(value) {
    return (value || '').toString().trim().toLocaleLowerCase('es-AR');
  }

  function filtrarInsumos() {
    const nombre = normalize(nombreInput && nombreInput.value);
    const categoria = categoriaSelect ? categoriaSelect.value : '';
    rows.forEach(function(row) {
      const coincideNombre = nombre === '' || normalize(row.dataset.insumoNombre).includes(nombre);
      const coincideCategoria = categoria === '' || row.dataset.insumoCategoria === categoria;
      row.style.display = coincideNombre && coincideCategoria ? '' : 'none';
    });
  }

  if (nombreInput) nombreInput.addEventListener('input', filtrarInsumos);
  if (categoriaSelect) categoriaSelect.addEventListener('change', filtrarInsumos);
})();
</script>
<?php render_page_end(); ?>
