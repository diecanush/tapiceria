<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$pedidos = read_json(data_file('pedidos'));
$clientes = read_json(data_file('clientes'));
$clientesById = [];
foreach ($clientes as $cliente) {
    $clientesById[(int) $cliente['id']] = (string) $cliente['nombre'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? 'create');

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $pedidos = array_values(array_filter($pedidos, static function (array $pedido) use ($id): bool {
            return (int) ($pedido['id'] ?? 0) !== $id;
        }));
        write_json(data_file('pedidos'), $pedidos);
        redirect_with_message('agenda.php', 'Trabajo eliminado de la agenda.');
    }

    if ($action === 'edit') {
        $id = (int) ($_POST['id'] ?? 0);
        $clienteId = (int) ($_POST['cliente_id'] ?? 0);
        $trabajo = trim((string) ($_POST['trabajo'] ?? ''));
        $fecha = (string) ($_POST['fecha'] ?? date('Y-m-d'));
        $prioridad = (string) ($_POST['prioridad'] ?? 'media');
        $estado = (string) ($_POST['estado'] ?? 'pendiente');

        if ($id <= 0 || $clienteId <= 0 || $trabajo === '') {
            redirect_with_message('agenda.php', 'Completá los datos obligatorios para editar.');
        }

        foreach ($pedidos as &$pedido) {
            if ((int) ($pedido['id'] ?? 0) !== $id) {
                continue;
            }

            $pedido['cliente_id'] = $clienteId;
            $pedido['trabajo'] = $trabajo;
            $pedido['fecha'] = $fecha;
            $pedido['prioridad'] = $prioridad;
            $pedido['estado'] = $estado;
            break;
        }
        unset($pedido);

        write_json(data_file('pedidos'), $pedidos);
        redirect_with_message('agenda.php', 'Trabajo actualizado correctamente.');
    }

    $clienteId = (int) ($_POST['cliente_id'] ?? 0);
    $trabajo = trim((string) ($_POST['trabajo'] ?? ''));
    $fecha = (string) ($_POST['fecha'] ?? date('Y-m-d'));
    $prioridad = (string) ($_POST['prioridad'] ?? 'media');

    if ($clienteId <= 0 || $trabajo === '') {
        redirect_with_message('agenda.php', 'Cliente y trabajo son obligatorios.');
    }

    $pedidos[] = [
        'id' => next_id($pedidos),
        'cliente_id' => $clienteId,
        'trabajo' => $trabajo,
        'fecha' => $fecha,
        'prioridad' => $prioridad,
        'estado' => 'pendiente',
    ];

    write_json(data_file('pedidos'), $pedidos);
    redirect_with_message('agenda.php', 'Trabajo agendado correctamente.');
}

usort($pedidos, static function (array $a, array $b): int {
    return strcmp((string) ($a['fecha'] ?? ''), (string) ($b['fecha'] ?? ''));
});

render_page_start('Agenda');
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
  <label>Trabajo
    <input type="text" name="trabajo" required>
  </label>
  <label>Fecha compromiso
    <input type="date" name="fecha" value="<?= date('Y-m-d') ?>" required>
  </label>
  <label>Prioridad
    <select name="prioridad">
      <option value="alta">Alta</option>
      <option value="media" selected>Media</option>
      <option value="baja">Baja</option>
    </select>
  </label>
  <div><button type="submit">Agregar a agenda</button></div>
</form>

<table class="table">
  <thead><tr><th>ID</th><th>Fecha</th><th>Cliente</th><th>Trabajo</th><th>Prioridad</th><th>Estado</th><th>Acciones</th></tr></thead>
  <tbody>
  <?php foreach ($pedidos as $pedido): ?>
    <tr>
      <form method="post">
        <td>
          <?= (int) $pedido['id'] ?>
          <input type="hidden" name="id" value="<?= (int) $pedido['id'] ?>">
        </td>
        <td><input type="date" name="fecha" value="<?= h((string) ($pedido['fecha'] ?? '')) ?>" required></td>
        <td>
          <select name="cliente_id" required>
            <option value="">Seleccionar...</option>
            <?php foreach ($clientes as $cliente): ?>
              <option value="<?= (int) $cliente['id'] ?>" <?= (int) $cliente['id'] === (int) ($pedido['cliente_id'] ?? 0) ? 'selected' : '' ?>>
                <?= h((string) $cliente['nombre']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </td>
        <td><input type="text" name="trabajo" value="<?= h((string) ($pedido['trabajo'] ?? '')) ?>" required></td>
        <td>
          <select name="prioridad">
            <option value="alta" <?= ($pedido['prioridad'] ?? 'media') === 'alta' ? 'selected' : '' ?>>Alta</option>
            <option value="media" <?= ($pedido['prioridad'] ?? 'media') === 'media' ? 'selected' : '' ?>>Media</option>
            <option value="baja" <?= ($pedido['prioridad'] ?? 'media') === 'baja' ? 'selected' : '' ?>>Baja</option>
          </select>
        </td>
        <td>
          <select name="estado">
            <option value="pendiente" <?= ($pedido['estado'] ?? 'pendiente') === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
            <option value="en_proceso" <?= ($pedido['estado'] ?? 'pendiente') === 'en_proceso' ? 'selected' : '' ?>>En proceso</option>
            <option value="finalizado" <?= ($pedido['estado'] ?? 'pendiente') === 'finalizado' ? 'selected' : '' ?>>Finalizado</option>
          </select>
        </td>
        <td style="display:flex;gap:6px;">
          <button type="submit" name="action" value="edit" class="secondary-btn">Guardar</button>
          <button type="submit" name="action" value="delete" class="danger-btn" onclick="return confirm('¿Eliminar trabajo de agenda?');">Borrar</button>
        </td>
      </form>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php render_page_end(); ?>
