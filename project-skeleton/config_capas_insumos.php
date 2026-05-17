<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$config = read_json(data_file('config_capas_insumos'));
$muebles = (array) ($config['muebles'] ?? []);
$capas = (array) ($config['capas'] ?? []);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $version = trim((string) ($_POST['version'] ?? '1.0'));

    $muebleKeys = $_POST['mueble_key'] ?? [];
    $muebleModulos = $_POST['mueble_modulos'] ?? [];
    $newMuebles = [];
    foreach ($muebleKeys as $i => $keyRaw) {
        $key = trim((string) $keyRaw);
        if ($key === '') {
            continue;
        }
        $modsRaw = trim((string) ($muebleModulos[$i] ?? ''));
        $mods = array_values(array_filter(array_map('trim', explode(',', $modsRaw)), static function (string $v): bool {
            return $v !== '';
        }));
        $newMuebles[$key] = ['modulos_default' => $mods];
    }

    $capaKeys = $_POST['capa_key'] ?? [];
    $capaTipos = $_POST['capa_tipos'] ?? [];
    $newCapas = [];
    foreach ($capaKeys as $i => $keyRaw) {
        $key = trim((string) $keyRaw);
        if ($key === '') {
            continue;
        }
        $tiposRaw = trim((string) ($capaTipos[$i] ?? ''));
        $tipos = array_values(array_filter(array_map('trim', explode(',', $tiposRaw)), static function (string $v): bool {
            return $v !== '';
        }));
        $newCapas[$key] = ['tipos_insumo_permitidos' => $tipos];
    }

    $payload = [
        'version' => $version === '' ? '1.0' : $version,
        'muebles' => $newMuebles,
        'capas' => $newCapas,
    ];

    write_json(data_file('config_capas_insumos'), $payload);
    redirect_with_message('config_capas_insumos.php', 'Configuración de capas e insumos actualizada.');
}

render_page_start('Configuración capas/insumos');
?>
<p class="muted">Definí tipos de mueble, módulos por defecto y capas con tipos de insumo permitidos. Usar valores separados por coma.</p>

<form method="post" class="form-grid">
  <label>Versión de configuración
    <input type="text" name="version" value="<?= h((string) ($config['version'] ?? '1.0')) ?>">
  </label>

  <fieldset class="config-capas-fieldset config-muebles-fieldset">
    <legend>Muebles y módulos por defecto</legend>
    <div class="piece-grid-head config-capas-head">
      <strong>Tipo de mueble</strong>
      <strong>Módulos (coma separados)</strong>
    </div>
    <?php
    $rowsMuebles = max(5, count($muebles) + 2);
    $muebleKeys = array_keys($muebles);
    for ($i = 0; $i < $rowsMuebles; $i++):
        $key = (string) ($muebleKeys[$i] ?? '');
        $mods = $key !== '' ? implode(', ', (array) ($muebles[$key]['modulos_default'] ?? [])) : '';
    ?>
      <div class="piece-grid config-capas-row config-mueble-row">
        <input class="config-input-parent config-input-mueble" type="text" name="mueble_key[]" value="<?= h($key) ?>" placeholder="ej: sillon_2_cuerpos" aria-label="Tipo de mueble">
        <input class="config-input-child config-input-modulos" type="text" name="mueble_modulos[]" value="<?= h($mods) ?>" placeholder="asiento, respaldo, base" aria-label="Módulos del mueble">
      </div>
    <?php endfor; ?>
    <button type="button" class="secondary-btn config-add-row" data-config-target="mueble">Agregar mueble</button>
  </fieldset>

  <fieldset class="config-capas-fieldset config-capas-insumos-fieldset">
    <legend>Capas y tipos de insumo permitidos</legend>
    <div class="piece-grid-head config-capas-head">
      <strong>Capa</strong>
      <strong>Tipos permitidos (coma separados)</strong>
    </div>
    <?php
    $rowsCapas = max(6, count($capas) + 2);
    $capaKeys = array_keys($capas);
    for ($i = 0; $i < $rowsCapas; $i++):
        $key = (string) ($capaKeys[$i] ?? '');
        $tipos = $key !== '' ? implode(', ', (array) ($capas[$key]['tipos_insumo_permitidos'] ?? [])) : '';
    ?>
      <div class="piece-grid config-capas-row config-capa-row">
        <input class="config-input-parent config-input-capa" type="text" name="capa_key[]" value="<?= h($key) ?>" placeholder="ej: cobertura" aria-label="Capa">
        <input class="config-input-child config-input-tipos" type="text" name="capa_tipos[]" value="<?= h($tipos) ?>" placeholder="tela, cierre, guata" aria-label="Tipos de insumo permitidos">
      </div>
    <?php endfor; ?>
    <button type="button" class="secondary-btn config-add-row" data-config-target="capa">Agregar capa</button>
  </fieldset>

  <div><button type="submit">Guardar configuración</button></div>
</form>

<script>
(function () {
  function addConfigRow(target) {
    const rowClass = target === 'mueble' ? '.config-mueble-row' : '.config-capa-row';
    const button = document.querySelector('[data-config-target="' + target + '"]');
    const source = document.querySelector(rowClass);
    if (!button || !source) return;

    const clone = source.cloneNode(true);
    clone.querySelectorAll('input').forEach(function(input) {
      input.value = '';
    });
    button.parentNode.insertBefore(clone, button);
    clone.querySelector('input')?.focus();
  }

  document.querySelectorAll('.config-add-row').forEach(function(button) {
    button.addEventListener('click', function() {
      addConfigRow(button.dataset.configTarget || 'mueble');
    });
  });
})();
</script>

<?php render_page_end();
