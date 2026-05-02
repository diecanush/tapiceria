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

            $piezasDetalle = [];
            foreach ($piezas as $pieza) {
                $piezaKey = 'item_pieza_' . $i . '_' . $modulo . '_' . $pieza;
                if (!isset($_POST[$piezaKey])) {
                    continue;
                }
                $alto = (float) ($_POST['item_alto_' . $i . '_' . $modulo . '_' . $pieza] ?? 0);
                $ancho = (float) ($_POST['item_ancho_' . $i . '_' . $modulo . '_' . $pieza] ?? 0);
                $cantidadPieza = (int) ($_POST['item_cant_pieza_' . $i . '_' . $modulo . '_' . $pieza] ?? 0);
                if ($alto <= 0 || $ancho <= 0 || $cantidadPieza <= 0) {
                    continue;
                }

                $piezasDetalle[] = [
                    'pieza' => $pieza,
                    'alto' => $alto,
                    'ancho' => $ancho,
                    'cantidad' => $cantidadPieza,
                    'area_total' => round(($alto * $ancho * $cantidadPieza), 4),
                ];
            }

            if ($piezasDetalle === []) {
                continue;
            }

            $modulosAplicados[] = [
                'modulo' => $modulo,
                'piezas' => $piezasDetalle,
            ];
        }

        if ($modulosAplicados === []) {
            continue;
        }

        $cantidadManual = (float) ($itemCantidades[$i] ?? 0);
        $areaPiezas = 0.0;
        foreach ($modulosAplicados as $moduloData) {
            foreach ($moduloData['piezas'] as $piezaData) {
                $areaPiezas += (float) ($piezaData['area_total'] ?? 0);
            }
        }

        $cantidadBase = $cantidadManual > 0 ? $cantidadManual : $areaPiezas;
        $merma = (float) ($itemMermas[$i] ?? 0);
        $rendimiento = max(0.0001, (float) ($itemRendimientos[$i] ?? 1));
        $costoUnitario = (float) ($insumosById[$insumoId]['precio'] ?? 0);
        $cantidadFinal = ($cantidadBase * (1 + ($merma / 100))) / $rendimiento;
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
                'cantidad_manual' => $cantidadManual,
                'cantidad_base' => round($cantidadBase, 4),
                'base_origen' => $cantidadManual > 0 ? 'manual' : 'piezas',
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
        redirect_with_message('presupuesto_nuevo_v2.php', 'Debe agregar al menos un insumo con módulo, pieza y medidas válidas.');
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
<p class="muted">Ahora podés confirmar piezas por módulo con medidas (alto x ancho x cantidad) y ver una estimación parcial por insumo antes de guardar.</p>
<form method="post" class="form-grid" id="v2-form">
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
    <input type="number" name="mano_obra" step="0.01" min="0" value="0" id="mano_obra_v2">
  </label>

  <label>Margen (%)
    <input type="number" name="margen" step="0.01" min="0" value="30" id="margen_v2">
  </label>

  <section class="card" style="grid-column:1 / -1;">
    <h3 style="margin-top:0;">Resumen parcial en vivo</h3>
    <p class="muted" id="resumen_v2">Materiales: $0.00 | Total estimado: $0.00</p>
  </section>

  <?php for ($i = 0; $i < 3; $i++): ?>
  <fieldset style="grid-column:1 / -1;" data-insumo-block="<?= $i ?>">
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
        <select name="item_insumo_id[]" class="insumo-selector" data-index="<?= $i ?>">
          <option value="">Seleccionar...</option>
          <?php foreach ($insumos as $insumo): ?>
            <option value="<?= (int) $insumo['id'] ?>" data-precio="<?= (float) ($insumo['precio'] ?? 0) ?>" data-unidad="<?= h((string) ($insumo['unidad'] ?? 'unidad')) ?>"><?= h((string) $insumo['nombre']) ?> (<?= h((string) ($insumo['unidad'] ?? 'unidad')) ?>)</option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Cantidad base manual (opcional)
        <input type="number" name="item_cantidad[]" step="0.01" min="0" value="0" class="cantidad-manual" data-index="<?= $i ?>">
      </label>
      <label>Merma %
        <input type="number" name="item_merma[]" step="0.01" min="0" value="10" class="merma" data-index="<?= $i ?>">
      </label>
      <label>Rendimiento
        <input type="number" name="item_rendimiento[]" step="0.01" min="0.01" value="1" class="rendimiento" data-index="<?= $i ?>">
      </label>
    </div>

    <details style="margin-top:8px;">
      <summary>Confirmar módulos y piezas (con medidas)</summary>
      <?php foreach ($modulos as $modulo): ?>
        <div class="card" style="margin-top:8px; padding:8px;">
          <label style="display:block; margin-bottom:6px;"><input type="checkbox" name="item_modulo_<?= $i ?>_<?= h($modulo) ?>"> <?= h(ucwords(str_replace('_', ' ', $modulo))) ?></label>
          <table class="table">
            <thead><tr><th>Usar</th><th>Pieza</th><th>Alto</th><th>Ancho</th><th>Cantidad</th></tr></thead>
            <tbody>
            <?php foreach ($piezas as $pieza): ?>
              <tr>
                <td><input type="checkbox" name="item_pieza_<?= $i ?>_<?= h($modulo) ?>_<?= h($pieza) ?>" class="pieza-check" data-index="<?= $i ?>"></td>
                <td><?= h(ucfirst($pieza)) ?></td>
                <td><input type="number" step="0.01" min="0" name="item_alto_<?= $i ?>_<?= h($modulo) ?>_<?= h($pieza) ?>" class="pieza-medida" data-index="<?= $i ?>"></td>
                <td><input type="number" step="0.01" min="0" name="item_ancho_<?= $i ?>_<?= h($modulo) ?>_<?= h($pieza) ?>" class="pieza-medida" data-index="<?= $i ?>"></td>
                <td><input type="number" step="1" min="0" name="item_cant_pieza_<?= $i ?>_<?= h($modulo) ?>_<?= h($pieza) ?>" class="pieza-medida" data-index="<?= $i ?>"></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endforeach; ?>
    </details>
    <p class="muted parcial-insumo" id="parcial_<?= $i ?>">Estimación parcial: completar datos.</p>
  </fieldset>
  <?php endfor; ?>

  <div><button type="submit">Guardar presupuesto V2</button></div>
