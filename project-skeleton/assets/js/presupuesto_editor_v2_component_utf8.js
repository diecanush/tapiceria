(() => {
const data=window.presupuestoV2Data||{};
const initial=data.initial||{};
const config=data.config||{};
let labor=data.labor||[];
let supplyCatalog=data.supplyCatalog||[];
const globalCustomCatalog=data.globalCustomCatalog||{};
const fallbackTypes=['tela','gomaespuma','madera','guata','fliselina','fleje','grapas','tachas','tornillos','cierre','cordon','adhesivo_contacto','otros'];
	let state=structuredClone(initial),ctx=null,autosaveTimer=null,autosaveBusy=false,autosaveAgain=false;
	const $=(s,r=document)=>r.querySelector(s), esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
	const uid=p=>p+'-'+Date.now().toString(36)+Math.random().toString(36).slice(2,6), money=v=>Number(v||0).toLocaleString('es-AR',{style:'currency',currency:'ARS'});
	function syncHeader(){state.cliente_id=Number($('#cliente').value||0);state.detalle=$('#detalle').value;state.fecha=$('#fecha').value;state.margen=Number($('#margen').value||0)}
	function setAutosaveStatus(text){const host=$('#autosave-status');if(host)host.textContent=text}
	function autosave(){
		syncHeader();
		if(!state.cliente_id||!state.items.length){setAutosaveStatus('Completá cliente y al menos un mueble para activar el autoguardado.');return}
		if(autosaveBusy){autosaveAgain=true;return}
		autosaveBusy=true;autosaveAgain=false;setAutosaveStatus('Guardando automáticamente...');
		const body=new FormData();body.set('action','autosave');body.set('id',String(state.id||0));body.set('presupuesto_payload',JSON.stringify(state));
		fetch(window.location.href,{method:'POST',body,credentials:'same-origin',headers:{'Accept':'application/json'}}).then(response=>response.json()).then(result=>{
			if(!result.ok){setAutosaveStatus('Autoguardado pendiente: faltan datos obligatorios.');return}
			if(result.id){state.id=Number(result.id);const idField=$("input[name='id']");if(idField)idField.value=state.id}
			setAutosaveStatus('Guardado automáticamente a las '+new Date().toLocaleTimeString('es-AR',{hour:'2-digit',minute:'2-digit'}));
		}).catch(()=>setAutosaveStatus('No se pudo autoguardar. Revisá la conexión y guardá manualmente.')).finally(()=>{autosaveBusy=false;if(autosaveAgain)queueAutosave()})
	}
	function queueAutosave(){clearTimeout(autosaveTimer);setAutosaveStatus('Cambios pendientes de guardar...');autosaveTimer=setTimeout(autosave,800)}
	function persistCatalogValue(type,value){const body=new FormData();body.set('action','catalog_save');body.set('catalog_type',type);body.set('value',value);fetch(window.location.href,{method:'POST',body,credentials:'same-origin',headers:{'Accept':'application/json'}}).then(response=>response.json()).then(result=>{if(result.ok&&result.catalog){Object.keys(result.catalog).forEach(key=>{state.catalogos_personalizados[key]=[...new Set([...(state.catalogos_personalizados[key]||[]),...(result.catalog[key]||[])])]})}}).catch(()=>setAutosaveStatus('El valor se agregó al presupuesto, pero no se pudo actualizar el catálogo global.'))}
let furniture=Object.keys(config.muebles||{}), layers=Object.keys(config.capas||{});
state.catalogos_personalizados=state.catalogos_personalizados||{muebles:[],trabajos:[],dificultades:[],capas:[],modulos:[],tipos_insumo:[]};
['muebles','trabajos','dificultades','capas','modulos','tipos_insumo'].forEach(key=>{state.catalogos_personalizados[key]=[...new Set([...(globalCustomCatalog[key]||[]),...(state.catalogos_personalizados[key]||[])])]});
furniture=[...new Set([...furniture,...(state.catalogos_personalizados.muebles||[])])];
layers=[...new Set([...layers,...(state.catalogos_personalizados.capas||[])])];
function normalizeModuleType(value){return String(value||'').trim().replace(/\s+/g,'_').toLowerCase()}
function moduleLabel(value){const label=String(value||'').replaceAll('_',' ');return label?label.charAt(0).toUpperCase()+label.slice(1):label}
let moduleCatalog=[...new Set([...(state.catalogos_personalizados.modulos||[]),...Object.values(config.muebles||{}).flatMap(entry=>(entry.modulos_default||[]).map(value=>typeof value==='string'?value:(value.tipo||value.nombre||'')))].map(normalizeModuleType).filter(Boolean))];
const opts=(values,current)=>values.map(v=>`<option value="${esc(v)}" ${v===current?'selected':''}>${esc(v.replaceAll('_',' '))}</option>`).join('');
const addOption='<option value="__add__">+ Agregar...</option>';
function normalizeSupplyType(value){return normalizeModuleType(value)}
function supplyTypeOptions(current,allowed=[]){const values=unique([...allowed,...fallbackTypes,...(state.catalogos_personalizados.tipos_insumo||[]),current]);return '<option value="">Seleccionar...</option>'+opts(values,current)+addOption}
function ensureSupplyCategoryOption(value){const select=$('#catalog-supply-category'),normalized=normalizeSupplyType(value);if(!select||!normalized)return;if(![...select.options].some(option=>option.value===normalized))select.insertAdjacentHTML('beforeend',`<option value="${esc(normalized)}">${esc(moduleLabel(normalized))}</option>`);select.value=normalized}
function moduleOptions(current){return '<option value="">Seleccionar...</option>'+moduleCatalog.map(v=>`<option value="${esc(v)}" ${v===current?'selected':''}>${esc(moduleLabel(v))}</option>`).join('')+addOption}
function newModule(type='modulo',quantity=1){return{id:uid('mod'),tipo:normalizeModuleType(type)||'modulo',alto:0,ancho:0,profundidad:0,cantidad:Math.max(1,Number(quantity||1)),capas:[]}}
function defaultModulesForFurniture(type){const defaults=config.muebles?.[type]?.modulos_default||[];return defaults.map(entry=>typeof entry==='string'?newModule(entry):newModule(entry.tipo||entry.nombre||'modulo',entry.cantidad||1))}
function newItem(){return{id:uid('item'),tipo_mueble:'',trabajo_tipo:'',complejidad:'',cantidad:1,mano_obra_unitaria:0,mano_obra_plantilla_id:null,mano_obra_snapshot:null,modulos:[]}}
function unique(values){return [...new Set(values.filter(Boolean))]}
function workOptions(item){const values=unique([...labor.map(x=>x.trabajo_tipo),...(state.catalogos_personalizados.trabajos||[]),item.trabajo_tipo]);return '<option value="">Seleccionar...</option>'+opts(values,item.trabajo_tipo)+addOption}
		function difficultyOptions(item){const values=unique([...labor.map(x=>x.complejidad),...(state.catalogos_personalizados.dificultades||[]),item.complejidad]);return '<option value="">Seleccionar...</option>'+opts(values,item.complejidad)+addOption}
	const laborTasks=['desarme','soporte_elastico','confort_gomaespuma','confort_guata','cobertura_corte_tela','cobertura_confeccion_tela','terminacion'];
	const laborTaskLabels={desarme:'Desarme',soporte_elastico:'Soporte elástico',confort_gomaespuma:'Confort gomaespuma',confort_guata:'Confort guata',cobertura_corte_tela:'Corte tela',cobertura_confeccion_tela:'Confección tela',terminacion:'Terminación'};
	function laborTemplate(record){const snapshot=record.snapshot||record,minutes=snapshot.tiempos_minutos||{};const total=laborTasks.reduce((sum,key)=>sum+Number(minutes[key]||0),0);return{...record,id:Number(record.id||snapshot.id||0),mueble_tipo:record.mueble_tipo||snapshot.mueble_tipo||'',trabajo_tipo:record.trabajo_tipo||snapshot.trabajo_tipo||'',complejidad:record.complejidad||snapshot.complejidad||'complejidad_1',costo:record.costo??(total/60*Number(snapshot.tarifa_hora||0)),snapshot}}
	function openLaborCatalog(ii){const item=state.items[ii],selected=labor.find(x=>x.mueble_tipo===item.tipo_mueble&&x.trabajo_tipo===item.trabajo_tipo&&x.complejidad===item.complejidad);$('#catalog-title').textContent=selected?'Modificar mano de obra':'Nueva mano de obra';$('#supply-catalog-form').style.display='none';$('#labor-catalog-form').style.display='grid';$('#catalog-labor-id').value=selected?.id||'';$('#catalog-labor-furniture').value=item.tipo_mueble||'';$('#catalog-labor-work').value=item.trabajo_tipo||'';$('#catalog-labor-complexity').value=item.complejidad||'complejidad_1';const raw=selected?.snapshot||{};$('#catalog-labor-rate').value=raw.tarifa_hora??0;$('#catalog-labor-tasks').innerHTML=laborTasks.map(task=>`<label>${laborTaskLabels[task]}<input type="number" min="0" step="1" data-labor-task="${task}" value="${Number(raw.tiempos_minutos?.[task]||0)}"></label>`).join('');$('#catalog-modal').classList.add('open');$('#catalog-modal').dataset.laborItem=String(ii)}
	function fillSupplyCatalog(id=0){const current=supplyCatalog.find(x=>Number(x.id)===Number(id));const category=current?.categoria||$('#mi-type').value||'otros';$('#catalog-supply-id').value=current?.id||'';$('#catalog-supply-name').value=current?.nombre||'';$('#catalog-supply-unit').value=current?.unidad||'unidad';ensureSupplyCategoryOption(category);$('#catalog-supply-price').value=current?.precio??0;$('#catalog-supply-stock').value=current?.stock??0;$('#catalog-supply-min').value=current?.stock_minimo??0;$('#catalog-title').textContent=current?'Modificar insumo':'Nuevo insumo'}
	function openSupplyCatalog(id=0){$('#supply-catalog-form').style.display='grid';$('#labor-catalog-form').style.display='none';$('#catalog-existing-supply').value=id?String(id):'';fillSupplyCatalog(id);$('#catalog-modal').classList.add('open')}
	function refreshSupplyOptions(selectedId=''){const select=$('#mi-supply');select.innerHTML='<option value="">Seleccionar...</option>'+supplyCatalog.map(s=>`<option value="${Number(s.id)}" data-price="${Number(s.precio||0)}" data-unit="${esc(s.unidad||'unidad')}" data-category="${esc(s.categoria||'otros')}">${esc(s.nombre||'Insumo')}</option>`).join('');const catalog=$('#catalog-existing-supply');if(catalog)catalog.innerHTML='<option value="">Nuevo insumo</option>'+supplyCatalog.map(s=>`<option value="${Number(s.id)}">${esc(s.nombre||'Insumo')}</option>`).join('');filterSupplies(selectedId)}
function applyLaborTemplate(item){const template=labor.find(x=>x.mueble_tipo===item.tipo_mueble&&x.trabajo_tipo===item.trabajo_tipo&&x.complejidad===item.complejidad);item.mano_obra_plantilla_id=template?.id||null;item.mano_obra_snapshot=template?.snapshot||null;if(template)item.mano_obra_unitaria=template.costo}
function addLayerOptions(){document.querySelectorAll('.layer-type').forEach(select=>{if(![...select.options].some(option=>option.value==='__add__'))select.insertAdjacentHTML('beforeend',addOption)})}
function draw(keep=null){
 $('#cliente').value=state.cliente_id||'';$('#detalle').value=state.detalle||'';$('#fecha').value=state.fecha||'';$('#margen').value=state.margen??30;
 $('#items').innerHTML=(state.items||[]).map((item,ii)=>`<section class="card tree item" data-item="${ii}"><details class="tree-details"><summary><span class="node-title"><span class="collapse-arrow" aria-hidden="true">›</span><div class="node-fields" onclick="event.stopPropagation()"><label>Mueble<select class="item-type"><option value="">Seleccionar...</option>${opts(furniture,item.tipo_mueble)}${addOption}</select></label><label>Trabajo<select class="item-work">${workOptions(item)}</select></label><label>Dificultad<select class="item-difficulty">${difficultyOptions(item)}</select></label><label>Cantidad<input class="item-qty" type="number" min="1" value="${Number(item.cantidad||1)}"></label><label>Mano de obra<input class="item-cost" type="number" min="0" step=".01" value="${Number(item.mano_obra_unitaria||0)}"></label></div><span class="node-actions"><button type="button" class="secondary-btn manage-labor">Plantilla</button><button type="button" class="danger-btn remove-item">Eliminar</button></span></span></summary><div class="tree-content">
 <div class="level-heading"><h3>Módulos</h3><button type="button" class="add-level add-module" title="Agregar módulo" aria-label="Agregar módulo">+</button></div>${(item.modulos||[]).map((m,mi)=>drawModule(m,mi)).join('')}</div></details></section>`).join('');bind();summary()
}
function drawModule(m,mi){return`<div class="tree module" data-module="${mi}"><details class="tree-details"><summary><span class="node-title"><span class="collapse-arrow" aria-hidden="true">›</span><div class="node-fields" onclick="event.stopPropagation()"><label>Tipo<select class="module-type">${moduleOptions(m.tipo)}</select></label><label>Alto<input class="module-h" type="number" min="0" value="${Number(m.alto||0)}"></label><label>Ancho<input class="module-w" type="number" min="0" value="${Number(m.ancho||0)}"></label><label>Profundidad<input class="module-d" type="number" min="0" value="${Number(m.profundidad||0)}"></label><label>Cantidad<input class="module-qty" type="number" min="1" step="1" value="${Math.max(1,Number(m.cantidad||1))}"></label></div><span class="node-actions"><button type="button" class="danger-btn remove-module">Eliminar</button></span></span></summary><div class="tree-content"><div class="level-heading"><h4>Capas</h4><button type="button" class="add-level add-layer" title="Agregar capa" aria-label="Agregar capa">+</button></div>${(m.capas||[]).map((l,li)=>drawLayer(l,li)).join('')}</div></details></div>`}
function drawLayer(l,li){return`<div class="tree layer" data-layer="${li}"><details class="tree-details"><summary><span class="node-title"><span class="collapse-arrow" aria-hidden="true">›</span><div class="node-fields" onclick="event.stopPropagation()"><label>Tipo de capa<select class="layer-type">${opts(layers,l.tipo)}</select></label><label>Insumos<strong>${(l.insumos||[]).length}</strong></label></div><span class="node-actions"><button type="button" class="danger-btn remove-layer">Eliminar</button></span></span></summary><div class="tree-content"><div class="level-heading"><h4>Insumos</h4><button type="button" class="add-level add-input" title="Agregar insumo" aria-label="Agregar insumo">+</button></div>${(l.insumos||[]).map((x,xi)=>`<div class="insumo-row" data-input="${xi}"><span><strong>${esc(x.nombre||'Insumo')}</strong><br><small>${esc(x.tipo||'')}${x.tipo==='fleje'?' · '+Number(x.tiras||0)+' tiras · '+Number(x.grapas_estimadas||0)+' grapas':''}</small></span><span>${Number(x.cantidad_final||x.cantidad_ajustada||0).toFixed(2)} ${esc(x.unidad||'')}</span><span>${money(x.costo_unitario_total||0)}</span><span><button type="button" class="secondary-btn edit-input">Editar</button> <button type="button" class="danger-btn remove-input">×</button></span></div>`).join('')}</div></details></div>`}
function drawLayer(l,li){const buttons=(l.insumos||[]).map((x,xi)=>`<button type="button" class="secondary-btn summary-input-button" data-input-index="${xi}" onclick="event.stopPropagation()">${esc(x.nombre||'Insumo')} · ${money(x.costo_unitario_total||0)}</button>`).join('');return`<div class="tree layer" data-layer="${li}"><details class="tree-details"><summary><span class="node-title"><span class="collapse-arrow" aria-hidden="true">›</span><div class="node-fields" onclick="event.stopPropagation()"><label>Tipo de capa<select class="layer-type">${opts(layers,l.tipo)}</select></label><div class="layer-inputs-summary"><span>Insumos</span>${buttons}<button type="button" class="add-level summary-add-input" title="Agregar insumo" aria-label="Agregar insumo" onclick="event.stopPropagation()">+</button></div></div><span class="node-actions"><button type="button" class="danger-btn remove-layer">Eliminar</button></span></span></summary><div class="tree-content">${(l.insumos||[]).map((x,xi)=>`<div class="insumo-row" data-input="${xi}"><span><strong>${esc(x.nombre||'Insumo')}</strong><br><small>${esc(x.tipo||'')}${x.tipo==='fleje'?' · '+Number(x.tiras||0)+' tiras · '+Number(x.grapas_estimadas||0)+' grapas':''}</small></span><span>${Number(x.cantidad_final||x.cantidad_ajustada||0).toFixed(2)} ${esc(x.unidad||'')}</span><span>${money(x.costo_unitario_total||0)}</span><span><button type="button" class="secondary-btn edit-input">Editar</button> <button type="button" class="danger-btn remove-input">×</button></span></div>`).join('')}</div></details></div>`}
function restoreOpenBranches(keep){if(!keep)return;const itemDetails=document.querySelector(`[data-item="${keep.ii}"]>.tree-details`),moduleDetails=document.querySelector(`[data-item="${keep.ii}"] [data-module="${keep.mi}"]>.tree-details`),layerDetails=document.querySelector(`[data-item="${keep.ii}"] [data-module="${keep.mi}"] [data-layer="${keep.li}"]>.tree-details`);if(itemDetails)itemDetails.open=true;if(moduleDetails)moduleDetails.open=true;if(layerDetails)layerDetails.open=true}
function captureOpenBranches(){return[...document.querySelectorAll('.tree-details[open]')].map(details=>{const item=details.closest('[data-item]'),module=details.closest('[data-module]'),layer=details.closest('[data-layer]');return{ii:item?.dataset.item,mi:module?.dataset.module,li:layer?.dataset.layer}})}
function restoreCapturedBranches(branches){(branches||[]).forEach(branch=>{const item=document.querySelector(`[data-item="${branch.ii}"]`),module=branch.mi===undefined?null:item?.querySelector(`[data-module="${branch.mi}"]`),layer=branch.li===undefined?null:module?.querySelector(`[data-layer="${branch.li}"]`);const details=layer?.querySelector(':scope > .tree-details')||module?.querySelector(':scope > .tree-details')||item?.querySelector(':scope > .tree-details');if(details)details.open=true})}
function indexes(el){return[Number(el.closest('[data-item]')?.dataset.item),Number(el.closest('[data-module]')?.dataset.module),Number(el.closest('[data-layer]')?.dataset.layer),Number(el.closest('[data-input]')?.dataset.input)]}
	function bind(){}
class BudgetTreeComponent {
	constructor(legacyRender) {
		this.legacyRender = legacyRender;
		this.root = $('#items');
		this.root.addEventListener('click', event => this.handleClick(event), true);
		this.root.addEventListener('change', event => this.handleChange(event), true);
		this.root.addEventListener('input', event => this.handleInput(event), true);
	}
	pathFrom(node) {
		const item = node?.closest('[data-item]');
		const module = node?.closest('[data-module]');
		const layer = node?.closest('[data-layer]');
		return {ii:item ? Number(item.dataset.item) : null,mi:module ? Number(module.dataset.module) : null,li:layer ? Number(layer.dataset.layer) : null};
	}
	captureOpen() {
		return [...this.root.querySelectorAll('.tree-details[open]')].map(details => {
			const p = this.pathFrom(details);
			return p.mi === null ? {ii:p.ii} : p.li === null ? {ii:p.ii,mi:p.mi} : p;
		});
	}
	restoreOpen(paths) {
		(paths || []).forEach(path => {
			const item = this.root.querySelector('[data-item="' + path.ii + '"]');
			const module = path.mi === undefined ? null : item?.querySelector('[data-module="' + path.mi + '"]');
			const layer = path.li === undefined || !module ? null : module.querySelector('[data-layer="' + path.li + '"]');
			const itemDetails = item?.querySelector(':scope > .tree-details');
			const moduleDetails = module?.querySelector(':scope > .tree-details');
			const layerDetails = layer?.querySelector(':scope > .tree-details');
			if (itemDetails) itemDetails.open = true;
			if (moduleDetails) moduleDetails.open = true;
			if (layerDetails) layerDetails.open = true;
		});
	}
	render(resetOpen=false, focus=null) {
		const open = resetOpen ? [] : this.captureOpen();
		if (focus) open.push(focus);
		this.legacyRender();
		addLayerOptions();
		this.restoreOpen(open);
		summary();
	}
	locate(node) {
		const path = this.pathFrom(node);
		return {path,item:state.items[path.ii],module:state.items[path.ii]?.modulos?.[path.mi],layer:state.items[path.ii]?.modulos?.[path.mi]?.capas?.[path.li]};
	}
	stop(event) { event.preventDefault(); event.stopPropagation(); }
	handleClick(event) {
		const button = event.target.closest('button');
		if (!button || !this.root.contains(button)) return;
		const {path,item,module,layer} = this.locate(button);
		if (button.matches('.remove-item') && item) {
			state.items.splice(path.ii,1); this.render();
		} else if (button.matches('.manage-labor') && item) {
			openLaborCatalog(path.ii);
		} else if (button.matches('.add-module') && item) {
			item.modulos=item.modulos||[]; item.modulos.push(newModule());
			this.render(false,{ii:path.ii,mi:item.modulos.length-1}); queueAutosave();
		} else if (button.matches('.remove-module') && module) {
			item.modulos.splice(path.mi,1); this.render();
		} else if (button.matches('.add-layer') && module) {
			module.capas=module.capas||[]; module.capas.push({id:uid('layer'),tipo:layers[0]||'estructura_suspension',insumos:[]});
			this.render(false,{ii:path.ii,mi:path.mi,li:module.capas.length-1}); queueAutosave();
		} else if (button.matches('.remove-layer') && module) {
			module.capas.splice(path.li,1); this.render();
		} else if (button.matches('.summary-add-input,.add-input') && layer) {
			openModal(path.ii,path.mi,path.li,-1);
		} else if (button.matches('.summary-input-button') && layer) {
			openModal(path.ii,path.mi,path.li,Number(button.dataset.inputIndex));
		} else if (button.matches('.edit-input') && layer) {
			openModal(path.ii,path.mi,path.li,Number(button.closest('[data-input]')?.dataset.input));
		} else if (button.matches('.remove-input') && layer) {
			layer.insumos.splice(Number(button.closest('[data-input]')?.dataset.input),1); this.render();
		} else return;
		this.stop(event); queueAutosave();
	}
	handleChange(event) {
		const target=event.target;
		if (target.matches('.item-type,.item-work,.item-difficulty')) {
			const {item}=this.locate(target); if (!item) return;
			let value=target.value;
			const catalog=target.matches('.item-type')?'muebles':target.matches('.item-work')?'trabajos':'dificultades';
			const promptText=target.matches('.item-type')?'Nombre del nuevo tipo de mueble:':target.matches('.item-work')?'Nombre del nuevo tipo de trabajo:':'Nombre del nuevo nivel de dificultad:';
			if (value==='__add__') {
				const custom=prompt(promptText);
				if (!custom?.trim()) { this.render(); this.stop(event); return; }
				value=target.matches('.item-type')?custom.trim().replace(/\s+/g,'_'):custom.trim();
				if (!state.catalogos_personalizados[catalog].includes(value)) state.catalogos_personalizados[catalog].push(value);
				persistCatalogValue(catalog,value);
				if (target.matches('.item-type') && !furniture.includes(value)) furniture.push(value);
			}
			if (target.matches('.item-type')) {
				const empty=!item.modulos?.length;
				item.tipo_mueble=value; item.trabajo_tipo=''; item.complejidad=''; item.mano_obra_plantilla_id=null; item.mano_obra_snapshot=null; item.mano_obra_unitaria=0;
				if (empty) item.modulos=defaultModulesForFurniture(value);
			} else if (target.matches('.item-work')) {
				item.trabajo_tipo=value; item.complejidad=''; item.mano_obra_plantilla_id=null; item.mano_obra_snapshot=null;
			} else { item.complejidad=value; applyLaborTemplate(item); }
			this.render(); this.stop(event); queueAutosave(); return;
		}
		if (target.matches('.module-type')) {
			const {module}=this.locate(target); if (!module) return;
			let value=target.value;
			if (value==='__add__') {
				const custom=prompt('Nombre del nuevo tipo de módulo:');
				if (!custom?.trim()) { this.render(); this.stop(event); return; }
				value=normalizeModuleType(custom);
				if (!moduleCatalog.includes(value)) moduleCatalog.push(value);
				if (!state.catalogos_personalizados.modulos.includes(value)) state.catalogos_personalizados.modulos.push(value);
				persistCatalogValue('modulos',value);
			}
			module.tipo=normalizeModuleType(value); this.render(); this.stop(event); queueAutosave(); return;
		}
		if (target.matches('.layer-type')) {
			const {layer}=this.locate(target); if (!layer) return;
			let value=target.value;
			if (value==='__add__') {
				const custom=prompt('Nombre de la nueva capa:');
				if (!custom?.trim()) { this.render(); this.stop(event); return; }
				value=custom.trim().replace(/\s+/g,'_');
				if (!layers.includes(value)) layers.push(value);
				if (!state.catalogos_personalizados.capas.includes(value)) state.catalogos_personalizados.capas.push(value);
				persistCatalogValue('capas',value);
			}
			layer.tipo=value; this.render(); this.stop(event); queueAutosave();
		}
	}
	handleInput(event) {
		const target=event.target,{item,module}=this.locate(target);
		if (target.matches('.item-qty') && item) item.cantidad=Math.max(1,Number(target.value||1));
		else if (target.matches('.item-cost') && item) item.mano_obra_unitaria=Number(target.value||0);
		else if (target.matches('.module-h') && module) module.alto=Math.max(0,Number(target.value||0));
		else if (target.matches('.module-w') && module) module.ancho=Math.max(0,Number(target.value||0));
		else if (target.matches('.module-d') && module) module.profundidad=Math.max(0,Number(target.value||0));
		else if (target.matches('.module-qty') && module) module.cantidad=Math.max(1,Number(target.value||1));
		else return;
		summary(); queueAutosave();
	}
}
const legacyTreeRender=draw;
const treeComponent=new BudgetTreeComponent(legacyTreeRender);
draw=function(resetOpen=false){treeComponent.render(resetOpen)};
function summary(){let work=0,materials=0;(state.items||[]).forEach(i=>{const q=Math.max(1,Number(i.cantidad||1));work+=Number(i.mano_obra_unitaria||0)*q;(i.modulos||[]).forEach(m=>(m.capas||[]).forEach(l=>(l.insumos||[]).forEach(x=>materials+=Number(x.costo_unitario_total||0)*q)))});const subtotal=work+materials,marginPercent=Math.max(0,Number(state.margen||0)),marginAmount=subtotal*marginPercent/100,total=subtotal+marginAmount;$('#summary').textContent=`Mano de obra: ${money(work)} · Materiales: ${money(materials)} · Subtotal: ${money(subtotal)} · Margen (${marginPercent}%): ${money(marginAmount)} · Total estimado: ${money(total)}`}
summary=function(){let work=0,materials=0;(state.items||[]).forEach(i=>{const q=Math.max(1,Number(i.cantidad||1));work+=Number(i.mano_obra_unitaria||0)*q;(i.modulos||[]).forEach(m=>(m.capas||[]).forEach(l=>(l.insumos||[]).forEach(x=>materials+=Number(x.costo_unitario_total||0)*Math.max(1,Number(m.cantidad||1))*q)))});const subtotal=work+materials,marginPercent=Math.max(0,Number(state.margen||0)),marginAmount=subtotal*marginPercent/100,total=subtotal+marginAmount;$('#summary').textContent=`Mano de obra: ${money(work)} · Materiales: ${money(materials)} · Subtotal: ${money(subtotal)} · Margen (${marginPercent}%): ${money(marginAmount)} · Total estimado: ${money(total)}`}
function foamThickness(name){const match=String(name||'').match(/(\d+(?:[.,]\d+)?)\s*cm/i);return match?Number(match[1].replace(',','.')):0}
function edgeDefaults(type){const value=['tela','guata','fliselina','cierre','cordon'].includes(type)?'ambos':'ninguno';return{superior:value,derecho:value,inferior:value,izquierdo:value}}
function edgeAllowance(value){return value==='fijacion'?5:value==='costura'?2:value==='ambos'?7:0}
function rawPieceDimensions(pieza,type,module,supplyName=''){const name=String(pieza||'pieza').toLowerCase();const h=Math.max(0,Number(module?.alto||0)),w=Math.max(0,Number(module?.ancho||0)),d=Math.max(0,Number(module?.profundidad||0));let a=h||w,b=w||d;if(name.includes('lateral')||name.includes('lado')){a=h;b=d}else if(name.includes('frente')||name.includes('trasera')||name.includes('dorso')){a=h;b=w||d}else if(name.includes('superior')||name.includes('inferior')||name.includes('asiento')){a=w||h;b=d||w}if(type==='gomaespuma'){const thickness=foamThickness(supplyName);if(thickness>1&&thickness<5){a=Math.max(0,a-5);b=Math.max(0,b-5)}}return{alto:a,ancho:b}}
function dimensionsWithEdges(base,bordes){return{alto:Math.max(0,base.alto+edgeAllowance(bordes.superior)+edgeAllowance(bordes.inferior)),ancho:Math.max(0,base.ancho+edgeAllowance(bordes.izquierdo)+edgeAllowance(bordes.derecho))}}
function defaultPiece(pieza,type,module,supplyName='',bordes=null){const edges=bordes||edgeDefaults(type),base=rawPieceDimensions(pieza,type,module,supplyName),dimensions=dimensionsWithEdges(base,edges);return{...dimensions,bordes:edges,rotatable:true}}
function edgeSelect(className,value){return`<select class="${className}"><option value="ninguno" ${value==='ninguno'?'selected':''}>Ninguno</option><option value="costura" ${value==='costura'?'selected':''}>Costura +2</option><option value="fijacion" ${value==='fijacion'?'selected':''}>Fijación +5</option><option value="ambos" ${value==='ambos'?'selected':''}>Ambos +7</option></select>`}
function pieceRow(p={},type=$('#mi-type')?.value||'',module=ctx?state.items[ctx.ii].modulos[ctx.mi]:null){const hasDimensions=Number(p.alto||0)>0&&Number(p.ancho||0)>0;const defaults=defaultPiece(p.pieza,type,module,$('#mi-supply')?.selectedOptions[0]?.textContent||'',p.bordes);const row={...defaults,...p,alto:hasDimensions?Number(p.alto):defaults.alto,ancho:hasDimensions?Number(p.ancho):defaults.ancho,bordes:p.bordes||defaults.bordes,rotatable:p.rotatable??p.rotable??true};const r=document.createElement('div');r.className='piece-row';r.dataset.manual=hasDimensions?'1':'0';r.innerHTML=`<input class="p-name" placeholder="Pieza" value="${esc(row.pieza||'pieza')}"><input class="p-h" type="number" min="0" placeholder="Alto cm" value="${Number(row.alto||0)}"><input class="p-w" type="number" min="0" placeholder="Ancho cm" value="${Number(row.ancho||0)}"><input class="p-q" type="number" min="1" value="${Number(row.cantidad||1)}"><label style="text-align:center"><input class="p-rotate" type="checkbox" ${row.rotatable!==false?'checked':''} aria-label="Permitir rotación 90 grados"></label><button type="button" class="danger-btn">×</button><div class="piece-edges"><label>Sup. ${edgeSelect('p-edge-top',row.bordes.superior)}</label><label>Der. ${edgeSelect('p-edge-right',row.bordes.derecho)}</label><label>Inf. ${edgeSelect('p-edge-bottom',row.bordes.inferior)}</label><label>Izq. ${edgeSelect('p-edge-left',row.bordes.izquierdo)}</label></div>`;$('button',r).onclick=()=>r.remove();$('.p-name',r).oninput=()=>{if(r.dataset.manual==='0'){const d=defaultPiece($('.p-name',r).value,type,module,$('#mi-supply')?.selectedOptions[0]?.textContent||'',row.bordes);$('.p-h',r).value=d.alto;$('.p-w',r).value=d.ancho}};['.p-h','.p-w'].forEach(selector=>$(selector,r).oninput=()=>{r.dataset.manual='1'});return r}
function filterSupplies(selectedId=''){const type=$('#mi-type').value,select=$('#mi-supply');[...select.options].forEach(option=>{if(!option.value)return;option.hidden=option.dataset.category!==type});const selected=[...select.options].find(option=>option.value===String(selectedId)&&!option.hidden);select.value=selected?selected.value:'';if(!select.value)$('#mi-cost').value=''}
function fields(selectedId=''){const type=$('#mi-type').value;document.querySelectorAll('[data-for]').forEach(x=>x.style.display=x.dataset.for.split(' ').includes(type)?'':'none');$('#direction-label').style.display=type==='fleje'&&$('#mi-pattern').value==='lineal'?'':'none';$('#pieces-section').style.display=['tela','gomaespuma','madera','guata','fliselina','cierre','cordon'].includes(type)?'':'none';filterSupplies(selectedId);previewCalculation()}
function linearPieceMeters(pieces,rollWidth){
 const rectangles=[],width=Math.max(.01,Number(rollWidth||1.4));let fallback=0;
 (pieces||[]).forEach(p=>{const q=Math.max(0,Math.round(Number(p.cantidad||0))),a=Math.max(0,Number(p.alto||0))/100,b=Math.max(0,Number(p.ancho||0))/100,rotatable=p.rotatable!==false&&p.rotable!==false;const candidates=[{length:a,width:b}];if(rotatable&&a!==b)candidates.push({length:b,width:a});const valid=candidates.filter(option=>option.width<=width);if(!valid.length){fallback+=(a*b/width)*q;return}for(let copy=0;copy<q;copy++)rectangles.push({options:valid})});
 if(!rectangles.length)return fallback;
 const pack=chosen=>{const shelves=[];chosen.slice().sort((a,b)=>Math.max(b.length,b.width)-Math.max(a.length,a.width)).forEach(rect=>{let best=-1,increase=Infinity;for(let i=0;i<shelves.length;i++){const shelf=shelves[i];if(shelf.width+rect.width<=width){const added=Math.max(shelf.length,rect.length)-shelf.length;if(added<increase){increase=added;best=i}}}if(best<0)shelves.push({width:rect.width,length:rect.length});else{shelves[best].width+=rect.width;shelves[best].length=Math.max(shelves[best].length,rect.length)}});return shelves.reduce((total,shelf)=>total+shelf.length,0)};
 let best=Infinity;
 if(rectangles.length<=12){const search=(index,chosen)=>{if(index===rectangles.length){best=Math.min(best,pack(chosen));return}rectangles[index].options.forEach(option=>search(index+1,[...chosen,option]))};search(0,[])}else best=pack(rectangles.map(rect=>rect.options.reduce((shortest,option)=>option.length<shortest.length?option:shortest)));
 return best+fallback;
}
function calculateInput(input,module){const type=input.tipo,waste=Math.max(0,Number(input.merma_pct||0)),manual=Math.max(0,Number(input.cantidad_ajustada||0));let base=0,strips=0,staples=0,origin='calculado';if(type==='fleje'){const height=Math.max(0,Number(module.alto||0))/100,width=Math.max(0,Number(module.ancho||0))/100,spacing=Math.max(.01,Number(input.separacion_cm||10)/100);const acrossWidth=Math.ceil(height/spacing),acrossLength=Math.ceil(width/spacing);if(input.patron==='cuadriculado'){strips=acrossWidth+acrossLength;base=acrossWidth*width+acrossLength*height}else if(input.direccion==='largo'){strips=acrossLength;base=strips*height}else{strips=acrossWidth;base=strips*width}staples=strips*2*Math.max(1,Number(input.grapas_por_extremo||2));origin='metros de fleje'}else{let area=0,linear=0;(input.piezas||[]).forEach(p=>{const q=Math.max(0,Number(p.cantidad||0)),h=Math.max(0,Number(p.alto||0))/100,w=Math.max(0,Number(p.ancho||0))/100;area+=h*w*q;linear+=Math.max(h,w)*q});base=area;if(['tela','guata','fliselina'].includes(type)){base=area/Math.max(.01,Number(input.ancho_util||1.4));origin='metros lineales'}else if(['gomaespuma','madera'].includes(type)){base=Math.ceil((area/(Math.max(.01,Number(input.placa_largo||2))*Math.max(.01,Number(input.placa_ancho||1))))*4)/4;origin='placas'}else if(['cierre','cordon'].includes(type)){base=linear;origin='metros lineales'}}const calculated=base*(1+waste/100),finalQty=manual>0?manual:calculated,cost=finalQty*Math.max(0,Number(input.costo_unitario||0));return{cantidad_calculada:Number(calculated.toFixed(4)),cantidad_final:Number(finalQty.toFixed(4)),costo_unitario_total:Number(cost.toFixed(2)),tiras:strips,grapas_estimadas:staples,origen_cantidad:manual>0?'ajuste manual':origin}}
const calculateInputLegacy=calculateInput;calculateInput=function(input,module){const result=calculateInputLegacy(input,module);if(['tela','guata','fliselina'].includes(input.tipo)&&!Number(input.cantidad_ajustada||0))result.cantidad_calculada=Number((linearPieceMeters(input.piezas,Math.max(.01,Number(input.ancho_util||1.4)))*(1+Math.max(0,Number(input.merma_pct||0))/100)).toFixed(4)),result.cantidad_final=result.cantidad_calculada,result.costo_unitario_total=Number((result.cantidad_final*Math.max(0,Number(input.costo_unitario||0))).toFixed(2));return result}
function modalInput(){return{tipo:$('#mi-type').value,costo_unitario:Number($('#mi-cost').value||0),merma_pct:Number($('#mi-waste').value||0),ancho_util:Number($('#mi-roll-width').value||0),placa_largo:Number($('#mi-sheet-length').value||0),placa_ancho:Number($('#mi-sheet-width').value||0),patron:$('#mi-pattern').value,direccion:$('#mi-direction').value,separacion_cm:Number($('#mi-spacing').value||0),grapas_por_extremo:Number($('#mi-staples').value||2),cantidad_ajustada:Number($('#mi-adjusted').value||0),piezas:[...document.querySelectorAll('.piece-row')].map(r=>({pieza:$('.p-name',r).value||'pieza',alto:Number($('.p-h',r).value||0),ancho:Number($('.p-w',r).value||0),cantidad:Number($('.p-q',r).value||1),rotatable:$('.p-rotate',r).checked,bordes:{superior:$('.p-edge-top',r).value,derecho:$('.p-edge-right',r).value,inferior:$('.p-edge-bottom',r).value,izquierdo:$('.p-edge-left',r).value}})).filter(p=>p.alto>0&&p.ancho>0)}}
function previewCalculation(){if(!ctx)return;const input=modalInput(),module=state.items[ctx.ii].modulos[ctx.mi],result=calculateInput(input,module),host=$('#calculation-preview');if(input.tipo==='fleje'&&(!Number(module.alto)||!Number(module.ancho))){host.textContent='Completá el alto y el ancho del módulo para calcular el fleje.';return}const unitLabel=['gomaespuma','madera'].includes(input.tipo)?' placas':'';host.textContent=input.tipo==='fleje'?('Consumo: '+result.cantidad_final.toFixed(2)+' m de cinta · '+result.tiras+' tiras · '+result.grapas_estimadas+' grapas · Costo: '+money(result.costo_unitario_total)):('Cantidad: '+result.cantidad_final.toFixed(2)+unitLabel+' · Costo: '+money(result.costo_unitario_total))}
function previewCalculation(){if(!ctx)return;const host=$('#calculation-preview');if(!Number($('#mi-supply').value||0)){host.textContent='Seleccioná un insumo para ver el consumo.';return}const input=modalInput(),module=state.items[ctx.ii].modulos[ctx.mi],result=calculateInput(input,module);if(input.tipo==='fleje'&&(!Number(module.alto)||!Number(module.ancho))){host.textContent='Completá el alto y el ancho del módulo para calcular el fleje.';return}const unitLabel=['gomaespuma','madera'].includes(input.tipo)?' placas':'';host.textContent=input.tipo==='fleje'?('Consumo: '+result.cantidad_final.toFixed(2)+' m de cinta · '+result.tiras+' tiras · '+result.grapas_estimadas+' grapas · Costo: '+money(result.costo_unitario_total)):('Cantidad: '+result.cantidad_final.toFixed(2)+unitLabel+' · Costo: '+money(result.costo_unitario_total))}
function openModal(ii,mi,li,xi){ctx={ii,mi,li,xi};const layer=state.items[ii].modulos[mi].capas[li],x=xi>=0?layer.insumos[xi]:{},allowed=config.capas?.[layer.tipo]?.tipos_insumo_permitidos||[];$('#modal-title').textContent=xi>=0?'Editar insumo':'Agregar insumo';$('#mi-type').innerHTML=supplyTypeOptions(x.tipo||'',allowed);$('#mi-supply').value=x.insumo_id||'';$('#mi-waste').value=x.merma_pct??10;$('#mi-cost').value=x.costo_unitario??0;$('#mi-roll-width').value=x.ancho_util??1.4;$('#mi-sheet-length').value=x.placa_largo??2;$('#mi-sheet-width').value=x.placa_ancho??1;$('#mi-pattern').value=x.patron??'lineal';$('#mi-direction').value=x.direccion??'ancho';$('#mi-spacing').value=x.separacion_cm??10;$('#mi-staples').value=x.grapas_por_extremo??2;$('#mi-adjusted').value=x.cantidad_ajustada??'';$('#mi-reason').value=x.motivo_ajuste??'';$('#pieces').innerHTML='';(x.piezas?.length?x.piezas:[{}]).forEach(p=>$('#pieces').append(pieceRow(p)));fields(x.insumo_id||'');if(xi<0)applyPieceRules(true);$('#modal').classList.add('open')}
		$('#mi-type').onchange=()=>{if($('#mi-type').value==='__add__'){const custom=prompt('Nombre del nuevo tipo de insumo:');if(!custom?.trim()){const previous=[...$('#mi-type').options].find(option=>option.value!=='__add__'&&option.value!=='')?.value||'';$('#mi-type').value=previous;fields();return}const value=normalizeSupplyType(custom);if(!state.catalogos_personalizados.tipos_insumo.includes(value))state.catalogos_personalizados.tipos_insumo.push(value);persistCatalogValue('tipos_insumo',value);$('#mi-type').innerHTML=supplyTypeOptions(value);$('#mi-type').value=value}fields();applyPieceRules()};$('#mi-pattern').onchange=()=>{fields();previewCalculation()};$('#mi-supply').onchange=e=>{$('#mi-cost').value=e.target.selectedOptions[0]?.dataset.price||0;applyPieceRules();previewCalculation()};$('#add-piece').onclick=()=>{$('#pieces').append(pieceRow({},$('#mi-type').value,ctx?state.items[ctx.ii].modulos[ctx.mi]:null));previewCalculation()};$('#apply-piece-rules').onclick=()=>{applyPieceRules(true);previewCalculation()};$('#modal').addEventListener('input',previewCalculation);$('#modal').addEventListener('change',e=>{if(e.target.matches('.piece-edges select')){const row=e.target.closest('.piece-row');row.bordes={superior:$('.p-edge-top',row).value,derecho:$('.p-edge-right',row).value,inferior:$('.p-edge-bottom',row).value,izquierdo:$('.p-edge-left',row).value};row.dataset.manual='0';applyPieceRules(false)}previewCalculation()});$('#cancel-input').onclick=()=>$('#modal').classList.remove('open');
	$('#new-supply').onclick=()=>openSupplyCatalog();$('#edit-supply').onclick=()=>{const id=Number($('#mi-supply').value||0);if(!id){alert('Seleccioná un insumo para modificarlo.');return}openSupplyCatalog(id)};$('#catalog-existing-supply').onchange=e=>fillSupplyCatalog(Number(e.target.value||0));
	$('#close-catalog').onclick=()=>$('#catalog-modal').classList.remove('open');$('#close-catalog-labor').onclick=()=>$('#catalog-modal').classList.remove('open');
	$('#supply-catalog-form').onsubmit=e=>{e.preventDefault();const body=new FormData();body.set('action','supply_save');body.set('supply_id',$('#catalog-supply-id').value);body.set('nombre',$('#catalog-supply-name').value);body.set('unidad',$('#catalog-supply-unit').value);body.set('categoria',$('#catalog-supply-category').value);body.set('precio',$('#catalog-supply-price').value);body.set('stock',$('#catalog-supply-stock').value);body.set('stock_minimo',$('#catalog-supply-min').value);fetch(window.location.href,{method:'POST',body,credentials:'same-origin',headers:{'Accept':'application/json'}}).then(r=>r.json()).then(result=>{if(!result.ok){alert(result.error||'No se pudo guardar el insumo.');return}const saved=result.supply;const index=supplyCatalog.findIndex(x=>Number(x.id)===Number(saved.id));if(index>=0)supplyCatalog[index]=saved;else supplyCatalog.push(saved);refreshSupplyOptions(saved.id);$('#mi-supply').value=String(saved.id);$('#mi-cost').value=Number(saved.precio||0);fields(saved.id);$('#catalog-modal').classList.remove('open')}).catch(()=>alert('No se pudo guardar el insumo. Revisá la conexión.'))};
	$('#labor-catalog-form').onsubmit=e=>{e.preventDefault();const body=new FormData();body.set('action','labor_save');body.set('labor_id',$('#catalog-labor-id').value);body.set('mueble_tipo',$('#catalog-labor-furniture').value);body.set('trabajo_tipo',$('#catalog-labor-work').value);body.set('complejidad',$('#catalog-labor-complexity').value);body.set('tarifa_hora',$('#catalog-labor-rate').value);document.querySelectorAll('[data-labor-task]').forEach(input=>body.set('tiempo_'+input.dataset.laborTask,input.value));fetch(window.location.href,{method:'POST',body,credentials:'same-origin',headers:{'Accept':'application/json'}}).then(r=>r.json()).then(result=>{if(!result.ok){alert(result.error||'No se pudo guardar la mano de obra.');return}const saved=laborTemplate(result.labor),index=labor.findIndex(x=>Number(x.id)===Number(saved.id));if(index>=0)labor[index]=saved;else labor.push(saved);const ii=Number($('#catalog-modal').dataset.laborItem),item=state.items[ii];item.tipo_mueble=saved.mueble_tipo;item.trabajo_tipo=saved.trabajo_tipo;item.complejidad=saved.complejidad;applyLaborTemplate(item);$('#catalog-modal').classList.remove('open');draw();queueAutosave()}).catch(()=>alert('No se pudo guardar la mano de obra. Revisá la conexión.'))};
$('#save-input').onclick=()=>{const{ii,mi,li,xi}=ctx,sel=$('#mi-supply'),id=Number(sel.value);if(!id){alert('Seleccioná un insumo.');return}const adjusted=Number($('#mi-adjusted').value||0),reason=$('#mi-reason').value;if(adjusted>0&&!reason.trim()){alert('Indicá el motivo del ajuste.');return}const module=state.items[ii].modulos[mi];if($('#mi-type').value==='fleje'&&(!Number(module.alto)||!Number(module.ancho))){alert('Para calcular el fleje, completá el alto y el ancho del módulo.');return}const input=modalInput(),result=calculateInput(input,module);const x={id:xi>=0?state.items[ii].modulos[mi].capas[li].insumos[xi].id:uid('input'),...input,...result,insumo_id:id,nombre:sel.selectedOptions[0].textContent,unidad:sel.selectedOptions[0].dataset.unit,motivo_ajuste:reason};const l=state.items[ii].modulos[mi].capas[li];if(xi>=0)l.insumos[xi]=x;else l.insumos.push(x);$('#modal').classList.remove('open');draw()};
['cliente','detalle','fecha','margen'].forEach(id=>$('#'+id).oninput=e=>{state[id==='cliente'?'cliente_id':id]=id==='cliente'?Number(e.target.value):id==='margen'?Number(e.target.value):e.target.value;summary()});$('#finalize').onclick=()=>$('#save-action').value='finalize';
$('#budget-form').onsubmit=e=>{state.cliente_id=Number($('#cliente').value);state.detalle=$('#detalle').value;state.fecha=$('#fecha').value;state.margen=Number($('#margen').value||0);if(!state.cliente_id||!state.items.length){e.preventDefault();alert('Seleccioná cliente y agregá al menos un mueble.');return}$('#payload').value=JSON.stringify(state)};
	function applyPieceRules(force=false){if(!ctx)return;const type=$('#mi-type').value,module=state.items[ctx.ii].modulos[ctx.mi],supplyName=$('#mi-supply').selectedOptions[0]?.textContent||'';document.querySelectorAll('.piece-row').forEach(row=>{if(!force&&row.dataset.manual==='1')return;const bordes={superior:$('.p-edge-top',row).value,derecho:$('.p-edge-right',row).value,inferior:$('.p-edge-bottom',row).value,izquierdo:$('.p-edge-left',row).value},d=defaultPiece($('.p-name',row).value,type,module,supplyName,bordes);$('.p-h',row).value=d.alto;$('.p-w',row).value=d.ancho;row.dataset.manual='0'})}
	state.items=state.items||[];
	state.items.forEach(item=>{item.trabajo_tipo=item.trabajo_tipo||item.mano_obra_snapshot?.trabajo_tipo||'';item.complejidad=item.complejidad||item.mano_obra_snapshot?.complejidad||''});
	document.addEventListener('input',event=>{if(event.target.closest('#budget-form'))queueAutosave()});
	document.addEventListener('change',event=>{if(event.target.closest('#budget-form'))queueAutosave()});
	$('#add-item').onclick=()=>{state.items.push(newItem());draw(true);queueAutosave()};
	['cliente','detalle','fecha','margen'].forEach(id=>$('#'+id).oninput=e=>{state[id==='cliente'?'cliente_id':id]=id==='cliente'?Number(e.target.value):id==='margen'?Number(e.target.value):e.target.value;summary();queueAutosave()});
	$('#finalize').onclick=()=>$('#save-action').value='finalize';
	function previewCalculation(){if(!ctx)return;const host=$('#calculation-preview');if(!Number($('#mi-supply').value||0)){host.textContent='Seleccion\u00e1 un insumo para ver el consumo.';return}const input=modalInput(),module=state.items[ctx.ii].modulos[ctx.mi],result=calculateInput(input,module),moduleQuantity=Math.max(1,Number(module.cantidad||1)),totalQuantity=result.cantidad_final*moduleQuantity,totalCost=result.costo_unitario_total*moduleQuantity;if(input.tipo==='fleje'&&(!Number(module.alto)||!Number(module.ancho))){host.textContent='Complet\u00e1 el alto y el ancho del m\u00f3dulo para calcular el fleje.';return}const unitLabel=['gomaespuma','madera'].includes(input.tipo)?' placas':'';const detail=moduleQuantity>1?'Por m\u00f3dulo: '+result.cantidad_final.toFixed(2)+unitLabel+' · Total ('+moduleQuantity+' m\u00f3dulos): '+totalQuantity.toFixed(2)+unitLabel:'Cantidad: '+totalQuantity.toFixed(2)+unitLabel;host.textContent=input.tipo==='fleje'?('Por m\u00f3dulo: '+result.cantidad_final.toFixed(2)+' m de cinta · Total ('+moduleQuantity+' m\u00f3dulos): '+totalQuantity.toFixed(2)+' m de cinta · '+result.tiras+' tiras · '+result.grapas_estimadas+' grapas · Costo: '+money(totalCost)):detail+' · Costo: '+money(totalCost)}
	function faceKey(face,name=''){const value=normalizeModuleType(face||name);if(value.includes('superior')||value.includes('asiento'))return'superior';if(value.includes('inferior'))return'inferior';if(value.includes('frente'))return'frente';if(value.includes('trasera')||value.includes('dorso'))return'trasera';if(value.includes('lateral')||value.includes('lado')||value.includes('costado'))return'lateral';return''}
	function faceOptions(current){const values=[['','Sin especificar'],['superior','Cara superior'],['inferior','Cara inferior'],['frente','Frente'],['trasera','Trasera'],['lateral_izq','Lateral izquierdo'],['lateral_der','Lateral derecho']];return values.map(([value,label])=>`<option value="${value}" ${value===current?'selected':''}>${label}</option>`).join('')}
	function rawPieceDimensions(pieza,type,module,supplyName='',cara=''){const name=String(pieza||'pieza').toLowerCase(),face=faceKey(cara,name),h=Math.max(0,Number(module?.alto||0)),w=Math.max(0,Number(module?.ancho||0)),d=Math.max(0,Number(module?.profundidad||0));let a=h||w,b=w||d;if(face==='superior'||face==='inferior'){a=d;b=w}else if(face==='frente'||face==='trasera'){a=h;b=w||d}else if(face==='lateral'){a=h;b=d}if(type==='gomaespuma'){const thickness=foamThickness(supplyName);if(thickness>1&&thickness<5){a=Math.max(0,a-5);b=Math.max(0,b-5)}}return{alto:a,ancho:b}}
	function defaultPiece(pieza,type,module,supplyName='',bordes=null,cara=''){const edges=bordes||edgeDefaults(type),base=rawPieceDimensions(pieza,type,module,supplyName,cara),dimensions=dimensionsWithEdges(base,edges);return{...dimensions,bordes:edges,rotatable:true,cara}}
	function pieceRow(p={},type=$('#mi-type')?.value||'',module=ctx?state.items[ctx.ii].modulos[ctx.mi]:null){const cara=faceKey(p.cara,p.pieza),hasDimensions=Number(p.alto||0)>0&&Number(p.ancho||0)>0,defaults=defaultPiece(p.pieza,type,module,$('#mi-supply')?.selectedOptions[0]?.textContent||'',p.bordes,cara),row={...defaults,...p,alto:hasDimensions?Number(p.alto):defaults.alto,ancho:hasDimensions?Number(p.ancho):defaults.ancho,bordes:p.bordes||defaults.bordes,cara:p.cara||cara,rotatable:p.rotatable??p.rotable??true};const r=document.createElement('div');r.className='piece-row';r.dataset.manual=hasDimensions?'1':'0';r.innerHTML=`<input class="p-name" placeholder="Pieza" value="${esc(row.pieza||'pieza')}"><select class="p-face" aria-label="Cara a tapizar">${faceOptions(row.cara)}</select><input class="p-h" type="number" min="0" placeholder="Alto cm" value="${Number(row.alto||0)}"><input class="p-w" type="number" min="0" placeholder="Ancho cm" value="${Number(row.ancho||0)}"><input class="p-q" type="number" min="1" value="${Number(row.cantidad||1)}"><label style="text-align:center"><input class="p-rotate" type="checkbox" ${row.rotatable!==false?'checked':''} aria-label="Permitir rotaci\u00f3n 90 grados"></label><button type="button" class="danger-btn">×</button><div class="piece-edges"><label>Sup. ${edgeSelect('p-edge-top',row.bordes.superior)}</label><label>Der. ${edgeSelect('p-edge-right',row.bordes.derecho)}</label><label>Inf. ${edgeSelect('p-edge-bottom',row.bordes.inferior)}</label><label>Izq. ${edgeSelect('p-edge-left',row.bordes.izquierdo)}</label></div>`;$('button',r).onclick=()=>r.remove();$('.p-face',r).onchange=()=>{r.dataset.manual='0';applyPieceRules(false);previewCalculation()};$('.p-name',r).oninput=()=>{if(r.dataset.manual==='0'){const d=defaultPiece($('.p-name',r).value,type,module,$('#mi-supply')?.selectedOptions[0]?.textContent||'',row.bordes,$('.p-face',r).value);$('.p-h',r).value=d.alto;$('.p-w',r).value=d.ancho}};['.p-h','.p-w'].forEach(selector=>$(selector,r).oninput=()=>{r.dataset.manual='1'});return r}
	function modalInput(){return{tipo:$('#mi-type').value,costo_unitario:Number($('#mi-cost').value||0),merma_pct:Number($('#mi-waste').value||0),ancho_util:Number($('#mi-roll-width').value||0),placa_largo:Number($('#mi-sheet-length').value||0),placa_ancho:Number($('#mi-sheet-width').value||0),patron:$('#mi-pattern').value,direccion:$('#mi-direction').value,separacion_cm:Number($('#mi-spacing').value||0),grapas_por_extremo:Number($('#mi-staples').value||2),cantidad_ajustada:Number($('#mi-adjusted').value||0),piezas:[...document.querySelectorAll('.piece-row')].map(r=>({pieza:$('.p-name',r).value||'pieza',cara:$('.p-face',r).value,alto:Number($('.p-h',r).value||0),ancho:Number($('.p-w',r).value||0),cantidad:Number($('.p-q',r).value||1),rotatable:$('.p-rotate',r).checked,bordes:{superior:$('.p-edge-top',r).value,derecho:$('.p-edge-right',r).value,inferior:$('.p-edge-bottom',r).value,izquierdo:$('.p-edge-left',r).value}})).filter(p=>p.alto>0&&p.ancho>0)}}
	function applyPieceRules(force=false){if(!ctx)return;const type=$('#mi-type').value,module=state.items[ctx.ii].modulos[ctx.mi],supplyName=$('#mi-supply').selectedOptions[0]?.textContent||'';document.querySelectorAll('.piece-row').forEach(row=>{if(!force&&row.dataset.manual==='1')return;const cara=$('.p-face',row)?.value||'',bordes={superior:$('.p-edge-top',row).value,derecho:$('.p-edge-right',row).value,inferior:$('.p-edge-bottom',row).value,izquierdo:$('.p-edge-left',row).value},d=defaultPiece($('.p-name',row).value,type,module,supplyName,bordes,cara);$('.p-h',row).value=d.alto;$('.p-w',row).value=d.ancho;row.dataset.manual='0'})}
	draw(true);

})();
