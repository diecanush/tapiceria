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
  <thead><tr><th>ID</th><th>Fecha</th><th>Cliente</th><th>Trabajo</th><th>Prioridad</th><th>Estado</th></tr></thead>
  <tbody>
  <?php foreach ($pedidos as $pedido): ?>
    <tr>
      <td><?= (int) $pedido['id'] ?></td>
      <td><?= h((string) ($pedido['fecha'] ?? '')) ?></td>
      <td><?= h($clientesById[(int) ($pedido['cliente_id'] ?? 0)] ?? 'N/D') ?></td>
      <td><?= h((string) ($pedido['trabajo'] ?? 'Sin detalle')) ?></td>
      <td><?= h((string) ($pedido['prioridad'] ?? 'media')) ?></td>
      <td><?= h((string) ($pedido['estado'] ?? 'pendiente')) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php render_page_end(); ?>
