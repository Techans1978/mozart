<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// /public_html/config.php
require_once dirname(__DIR__, 2) . '/config.php';

// autenticação e conexão
require_once ROOT_PATH . '/system/includes/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';

// cabeçalhos/menus
include_once ROOT_PATH . '/system/includes/head.php';
include_once ROOT_PATH . '/system/includes/navbar.php';
?>
<div id="page-wrapper">
  <div class="container-fluid">
    <div class="row"><div class="col-lg-12"><h1 class="page-header">Designer BPMN</h1></div></div>

    <div class="row">
      <div class="col-lg-12">

        <div class="toolbar" style="margin-bottom:10px; display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
          <button id="btnNew">Novo</button>
          <button id="btnOpen">Abrir</button>
          <button id="btnSave">Salvar</button>
          <button id="btnSaveAs">Salvar como…</button>
          <button id="btnDownload">Baixar XML</button>
          <button id="btnPublish">Publicar</button>
          <div style="margin-left:auto; display:flex; gap:8px; align-items:center;">
            <label>Versões</label>
            <select id="selVersions" style="min-width:280px;"></select>
          </div>
        </div>

        <!-- Canvas + Propriedades -->
        <div style="display:flex; gap:8px; align-items:stretch;">
          <div id="canvas" style="flex:1; height:600px; border:1px solid #ccc; border-radius:6px; background:#fafafa"></div>
          <div id="properties" style="width:320px; height:600px; border:1px solid #ccc; border-radius:6px; background:#fff; overflow:auto"></div>
        </div>

        <div class="row" style="margin-top:12px;">
          <button id="btnUpload">Enviar XML</button>
          <input type="file" id="fileXml" accept=".bpmn,.xml" style="display:none">
          <button id="btnAI">Criar por IA</button>
          <small style="margin-left:auto;">Dica: use <b>?</b> para gateways. Ex.: <i>Aprovar gestor?</i> / <i>sim: Criar acesso</i> / <i>não: Encerrar</i></small>
        </div>

        <!-- Modal Salvar como -->
        <div id="modalSaveAs" class="modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.4); align-items:center; justify-content:center;">
          <div class="card" style="background:#fff; border-radius:10px; padding:16px; width:min(600px,90vw);">
            <h3>Salvar como</h3>
            <label>Nome do processo <input id="procName" placeholder="ex.: onboarding_colaborador"></label><br>
            <label>Versão <input id="procVersion" type="number" min="1" value="1"></label><br>
            <div class="row">
              <button id="doSaveAs">Salvar</button>
              <button id="cancelSaveAs">Cancelar</button>
            </div>
          </div>
        </div>

        <!-- Modal IA -->
        <div id="modalAI" class="modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.4); align-items:center; justify-content:center;">
          <div class="card" style="background:#fff; border-radius:10px; padding:16px; width:min(720px,90vw);">
            <h3>Criar processo por IA</h3>
            <p>Descreva o fluxo. Ex.: <code>Início ➜ RH cadastra ➜ Aprovação do Gestor? ➜ sim: TI cria acesso ➜ não: Encerrar ➜ Fim</code></p>
            <textarea id="aiPrompt" rows="8" style="width:100%"></textarea><br>
            <div class="row" style="margin-top:8px;">
              <button id="doAI">Gerar</button>
              <button id="cancelAI">Cancelar</button>
              <small style="margin-left:auto;">Isto cria um esqueleto. Revise no editor.</small>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>

<?php include_once ROOT_PATH . '/system/includes/code_footer.php'; ?>

<!-- CSS (CDN) -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bpmn-js@11.5.0/dist/assets/diagram-js.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bpmn-js@11.5.0/dist/assets/bpmn-font/css/bpmn-embedded.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bpmn-js-properties-panel@5.42.0/dist/assets/properties-panel.css">

<!-- JS (CDN) – ordem importa -->
<script src="https://cdn.jsdelivr.net/npm/bpmn-js@11.5.0/dist/bpmn-modeler.development.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bpmn-js-properties-panel@5.42.0/dist/bpmn-js-properties-panel.umd.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bpmn-js-properties-panel@5.42.0/dist/provider/bpmn.umd.js"></script>