</form>

<script>
(function () {
  function n(v) { return Number(v || 0); }
  function money(v) { return '$' + v.toFixed(2); }

  function piezasArea(index) {
    let total = 0;
    document.querySelectorAll('[data-index="' + index + '"]').forEach(function(el) {
      if (!el.name || !el.name.includes('item_alto_')) return;
      const suf = el.name.replace('item_alto_', '');
      const alto = n(el.value);
      const ancho = n(document.querySelector('[name="item_ancho_' + suf + '"]')?.value);
      const cant = n(document.querySelector('[name="item_cant_pieza_' + suf + '"]')?.value);
      const marcada = !!document.querySelector('[name="item_pieza_' + suf + '"]')?.checked;
      if (marcada && alto > 0 && ancho > 0 && cant > 0) total += alto * ancho * cant;
    });
    return total;
  }

  function recalc() {
    let materiales = 0;
    for (let i = 0; i < 3; i++) {
      const select = document.querySelector('.insumo-selector[data-index="' + i + '"]');
      const precio = n(select?.selectedOptions[0]?.dataset?.precio);
      const unidad = select?.selectedOptions[0]?.dataset?.unidad || 'unidad';
      const manual = n(document.querySelector('.cantidad-manual[data-index="' + i + '"]')?.value);
      const merma = n(document.querySelector('.merma[data-index="' + i + '"]')?.value);
      const rendimiento = Math.max(0.0001, n(document.querySelector('.rendimiento[data-index="' + i + '"]')?.value || 1));
      const area = piezasArea(i);
      const base = manual > 0 ? manual : area;
      const finalCant = (base * (1 + merma / 100)) / rendimiento;
      const subtotal = finalCant * precio;
      if (base > 0 && precio >= 0) materiales += subtotal;

      const parcial = document.getElementById('parcial_' + i);
      if (parcial) {
        parcial.textContent = 'Estimación parcial: base ' + base.toFixed(2) + ' ' + unidad + ' (' + (manual > 0 ? 'manual' : 'piezas') + '), total ' + finalCant.toFixed(2) + ' ' + unidad + ', costo ' + money(subtotal);
      }
    }

    const manoObra = n(document.getElementById('mano_obra_v2')?.value);
    const margen = n(document.getElementById('margen_v2')?.value);
    const total = (manoObra + materiales) * (1 + margen / 100);
    const resumen = document.getElementById('resumen_v2');
    if (resumen) {
      resumen.textContent = 'Materiales: ' + money(materiales) + ' | Total estimado: ' + money(total);
    }
  }

  document.getElementById('v2-form')?.addEventListener('input', recalc);
  recalc();
})();
</script>
<?php render_page_end();
