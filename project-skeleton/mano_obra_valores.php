<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$valores = read_json(data_file('mano_obra_valores'));
$configCapas = read_json(data_file('config_capas_insumos'));
$mueblesConfig = array_keys((array) ($configCapas['muebles'] ?? []));
$tiposMueble = $mueblesConfig === [] ? ['silla', 'sillon_2_cuerpos', 'sillon_3_cuerpos', 'personalizado'] : $mueblesConfig;
$trabajosSugeridos = ['retapizado_asiento', 'retapizado_completo', 'cambio_gomaespuma', 'confeccion_funda', 'reparacion_suspension', 'personalizado'];
$complejidades = ['complejidad_1', 'complejidad_2', 'complejidad_3', 'especial'];
$tareas = ['desarme', 'soporte_elastico', 'confort_gomaespuma', 'confort_guata', 'cobertura_corte_tela', 'cobertura_confeccion_tela', 'terminacion'];
$tareaLabels = [
    'desarme' => 'Desarme',
    'soporte_elastico' => 'Soporte elástico',
    'confort_gomaespuma' => 'Confort gomaespuma',
    'confort_guata' => 'Confort guata',
    'cobertura_corte_tela' => 'Cobertura corte tela',
    'cobertura_confeccion_tela' => 'Cobertura confección tela',
    'terminacion' => 'Terminación',
];

function parse_minutes_value(array $values, int $index): int
{
    return max(0, (int) ($values[$index] ?? 0));
}

function mano_obra_total_minutos(array $tiempos): int
{
    $total = 0;
    foreach ($tiempos as $minutos) {
        $total += max(0, (int) $minutos);
    }

    return $total;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ids = $_POST['id'] ?? [];
    $muebles = $_POST['mueble_tipo'] ?? [];
    $trabajos = $_POST['trabajo_tipo'] ?? [];
    $complejidadesPost = $_POST['complejidad'] ?? [];
    $tarifas = $_POST['tarifa_hora'] ?? [];
    $activos = $_POST['activo'] ?? [];
    $tiemposPost = [];
    foreach ($tareas as $tarea) {
        $tiemposPost[$tarea] = $_POST['tiempo_' . $tarea] ?? [];
    }

    $nuevosValores = [];
    foreach ($muebles as $i => $muebleRaw) {
        $mueble = trim((string) $muebleRaw);
        $trabajo = trim((string) ($trabajos[$i] ?? ''));
        if ($mueble === '' || $trabajo === '') {
            continue;
        }

        $tiempos = [];
        foreach ($tareas as $tarea) {
            $tiempos[$tarea] = parse_minutes_value((array) ($tiemposPost[$tarea] ?? []), (int) $i);
        }

        $nuevosValores[] = [
            'id' => (int) ($ids[$i] ?? 0) > 0 ? (int) $ids[$i] : next_id(array_merge($valores, $nuevosValores)),
            'mueble_tipo' => $mueble,
            'trabajo_tipo' => $trabajo,
            'complejidad' => trim((string) ($complejidadesPost[$i] ?? 'media')) ?: 'media',
            'tarifa_hora' => max(0, (float) ($tarifas[$i] ?? 0)),
            'tiempos_minutos' => $tiempos,
            'activo' => isset($activos[$i]),
        ];
    }

    write_json(data_file('mano_obra_valores'), $nuevosValores);
    redirect_with_message('mano_obra_valores.php', 'Valores de mano de obra actualizados.');
}

render_page_start('Valores de mano de obra');
?>
<p class="muted">Configurá tiempos estimados por tipo de trabajo, mueble y complejidad. Las etapas siguen el cuadro operativo del tapicero: desarme, soporte elástico, confort, cobertura y terminación.</p>

<section class="card" style="margin-bottom:12px;">
  <h3 style="margin-top:0;">Calculador rápido de horas hombre</h3>
  <div class="form-grid">
    <label>Plantilla
      <select id="mo_calc_preset">
        <option value="">Seleccionar...</option>
        <?php foreach ($valores as $valor): ?>
          <?php
            $tiempos = (array) ($valor['tiempos_minutos'] ?? []);
            $minutos = mano_obra_total_minutos($tiempos);
            $horas = $minutos / 60;
          ?>
          <option
            value="<?= (int) ($valor['id'] ?? 0) ?>"
            data-horas="<?= (float) $horas ?>"
            data-tarifa="<?= (float) ($valor['tarifa_hora'] ?? 0) ?>"
          ><?= h((string) ($valor['mueble_tipo'] ?? 'mueble')) ?> / <?= h((string) ($valor['trabajo_tipo'] ?? 'trabajo')) ?> / <?= h((string) ($valor['complejidad'] ?? 'media')) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Cantidad de muebles
      <input type="number" id="mo_calc_cantidad" min="1" step="1" value="1">
    </label>
    <label>Personas asignadas
      <input type="number" id="mo_calc_personas" min="1" step="1" value="1">
    </label>
    <label>Ajuste complejidad (%)
      <input type="number" id="mo_calc_ajuste" step="1" value="0">
    </label>
    <label>Minutos extra
      <input type="number" id="mo_calc_extra" min="0" step="1" value="0">
    </label>
  </div>
  <p class="flash" id="mo_calc_resultado">Seleccioná una plantilla para calcular.</p>