<script>
(function () {
  const $ = s => document.querySelector(s);
  const modal = (el, show)=>{ if (el) el.style.display = show ? 'flex' : 'none'; };

  const canvasEl = $('#canvas');
  const propsEl  = $('#properties');
  if (!canvasEl) { console.error('#canvas não encontrado'); return; }

  // BpmnJS ou BpmnModeler (dependendo do bundle)
  const BpmnCtor = window.BpmnModeler || window.BpmnJS;

  // Módulos do properties panel (v5.x)
  const PanelMod    = window.BpmnPropertiesPanelModule;
  const ProviderMod = window.BpmnPropertiesProviderModule;

  if (!BpmnCtor) { alert('❌ bpmn-js não carregou (verifique CDN).'); return; }

  const modeler = new BpmnCtor({
    container: '#canvas',
    keyboard: { bindTo: document },
    additionalModules: [ PanelMod, ProviderMod ].filter(Boolean),
    propertiesPanel: propsEl ? { parent: '#properties' } : undefined
  });

  let currentName = null, currentVersion = null;

  async function loadVersions() {
    try {
      const r = await fetch('api/list_processes.php', { cache:'no-store' });
      const data = await r.json();
      const sel = $('#selVersions'); if (!sel) return;
      sel.innerHTML = '';
      (data.items || []).forEach(it=>{
        const opt = document.createElement('option');
        opt.value = JSON.stringify(it);
        opt.textContent = it.name + ' v' + it.version + (it.status ? (' ['+it.status+']') : '');
        sel.appendChild(opt);
      });
    } catch (e) { console.warn('loadVersions()', e); }
  }

  async function newDiagram(){
    try {
      await modeler.createDiagram();
      currentName = null; currentVersion = null;
      console.log('✅ BPMN inicializado');
    } catch(e) { console.error('Erro ao criar novo diagrama', e); alert('Falha ao criar diagrama.'); }
  }

  async function doSave(name, version, xml){
    const r = await fetch('api/save_process.php', {
      method: 'POST', headers: { 'Content-Type':'application/json' },
      body: JSON.stringify({ name, version, xml })
    });
    if(!r.ok){ alert('Falha ao salvar'); return; }
    await loadVersions();
    alert('Salvo: '+name+' v'+version);
  }

  async function saveDiagram(asNew=false){
    const { xml } = await modeler.saveXML({ format: true });
    let name = currentName, version = currentVersion;

    if (asNew || !name) {
      const modalEl = $('#modalSaveAs'); if (!modalEl) { alert('Modal não encontrado'); return; }
      modal(modalEl, true);
      return new Promise(resolve=>{
        $('#doSaveAs').onclick = async ()=>{
          name = $('#procName').value.trim();
          version = parseInt($('#procVersion').value || '1', 10);
          if(!name){ alert('Informe o nome do processo'); return; }
          await doSave(name, version, xml);
          currentName=name; currentVersion=version;
          modal(modalEl, false); resolve();
        };
        $('#cancelSaveAs').onclick = ()=>{ modal(modalEl, false); resolve(); };
      });
    } else {
      await doSave(name, version, xml);
    }
  }

  async function openSelected(){
    const sel = $('#selVersions');
    if(!sel || !sel.value){ alert('Nenhuma versão selecionada'); return; }
    const it = JSON.parse(sel.value);
    const r = await fetch('api/load_process.php?name='+encodeURIComponent(it.name)+'&version='+encodeURIComponent(it.version), { cache:'no-store' });
    if(!r.ok){ alert('Falha ao abrir'); return; }
    const data = await r.json();
    await modeler.importXML(data.xml);
    currentName = it.name; currentVersion = it.version;
  }

  function downloadXML(){
    modeler.saveXML({format:true}).then(({xml})=>{
      const blob = new Blob([xml], {type:'application/xml'});
      const a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = (currentName||'process') + (currentVersion?('_v'+currentVersion):'') + '.bpmn';
      a.click(); URL.revokeObjectURL(a.href);
    });
  }

  function uploadXML(){ const f = $('#fileXml'); if (f) f.click(); }
  const fileEl = $('#fileXml');
  if (fileEl) fileEl.addEventListener('change', async (ev)=>{
    const f = ev.target.files[0]; if(!f) return;
    const text = await f.text(); await modeler.importXML(text);
  });

  async function publishCurrent(){
    if (!currentName || !currentVersion) { alert('Abra ou salve primeiro.'); return; }
    const r = await fetch('api/publish_process.php', {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ name: currentName, version: currentVersion })
    });
    if (!r.ok) { alert('Falha ao publicar'); return; }
    const data = await r.json();
    if (data.ok) { alert(`Publicado: ${currentName} v${currentVersion}`); await loadVersions(); }
    else { alert('Não foi possível publicar.'); }
  }

  const bind = (id, fn)=>{ const el=document.getElementById(id); if (el) el.onclick=fn; };
  bind('btnNew', ()=> newDiagram());
  bind('btnSave', ()=> saveDiagram(false));
  bind('btnSaveAs', ()=> saveDiagram(true));
  bind('btnOpen', ()=> openSelected());
  bind('btnDownload', ()=> downloadXML());
  bind('btnUpload', ()=> uploadXML());
  bind('btnAI', ()=> modal($('#modalAI'), true));
  bind('doAI', ()=> alert('IA: a gente ativa depois 🙂'));
  bind('cancelAI', ()=> modal($('#modalAI'), false));
  bind('btnPublish', ()=> publishCurrent());

  loadVersions();
  newDiagram();
})();
</script>

<?php include_once ROOT_PATH . '/system/includes/footer.php'; ?>
