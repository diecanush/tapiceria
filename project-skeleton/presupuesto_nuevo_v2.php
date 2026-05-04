<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$clientes = read_json(data_file('clientes'));
$insumos = read_json(data_file('insumos'));
$presupuestos = read_json(data_file('presupuestos'));
$configCapas = read_json(data_file('config_capas_insumos'));

$capas = ['estructura', 'confort', 'terminacion', 'proteccion'];
$modulos = ['asiento', 'respaldo', 'brazo_izq', 'brazo_der', 'base'];
$piezas = ['frente', 'lateral', 'superior', 'inferior', 'trasera'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clienteId = (int) ($_POST['cliente_id'] ?? 0);
    $detalle = trim((string) ($_POST['detalle'] ?? ''));
    $manoObra = (float) ($_POST['mano_obra'] ?? 0);
    $margen = (float) ($_POST['margen'] ?? 30);
    $muebleTipo = trim((string) ($_POST['mueble_tipo'] ?? 'personalizado'));

    if ($clienteId <= 0) {
        redirect_with_message('presupuesto_nuevo_v2.php', 'Debe seleccionar un cliente.');
    }

    if ($muebleTipo === '') {
        $muebleTipo = 'personalizado';
    }

    $itemCapas = $_POST['item_capa'] ?? [];
    $itemTiposInsumo = $_POST['item_tipo_insumo'] ?? [];
    $itemInsumos = $_POST['item_insumo_id'] ?? [];
    $itemMermas = $_POST['item_merma'] ?? [];
    $itemRendimientos = $_POST['item_rendimiento'] ?? [];
    $itemCantidades = $_POST['item_cantidad'] ?? [];
    $itemUnidades = $_POST['item_unidad'] ?? [];
    $itemAnchosTela = $_POST['item_ancho_tela'] ?? [];
    $itemPreciosManual = $_POST['item_precio_manual'] ?? [];
    $itemSeparacionFleje = $_POST['item_separacion_fleje'] ?? [];
    $itemLargoPlaca = $_POST['item_largo_placa'] ?? [];
    $itemAnchoPlaca = $_POST['item_ancho_placa'] ?? [];
    $itemEspesor = $_POST['item_espesor'] ?? [];
    $itemConfirmados = $_POST['item_confirmado'] ?? [];

    $estructura = [];
    $materiales = 0.0;
    $insumosById = [];
    foreach ($insumos as $insumo) {
        $insumosById[(int) ($insumo['id'] ?? 0)] = $insumo;
    }

    foreach ($itemCapas as $i => $capaRaw) {
        $confirmado = (int) ($itemConfirmados[$i] ?? 0) === 1;
        if (!$confirmado) {
            continue;
        }

        $capa = trim((string) $capaRaw);
        $tipoInsumo = trim((string) ($itemTiposInsumo[$i] ?? ''));
        $insumoId = (int) ($itemInsumos[$i] ?? 0);
        if ($capa === '' || $tipoInsumo === '' || $insumoId <= 0 || !isset($insumosById[$insumoId])) {
            continue;
        }
        $permitidos = (array) (($configCapas['capas'][$capa]['tipos_insumo_permitidos'] ?? []));
        if (!in_array($tipoInsumo, $permitidos, true)) {
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
                $rotable = isset($_POST['item_rotable_' . $i . '_' . $modulo . '_' . $pieza]);
                if ($alto <= 0 || $ancho <= 0 || $cantidadPieza <= 0) {
                    continue;
                }

                $piezasDetalle[] = [
                    'pieza' => $pieza,
                    'alto' => $alto,
                    'ancho' => $ancho,
                    'cantidad' => $cantidadPieza,
                    'area_total' => round(($alto * $ancho * $cantidadPieza), 4),
                    'rotable' => $rotable,
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
        $unidad = (string) ($itemUnidades[$i] ?? 'm');
        $anchoTela = (float) ($itemAnchosTela[$i] ?? 0);
        $precioManual = (float) ($itemPreciosManual[$i] ?? 0);
        $separacionFleje = (float) ($itemSeparacionFleje[$i] ?? 0);
        $largoPlaca = (float) ($itemLargoPlaca[$i] ?? 0);
        $anchoPlaca = (float) ($itemAnchoPlaca[$i] ?? 0);
        $espesor = (float) ($itemEspesor[$i] ?? 0);

        $areaPiezas = 0.0;
        foreach ($modulosAplicados as $moduloData) {
            foreach ($moduloData['piezas'] as $piezaData) {
                $areaPiezas += (float) ($piezaData['area_total'] ?? 0);
            }
        }

        if ($unidad === 'cm') {
            $areaPiezas = $areaPiezas / 10000;
            $cantidadManual = $cantidadManual / 100;
        }


        $telaInvalida = false;
        if ($tipoInsumo === 'tela' && $anchoTela > 0) {
            foreach ($modulosAplicados as $moduloData) {
                foreach ((array) ($moduloData['piezas'] ?? []) as $piezaData) {
                    $w = (float) ($piezaData['ancho'] ?? 0);
                    $h = (float) ($piezaData['alto'] ?? 0);
                    $rotablePieza = (bool) ($piezaData['rotable'] ?? false);
                    if ($unidad === 'cm') {
                        $w = $w / 100;
                        $h = $h / 100;
                    }
                    if ($w > $anchoTela && (!$rotablePieza || $h > $anchoTela)) {
                        $telaInvalida = true;
                        break 2;
                    }
                }
            }
        }
        if ($telaInvalida) {
            continue;
        }

                $cantidadBase = $cantidadManual > 0 ? $cantidadManual : $areaPiezas;
        if ((string) ($insumosById[$insumoId]['categoria'] ?? '') === 'fleje' && $separacionFleje > 0 && $cantidadManual <= 0) {
            $cantidadBase = $areaPiezas / $separacionFleje;
        }
        if ($tipoInsumo === 'gomaespuma' && $largoPlaca > 0 && $anchoPlaca > 0 && $cantidadManual <= 0) {
            $placaArea = $largoPlaca * $anchoPlaca;
            $cantidadBase = (float) ceil($areaPiezas / $placaArea);
        }
        $merma = (float) ($itemMermas[$i] ?? 0);
        $rendimiento = max(0.0001, (float) ($itemRendimientos[$i] ?? 1));
        $costoUnitario = $precioManual > 0 ? $precioManual : (float) ($insumosById[$insumoId]['precio'] ?? 0);
        $cantidadFinal = ($cantidadBase * (1 + ($merma / 100))) / $rendimiento;
        $subtotal = $cantidadFinal * $costoUnitario;

        $materiales += $subtotal;
        $estructura[] = [
            'capa' => $capa,
            'tipo_insumo' => $tipoInsumo,
            'insumo' => [
                'id' => $insumoId,
                'nombre' => (string) ($insumosById[$insumoId]['nombre'] ?? 'Insumo'),
                'unidad' => (string) ($insumosById[$insumoId]['unidad'] ?? 'unidad'),
                'costo_unitario' => round($costoUnitario, 2),
            ],
            'modulos' => $modulosAplicados,
            'parametros_calculo' => [
                'cantidad_manual' => $cantidadManual,
                'unidad_carga' => $unidad,
                'ancho_tela' => $anchoTela,
                'separacion_fleje' => $separacionFleje,
                'largo_placa' => $largoPlaca,
                'ancho_placa' => $anchoPlaca,
                'espesor' => $espesor,
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
        redirect_with_message('presupuesto_nuevo_v2.php', 'Debes confirmar al menos un insumo válido. Revisá alertas de tela/ancho útil o datos incompletos.');
    }

    $subtotal = $manoObra + $materiales;
    $total = $subtotal * (1 + ($margen / 100));

    $presupuestos[] = [
        'id' => next_id($presupuestos),
        'cliente_id' => $clienteId,
        'detalle' => $detalle,
        'mueble_tipo' => $muebleTipo,
        'mano_obra' => round($manoObra, 2),
        'materiales' => round($materiales, 2),
        'margen' => round($margen, 2),
        'impuesto' => 0,
        'total' => round($total, 2),
        'estado' => 'borrador',
        'fecha' => date('Y-m-d'),
        'estructura_insumos_v2' => $estructura,
        'version_flujo' => 'capa_insumo_modulos_piezas',
        'config_capas_version' => (string) ($configCapas['version'] ?? 'manual'),
    ];

    write_json(data_file('presupuestos'), $presupuestos);
    redirect_with_message('presupuesto_nuevo_v2.php', 'Presupuesto v2 creado correctamente.');
}

render_page_start('Presupuesto nuevo (V2 por insumo)');
?>
<p class="muted">Cargá medidas en cm o m. Merma = % extra por desperdicio. Rendimiento = eficiencia del uso (1 = normal). Si elegís fleje, podés indicar separación para estimar tiras.</p>
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



  <label>Tipo de mueble
    <select name="mueble_tipo" id="mueble_tipo_v2">
      <?php foreach (array_keys((array) ($configCapas['muebles'] ?? [])) as $muebleKey): ?>
        <option value="<?= h((string) $muebleKey) ?>"><?= h(ucwords(str_replace('_', ' ', (string) $muebleKey))) ?></option>
      <?php endforeach; ?>
    </select>
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
        <select name="item_capa[]" class="capa-select" data-index="<?= $i ?>">
          <option value="">Seleccionar...</option>
          <?php foreach (array_keys((array) ($configCapas['capas'] ?? [])) as $capa): ?>
            <option value="<?= h((string) $capa) ?>"><?= h(ucwords(str_replace('_', ' ', (string) $capa))) ?></option>
          <?php endforeach; ?>
        </select>
      </label>

      <label>Tipo de insumo
        <select name="item_tipo_insumo[]" class="tipo-insumo" data-index="<?= $i ?>">
          <option value="">Seleccionar...</option>
        </select>
      </label>
      <small class="muted tipo-ayuda" id="tipo_ayuda_<?= $i ?>">Elegí capa y tipo para ver campos recomendados.</small>
      <label>Insumo
        <select name="item_insumo_id[]" class="insumo-selector" data-index="<?= $i ?>">
          <option value="">Seleccionar...</option>
          <?php foreach ($insumos as $insumo): ?>
            <option value="<?= (int) $insumo['id'] ?>" data-precio="<?= (float) ($insumo['precio'] ?? 0) ?>" data-unidad="<?= h((string) ($insumo['unidad'] ?? 'unidad')) ?>" data-categoria="<?= h((string) ($insumo['categoria'] ?? 'otros')) ?>"><?= h((string) $insumo['nombre']) ?> (<?= h((string) ($insumo['unidad'] ?? 'unidad')) ?>)</option>
          <?php endforeach; ?>
        </select>
      </label>
      <label data-tipo-field="all">Cantidad final manual (opcional)
        <input type="number" name="item_cantidad[]" step="0.01" min="0" value="0" class="cantidad-manual" data-index="<?= $i ?>">
      </label>
      <label data-tipo-field="all">Merma %
        <input type="number" name="item_merma[]" step="0.01" min="0" value="10" class="merma" data-index="<?= $i ?>">
      </label>
      <label data-tipo-field="all">Rendimiento (ej: 0.90 = 10% pérdida adicional)
        <input type="number" name="item_rendimiento[]" step="0.01" min="0.01" value="1" class="rendimiento" data-index="<?= $i ?>">
      </label>

      <label data-tipo-field="all">Unidad de carga
        <select name="item_unidad[]" class="unidad" data-index="<?= $i ?>">
          <option value="m">Metros</option>
          <option value="cm">Centímetros</option>
        </select>
      </label>
      <label data-tipo-field="tela gomaespuma guata fliselina">Ancho útil tela/placa (m)
        <input type="number" name="item_ancho_tela[]" step="0.01" min="0" value="1.40" class="ancho-tela" data-index="<?= $i ?>">
      </label>
      <label>Precio unitario (opcional)
        <input type="number" name="item_precio_manual[]" step="0.01" min="0" value="0" class="precio-manual" data-index="<?= $i ?>" placeholder="Si va vacío usa catálogo">
      </label>
      <label data-tipo-field="fleje">Separación fleje (m)
        <input type="number" name="item_separacion_fleje[]" step="0.01" min="0" value="0" class="separacion-fleje" data-index="<?= $i ?>" placeholder="Solo para fleje">
      </label>

      <label data-tipo-field="gomaespuma">Largo placa (m)
        <input type="number" name="item_largo_placa[]" step="0.01" min="0" value="2.00" class="largo-placa" data-index="<?= $i ?>">
      </label>
      <label data-tipo-field="gomaespuma">Ancho placa (m)
        <input type="number" name="item_ancho_placa[]" step="0.01" min="0" value="1.00" class="ancho-placa" data-index="<?= $i ?>">
      </label>
      <label data-tipo-field="gomaespuma">Espesor (mm)
        <input type="number" name="item_espesor[]" step="1" min="0" value="30" class="espesor" data-index="<?= $i ?>">
      </label>

    </div>

    <details style="margin-top:8px;">
      <summary>Confirmar módulos y piezas (con medidas)</summary>
      <?php foreach ($modulos as $modulo): ?>
        <div class="card" style="margin-top:8px; padding:8px;">
          <label style="display:block; margin-bottom:6px;"><input type="checkbox" name="item_modulo_<?= $i ?>_<?= h($modulo) ?>" data-modulo="<?= h($modulo) ?>" data-index="<?= $i ?>"> <?= h(ucwords(str_replace('_', ' ', $modulo))) ?></label>
          <table class="table">
            <thead><tr><th>Usar</th><th>Pieza</th><th>Alto</th><th>Ancho</th><th>Cantidad</th><th>Rotable</th></tr></thead>
            <tbody>
            <?php foreach ($piezas as $pieza): ?>
              <tr>
                <td><input type="checkbox" name="item_pieza_<?= $i ?>_<?= h($modulo) ?>_<?= h($pieza) ?>" class="pieza-check" data-index="<?= $i ?>"></td>
                <td><?= h(ucfirst($pieza)) ?></td>
                <td><input type="number" step="0.01" min="0" name="item_alto_<?= $i ?>_<?= h($modulo) ?>_<?= h($pieza) ?>" class="pieza-medida" data-index="<?= $i ?>"></td>
                <td><input type="number" step="0.01" min="0" name="item_ancho_<?= $i ?>_<?= h($modulo) ?>_<?= h($pieza) ?>" class="pieza-medida" data-index="<?= $i ?>"></td>
                <td><input type="number" step="1" min="0" name="item_cant_pieza_<?= $i ?>_<?= h($modulo) ?>_<?= h($pieza) ?>" class="pieza-medida" data-index="<?= $i ?>"></td>
                              <td><input type="checkbox" name="item_rotable_<?= $i ?>_<?= h($modulo) ?>_<?= h($pieza) ?>" checked></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endforeach; ?>
    </details>
    <input type="hidden" name="item_confirmado[]" value="0" class="confirmado-input" data-index="<?= $i ?>">
    <div class="inline-actions">
      <button type="button" class="secondary-btn btn-confirmar" data-index="<?= $i ?>">Confirmar insumo</button>
      <button type="button" class="secondary-btn btn-editar" data-index="<?= $i ?>" disabled>Modificar</button>
    </div>
    <p class="muted parcial-insumo" id="parcial_<?= $i ?>">Estado: pendiente de confirmación.</p>
    <p class="flash" id="alerta_<?= $i ?>" style="display:none;"></p>
  </fieldset>
  <?php endfor; ?>

  <div><button type="submit">Guardar presupuesto V2</button></div>
</form>

<script>
(function () {
  const configCapas = <?= json_encode($configCapas, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
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


  function setReadonlyByIndex(index, state) {
    document.querySelectorAll('[data-index="' + index + '"]').forEach(function(el) {
      if (el.classList.contains('btn-confirmar') || el.classList.contains('btn-editar') || el.classList.contains('confirmado-input')) return;
      if (el.type === 'checkbox' || el.tagName === 'SELECT' || el.tagName === 'INPUT') {
        el.disabled = state;
      }
    });
  }

  function confirmar(index) {
    const alerta = document.getElementById('alerta_' + index);
    if (alerta && alerta.style.display !== 'none' && alerta.textContent.trim() !== '') {
      alert('No se puede confirmar: hay alertas de corte en este insumo.');
      return;
    }
    const hidden = document.querySelector('.confirmado-input[data-index="' + index + '"]');
    if (hidden) hidden.value = '1';
    setReadonlyByIndex(index, true);
    document.querySelector('.btn-confirmar[data-index="' + index + '"]').disabled = true;
    document.querySelector('.btn-editar[data-index="' + index + '"]').disabled = false;
    const parcial = document.getElementById('parcial_' + index);
    if (parcial) parcial.textContent = 'Estado: insumo confirmado y listo para guardar en JSON.';
  }

  function editar(index) {
    const hidden = document.querySelector('.confirmado-input[data-index="' + index + '"]');
    if (hidden) hidden.value = '0';
    setReadonlyByIndex(index, false);
    document.querySelector('.btn-confirmar[data-index="' + index + '"]').disabled = false;
    document.querySelector('.btn-editar[data-index="' + index + '"]').disabled = true;
    const parcial = document.getElementById('parcial_' + index);
    if (parcial) parcial.textContent = 'Estado: en edición. Presioná Confirmar insumo para incluirlo en JSON.';
    recalc();
  }



  function fillTiposByCapa(index) {
    const capaSel = document.querySelector('.capa-select[data-index="' + index + '"]');
    const tipoSel = document.querySelector('.tipo-insumo[data-index="' + index + '"]');
    if (!capaSel || !tipoSel) return;
    const capa = capaSel.value;
    const tipos = (configCapas.capas && configCapas.capas[capa] && configCapas.capas[capa].tipos_insumo_permitidos) ? configCapas.capas[capa].tipos_insumo_permitidos : [];
    tipoSel.innerHTML = '<option value="">Seleccionar...</option>';
    tipos.forEach(function(t){
      const op=document.createElement('option');
      op.value=t; op.textContent=t.replaceAll('_',' ');
      tipoSel.appendChild(op);
    });
  }

  function filterInsumos(index) {
    const tipo = document.querySelector('.tipo-insumo[data-index="' + index + '"]')?.value || '';
    const insSel = document.querySelector('.insumo-selector[data-index="' + index + '"]');
    if (!insSel) return;
    insSel.querySelectorAll('option').forEach(function(op){
      if (!op.value) return;
      op.hidden = tipo !== '' && op.dataset.categoria !== tipo;
    });
    if (insSel.selectedOptions[0] && insSel.selectedOptions[0].hidden) insSel.value = '';
  }


  function adaptCamposPorTipo(index) {
    const tipo = document.querySelector('.tipo-insumo[data-index="' + index + '"]')?.value || '';
    const scope = document.querySelector('[data-insumo-block="' + index + '"]');
    if (!scope) return;
    scope.querySelectorAll('[data-tipo-field]').forEach(function(label){
      const rule = label.getAttribute('data-tipo-field') || 'all';
      const show = rule === 'all' || rule.split(' ').includes(tipo);
      label.style.display = show ? '' : 'none';
    });
    const ayuda = document.getElementById('tipo_ayuda_' + index);
    if (ayuda) {
      if (tipo === 'tela') ayuda.textContent = 'Tela: usar ancho útil y validar piezas rotable/no rotable.';
      else if (tipo === 'gomaespuma') ayuda.textContent = 'Gomaespuma: cargar largo/ancho de placa y espesor para estimar cantidad de placas.';
      else if (tipo === 'fleje') ayuda.textContent = 'Fleje: cargar separación para estimar tiras por módulo.';
      else if (tipo === '') ayuda.textContent = 'Elegí capa y tipo para ver campos recomendados.';
      else ayuda.textContent = 'Completá campos del tipo seleccionado y confirmá el bloque.';
    }
  }

  function applyMuebleDefaults() {
    const tipo = document.getElementById('mueble_tipo_v2')?.value || 'personalizado';
    const defaults = (configCapas.muebles && configCapas.muebles[tipo] && configCapas.muebles[tipo].modulos_default) ? configCapas.muebles[tipo].modulos_default : [];
    for (let i = 0; i < 3; i++) {
      document.querySelectorAll('input[data-index="' + i + '"][data-modulo]').forEach(function(chk){
        chk.checked = defaults.includes(chk.dataset.modulo);
      });
    }
  }

  function recalc() {
    let materiales = 0;
    for (let i = 0; i < 3; i++) {
      const select = document.querySelector('.insumo-selector[data-index="' + i + '"]');
      const precioCatalogo = n(select?.selectedOptions[0]?.dataset?.precio);
      const precioManual = n(document.querySelector('.precio-manual[data-index="' + i + '"]')?.value);
      const precio = precioManual > 0 ? precioManual : precioCatalogo;
      let manual = n(document.querySelector('.cantidad-manual[data-index="' + i + '"]')?.value);
      const unidad = (document.querySelector('.unidad[data-index="' + i + '"]')?.value || 'm');
      const sepFleje = n(document.querySelector('.separacion-fleje[data-index="' + i + '"]')?.value);
      const merma = n(document.querySelector('.merma[data-index="' + i + '"]')?.value);
      const rendimiento = Math.max(0.0001, n(document.querySelector('.rendimiento[data-index="' + i + '"]')?.value || 1));
      let area = piezasArea(i);
      if (unidad === 'cm') { area = area / 10000; manual = manual / 100; }
      let base = manual > 0 ? manual : area;
      const categoria = (select?.selectedOptions[0]?.textContent || '').toLowerCase();
      if (categoria.includes('fleje') && sepFleje > 0 && manual <= 0) { base = area / sepFleje; }
      const largoPlaca = n(document.querySelector('.largo-placa[data-index="' + i + '"]')?.value);
      const anchoPlaca = n(document.querySelector('.ancho-placa[data-index="' + i + '"]')?.value);
      if (tipo === 'gomaespuma' && largoPlaca > 0 && anchoPlaca > 0 && manual <= 0) { base = Math.ceil(area / (largoPlaca * anchoPlaca)); }
      const finalCant = (base * (1 + merma / 100)) / rendimiento;
      
      const anchoTela = n(document.querySelector('.ancho-tela[data-index="' + i + '"]')?.value);
      const warning = [];
      document.querySelectorAll('[data-index="' + i + '"]').forEach(function(el) {
        if (!el.name || !el.name.includes('item_ancho_')) return;
        const suf = el.name.replace('item_ancho_', '');
        const anchoP = n(el.value);
        const altoP = n(document.querySelector('[name="item_alto_' + suf + '"]')?.value);
        const usada = !!document.querySelector('[name="item_pieza_' + suf + '"]')?.checked;
        const rotable = !!document.querySelector('[name="item_rotable_' + suf + '"]')?.checked;
        if (!usada || anchoTela <= 0) return;
        let aw = anchoP;
        let ah = altoP;
        if (unidad === 'cm') { aw = aw / 100; ah = ah / 100; }
        if (aw > anchoTela && (!rotable || ah > anchoTela)) {
          warning.push('ALERTA: Pieza ' + suf.split('_').slice(-1)[0] + ' supera ancho útil (' + aw.toFixed(2) + 'm > ' + anchoTela.toFixed(2) + 'm). Sugerencia: dividir en paños.');
        }
      });

      const subtotal = finalCant * precio;
      if (base > 0 && precio >= 0) materiales += subtotal;

      const parcial = document.getElementById('parcial_' + i);
      if (parcial) {
        const confirmado = document.querySelector('.confirmado-input[data-index="' + i + '"]')?.value === '1';
        parcial.textContent = (confirmado ? '[Confirmado] ' : '[Pendiente] ') + 'Estimación parcial: base ' + base.toFixed(2) + ' m (' + (manual > 0 ? 'manual' : 'piezas') + '), merma ' + merma.toFixed(2) + '%, rendimiento ' + rendimiento.toFixed(2) + ', precio ' + money(precio) + ', costo ' + money(subtotal);
      }
      const alerta = document.getElementById('alerta_' + i);
      if (alerta) {
        if (warning.length > 0) { alerta.style.display = 'block'; alerta.textContent = warning.join(' | '); }
        else { alerta.style.display = 'none'; alerta.textContent = ''; }
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
  document.getElementById('v2-form')?.addEventListener('submit', function(e){
    const alerts = Array.from(document.querySelectorAll('[id^=alerta_]')).filter(function(a){ return a.style.display !== 'none' && a.textContent.trim() !== ''; });
    if (alerts.length > 0) {
      e.preventDefault();
      alert('Hay alertas de corte pendientes. Corregí o ajustá piezas antes de guardar.');
    }
  });
  document.querySelectorAll('.btn-confirmar').forEach(function(btn){ btn.addEventListener('click', function(){ confirmar(btn.dataset.index); }); });
  document.querySelectorAll('.btn-editar').forEach(function(btn){ btn.addEventListener('click', function(){ editar(btn.dataset.index); }); });
  document.getElementById('mueble_tipo_v2')?.addEventListener('change', function(){ applyMuebleDefaults(); recalc(); });
  document.querySelectorAll('.capa-select').forEach(function(sel){ sel.addEventListener('change', function(){ fillTiposByCapa(sel.dataset.index); filterInsumos(sel.dataset.index); recalc(); }); });
  document.querySelectorAll('.tipo-insumo').forEach(function(sel){ sel.addEventListener('change', function(){ filterInsumos(sel.dataset.index); adaptCamposPorTipo(sel.dataset.index); recalc(); }); });
  for (let i=0;i<3;i++){ fillTiposByCapa(i); filterInsumos(i); adaptCamposPorTipo(i); }
  applyMuebleDefaults();
  recalc();
})();
</script>
<?php render_page_end();