</section>

<form method="post">
  <section class="card">
    <h3 style="margin-top:0;">Listado predeterminado</h3>
    <p class="muted">Dejá vacía la fila para no guardarla. La grilla muestra el resumen; abrí <strong>Editar tareas</strong> en cada fila para modificar tarifa y minutos por tarea.</p>
    <div class="table-scroll">
    <table class="table mano-obra-table">
      <thead>
        <tr>
          <th>Activo <span class="tooltip-icon" data-tooltip="Marcá si la plantilla debe estar disponible en el calculador y presupuestos.">?</span></th>
          <th>Mueble <span class="tooltip-icon" data-tooltip="Tipo de mueble al que aplica la plantilla.">?</span></th>
          <th>Trabajo <span class="tooltip-icon" data-tooltip="Tipo de trabajo o plantilla operativa sugerida.">?</span></th>
          <th>Complejidad / tareas <span class="tooltip-icon" data-tooltip="La complejidad queda visible; los minutos por tarea se editan desplegando la fila.">?</span></th>
          <th>Total horas <span class="tooltip-icon" data-tooltip="Suma automática de todos los minutos cargados en las tareas, expresada en horas.">?</span></th>
          <th>Costo <span class="tooltip-icon" data-tooltip="Total horas multiplicado por la tarifa por hora configurada en el detalle desplegable.">?</span></th>
        </tr>
      </thead>
      <tbody>
        <?php $rows = max(8, count($valores) + 3); ?>
        <?php for ($i = 0; $i < $rows; $i++): ?>
          <?php
            $valor = $valores[$i] ?? [];
            $tiempos = (array) ($valor['tiempos_minutos'] ?? []);
            $totalMinutos = mano_obra_total_minutos($tiempos);
            $horas = $totalMinutos / 60;
            $tarifa = (float) ($valor['tarifa_hora'] ?? 0);
          ?>
          <tr class="mano-obra-row" data-row="<?= $i ?>">
            <td>
              <input type="hidden" name="id[]" value="<?= (int) ($valor['id'] ?? 0) ?>">
              <input type="checkbox" name="activo[<?= $i ?>]" <?= (bool) ($valor['activo'] ?? true) ? 'checked' : '' ?> aria-label="Plantilla activa">
            </td>
            <td>
              <select name="mueble_tipo[]" title="<?= h((string) ($valor['mueble_tipo'] ?? '')) ?>">
                <option value="">Seleccionar...</option>
                <?php foreach ($tiposMueble as $tipoMueble): ?>
                  <option value="<?= h((string) $tipoMueble) ?>" <?= (string) ($valor['mueble_tipo'] ?? '') === (string) $tipoMueble ? 'selected' : '' ?>><?= h(ucwords(str_replace('_', ' ', (string) $tipoMueble))) ?></option>
                <?php endforeach; ?>
              </select>
            </td>
            <td>
              <input type="text" name="trabajo_tipo[]" list="trabajos_sugeridos" value="<?= h((string) ($valor['trabajo_tipo'] ?? '')) ?>" title="<?= h((string) ($valor['trabajo_tipo'] ?? '')) ?>" placeholder="retapizado_completo">
            </td>
            <td class="mano-obra-detail-cell">
              <select name="complejidad[]" title="<?= h(ucwords(str_replace('_', ' ', (string) ($valor['complejidad'] ?? 'media')))) ?>">
                <?php foreach ($complejidades as $complejidad): ?>
                  <option value="<?= h($complejidad) ?>" <?= (string) ($valor['complejidad'] ?? 'media') === $complejidad ? 'selected' : '' ?>><?= h(ucwords(str_replace('_', ' ', $complejidad))) ?></option>
                <?php endforeach; ?>
              </select>
              <details class="mano-obra-tareas">
                <summary>Editar tareas</summary>
                <div class="mano-obra-tareas-grid">
                  <label>$/hora
                    <span class="tooltip-icon" data-tooltip="Tarifa de referencia para calcular el costo de esta plantilla.">?</span>
                    <input type="number" name="tarifa_hora[]" min="0" step="0.01" value="<?= (float) $tarifa ?>" class="mano-obra-tarifa" data-row="<?= $i ?>">
                  </label>
                  <?php foreach ($tareas as $tarea): ?>
                    <label><?= h($tareaLabels[$tarea] ?? $tarea) ?>
                      <span class="tooltip-icon" data-tooltip="Minutos estimados para <?= h($tareaLabels[$tarea] ?? $tarea) ?>.">?</span>
                      <input type="number" name="tiempo_<?= h($tarea) ?>[]" min="0" step="1" value="<?= (int) ($tiempos[$tarea] ?? 0) ?>" class="mano-obra-minutos" data-row="<?= $i ?>">
                    </label>
                  <?php endforeach; ?>
                </div>
              </details>
            </td>
            <td><span class="mano-obra-horas" data-row="<?= $i ?>"><?= number_format($horas, 2, ',', '.') ?></span></td>
            <td><span class="mano-obra-costo" data-row="<?= $i ?>"><?= money($horas * $tarifa) ?></span></td>
          </tr>
        <?php endfor; ?>
      </tbody>
    </table>
    </div>
    <datalist id="trabajos_sugeridos">
      <?php foreach ($trabajosSugeridos as $trabajo): ?>
        <option value="<?= h($trabajo) ?>">
      <?php endforeach; ?>
    </datalist>
    <div style="margin-top:12px;"><button type="submit">Guardar valores</button></div>
  </section>
