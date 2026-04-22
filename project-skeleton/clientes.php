<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$clientes = read_json(data_file('clientes'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? 'create');

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $clientes = array_values(array_filter($clientes, static function (array $cliente) use ($id): bool {
            return (int) ($cliente['id'] ?? 0) !== $id;
        }));
        write_json(data_file('clientes'), $clientes);
        redirect_with_message('clientes.php', 'Cliente eliminado correctamente.');
    }

    if ($action === 'edit') {
        $id = (int) ($_POST['id'] ?? 0);
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $telefono = trim((string) ($_POST['telefono'] ?? ''));

        if ($id <= 0 || $nombre === '') {
            redirect_with_message('clientes.php', 'Debe indicar ID y nombre para editar.');
        }

        foreach ($clientes as &$cliente) {
            if ((int) ($cliente['id'] ?? 0) !== $id) {
                continue;
            }

            $cliente['nombre'] = $nombre;
            $cliente['telefono'] = $telefono;
            break;
        }
        unset($cliente);

        write_json(data_file('clientes'), $clientes);
        redirect_with_message('clientes.php', 'Cliente actualizado correctamente.');
    }

    $nombre = trim((string) ($_POST['nombre'] ?? ''));
    $telefono = trim((string) ($_POST['telefono'] ?? ''));

    if ($nombre === '') {
        redirect_with_message('clientes.php', 'El nombre es obligatorio.');
    }

    $clientes[] = [
        'id' => next_id($clientes),
        'nombre' => $nombre,
        'telefono' => $telefono,
    ];

    write_json(data_file('clientes'), $clientes);
    redirect_with_message('clientes.php', 'Cliente creado correctamente.');
}

render_page_start('Clientes');
?>
<form method="post" class="form-grid">
  <label>Nombre
    <input type="text" name="nombre" required>
  </label>
  <label>Teléfono
    <input type="text" name="telefono" placeholder="+54 ...">
  </label>
  <div><button type="submit">Agregar cliente</button></div>
</form>

<table class="table">
  <thead><tr><th>ID</th><th>Nombre</th><th>Teléfono</th><th>Acciones</th></tr></thead>
  <tbody>
  <?php foreach ($clientes as $cliente): ?>
    <tr>
      <form method="post">
        <td>
          <?= (int) $cliente['id'] ?>
          <input type="hidden" name="id" value="<?= (int) $cliente['id'] ?>">
        </td>
        <td><input type="text" name="nombre" value="<?= h((string) $cliente['nombre']) ?>" required></td>
        <td><input type="text" name="telefono" value="<?= h((string) ($cliente['telefono'] ?? '')) ?>"></td>
        <td style="display:flex;gap:6px;">
          <button type="submit" name="action" value="edit" class="secondary-btn">Guardar</button>
          <button type="submit" name="action" value="delete" class="danger-btn" onclick="return confirm('¿Eliminar cliente?');">Borrar</button>
        </td>
      </form>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php render_page_end(); ?>
