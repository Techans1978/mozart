<?php
// modules/bpm/bpm_designer.php
// Mozart BPM — Modeler com Properties + Element Templates (CDN + fallback local)

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';

// Abre <html><head>...<body>
include_once ROOT_PATH . 'system/includes/head.php';

// Inclui dependencias BPM
include_once ROOT_PATH . 'modules/bpm/includes/content_header.php';

// Inclui dependencias BPM
include_once ROOT_PATH . 'modules/bpm/includes/content_style.php';

?>

<!-- ===== Estilos locais desta tela ===== -->
<style>
  :root { --toolbar-h:56px; --sidebar-w:360px; --gap:10px; }
  #page-wrapper { background:#f6f7f9; }
  .shell { display:flex; flex-direction:column; height: calc(100vh - 70px); }
  .toolbar {
    height:var(--toolbar-h); display:flex; gap:8px; align-items:center; padding:8px 12px;
    background:#fff; border:1px solid #e5e7eb; border-radius:10px; margin-bottom:10px;
  }
  .toolbar h2 { font-size:16px; margin:0 12px 0 0; font-weight:600; color:#111827; }
  .toolbar .spacer { flex:1; }
  .btn {
    border:1px solid #d1d5db; background:#fff; padding:8px 12px; border-radius:10px; cursor:pointer;
    transition:.15s; font-weight:600;
  }
  .btn:hover { background:#f3f4f6; }
  .btn.primary { background:#111827; color:#fff; border-color:#111827; }
  .btn.primary:hover { background:#0b1220; }
  input[type="file"] { display:none; }

  .work { display:flex; gap:var(--gap); height: calc(100% - var(--toolbar-h) - 10px); }
  #canvas {
    flex:1; background:#fff; border:1px solid #e5e7eb; border-radius:12px; overflow:hidden; min-height:520px;
  }
  #properties {
    width:var(--sidebar-w); background:#fff; border:1px solid #e5e7eb; border-radius:12px; overflow:auto; min-height:520px;
  }
</style>

  <div class="shell">
    <div class="toolbar">
      <h2>Mozart BPM — Designer</h2>

      <button class="btn" id="btnNew">Novo</button>

      <input type="file" id="fileOpen" accept=".bpmn,.xml" />
      <button class="btn" id="btnOpen" title="Ctrl+O">Abrir</button>

      <button class="btn" id="btnSave"    title="Ctrl+S">Salvar</button>
      <button class="btn" id="btnSaveAs"  title="Ctrl+Shift+S">Salvar como…</button>

      <button class="btn" id="btnExportXML">Baixar XML</button>
      <button class="btn" id="btnExportSVG">Baixar SVG</button>

      <div class="spacer"></div>

      <label for="tplSelect" class="mb-0" style="font-size:12px; color:#6b7280;">Template</label>
      <select id="tplSelect" class="form-control" style="min-width:200px; margin:0 6px;">
        <option value="">— selecionar —</option>
      </select>
      <button class="btn" id="btnApplyTpl" title="Aplica o template no elemento selecionado">Aplicar</button>

      <button class="btn primary" id="btnPublish">Publicar</button>
    </div>

    <div class="work">
      <div id="canvas"></div>
      <div id="properties"></div>
    </div>
  </div>

<?php
// carrega seus scripts globais + Camunda JS (inserido no code_footer.php)
include_once ROOT_PATH . 'system/includes/code_footer.php';
?>

<?php
// Inclui dependencias BPM
include_once ROOT_PATH . 'modules/bpm/includes/content_footer.php';
?>

<script>
(function () {
  const $ = (sel, ctx=document) => ctx.querySelector(sel);

  // utilidades
  const saveAs = (blob, filename) => {
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    setTimeout(()=> { URL.revokeObjectURL(a.href); a.remove(); }, 1000);
  };
  const readFile = (file) => new Promise((res, rej) => {
    const fr = new FileReader();
    fr.onload = () => res(String(fr.result));
    fr.onerror = rej;
    fr.readAsText(file);
  });

  // fallback local se CDN falhar
  function ensureCamundaLoaded() {
    return new Promise((resolve) => {
      function ok() {
        const Ctor =
          (window.CamundaPlatformModeler && (window.CamundaPlatformModeler.default || window.CamundaPlatformModeler)) ||
          (window.BpmnModeler && (window.BpmnModeler.default || window.BpmnModeler));
        resolve(Ctor || null);
      }
      // verifica imediatamente
      setTimeout(ok, 0);
      // se não carregou ainda, tenta fallback após 1s
      setTimeout(() => {
        const Ctor =
          (window.CamundaPlatformModeler && (window.CamundaPlatformModeler.default || window.CamundaPlatformModeler)) ||
          (window.BpmnModeler && (window.BpmnModeler.default || window.BpmnModeler));
        if (!Ctor) {
          console.warn('CDN do camunda-bpmn-js falhou, tentando fallback local…');
          const css = document.createElement('link');
          css.rel = 'stylesheet';
          css.href = '<?= BASE_URL ?>/modules/bpm/vendor/camunda-bpmn-js@5/dist/assets/camunda-platform-modeler.css';
          document.head.appendChild(css);

          const s = document.createElement('script');
          s.src = '<?= BASE_URL ?>/modules/bpm/vendor/camunda-bpmn-js@5/dist/camunda-platform-modeler.development.js';
          s.onload = ok;
          s.onerror = () => resolve(null);
          document.body.appendChild(s);
        }
      }, 1000);
    });
  }

  // templates de exemplo
  const ELEMENT_TEMPLATES = [
    {
      name: "HTTP Task (GET)",
      id: "mozart.http.get.v1",
      appliesTo: [ "bpmn:ServiceTask" ],
      properties: [
        { label:"URL", type:"String", binding:{ type:"camunda:inputParameter", name:"url" }, constraints:{ notEmpty:true } },
        { label:"Query Params (json)", type:"Text", binding:{ type:"camunda:inputParameter", name:"query" } },
        { label:"Resultado → variável", type:"String", binding:{ type:"camunda:outputParameter", source:"${httpResult}", script:false, name:"resultVar" } }
      ]
    },
    {
      name: "Gateway de Decisão (expressão)",
      id: "mozart.gateway.expr.v1",
      appliesTo: [ "bpmn:ExclusiveGateway" ],
      properties: [
        { label:"Expressão (EL)", type:"String", binding:{ type:"bpmn:conditionExpression", language:"groovy" }, constraints:{ notEmpty:true } }
      ]
    }
  ];

  function populateTemplateChooser(templates) {
    const sel = $('#tplSelect');
    sel.innerHTML = '<option value="">— selecionar —</option>';
    templates.forEach(t => {
      const opt = document.createElement('option');
      opt.value = t.id;
      opt.textContent = t.name;
      sel.appendChild(opt);
    });
  }

  // boot
  let modeler = null;
  let currentFileName = 'diagram.bpmn';

  ensureCamundaLoaded().then((Ctor) => {
    if (!Ctor) {
      alert('❌ Não foi possível carregar o camunda-bpmn-js (CDN e fallback). Verifique modules/bpm/vendor/camunda-bpmn-js@5/dist/');
      return;
    }

    modeler = new Ctor({
      container: '#canvas',
      propertiesPanel: { parent: '#properties' },
      keyboard: { bindTo: document },
    });

    try {
      const templatesSvc = modeler.get('elementTemplates');
      if (templatesSvc?.set) templatesSvc.set(ELEMENT_TEMPLATES);
      populateTemplateChooser(ELEMENT_TEMPLATES);
    } catch (e) {
      console.warn('ElementTemplates service indisponível no bundle atual:', e);
    }

    modeler.on('import.done', (e) => {
      if (e?.warnings?.length) console.warn('Import warnings:', e.warnings);
      modeler.get('canvas').zoom('fit-viewport', 'auto');
    });

    newDiagram();
    bindToolbar(modeler);
    bindDnD(modeler);
    bindShortcuts(modeler);
  });

  function bindToolbar(modeler) {
    bind('btnNew',      () => newDiagram());
    bind('btnOpen',     () => $('#fileOpen').click());
    bind('btnSave',     () => saveDiagram(false));
    bind('btnSaveAs',   () => saveDiagram(true));
    bind('btnExportXML',() => exportXML());
    bind('btnExportSVG',() => exportSVG());
    bind('btnApplyTpl', () => applySelectedTemplate());
    bind('btnPublish',  () => publish());
    $('#fileOpen').addEventListener('change', async (ev) => {
      const f = ev.target.files?.[0];
      if (!f) return;
      currentFileName = /\.(bpmn|xml)$/i.test(f.name) ? f.name : (f.name + '.bpmn');
      const xml = await readFile(f);
      await importXML(xml);
      ev.target.value = '';
    });
  }
  function bind(id, fn){ const el = document.getElementById(id); if (el) el.onclick = fn; }

  async function newDiagram() {
    if (!modeler) return;
    currentFileName = 'diagram.bpmn';
    await modeler.createDiagram();
    modeler.get('canvas').zoom('fit-viewport', 'auto');
  }

  async function importXML(xml) {
    try { await modeler.importXML(xml); }
    catch (err) { console.error(err); alert('Falha ao importar BPMN: ' + (err?.message || err)); }
  }

  async function saveDiagram(forceAs) {
    if (!modeler) return;
    const { xml } = await modeler.saveXML({ format:true });
    const name = forceAs ? prompt('Nome do arquivo .bpmn:', currentFileName) : currentFileName;
    if (!name) return;
    currentFileName = /\.bpmn$/i.test(name) ? name : (name + '.bpmn');
    saveAs(new Blob([xml], { type:'application/xml' }), currentFileName);
  }

  async function exportXML() {
    if (!modeler) return;
    const { xml } = await modeler.saveXML({ format:true });
    const base = currentFileName.replace(/\.(bpmn|xml)$/i,'') || 'diagram';
    saveAs(new Blob([xml], { type:'application/xml' }), base + '.bpmn');
  }

  async function exportSVG() {
    if (!modeler) return;
    const { svg } = await modeler.saveSVG();
    const base = currentFileName.replace(/\.(bpmn|xml)$/i,'') || 'diagram';
    saveAs(new Blob([svg], { type:'image/svg+xml' }), base + '.svg');
  }

  function bindDnD(modeler) {
    document.body.addEventListener('dragover', e => e.preventDefault());
    document.body.addEventListener('drop', async e => {
      e.preventDefault();
      const file = e.dataTransfer?.files?.[0];
      if (file && /\.(bpmn|xml)$/i.test(file.name)) {
        currentFileName = file.name;
        const xml = await readFile(file);
        await importXML(xml);
      }
    });
  }

  function bindShortcuts(modeler) {
    window.addEventListener('keydown', (e) => {
      const mod = e.ctrlKey || e.metaKey;
      if (mod && e.key.toLowerCase()==='s') {
        e.preventDefault();
        if (e.shiftKey) saveDiagram(true);
        else saveDiagram(false);
      }
      if (mod && e.key.toLowerCase()==='o') {
        e.preventDefault();
        $('#fileOpen').click();
      }
    });
  }

  function applySelectedTemplate() {
    const selId = $('#tplSelect').value;
    if (!selId) return alert('Selecione um template.');
    const sel = modeler.get('selection').get()[0];
    if (!sel) return alert('Selecione um elemento do diagrama.');
    try {
      const templatesSvc = modeler.get('elementTemplates');
      const tpl = ELEMENT_TEMPLATES.find(t => t.id === selId);
      if (!templatesSvc?.applyTemplate) throw new Error('Service indisponível');
      templatesSvc.applyTemplate(sel, tpl);
    } catch (e) {
      console.warn('Falhou ao aplicar template:', e);
      alert('Este bundle não expôs o serviço de Element Templates. Vamos habilitar quando plugarmos o pacote de templates JSON.');
    }
  }

  async function publish() {
    try {
      const { xml } = await modeler.saveXML({ format:true });
      // TODO: plugue seu endpoint de publicação aqui
      console.log('XML pronto para publicar (%s bytes)', xml.length);
      alert('✔ XML preparado para publicar.\n\nQuando o endpoint estiver pronto, envio via fetch().');
    } catch (e) {
      console.error(e);
      alert('Erro ao publicar: ' + (e?.message || e));
    }
  }
})();
</script>

<?php
// fecha </body></html>
include_once ROOT_PATH . 'system/includes/footer.php';
