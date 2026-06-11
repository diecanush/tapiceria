<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/presupuesto_tree.php';

$clientes = read_json(data_file('clientes'));
$insumos = read_json(data_file('insumos'));
$manoObraValores = read_json(data_file('mano_obra_valores'));
$presupuestos = read_json(data_file('presupuestos'));
$configCapas = read_json(data_file('config_capas_insumos'));
$clientNames = [];
foreach ($clientes as $client) {
    $clientNames[(int) ($client['id'] ?? 0)] = (string) ($client['nombre'] ?? '');
}

function budget_supply_category(array $supply): string
{
    $category = mb_strtolower(trim((string) ($supply['categoria'] ?? 'otros')));
    $name = mb_strtolower((string) ($supply['nombre'] ?? ''));
    $rules = [
        'gomaespuma' => ['gomaespuma', 'goma espuma', 'espuma', 'alta densidad'],
        'guata' => ['guata', 'vellon', 'vellón'],
        'fliselina' => ['fliselina', 'friselina'],
        'fleje' => ['fleje', 'cincha'],
        'grapas' => ['grapa'],
        'tachas' => ['tacha'],
        'cierre' => ['cierre', 'cremallera', 'deslizador', 'tiracierre'],
        'adhesivo_contacto' => ['adhesivo', 'pegamento', 'cemento de contacto'],
    ];
    foreach ($rules as $type => $tokens) {
        foreach ($tokens as $token) {
            if (str_contains($name, $token)) {
                return $type;
            }
        }
    }
    return $category === '' ? 'otros' : $category;
}

usort($insumos, static function (array $a, array $b): int {
    return strcasecmp((string) ($a['nombre'] ?? ''), (string) ($b['nombre'] ?? ''));
});

function budget_find(array $rows, int $id): ?array
{
    foreach ($rows as $row) {
        if ((int) ($row['id'] ?? 0) === $id) {
            return $row;
        }
    }
    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? 'save');
    $id = (int) ($_POST['id'] ?? 0);
    if ($action === 'delete') {
        $presupuestos = array_values(array_filter($presupuestos, static function (array $row) use ($id): bool {
            return (int) ($row['id'] ?? 0) !== $id;
        }));
        write_json(data_file('presupuestos'), $presupuestos);
        redirect_with_message('presupuesto_nuevo_v2.php', 'Presupuesto eliminado.');
    }
    if ($action === 'duplicate') {
        $copy = budget_find($presupuestos, $id);
        if ($copy === null) {
            redirect_with_message('presupuesto_nuevo_v2.php', 'No se encontró el presupuesto.');
        }
        $copy['id'] = next_id($presupuestos);
        $copy['fecha'] = date('Y-m-d');
        $copy['estado'] = 'borrador';
        $copy['audit'] = ['created_at' => date('c'), 'flow' => 'presupuesto_tree_duplicate'];
        $presupuestos[] = $copy;
        write_json(data_file('presupuestos'), $presupuestos);
        redirect_with_message('presupuesto_nuevo_v2.php?editar_v2=' . $copy['id'], 'Presupuesto duplicado.');
    }

    $decoded = json_decode((string) ($_POST['presupuesto_payload'] ?? ''), true);
    if (!is_array($decoded) || (int) ($decoded['cliente_id'] ?? 0) <= 0 || empty($decoded['items'])) {
        redirect_with_message('presupuesto_nuevo_v2.php', 'Seleccioná un cliente y agregá al menos un mueble.');
    }
    $existing = $id > 0 ? budget_find($presupuestos, $id) : null;
    $payload = presupuesto_tree_calculate($decoded, $insumos);
    $payload = array_replace($existing ?? [], $payload);
    $payload['id'] = $existing !== null ? $id : next_id($presupuestos);
    $payload['estado'] = $action === 'finalize' ? 'finalizado' : (string) ($existing['estado'] ?? 'borrador');
    $payload['fecha'] = (string) ($payload['fecha'] ?? date('Y-m-d'));
    $payload['version_flujo'] = 'presupuesto_jerarquico_v3';
    $payload['config_capas_version'] = (string) ($configCapas['version'] ?? 'manual');
    $payload['config_capas_snapshot'] = $configCapas;
    $payload['audit'] = array_replace((array) ($existing['audit'] ?? []), [
        $existing === null ? 'created_at' : 'updated_at' => date('c'),
        'flow' => $existing === null ? 'presupuesto_tree_create' : 'presupuesto_tree_update',
    ]);
    $updated = false;
    foreach ($presupuestos as &$row) {
        if ((int) ($row['id'] ?? 0) === $payload['id']) {
            $row = $payload;
            $updated = true;
            break;
        }
    }
    unset($row);
    if (!$updated) {
        $presupuestos[] = $payload;
    }
    write_json(data_file('presupuestos'), $presupuestos);
    redirect_with_message('presupuesto_nuevo_v2.php?editar_v2=' . $payload['id'], $action === 'finalize' ? 'Presupuesto finalizado.' : 'Borrador guardado.');
}