</form>

<script>
(function () {
  function money(v) { return '$' + v.toFixed(2); }
  function n(v) { return Number(v || 0); }

  function recalcManoObra() {
    const preset = document.getElementById('mo_calc_preset');
    const selected = preset && preset.selectedOptions ? preset.selectedOptions[0] : null;
    const horasBase = n(selected && selected.dataset ? selected.dataset.horas : 0);
    const tarifa = n(selected && selected.dataset ? selected.dataset.tarifa : 0);
    const cantidad = Math.max(1, n(document.getElementById('mo_calc_cantidad')?.value || 1));
    const personas = Math.max(1, n(document.getElementById('mo_calc_personas')?.value || 1));
    const ajuste = n(document.getElementById('mo_calc_ajuste')?.value);
    const extraHoras = n(document.getElementById('mo_calc_extra')?.value) / 60;
    const horasHombre = ((horasBase + extraHoras) * cantidad) * (1 + (ajuste / 100));
    const duracionEquipo = horasHombre / personas;
    const costo = horasHombre * tarifa;
    const target = document.getElementById('mo_calc_resultado');
    if (!target || horasBase <= 0) {
      if (target) target.textContent = 'Seleccioná una plantilla para calcular.';
      return;
    }
    target.textContent = 'Horas hombre: ' + horasHombre.toFixed(2) + ' h | Duración con equipo: ' + duracionEquipo.toFixed(2) + ' h | Costo sugerido: ' + money(costo);
  }

  function formatHoras(horas) {
    return horas.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function recalcRow(rowId) {
    const minutos = Array.from(document.querySelectorAll('.mano-obra-minutos[data-row="' + rowId + '"]'))
      .reduce(function(total, el) { return total + Math.max(0, n(el.value)); }, 0);
    const horas = minutos / 60;
    const tarifa = n(document.querySelector('.mano-obra-tarifa[data-row="' + rowId + '"]')?.value);
    const horasEl = document.querySelector('.mano-obra-horas[data-row="' + rowId + '"]');
    const costoEl = document.querySelector('.mano-obra-costo[data-row="' + rowId + '"]');
    if (horasEl) horasEl.textContent = formatHoras(horas);
    if (costoEl) costoEl.textContent = money(horas * tarifa);
  }

  ['mo_calc_preset', 'mo_calc_cantidad', 'mo_calc_personas', 'mo_calc_ajuste', 'mo_calc_extra'].forEach(function(id) {
    const el = document.getElementById(id);
    if (el) el.addEventListener('input', recalcManoObra);
    if (el) el.addEventListener('change', recalcManoObra);
  });

  document.querySelectorAll('.mano-obra-minutos, .mano-obra-tarifa').forEach(function(el) {
    el.addEventListener('input', function() { recalcRow(el.dataset.row); });
    el.addEventListener('change', function() { recalcRow(el.dataset.row); });
  });

  recalcManoObra();
})();
</script>
<?php render_page_end();
