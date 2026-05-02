<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$clientes = read_json(data_file('clientes'));
$insumos = read_json(data_file('insumos'));
$presupuestos = read_json(data_file('presupuestos'));

$capas = ['estructura', 'confort', 'terminacion', 'proteccion'];
$modulos = ['asiento', 'respaldo', 'brazo_izq', 'brazo_der', 'base'];
$piezas = ['frente', 'lateral', 'superior', 'inferior', 'trasera'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clienteId = (int) ($_POST['cliente_id'] ?? 0);
    $detalle = trim((string) ($_POST['detalle'] ?? ''));
    $manoObra = (float) ($_POST['mano_obra'] ?? 0);
    $margen = (float) ($_POST['margen'] ?? 30);

    if ($clienteId <= 0) {
        redirect_with_message('presupuesto_nuevo_v2.php', 'Debe seleccionar un cliente.');
    }

    $itemCapas = $_POST['item_capa'] ?? [];
    $itemInsumos = $_POST['item_insumo_id'] ?? [];
    $itemMermas = $_POST['item_merma'] ?? [];
    $itemRendimientos = $_POST['item_rendimiento'] ?? [];
    $itemCantidades = $_POST['item_cantidad'] ?? [];

    $estructura = [];
    $materiales = 0.0;
    $insumosById = [];
    foreach ($insumos as $insumo) {
        $insumosById[(int) ($insumo['id'] ?? 0)] = $insumo;
    }

    foreach ($itemCapas as $i => $capaRaw) {
        $capa = trim((string) $capaRaw);
        $insumoId = (int) ($itemInsumos[$i] ?? 0);
        if ($capa === '' || $insumoId <= 0 || !isset($insumosById[$insumoId])) {
            continue;
        }

        $modulosAplicados = [];
        foreach ($modulos as $modulo) {
            $isChecked = isset($_POST['item_modulo_' . $i . '_' . $modulo]);
            if (!$isChecked) {
                continue;
            }

            $piezasElegidas = $_POST['item_piezas_' . $i . '_' . $modulo] ?? [];
            $piezasLimpias = array_values(array_filter(array_map('strval', (array) $piezasElegidas), static function (string $pieza): bool {
                return $pieza !== '';
            }));
            if ($piezasLimpias === []) {
                continue;
            }

            $modulosAplicados[] = [
                'modulo' => $modulo,
                'piezas' => $piezasLimpias,
            ];
        }

        if ($modulosAplicados === []) {
            continue;
        }

        $cantidad = (float) ($itemCantidades[$i] ?? 0);
        $merma = (float) ($itemMermas[$i] ?? 0);
        $rendimiento = max(0.0001, (float) ($itemRendimientos[$i] ?? 1));
        $costoUnitario = (float) ($insumosById[$insumoId]['precio'] ?? 0);
        $cantidadFinal = ($cantidad * (1 + ($merma / 100))) / $rendimiento;
        $subtotal = $cantidadFinal * $costoUnitario;

        $materiales += $subtotal;
        $estructura[] = [
            'capa' => $capa,
            'insumo' => [
                'id' => $insumoId,
                'nombre' => (string) ($insumosById[$insumoId]['nombre'] ?? 'Insumo'),
                'unidad' => (string) ($insumosById[$insumoId]['unidad'] ?? 'unidad'),
                'costo_unitario' => round($costoUnitario, 2),
            ],
            'modulos' => $modulosAplicados,
            'parametros_calculo' => [
                'cantidad_base' => $cantidad,
                'merma_pct' => $merma,
                'rendimiento' => $rendimiento,
            ],
            'totales' => [
                'cantidad_total' => round($cantidadFinal, 2),
                'costo_total' => round($subtotal, 2),
            ],
        ];
    }

    if ($estructura === []) {
        redirect_with_message('presupuesto_nuevo_v2.php', 'Debe agregar al menos un insumo con módulos y piezas.');
    }

    $subtotal = $manoObra + $materiales;
    $total = $subtotal * (1 + ($margen / 100));

    $presupuestos[] = [
        'id' => next_id($presupuestos),
        'cliente_id' => $clienteId,
        'detalle' => $detalle,
        'mano_obra' => round($manoObra, 2),
        'materiales' => round($materiales, 2),
        'margen' => round($margen, 2),
        'impuesto' => 0,
        'total' => round($total, 2),
        'estado' => 'borrador',
        'fecha' => date('Y-m-d'),
        'estructura_insumos_v2' => $estructura,
        'version_flujo' => 'capa_insumo_modulos_piezas',
    ];

    write_json(data_file('presupuestos'), $presupuestos);
    redirect_with_message('presupuesto_nuevo_v2.php', 'Presupuesto v2 creado correctamente.');
}

render_page_start('Presupuesto nuevo (V2 por insumo)');
?>
<p class="muted">Prototipo paralelo: Capa → Insumo → Módulos → Piezas. Guarda la estructura completa en JSON dentro del presupuesto.</p>
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
    <input type="text" name="detalle" placeholder="Ej: Prueba flujo por insumo">
  </label>

  <label>Mano de obra
    <input type="number" name="mano_obra" step="0.01" min="0" value="0">
  </label>

  <label>Margen (%)
    <input type="number" name="margen" step="0.01" min="0" value="30">
  </label>

  <?php for ($i = 0; $i < 3; $i++): ?>
  <fieldset style="grid-column:1 / -1;">
    <legend>Insumo <?= $i + 1 ?></legend>
    <div class="form-grid">
      <label>Capa
        <select name="item_capa[]">
          <option value="">Seleccionar...</option>
          <?php foreach ($capas as $capa): ?>
            <option value="<?= h($capa) ?>"><?= h(ucfirst($capa)) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Insumo
        <select name="item_insumo_id[]">
          <option value="">Seleccionar...</option>
          <?php foreach ($insumos as $insumo): ?>
            <option value="<?= (int) $insumo['id'] ?>"><?= h((string) $insumo['nombre']) ?> (<?= h((string) ($insumo['unidad'] ?? 'unidad')) ?>)</option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Cantidad base
        <input type="number" name="item_cantidad[]" step="0.01" min="0" value="0">
      </label>
      <label>Merma %
        <input type="number" name="item_merma[]" step="0.01" min="0" value="10">
      </label>
      <label>Rendimiento
        <input type="number" name="item_rendimiento[]" step="0.01" min="0.01" value="1">
      </label>
    </div>

    <details style="margin-top:8px;">
      <summary>Seleccionar módulos y piezas</summary>
      <?php foreach ($modulos as $modulo): ?>
        <div class="card" style="margin-top:8px; padding:8px;">
          <label style="display:block; margin-bottom:6px;"><input type="checkbox" name="item_modulo_<?= $i ?>_<?= h($modulo) ?>"> <?= h(ucwords(str_replace('_', ' ', $modulo))) ?></label>
          <label>Piezas
            <select name="item_piezas_<?= $i ?>_<?= h($modulo) ?>[]" multiple size="3">
              <?php foreach ($piezas as $pieza): ?>
                <option value="<?= h($pieza) ?>"><?= h(ucfirst($pieza)) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        </div>
      <?php endforeach; ?>
    </details>
  </fieldset>
  <?php endfor; ?>

  <div><button type="submit">Guardar presupuesto V2</button></div>
</form>
<?php render_page_end();
