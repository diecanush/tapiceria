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
    <p class="muted">Dejá vacía la fila para no guardarla. Los tiempos se cargan en minutos por tarea.</p>
    <div class="table-scroll">
    <table class="table">
      <thead>
        <tr>
          <th>Activo</th>
          <th>Mueble</th>
          <th>Trabajo</th>
          <th>Complejidad</th>
          <th>$/hora</th>
          <?php foreach ($tareas as $tarea): ?>
            <th><?= h($tareaLabels[$tarea] ?? $tarea) ?></th>
          <?php endforeach; ?>
          <th>Horas</th>
          <th>Costo</th>
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
          <tr>
            <td>
              <input type="hidden" name="id[]" value="<?= (int) ($valor['id'] ?? 0) ?>">
              <input type="checkbox" name="activo[<?= $i ?>]" <?= (bool) ($valor['activo'] ?? true) ? 'checked' : '' ?>>
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
            <td>
              <select name="complejidad[]" title="<?= h(ucwords(str_replace('_', ' ', (string) ($valor['complejidad'] ?? 'media')))) ?>">
                <?php foreach ($complejidades as $complejidad): ?>
                  <option value="<?= h($complejidad) ?>" <?= (string) ($valor['complejidad'] ?? 'media') === $complejidad ? 'selected' : '' ?>><?= h(ucwords(str_replace('_', ' ', $complejidad))) ?></option>
                <?php endforeach; ?>
              </select>
            </td>
            <td><input type="number" name="tarifa_hora[]" min="0" step="0.01" value="<?= (float) $tarifa ?>" style="width:90px;"></td>
            <?php foreach ($tareas as $tarea): ?>
              <td><input type="number" name="tiempo_<?= h($tarea) ?>[]" min="0" step="1" value="<?= (int) ($tiempos[$tarea] ?? 0) ?>" style="width:75px;"></td>
            <?php endforeach; ?>
            <td><?= number_format($horas, 2, ',', '.') ?></td>
            <td><?= money($horas * $tarifa) ?></td>
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

  ['mo_calc_preset', 'mo_calc_cantidad', 'mo_calc_personas', 'mo_calc_ajuste', 'mo_calc_extra'].forEach(function(id) {
    const el = document.getElementById(id);
    if (el) el.addEventListener('input', recalcManoObra);
    if (el) el.addEventListener('change', recalcManoObra);
  });
  recalcManoObra();
})();
</script>
<?php render_page_end();