$budgets = array_values(array_filter($presupuestos, static function (array $row): bool {
    return isset($row['items']) || isset($row['estructura_insumos_v2']);
}));
$editId = (int) ($_GET['editar_v2'] ?? 0);
$viewId = (int) ($_GET['ver_v2'] ?? 0);
$editing = budget_find($budgets, $editId);
$viewing = budget_find($budgets, $viewId);
$editing = $editing ? presupuesto_tree_from_legacy($editing) : null;
$viewing = $viewing ? presupuesto_tree_from_legacy($viewing) : null;
$initial = $editing ?? ['cliente_id' => 0, 'detalle' => '', 'fecha' => date('Y-m-d'), 'margen' => 30, 'items' => []];
$laborTemplates = [];
foreach ($manoObraValores as $template) {
    if (!(bool) ($template['activo'] ?? true)) {
        continue;
    }
    $laborTemplates[] = [
        'id' => (int) ($template['id'] ?? 0),
        'mueble_tipo' => (string) ($template['mueble_tipo'] ?? ''),
        'trabajo_tipo' => (string) ($template['trabajo_tipo'] ?? ''),
        'complejidad' => (string) ($template['complejidad'] ?? ''),
        'costo' => presupuesto_tree_labor_total($template),
        'snapshot' => $template,
    ];
}

