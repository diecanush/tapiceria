<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$clientes = read_json(data_file('clientes'));
$insumos = read_json(data_file('insumos'));
$presupuestos = read_json(data_file('presupuestos'));
$pedidos = read_json(data_file('pedidos'));

$stockBajo = array_filter(
    $insumos,
    static function (array $insumo): bool {
        return (float) ($insumo['stock'] ?? 0) <= (float) ($insumo['stock_minimo'] ?? 0);
    }
);

render_page_start('Inicio');
?>
<p class="muted">Primer MVP funcional para pruebas del cliente.</p>
<section class="grid">
  <article class="card"><h3>Clientes</h3><div class="value"><?= count($clientes) ?></div></article>
  <article class="card"><h3>Insumos</h3><div class="value"><?= count($insumos) ?></div></article>
  <article class="card"><h3>Presupuestos</h3><div class="value"><?= count($presupuestos) ?></div></article>
  <article class="card"><h3>Trabajos en agenda</h3><div class="value"><?= count($pedidos) ?></div></article>
  <article class="card"><h3>Stock bajo</h3><div class="value"><?= count($stockBajo) ?></div></article>
</section>
<?php render_page_end();
require_once __DIR__ . '/includes/bootstrap.php';
render_header(app_title());
echo '<p>Bienvenido al esqueleto del sistema.</p>';
