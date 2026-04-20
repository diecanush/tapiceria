<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$clientes = read_json(data_file('clientes'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
  <thead><tr><th>ID</th><th>Nombre</th><th>Teléfono</th></tr></thead>
  <tbody>
  <?php foreach ($clientes as $cliente): ?>
    <tr>
      <td><?= (int) $cliente['id'] ?></td>
      <td><?= h((string) $cliente['nombre']) ?></td>
      <td><?= h((string) ($cliente['telefono'] ?? '')) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php render_page_end();