render_page_start('Presupuestos por muebles');
?>
<style>
.budget-grid,.item-grid,.module-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;align-items:end}
.tree{border-left:3px solid #d4dae2;margin:12px 0 12px 12px;padding-left:14px}.tree.item{border-color:#47739f}.tree.module{border-color:#708f58}.tree.layer{border-color:#aa7c4f}
.node-title{display:flex;align-items:center;gap:8px}.node-title h3,.node-title h4{margin:0}.node-actions{display:flex;gap:6px;flex-wrap:wrap;margin-left:auto}.level-heading{display:flex;align-items:center;gap:8px;margin:14px 0 8px}.level-heading h2,.level-heading h3,.level-heading h4{margin:0}.add-level{width:34px;height:34px;padding:0;font-size:20px;line-height:1}.tree-details>summary{cursor:pointer;list-style:none}.tree-details>summary::-webkit-details-marker{display:none}.collapse-arrow{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;font-size:25px;line-height:1;color:#365a7c;transition:transform .15s ease}.tree-details[open]>summary .collapse-arrow{transform:rotate(90deg)}.tree-content{padding-top:10px}
.insumo-row{display:grid;grid-template-columns:2fr 1fr 1fr auto;gap:8px;align-items:center;padding:7px 0;border-bottom:1px solid #eee}
.summary-card{position:sticky;bottom:8px;z-index:5;background:#fff;box-shadow:0 4px 18px #0002}
.modal-bg{display:none;position:fixed;inset:0;background:#0008;z-index:30;align-items:center;justify-content:center}.modal-bg.open{display:flex}
.modal-card{background:#fff;width:min(900px,94vw);max-height:92vh;overflow:auto;padding:18px;border-radius:8px}.piece-head,.piece-row{display:grid;grid-template-columns:1fr repeat(3,110px) 42px;gap:7px;margin:6px 0}.piece-head{font-size:12px;font-weight:700;color:#56616f}.calculation-preview{margin:12px 0;padding:12px;border:1px solid #b9c9dc;border-radius:6px;background:#f4f8fc;font-weight:700}
.budget-history-wrap{overflow-x:auto}.budget-history{min-width:940px}.budget-history th,.budget-history td{white-space:nowrap}.budget-history .history-description{white-space:normal;min-width:150px}.budget-history .history-actions{display:flex;flex-wrap:nowrap;gap:5px;align-items:center}.budget-history .history-actions .action-link,.budget-history .history-actions button{width:auto;margin:0;padding:6px 8px;line-height:1.2}.budget-history .history-actions .danger-btn{margin-left:0}
@media(max-width:800px){.budget-grid,.item-grid,.module-grid,.insumo-row,.piece-row{grid-template-columns:1fr 1fr}}
</style>

<?php if ($viewing !== null): ?>
<section class="card">
  <h3>Presupuesto #<?= (int) ($viewing['id'] ?? 0) ?></h3>
  <p><?= h((string) ($viewing['detalle'] ?? '')) ?> · <?= h((string) ($viewing['fecha'] ?? '')) ?> · <strong><?= money((float) ($viewing['total'] ?? 0)) ?></strong></p>
  <?php foreach ((array) ($viewing['items'] ?? []) as $item): ?>
    <details open><summary><?= h((string) ($item['tipo_mueble'] ?? 'Mueble')) ?> × <?= (int) ($item['cantidad'] ?? 1) ?> · <?= money((float) ($item['subtotal_total'] ?? 0)) ?></summary>
    <?php foreach ((array) ($item['modulos'] ?? []) as $module): ?><div style="margin-left:16px"><strong><?= h((string) ($module['tipo'] ?? 'Módulo')) ?></strong>: <?= (float) ($module['alto'] ?? 0) ?> × <?= (float) ($module['ancho'] ?? 0) ?> × <?= (float) ($module['profundidad'] ?? 0) ?> cm</div><?php endforeach; ?>
    </details>
  <?php endforeach; ?>
</section>
<?php endif; ?>

<section class="card">
  <h3>Historial</h3>
  <div class="budget-history-wrap">
  <table class="table budget-history"><thead><tr><th>ID</th><th>Fecha</th><th>Cliente</th><th>Descripción</th><th>Estado</th><th>Total</th><th>Acciones</th></tr></thead><tbody>
  <?php foreach (array_slice(array_reverse($budgets), 0, 12) as $row): ?>
    <?php
    $clientId = (int) ($row['cliente_id'] ?? 0);
    $clientName = $clientNames[$clientId] ?? ($clientId > 0 ? 'Cliente #' . $clientId : 'Sin cliente');
    ?>
    <tr><td>#<?= (int) ($row['id'] ?? 0) ?></td><td><?= h((string) ($row['fecha'] ?? '')) ?></td><td><?= h($clientName) ?></td><td class="history-description"><?= h((string) ($row['detalle'] ?? '')) ?></td><td><?= h((string) ($row['estado'] ?? 'borrador')) ?></td><td><?= money((float) ($row['total'] ?? 0)) ?></td>
    <td><form method="post" class="history-actions"><input type="hidden" name="id" value="<?= (int) ($row['id'] ?? 0) ?>"><a class="secondary-btn action-link" href="?ver_v2=<?= (int) ($row['id'] ?? 0) ?>">Detalle</a><a class="secondary-btn action-link" href="?editar_v2=<?= (int) ($row['id'] ?? 0) ?>">Editar</a><button class="secondary-btn" name="action" value="duplicate">Duplicar</button><button class="danger-btn" name="action" value="delete" onclick="return confirm('¿Eliminar presupuesto?')">Eliminar</button></form></td></tr>
  <?php endforeach; ?>
  </tbody></table>
  </div>
</section>

<form method="post" id="budget-form">
  <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
  <input type="hidden" name="action" id="save-action" value="save">
  <input type="hidden" name="presupuesto_payload" id="payload">
  <section class="card">
    <h2><?= $editing ? 'Editar presupuesto #' . (int) $editing['id'] : 'Nuevo presupuesto' ?></h2>
    <div class="budget-grid">
      <label>Cliente<select id="cliente" required><option value="">Seleccionar...</option><?php foreach ($clientes as $client): ?><option value="<?= (int) $client['id'] ?>"><?= h((string) $client['nombre']) ?></option><?php endforeach; ?></select></label>
      <label>Descripción<input id="detalle" type="text"></label>
      <label>Fecha<input id="fecha" type="date"></label>
      <label>Margen %<input id="margen" type="number" min="0" step=".01"></label>
    </div>
  </section>
  <div class="level-heading"><h2>Muebles</h2><button type="button" id="add-item" class="add-level" title="Agregar mueble" aria-label="Agregar mueble">+</button></div>
  <div id="items"></div>
  <section class="card summary-card"><strong id="summary"></strong><div class="inline-actions" style="margin-top:8px"><button type="submit">Guardar borrador</button><button type="submit" class="secondary-btn" id="finalize">Finalizar</button></div></section>
</form>

<div class="modal-bg" id="modal"><div class="modal-card">
  <h3 id="modal-title">Agregar insumo</h3>
  <div class="form-grid">
    <label>Tipo<select id="mi-type"></select></label>
    <label>Insumo<select id="mi-supply"><option value="">Seleccionar...</option><?php foreach ($insumos as $supply): ?><option value="<?= (int) $supply['id'] ?>" data-price="<?= (float) ($supply['precio'] ?? 0) ?>" data-unit="<?= h((string) ($supply['unidad'] ?? 'unidad')) ?>" data-category="<?= h(budget_supply_category($supply)) ?>"><?= h((string) ($supply['nombre'] ?? 'Insumo')) ?></option><?php endforeach; ?></select></label>
    <label>Merma %<input id="mi-waste" type="number" min="0" step=".01" value="10"></label>
    <label>Costo unitario<input id="mi-cost" type="number" min="0" step=".01"></label>
    <label data-for="tela guata fliselina">Ancho útil (m)<input id="mi-roll-width" type="number" min=".01" step=".01" value="1.4"></label>
    <label data-for="gomaespuma">Largo placa (m)<input id="mi-sheet-length" type="number" min=".01" step=".01" value="2"></label>
    <label data-for="gomaespuma">Ancho placa (m)<input id="mi-sheet-width" type="number" min=".01" step=".01" value="1"></label>
    <label data-for="fleje">Patrón<select id="mi-pattern"><option value="lineal">Lineal</option><option value="cuadriculado">Cuadriculado</option></select></label>
    <label data-for="fleje" id="direction-label">Dirección<select id="mi-direction"><option value="ancho">A lo ancho</option><option value="largo">A lo largo</option></select></label>
    <label data-for="fleje">Separación (cm)<input id="mi-spacing" type="number" min="1" value="10"></label>
    <label data-for="fleje">Grapas por extremo<input id="mi-staples" type="number" min="1" value="2"></label>
    <label>Cantidad ajustada<input id="mi-adjusted" type="number" min="0" step=".01"></label>
    <label>Motivo del ajuste<input id="mi-reason" type="text"></label>
  </div>
  <section id="pieces-section"><h4>Piezas a cortar</h4><p class="muted">Cada fila indica: nombre de la pieza, alto en centímetros, ancho en centímetros y cantidad.</p><div class="piece-head"><span>Pieza</span><span>Alto (cm)</span><span>Ancho (cm)</span><span>Cantidad</span><span></span></div><div id="pieces"></div><button type="button" class="secondary-btn" id="add-piece">+ Pieza</button></section>
  <div id="calculation-preview" class="calculation-preview">Completá los datos para calcular el consumo.</div>
  <div class="inline-actions" style="margin-top:12px"><button type="button" id="save-input">Aceptar</button><button type="button" class="secondary-btn" id="cancel-input">Cancelar</button></div>
</div></div>

<script>
(() => {
const initial=<?= json_encode($initial, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
const config=<?= json_encode($configCapas, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
const labor=<?= json_encode($laborTemplates, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
const fallbackTypes=['tela','gomaespuma','guata','fliselina','fleje','grapas','tachas','cierre','cordon','adhesivo_contacto','otros'];
let state=structuredClone(initial),ctx=null;
const $=(s,r=document)=>r.querySelector(s), esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const uid=p=>p+'-'+Date.now().toString(36)+Math.random().toString(36).slice(2,6), money=v=>Number(v||0).toLocaleString('es-AR',{style:'currency',currency:'ARS'});
const furniture=Object.keys(config.muebles||{}), layers=Object.keys(config.capas||{});
const opts=(values,current)=>values.map(v=>`<option value="${esc(v)}" ${v===current?'selected':''}>${esc(v.replaceAll('_',' '))}</option>`).join('');
function newModule(type='modulo'){return{id:uid('mod'),tipo:type,alto:0,ancho:0,profundidad:0,capas:[]}}
function newItem(){const type=furniture.includes('personalizado')?'personalizado':'';return{id:uid('item'),tipo_mueble:type,trabajo_tipo:'',complejidad:'',cantidad:1,mano_obra_unitaria:0,mano_obra_plantilla_id:null,mano_obra_snapshot:null,modulos:[]}}
function unique(values){return [...new Set(values.filter(Boolean))]}
function workOptions(item){const values=unique(labor.filter(x=>x.mueble_tipo===item.tipo_mueble).map(x=>x.trabajo_tipo));return values.length?'<option value="">Seleccionar...</option>'+opts(values,item.trabajo_tipo):'<option value="">Sin trabajos configurados</option>'}
function difficultyOptions(item){const values=unique(labor.filter(x=>x.mueble_tipo===item.tipo_mueble&&x.trabajo_tipo===item.trabajo_tipo).map(x=>x.complejidad));return values.length?'<option value="">Seleccionar...</option>'+opts(values,item.complejidad):'<option value="">Sin dificultades configuradas</option>'}
function applyLaborTemplate(item){const template=labor.find(x=>x.mueble_tipo===item.tipo_mueble&&x.trabajo_tipo===item.trabajo_tipo&&x.complejidad===item.complejidad);item.mano_obra_plantilla_id=template?.id||null;item.mano_obra_snapshot=template?.snapshot||null;if(template)item.mano_obra_unitaria=template.costo}
function draw(){
 $('#cliente').value=state.cliente_id||'';$('#detalle').value=state.detalle||'';$('#fecha').value=state.fecha||'';$('#margen').value=state.margen??30;
 $('#items').innerHTML=(state.items||[]).map((item,ii)=>`<section class="card tree item" data-item="${ii}"><details class="tree-details" open><summary><span class="node-title"><strong>Mueble ${ii+1}: ${esc(item.tipo_mueble.replaceAll('_',' '))}</strong><span class="collapse-arrow" aria-hidden="true">›</span><span class="node-actions"><button type="button" class="danger-btn remove-item">Eliminar</button></span></span></summary><div class="tree-content">
 <div class="item-grid"><label>Tipo de mueble<select class="item-type"><option value="">Seleccionar...</option>${opts(furniture,item.tipo_mueble)}</select></label><label>Tipo de trabajo<select class="item-work">${workOptions(item)}</select></label><label>Nivel de dificultad<select class="item-difficulty">${difficultyOptions(item)}</select></label><label>Cantidad<input class="item-qty" type="number" min="1" value="${Number(item.cantidad||1)}"></label><label>Mano de obra unitaria<input class="item-cost" type="number" min="0" step=".01" value="${Number(item.mano_obra_unitaria||0)}"></label></div>
 <div class="level-heading"><h3>Módulos</h3><button type="button" class="add-level add-module" title="Agregar módulo" aria-label="Agregar módulo">+</button></div>${(item.modulos||[]).map((m,mi)=>drawModule(m,mi)).join('')}</div></details></section>`).join('');bind();summary()
}
function drawModule(m,mi){return`<div class="tree module" data-module="${mi}"><details class="tree-details" open><summary><span class="node-title"><strong>Módulo: ${esc(m.tipo)}</strong><span class="collapse-arrow" aria-hidden="true">›</span><span class="node-actions"><button type="button" class="danger-btn remove-module">Eliminar</button></span></span></summary><div class="tree-content"><div class="module-grid"><label>Tipo<input class="module-type" value="${esc(m.tipo)}"></label><label>Alto cm<input class="module-h" type="number" min="0" value="${Number(m.alto||0)}"></label><label>Ancho cm<input class="module-w" type="number" min="0" value="${Number(m.ancho||0)}"></label><label>Profundidad cm<input class="module-d" type="number" min="0" value="${Number(m.profundidad||0)}"></label></div><div class="level-heading"><h4>Capas</h4><button type="button" class="add-level add-layer" title="Agregar capa" aria-label="Agregar capa">+</button></div>${(m.capas||[]).map((l,li)=>drawLayer(l,li)).join('')}</div></details></div>`}
function drawLayer(l,li){return`<div class="tree layer" data-layer="${li}"><details class="tree-details" open><summary><span class="node-title"><strong>Capa: ${esc((l.tipo||'').replaceAll('_',' '))}</strong><span class="collapse-arrow" aria-hidden="true">›</span><span class="node-actions"><button type="button" class="danger-btn remove-layer">Eliminar</button></span></span></summary><div class="tree-content"><label>Tipo de capa <select class="layer-type">${opts(layers,l.tipo)}</select></label><div class="level-heading"><h4>Insumos</h4><button type="button" class="add-level add-input" title="Agregar insumo" aria-label="Agregar insumo">+</button></div>${(l.insumos||[]).map((x,xi)=>`<div class="insumo-row" data-input="${xi}"><span><strong>${esc(x.nombre||'Insumo')}</strong><br><small>${esc(x.tipo||'')}${x.tipo==='fleje'?' · '+Number(x.tiras||0)+' tiras · '+Number(x.grapas_estimadas||0)+' grapas':''}</small></span><span>${Number(x.cantidad_final||x.cantidad_ajustada||0).toFixed(2)} ${esc(x.unidad||'')}</span><span>${money(x.costo_unitario_total||0)}</span><span><button type="button" class="secondary-btn edit-input">Editar</button> <button type="button" class="danger-btn remove-input">×</button></span></div>`).join('')}</div></details></div>`}
function indexes(el){return[Number(el.closest('[data-item]')?.dataset.item),Number(el.closest('[data-module]')?.dataset.module),Number(el.closest('[data-layer]')?.dataset.layer),Number(el.closest('[data-input]')?.dataset.input)]}
function bind(){
 document.querySelectorAll('[data-item]').forEach(n=>{const ii=Number(n.dataset.item),item=state.items[ii];$('.remove-item',n).onclick=()=>{state.items.splice(ii,1);draw()};$('.add-module',n).onclick=()=>{item.modulos.push(newModule());draw()};$('.item-type',n).onchange=e=>{item.tipo_mueble=e.target.value;item.trabajo_tipo='';item.complejidad='';item.mano_obra_plantilla_id=null;item.mano_obra_snapshot=null;item.mano_obra_unitaria=0;draw()};$('.item-work',n).onchange=e=>{item.trabajo_tipo=e.target.value;item.complejidad='';item.mano_obra_plantilla_id=null;item.mano_obra_snapshot=null;draw()};$('.item-difficulty',n).onchange=e=>{item.complejidad=e.target.value;applyLaborTemplate(item);draw()};$('.item-qty',n).oninput=e=>{item.cantidad=Math.max(1,Number(e.target.value||1));summary()};$('.item-cost',n).oninput=e=>{item.mano_obra_unitaria=Number(e.target.value||0);summary()}});
 document.querySelectorAll('[data-module]').forEach(n=>{const[ii,mi]=indexes(n),m=state.items[ii].modulos[mi];$('.remove-module',n).onclick=()=>{state.items[ii].modulos.splice(mi,1);draw()};$('.add-layer',n).onclick=()=>{m.capas.push({id:uid('layer'),tipo:layers[0]||'estructura',insumos:[]});draw()};[['.module-type','tipo'],['.module-h','alto'],['.module-w','ancho'],['.module-d','profundidad']].forEach(([s,k])=>$(s,n).oninput=e=>{m[k]=k==='tipo'?e.target.value:Number(e.target.value||0)})});
 document.querySelectorAll('[data-layer]').forEach(n=>{const[ii,mi,li]=indexes(n),l=state.items[ii].modulos[mi].capas[li];$('.remove-layer',n).onclick=()=>{state.items[ii].modulos[mi].capas.splice(li,1);draw()};$('.layer-type',n).onchange=e=>l.tipo=e.target.value;$('.add-input',n).onclick=()=>openModal(ii,mi,li,-1)});
 document.querySelectorAll('[data-input]').forEach(n=>{const[ii,mi,li,xi]=indexes(n);$('.edit-input',n).onclick=()=>openModal(ii,mi,li,xi);$('.remove-input',n).onclick=()=>{state.items[ii].modulos[mi].capas[li].insumos.splice(xi,1);draw()}});
}
function summary(){let work=0,materials=0;(state.items||[]).forEach(i=>{const q=Math.max(1,Number(i.cantidad||1));work+=Number(i.mano_obra_unitaria||0)*q;(i.modulos||[]).forEach(m=>(m.capas||[]).forEach(l=>(l.insumos||[]).forEach(x=>materials+=Number(x.costo_unitario_total||0)*q)))});$('#summary').textContent=`Mano de obra: ${money(work)} · Materiales: ${money(materials)} · Total estimado: ${money((work+materials)*(1+Number(state.margen||0)/100))}`}
function pieceRow(p={}){const r=document.createElement('div');r.className='piece-row';r.innerHTML=`<input class="p-name" placeholder="Pieza" value="${esc(p.pieza||'')}"><input class="p-h" type="number" min="0" placeholder="Alto cm" value="${Number(p.alto||0)}"><input class="p-w" type="number" min="0" placeholder="Ancho cm" value="${Number(p.ancho||0)}"><input class="p-q" type="number" min="1" value="${Number(p.cantidad||1)}"><button type="button" class="danger-btn">×</button>`;$('button',r).onclick=()=>r.remove();return r}
function filterSupplies(selectedId=''){const type=$('#mi-type').value,select=$('#mi-supply');[...select.options].forEach(option=>{if(!option.value)return;option.hidden=option.dataset.category!==type});const selected=[...select.options].find(option=>option.value===String(selectedId)&&!option.hidden);select.value=selected?selected.value:'';if(!select.value)$('#mi-cost').value=''}
function fields(selectedId=''){const type=$('#mi-type').value;document.querySelectorAll('[data-for]').forEach(x=>x.style.display=x.dataset.for.split(' ').includes(type)?'':'none');$('#direction-label').style.display=type==='fleje'&&$('#mi-pattern').value==='lineal'?'':'none';$('#pieces-section').style.display=['tela','gomaespuma','guata','fliselina','cierre','cordon'].includes(type)?'':'none';filterSupplies(selectedId);previewCalculation()}
function calculateInput(input,module){const type=input.tipo,waste=Math.max(0,Number(input.merma_pct||0)),manual=Math.max(0,Number(input.cantidad_ajustada||0));let base=0,strips=0,staples=0,origin='calculado';if(type==='fleje'){const height=Math.max(0,Number(module.alto||0))/100,width=Math.max(0,Number(module.ancho||0))/100,spacing=Math.max(.01,Number(input.separacion_cm||10)/100);const acrossWidth=Math.ceil(height/spacing),acrossLength=Math.ceil(width/spacing);if(input.patron==='cuadriculado'){strips=acrossWidth+acrossLength;base=acrossWidth*width+acrossLength*height}else if(input.direccion==='largo'){strips=acrossLength;base=strips*height}else{strips=acrossWidth;base=strips*width}staples=strips*2*Math.max(1,Number(input.grapas_por_extremo||2));origin='metros de fleje'}else{let area=0,linear=0;(input.piezas||[]).forEach(p=>{const q=Math.max(0,Number(p.cantidad||0)),h=Math.max(0,Number(p.alto||0))/100,w=Math.max(0,Number(p.ancho||0))/100;area+=h*w*q;linear+=Math.max(h,w)*q});base=area;if(['tela','guata','fliselina'].includes(type)){base=area/Math.max(.01,Number(input.ancho_util||1.4));origin='metros lineales'}else if(type==='gomaespuma'){base=Math.ceil((area/(Math.max(.01,Number(input.placa_largo||2))*Math.max(.01,Number(input.placa_ancho||1))))*4)/4;origin='placas'}else if(['cierre','cordon'].includes(type)){base=linear;origin='metros lineales'}}const calculated=base*(1+waste/100),finalQty=manual>0?manual:calculated,cost=finalQty*Math.max(0,Number(input.costo_unitario||0));return{cantidad_calculada:Number(calculated.toFixed(4)),cantidad_final:Number(finalQty.toFixed(4)),costo_unitario_total:Number(cost.toFixed(2)),tiras:strips,grapas_estimadas:staples,origen_cantidad:manual>0?'ajuste manual':origin}}
function modalInput(){return{tipo:$('#mi-type').value,costo_unitario:Number($('#mi-cost').value||0),merma_pct:Number($('#mi-waste').value||0),ancho_util:Number($('#mi-roll-width').value||0),placa_largo:Number($('#mi-sheet-length').value||0),placa_ancho:Number($('#mi-sheet-width').value||0),patron:$('#mi-pattern').value,direccion:$('#mi-direction').value,separacion_cm:Number($('#mi-spacing').value||0),grapas_por_extremo:Number($('#mi-staples').value||2),cantidad_ajustada:Number($('#mi-adjusted').value||0),piezas:[...document.querySelectorAll('.piece-row')].map(r=>({pieza:$('.p-name',r).value||'pieza',alto:Number($('.p-h',r).value||0),ancho:Number($('.p-w',r).value||0),cantidad:Number($('.p-q',r).value||1)})).filter(p=>p.alto>0&&p.ancho>0)}}
function previewCalculation(){if(!ctx)return;const input=modalInput(),module=state.items[ctx.ii].modulos[ctx.mi],result=calculateInput(input,module),host=$('#calculation-preview');if(input.tipo==='fleje'&&(!Number(module.alto)||!Number(module.ancho))){host.textContent='Completá el alto y el ancho del módulo para calcular el fleje.';return}host.textContent=input.tipo==='fleje'?('Consumo: '+result.cantidad_final.toFixed(2)+' m de cinta · '+result.tiras+' tiras · '+result.grapas_estimadas+' grapas · Costo: '+money(result.costo_unitario_total)):('Cantidad: '+result.cantidad_final.toFixed(2)+' · Costo: '+money(result.costo_unitario_total))}
function openModal(ii,mi,li,xi){ctx={ii,mi,li,xi};const layer=state.items[ii].modulos[mi].capas[li],x=xi>=0?layer.insumos[xi]:{};$('#modal-title').textContent=xi>=0?'Editar insumo':'Agregar insumo';$('#mi-type').innerHTML=opts(config.capas?.[layer.tipo]?.tipos_insumo_permitidos||fallbackTypes,x.tipo||'');$('#mi-supply').value=x.insumo_id||'';$('#mi-waste').value=x.merma_pct??10;$('#mi-cost').value=x.costo_unitario??0;$('#mi-roll-width').value=x.ancho_util??1.4;$('#mi-sheet-length').value=x.placa_largo??2;$('#mi-sheet-width').value=x.placa_ancho??1;$('#mi-pattern').value=x.patron??'lineal';$('#mi-direction').value=x.direccion??'ancho';$('#mi-spacing').value=x.separacion_cm??10;$('#mi-staples').value=x.grapas_por_extremo??2;$('#mi-adjusted').value=x.cantidad_ajustada??'';$('#mi-reason').value=x.motivo_ajuste??'';$('#pieces').innerHTML='';(x.piezas?.length?x.piezas:[{}]).forEach(p=>$('#pieces').append(pieceRow(p)));fields(x.insumo_id||'');$('#modal').classList.add('open')}
$('#mi-type').onchange=()=>fields();$('#mi-pattern').onchange=()=>{fields();previewCalculation()};$('#mi-supply').onchange=e=>{$('#mi-cost').value=e.target.selectedOptions[0]?.dataset.price||0;previewCalculation()};$('#add-piece').onclick=()=>{$('#pieces').append(pieceRow());previewCalculation()};$('#modal').addEventListener('input',previewCalculation);$('#modal').addEventListener('change',previewCalculation);$('#cancel-input').onclick=()=>$('#modal').classList.remove('open');
$('#save-input').onclick=()=>{const{ii,mi,li,xi}=ctx,sel=$('#mi-supply'),id=Number(sel.value);if(!id){alert('Seleccioná un insumo.');return}const adjusted=Number($('#mi-adjusted').value||0),reason=$('#mi-reason').value;if(adjusted>0&&!reason.trim()){alert('Indicá el motivo del ajuste.');return}const module=state.items[ii].modulos[mi];if($('#mi-type').value==='fleje'&&(!Number(module.alto)||!Number(module.ancho))){alert('Para calcular el fleje, completá el alto y el ancho del módulo.');return}const input=modalInput(),result=calculateInput(input,module);const x={id:xi>=0?state.items[ii].modulos[mi].capas[li].insumos[xi].id:uid('input'),...input,...result,insumo_id:id,nombre:sel.selectedOptions[0].textContent,unidad:sel.selectedOptions[0].dataset.unit,motivo_ajuste:reason};const l=state.items[ii].modulos[mi].capas[li];if(xi>=0)l.insumos[xi]=x;else l.insumos.push(x);$('#modal').classList.remove('open');draw()};
$('#add-item').onclick=()=>{state.items.push(newItem());draw()};['cliente','detalle','fecha','margen'].forEach(id=>$('#'+id).oninput=e=>{state[id==='cliente'?'cliente_id':id]=id==='cliente'?Number(e.target.value):id==='margen'?Number(e.target.value):e.target.value;summary()});$('#finalize').onclick=()=>$('#save-action').value='finalize';
$('#budget-form').onsubmit=e=>{state.cliente_id=Number($('#cliente').value);state.detalle=$('#detalle').value;state.fecha=$('#fecha').value;state.margen=Number($('#margen').value||0);if(!state.cliente_id||!state.items.length){e.preventDefault();alert('Seleccioná cliente y agregá al menos un mueble.');return}$('#payload').value=JSON.stringify(state)};
state.items=state.items||[];state.items.forEach(item=>{item.trabajo_tipo=item.trabajo_tipo||item.mano_obra_snapshot?.trabajo_tipo||'';item.complejidad=item.complejidad||item.mano_obra_snapshot?.complejidad||''});draw();
})();
</script>
<?php render_page_end(); ?>
