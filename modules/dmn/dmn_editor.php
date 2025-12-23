<?php
// modules/dmn/dmn_editor.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
proteger_pagina();

$decisionId = isset($_GET['id']) ? (int)$_GET['id'] : 0;


?>


<?php
include_once ROOT_PATH . 'system/includes/head.php';
?>

<!-- CSS do dmn-js -->
<link rel="stylesheet" href="https://unpkg.com/dmn-js@16.2.0/dist/assets/diagram-js.css">
<link rel="stylesheet" href="https://unpkg.com/dmn-js@16.2.0/dist/assets/dmn-js.css">
<link rel="stylesheet" href="https://unpkg.com/dmn-js@16.2.0/dist/assets/dmn-font/css/dmn.css">
<link rel="stylesheet" href="https://unpkg.com/dmn-js@16.2.0/dist/assets/dmn-js-decision-table.css">
<link rel="stylesheet" href="https://unpkg.com/dmn-js@16.2.0/dist/assets/dmn-js-literal-expression.css">

<style>
  :root { --gap:12px; --bg:#f6f7f9; --card:#fff; --bd:#e5e7eb; --txt:#111; }
  *{ box-sizing:border-box; }
  html, body { height: 100%; }
  body{
    margin:0; font:14px/1.45 system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial;
    background:var(--bg); color:var(--txt);
  }

  .topbar{
    position:sticky; top:0; z-index:10;
    background:var(--card); border-bottom:1px solid var(--bd);
    padding:10px 12px;
    display:flex; gap:10px; align-items:center; flex-wrap:wrap;
  }
  .brand{ font-weight:800; }
  .spacer{ flex:1; }

  .btn{
    border:1px solid #d1d5db; background:#fff; padding:8px 12px; border-radius:10px;
    font-weight:700; cursor:pointer;
  }
  .btn:hover{ background:#f3f4f6;}
  .btn.primary{ background:#111827; color:#fff; border-color:#111827;}
  .btn.danger{ background:#b91c1c; color:#fff; border-color:#b91c1c;}
  .btn:disabled{ opacity:.6; cursor:not-allowed; }

  .layout{
    display:grid;
    grid-template-columns: 420px 1fr;
    gap: var(--gap);
    padding: var(--gap);
    height: calc(100vh - 64px);
    min-height:0;
  }

  .panel{
    background:var(--card);
    border:1px solid var(--bd);
    border-radius:12px;
    padding:12px;
    min-height:0;
    overflow:auto;
  }

  .panel h3{
    margin:0 0 10px 0; font-size:14px; letter-spacing:.2px;
  }

  .row{ display:flex; gap:10px; }
  .col{ flex:1; }
  label{ display:block; font-weight:700; margin:10px 0 6px; color:#111827; }
  input[type="text"], input[type="number"], select, textarea{
    width:100%; padding:10px 10px; border-radius:10px;
    border:1px solid #d1d5db; outline:none;
    background:#fff;
  }
  textarea{ min-height:90px; resize:vertical; }

  .hint{ font-size:12px; color:#6b7280; margin-top:6px; }
  .badge{
    display:inline-flex; align-items:center; gap:6px;
    padding:4px 10px; border-radius:999px; font-size:12px; font-weight:800;
    border:1px solid var(--bd); background:#fff;
  }
  .badge.ok{ border-color:#86efac; }
  .badge.warn{ border-color:#fde68a; }
  .badge.err{ border-color:#fecaca; }

  #canvasWrap{ height: 100%; min-height:0; display:flex; }
  #dmnCanvas{
    height: 100%; width: 100%;
    background:#fff; border:1px solid var(--bd); border-radius:12px;
    overflow: visible;
    flex:1 1 auto; min-height:0;
  }
  #dmnCanvas .dmn-js-parent,
  #dmnCanvas .dmn-js-container,
  #dmnCanvas .djs-container{ height:100% !important; width:100% !important; }
  .dmn-drd-container,
  .dmn-decision-table-container,
  .dmn-literal-expression-container { height: 100%; }

  .toolbar2{ display:flex; gap:8px; flex-wrap:wrap; margin-top:10px; }

  /* Modal simples */
  .modal-backdrop{
    position:fixed; inset:0; background:rgba(0,0,0,.45);
    display:none; align-items:center; justify-content:center; z-index:50;
    padding:12px;
  }
  .modal{
    width:min(900px, 100%);
    max-height:90vh;
    background:#fff; border-radius:14px; border:1px solid var(--bd);
    overflow:hidden; display:flex; flex-direction:column;
  }
  .modal-header{
    padding:10px 12px; border-bottom:1px solid var(--bd);
    display:flex; align-items:center; gap:10px;
  }
  .modal-title{ font-weight:900; }
  .modal-body{ padding:12px; overflow:auto; min-height:0; }
  .modal-footer{
    padding:10px 12px; border-top:1px solid var(--bd);
    display:flex; gap:8px; justify-content:flex-end; flex-wrap:wrap;
  }
  pre{
    background:#0b1020; color:#e5e7eb; padding:12px; border-radius:12px;
    overflow:auto; max-height:280px;
  }

  .list{ margin-top:8px; display:flex; flex-direction:column; gap:8px; }
  .item{
    border:1px solid var(--bd); border-radius:12px; padding:10px;
    display:flex; gap:10px; align-items:flex-start;
  }
  .item .meta{ flex:1; min-width:0; }
  .item .name{ font-weight:900; margin:0; }
  .item .sub{ font-size:12px; color:#6b7280; margin-top:4px; }
  .item .actions{ display:flex; gap:6px; flex-wrap:wrap; }
  .small{ font-size:12px; padding:6px 10px; border-radius:10px; }

  .sep{ height:1px; background:var(--bd); margin:12px 0; }
</style>


<?php
include_once ROOT_PATH . 'system/includes/navbar.php';
?>


<!-- Incio Page Content -->
<div id="page-wrapper">
  <div class="container-fluid">
    <div class="row"></div></div>

    <div class="row">
      <div class="col-lg-12">
<!-- Meio Page Content -->


<div class="topbar">
  <div class="brand">Mozart — DMN Editor</div>

  <span id="statusBadge" class="badge warn">Carregando…</span>

  <div class="spacer"></div>

  <button class="btn" id="btnNew">Novo DMN</button>
  <button class="btn" id="btnCreateDecision" title="Cria um Decision com tabela vazia">Nova decisão</button>

  <button class="btn" id="btnOpenTable">Abrir tabela</button>
  <button class="btn" id="btnViewDRD">View DRD</button>

  <button class="btn" id="zoomIn">Zoom +</button>
  <button class="btn" id="zoomOut">Zoom −</button>
  <button class="btn" id="zoomFit">Ajustar</button>

  <button class="btn" id="btnRunner">Testar (Runner)</button>
  <button class="btn primary" id="btnSave">Salvar (draft)</button>
  <button class="btn primary" id="btnPublish">Publicar</button>
</div>

<div class="layout">
  <!-- Painel esquerdo -->
  <div class="panel">
    <h3>Metadados</h3>

    <div class="row">
      <div class="col">
        <label>ID</label>
        <input type="text" id="decisionId" value="<?php echo htmlspecialchars((string)$decisionId); ?>" readonly>
      </div>
      <div class="col">
        <label>Status</label>
        <select id="decisionStatus">
          <option value="draft">draft</option>
          <option value="published">published</option>
          <option value="archived">archived</option>
        </select>
      </div>
    </div>

    <label>Rule Key (integração BPM)</label>
    <input type="text" id="ruleKey" placeholder="ex: ti.sla.route">

    <div class="hint">O BPM vai chamar este DMN usando <b>rule_key</b>. Mantenha estável.</div>

    <label>Nome</label>
    <input type="text" id="name" placeholder="ex: Roteamento SLA">

    <label>Categoria</label>
    <select id="categoryId">
      <option value="">(sem categoria)</option>
    </select>

    <label>Tags (separadas por vírgula)</label>
    <input type="text" id="tags" placeholder="ti, sla, aprovacao">

    <label>Descrição</label>
    <textarea id="description" placeholder="Descrição curta (opcional)"></textarea>

    <div class="sep"></div>

    <h3>Versão publicada</h3>
    <div class="row">
      <div class="col">
        <label>Número</label>
        <input type="text" id="pubVersion" readonly>
      </div>
      <div class="col">
        <label>Publicado em</label>
        <input type="text" id="pubAt" readonly>
      </div>
    </div>

    <div class="sep"></div>

    <h3>Testcases (Runner)</h3>
    <div class="toolbar2">
      <button class="btn small" id="btnReloadCases">Atualizar</button>
      <button class="btn small" id="btnNewCase">Novo testcase</button>
    </div>

    <div id="casesList" class="list"></div>
  </div>

  <!-- Canvas -->
  <div id="canvasWrap">
    <div id="dmnCanvas"></div>
  </div>
</div>

<!-- Modal Runner -->
<div class="modal-backdrop" id="modalRunner">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">Runner — Testar DMN</div>
      <div class="spacer"></div>
      <button class="btn small" id="btnCloseRunner">Fechar</button>
    </div>
    <div class="modal-body">
      <div class="row">
        <div class="col">
          <label>Context JSON</label>
          <textarea id="runnerContext" style="min-height:180px" placeholder='{"categoria":"Telefonia","prioridade":3}'></textarea>
          <div class="hint">O engine v1 usa Decision Table (hitPolicy FIRST). Condições: =, &lt;, &lt;=, &gt;, &gt;=, in(...), matches("...").</div>
        </div>
        <div class="col">
          <label>Resultado</label>
          <pre id="runnerResult">{}</pre>
        </div>
      </div>

      <div class="sep"></div>

      <div class="row">
        <div class="col">
          <label>Salvar como testcase</label>
          <input type="text" id="tcName" placeholder="Nome do testcase (ex: Telefonia alta)">
        </div>
        <div class="col">
          <label>Expected (opcional)</label>
          <input type="text" id="tcExpectedHint" placeholder='ex: {"fila":"TI Infra","slaHoras":8}'>
          <div class="hint">Se quiser, cole um JSON. (v1: só armazenamos, não validamos automaticamente)</div>
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn" id="btnRunNow">Executar</button>
      <button class="btn primary" id="btnSaveCase">Salvar testcase</button>
    </div>
  </div>
</div>

<!-- Meio Page Content -->
      </div>
    </div>
  </div>
</div>
<!-- Fim Page Content -->


<?php
// carrega seus scripts globais + Camunda JS (inserido no code_footer.php)
include_once ROOT_PATH . 'system/includes/code_footer.php';
?>

<!-- UMD dmn-js -->
<script src="https://unpkg.com/dmn-js@16.2.0/dist/dmn-modeler.development.js"></script>

<script>
const API = {
  categories:  'api/categories.php?action=list&active=1',
  decisionGet: 'api/decision_get.php?id=',
  decisionSave:'api/decision_save.php',
  decisionPublish:'api/decision_publish.php',
  testcaseList:'api/testcase_list.php?decision_id=',
  testcaseSave:'api/testcase_save.php',
  evaluate:'api/evaluate.php'
};

const $ = (s, c=document)=>c.querySelector(s);

const EMPTY_XML =
`<?xml version="1.0" encoding="UTF-8"?>
<definitions xmlns="https://www.omg.org/spec/DMN/20191111/MODEL/"
             xmlns:dmndi="https://www.omg.org/spec/DMN/20191111/DMNDI/"
             xmlns:di="http://www.omg.org/spec/DMN/20191111/DI/"
             xmlns:dc="http://www.omg.org/spec/DMN/20180521/DC/"
             id="Definitions_${Date.now()}"
             name="Mozart-DMN"
             namespace="http://mozart.local/dmn">
  <dmndi:DMNDI>
    <dmndi:DMNDiagram id="DRD_1"/>
  </dmndi:DMNDI>
</definitions>`;

let modeler = null;
let currentDecisionId = parseInt($('#decisionId').value || '0', 10) || 0;

function setBadge(text, kind='warn') {
  const b = $('#statusBadge');
  b.textContent = text;
  b.classList.remove('ok','warn','err');
  b.classList.add(kind);
}

async function apiGet(url){
  const r = await fetch(url, { credentials:'same-origin' });
  const j = await r.json().catch(()=>null);
  if (!r.ok || !j || j.ok === false) throw new Error(j?.error || ('HTTP '+r.status));
  return j;
}

async function apiPost(url, payload){
  const r = await fetch(url, {
    method:'POST',
    credentials:'same-origin',
    headers:{'Content-Type':'application/json; charset=utf-8'},
    body: JSON.stringify(payload || {})
  });
  const j = await r.json().catch(()=>null);
  if (!r.ok || !j || j.ok === false) throw new Error(j?.error || ('HTTP '+r.status));
  return j;
}

const saveAs = (blob, filename) => {
  const a=document.createElement('a');
  a.href=URL.createObjectURL(blob);
  a.download=filename;
  document.body.appendChild(a);
  a.click();
  setTimeout(()=>{ URL.revokeObjectURL(a.href); a.remove(); }, 700);
};

async function zoomFit() {
  try {
    const av = modeler.getActiveViewer();
    av.get('canvas').zoom('fit-viewport', 'auto');
  } catch {}
}
function zoomScale(factor) {
  try {
    const av = modeler.getActiveViewer();
    const canvas = av.get('canvas');
    canvas.zoom(canvas.zoom() * factor);
  } catch {}
}

/* ===== views ===== */
async function getViews(){ return await modeler.getViews(); }
async function openViewBy(pred){
  const views = await getViews();
  const v = views.find(pred) || views[0];
  if (v) await modeler.open(v);
  return v;
}
async function openDRD(){
  const v = await openViewBy(v => v.type === 'drd');
  await zoomFit();
  return v;
}
async function openDecisionTableFor(decisionIdOrEl){
  const id = typeof decisionIdOrEl === 'string' ? decisionIdOrEl : decisionIdOrEl?.id;
  return await openViewBy(v => v.type === 'decisionTable' && v.element?.id === id);
}

/* ===== boot ===== */
(async function boot(){
  setBadge('Carregando…','warn');

  // carrega categorias
  try {
    const cats = await apiGet(API.categories);
    const sel = $('#categoryId');
    for (const c of (cats.items||[])) {
      const opt = document.createElement('option');
      opt.value = c.id;
      opt.textContent = c.name;
      sel.appendChild(opt);
    }
  } catch(e) {
    console.warn('cats:', e);
  }

  modeler = new window.DmnJS({ container:'#dmnCanvas' });

  // resize refit
  let t=null;
  window.addEventListener('resize', ()=>{ clearTimeout(t); t=setTimeout(zoomFit, 120); });

  // hook dblclick
  try {
    const av = modeler.getActiveViewer();
    const eventBus = av.get('eventBus');
    eventBus.on('element.dblclick', async (e) => {
      const el = e?.element;
      if (el?.type === 'dmn:Decision') {
        await openDecisionTableFor(el.businessObject?.id || el.id);
      }
    });
  } catch {}

  if (currentDecisionId > 0) {
    await loadDecision(currentDecisionId);
  } else {
    await modeler.importXML(EMPTY_XML);
    await openDRD();
    setBadge('Novo (não salvo)','warn');
  }
})();

/* ===== load decision ===== */
async function loadDecision(id){
  try {
    setBadge('Carregando decisão…','warn');
    const data = await apiGet(API.decisionGet + id);

    const dec = data.decision;
    $('#decisionId').value = dec.id;
    currentDecisionId = parseInt(dec.id,10);

    $('#ruleKey').value = dec.rule_key || '';
    $('#name').value = dec.name || '';
    $('#description').value = dec.description || '';
    $('#decisionStatus').value = dec.status || 'draft';

    // category
    $('#categoryId').value = (dec.category_id ?? '') + '';

    // tags
    try {
      const tags = dec.tags ? JSON.parse(dec.tags) : [];
      $('#tags').value = Array.isArray(tags) ? tags.join(', ') : '';
    } catch {
      $('#tags').value = '';
    }

    // published info
    if (data.published) {
      $('#pubVersion').value = data.published.version_num ?? '';
      $('#pubAt').value = data.published.published_at ?? data.published.created_at ?? '';
    } else {
      $('#pubVersion').value = '';
      $('#pubAt').value = '';
    }

    const xml = (data.draft && data.draft.xml) ? data.draft.xml : (data.published && data.published.xml ? data.published.xml : EMPTY_XML);

    await modeler.importXML(xml);
    await openDRD();

    setBadge('Carregado','ok');
    await loadCases();

  } catch(e) {
    console.error(e);
    setBadge('Erro ao carregar','err');
    alert('Erro ao carregar DMN: ' + e.message);
    await modeler.importXML(EMPTY_XML);
    await openDRD();
  }
}

/* ===== CRUD actions ===== */
function collectMeta(){
  const tags = ($('#tags').value || '').split(',').map(s=>s.trim()).filter(Boolean);
  return {
    id: currentDecisionId || 0,
    category_id: $('#categoryId').value !== '' ? parseInt($('#categoryId').value,10) : null,
    rule_key: $('#ruleKey').value.trim(),
    name: $('#name').value.trim(),
    description: $('#description').value || '',
    tags
  };
}

async function saveDraft(){
  const meta = collectMeta();
  if (!meta.rule_key) return alert('Informe o Rule Key.');
  if (!meta.name) return alert('Informe o Nome.');

  setBadge('Salvando draft…','warn');
  $('#btnSave').disabled = true;
  $('#btnPublish').disabled = true;

  try {
    const { xml } = await modeler.saveXML({ format:true });

    const resp = await apiPost(API.decisionSave, { ...meta, xml });

    // se era novo, atualiza URL/ID
    if (!currentDecisionId || currentDecisionId === 0) {
      currentDecisionId = parseInt(resp.id,10);
      $('#decisionId').value = currentDecisionId;
      const u = new URL(window.location.href);
      u.searchParams.set('id', currentDecisionId);
      window.history.replaceState({}, '', u.toString());
    }

    setBadge('Draft salvo','ok');
    await loadCases();

  } catch(e) {
    console.error(e);
    setBadge('Erro ao salvar','err');
    alert('Erro ao salvar: ' + e.message);
  } finally {
    $('#btnSave').disabled = false;
    $('#btnPublish').disabled = false;
  }
}

async function publish(){
  if (!currentDecisionId) return alert('Salve o DMN antes de publicar.');

  const notes = prompt('Notas da publicação (opcional):', '');
  setBadge('Publicando…','warn');
  $('#btnPublish').disabled = true;

  try {
    const resp = await apiPost(API.decisionPublish, { id: currentDecisionId, notes: notes || '' });
    $('#decisionStatus').value = 'published';
    $('#pubVersion').value = resp.version_num ?? '';
    $('#pubAt').value = new Date().toISOString().slice(0,19).replace('T',' ');
    setBadge('Publicado','ok');
  } catch(e) {
    console.error(e);
    setBadge('Erro ao publicar','err');
    alert('Erro ao publicar: ' + e.message);
  } finally {
    $('#btnPublish').disabled = false;
  }
}

/* ===== Testcases ===== */
async function loadCases(){
  if (!currentDecisionId) { $('#casesList').innerHTML = '<div class="hint">Salve primeiro para habilitar testcases.</div>'; return; }

  try {
    const data = await apiGet(API.testcaseList + currentDecisionId);
    const items = data.items || [];
    const list = $('#casesList');
    list.innerHTML = '';

    if (items.length === 0) {
      list.innerHTML = '<div class="hint">Nenhum testcase ainda.</div>';
      return;
    }

    for (const tc of items) {
      const el = document.createElement('div');
      el.className = 'item';

      const meta = document.createElement('div');
      meta.className = 'meta';

      const title = document.createElement('div');
      title.className = 'name';
      title.textContent = tc.name;

      const sub = document.createElement('div');
      sub.className = 'sub';
      sub.textContent = `#${tc.id} • ${tc.created_at} • ativo: ${tc.is_active}`;

      meta.appendChild(title);
      meta.appendChild(sub);

      const actions = document.createElement('div');
      actions.className = 'actions';

      const b1 = document.createElement('button');
      b1.className = 'btn small';
      b1.textContent = 'Carregar';
      b1.onclick = () => {
        try {
          const ctx = typeof tc.context_json === 'string' ? JSON.parse(tc.context_json) : tc.context_json;
          $('#runnerContext').value = JSON.stringify(ctx, null, 2);
          $('#tcName').value = tc.name;
          // expected (se tiver)
          if (tc.expected_json) {
            const ex = typeof tc.expected_json === 'string' ? JSON.parse(tc.expected_json) : tc.expected_json;
            $('#tcExpectedHint').value = JSON.stringify(ex);
          } else {
            $('#tcExpectedHint').value = '';
          }
          openRunner(true);
        } catch(e) {
          alert('Erro lendo JSON do testcase.');
        }
      };

      actions.appendChild(b1);

      el.appendChild(meta);
      el.appendChild(actions);
      list.appendChild(el);
    }
  } catch(e) {
    console.warn(e);
    $('#casesList').innerHTML = '<div class="hint">Erro ao carregar testcases.</div>';
  }
}

async function saveCaseFromRunner(){
  if (!currentDecisionId) return alert('Salve a decisão primeiro.');

  const name = $('#tcName').value.trim();
  if (!name) return alert('Informe o nome do testcase.');

  let ctx = null;
  try { ctx = JSON.parse($('#runnerContext').value || '{}'); }
  catch { return alert('Context JSON inválido.'); }

  let expected = null;
  const expectedRaw = $('#tcExpectedHint').value.trim();
  if (expectedRaw) {
    try { expected = JSON.parse(expectedRaw); }
    catch { return alert('Expected JSON inválido.'); }
  }

  try {
    await apiPost(API.testcaseSave, {
      id: 0,
      decision_id: currentDecisionId,
      name,
      context: ctx,
      expected,
      is_active: 1
    });
    setBadge('Testcase salvo','ok');
    await loadCases();
    alert('Testcase salvo!');
  } catch(e) {
    alert('Erro ao salvar testcase: ' + e.message);
  }
}

/* ===== Runner ===== */
function openRunner(prefill=false){
  $('#modalRunner').style.display = 'flex';
  if (!prefill && !$('#runnerContext').value.trim()) {
    $('#runnerContext').value = JSON.stringify({ exemplo: "preencha aqui" }, null, 2);
  }
}
function closeRunner(){ $('#modalRunner').style.display = 'none'; }

async function runNow(){
  const rk = $('#ruleKey').value.trim();
  if (!rk) return alert('Informe o Rule Key.');
  let ctx = null;
  try { ctx = JSON.parse($('#runnerContext').value || '{}'); }
  catch { return alert('Context JSON inválido.'); }

  $('#runnerResult').textContent = 'Executando...';

  try {
    const resp = await apiPost(API.evaluate, { rule_key: rk, context: ctx });
    $('#runnerResult').textContent = JSON.stringify(resp, null, 2);
  } catch(e) {
    $('#runnerResult').textContent = JSON.stringify({ ok:false, error: e.message }, null, 2);
  }
}

/* ===== Toolbar handlers ===== */
$('#btnSave').onclick = saveDraft;
$('#btnPublish').onclick = publish;

$('#btnRunner').onclick = () => openRunner(false);
$('#btnCloseRunner').onclick = closeRunner;
$('#btnRunNow').onclick = runNow;
$('#btnSaveCase').onclick = saveCaseFromRunner;

$('#btnReloadCases').onclick = loadCases;
$('#btnNewCase').onclick = () => { openRunner(false); $('#tcName').value=''; $('#tcExpectedHint').value=''; };

$('#btnNew').onclick = async ()=>{
  if (!confirm('Criar novo DMN? (não apaga o atual, mas você vai trocar o editor para um novo rascunho)')) return;
  currentDecisionId = 0;
  $('#decisionId').value = '0';
  $('#decisionStatus').value = 'draft';
  $('#ruleKey').value = '';
  $('#name').value = '';
  $('#description').value = '';
  $('#tags').value = '';
  $('#categoryId').value = '';
  $('#pubVersion').value = '';
  $('#pubAt').value = '';
  $('#casesList').innerHTML = '<div class="hint">Salve primeiro para habilitar testcases.</div>';
  await modeler.importXML(EMPTY_XML);
  await openDRD();
  setBadge('Novo (não salvo)','warn');

  const u = new URL(window.location.href);
  u.searchParams.delete('id');
  window.history.replaceState({}, '', u.toString());
};

$('#btnCreateDecision').onclick = async ()=>{
  const moddle  = modeler.get('moddle');
  const viewer  = modeler.getActiveViewer();
  const modeling = viewer.get('modeling');
  const elementFactory = viewer.get('elementFactory');
  const canvas = viewer.get('canvas');

  const decisionId = 'Decision_' + Date.now().toString(36);
  const tableId    = 'DecisionTable_' + Date.now().toString(36);

  const decisionBo = moddle.create('dmn:Decision', { id: decisionId, name: 'Nova decisão' });
  const dTable     = moddle.create('dmn:DecisionTable', { id: tableId, hitPolicy: 'FIRST' });

  const inputExpr  = moddle.create('dmn:LiteralExpression', { id: 'InputExpr_'+Date.now().toString(36), text: 'valor' });
  const input      = moddle.create('dmn:Input',  { id:'Input_'+Date.now().toString(36), inputExpression: inputExpr, label:'Entrada' });
  const output     = moddle.create('dmn:Output', { id:'Output_'+Date.now().toString(36), name:'Resultado', typeRef:'string' });
  dTable.input  = [ input ];
  dTable.output = [ output ];

  decisionBo.decisionLogic = dTable;

  const shape = elementFactory.createShape({ type: 'dmn:Decision', businessObject: decisionBo });
  const root = canvas.getRootElement();
  modeling.createShape(shape, { x: 300, y: 200 }, root);

  await openDecisionTableFor(decisionId);
};

$('#btnOpenTable').onclick = async ()=>{
  try{
    const av = modeler.getActiveViewer();
    const sel = av.get('selection').get();
    const el  = sel?.[0];
    if (!el || el.type !== 'dmn:Decision') {
      alert('Selecione uma Decision no DRD para abrir a tabela.');
      return;
    }
    await openDecisionTableFor(el.businessObject?.id || el.id);
  }catch(e){ console.error(e); }
};
$('#btnViewDRD').onclick = openDRD;

$('#zoomIn').onclick  = ()=> zoomScale(1.2);
$('#zoomOut').onclick = ()=> zoomScale(1/1.2);
$('#zoomFit').onclick = zoomFit;

// ESC fecha modal
window.addEventListener('keydown', (e)=>{
  if (e.key === 'Escape') closeRunner();
});
</script>

<?php
include_once ROOT_PATH . 'system/includes/footer.php';
?>
