<?php
// modules/dmn/dmn_runner.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';

if (session_status()===PHP_SESSION_NONE) session_start();
proteger_pagina();
?>

<?php
include_once ROOT_PATH . 'system/includes/head.php';
?>
<style>
  :root{ --bg:#f6f7f9; --card:#fff; --bd:#e5e7eb; --txt:#111; }
  *{ box-sizing:border-box; }
  body{ margin:0; font:14px/1.45 system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial; background:var(--bg); color:var(--txt); }
  .wrap{ padding:12px; }
  .card{ background:var(--card); border:1px solid var(--bd); border-radius:12px; padding:12px; }
  .row{ display:flex; gap:12px; flex-wrap:wrap; }
  .col{ flex:1; min-width: 280px; }
  label{ display:block; font-weight:900; margin:10px 0 6px; color:#111827; }
  input, textarea, select{ width:100%; padding:10px; border:1px solid #d1d5db; border-radius:10px; }
  textarea{ min-height:220px; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono","Courier New", monospace; }
  .btn{ border:1px solid #d1d5db; background:#fff; padding:8px 12px; border-radius:10px; font-weight:800; cursor:pointer; }
  .btn:hover{ background:#f3f4f6; }
  .btn.primary{ background:#111827; border-color:#111827; color:#fff; }
  .btn.danger{ background:#b91c1c; border-color:#b91c1c; color:#fff; }
  pre{
    background:#0b1020; color:#e5e7eb; padding:12px; border-radius:12px;
    overflow:auto; max-height:420px;
  }
  .hint{ color:#6b7280; font-size:12px; margin-top:6px; }
  .history{ display:flex; flex-direction:column; gap:8px; margin-top:10px; }
  .hitem{ border:1px solid var(--bd); border-radius:12px; padding:10px; background:#fff; }
  .hitem .t{ font-weight:900; }
  .hitem .s{ color:#6b7280; font-size:12px; margin-top:4px; }
  .hitem .a{ margin-top:8px; display:flex; gap:8px; flex-wrap:wrap; }
</style>
// (se o seu navbar ficar dentro do head/footer, não precisa incluir aqui)

<?php
include_once ROOT_PATH . 'system/includes/navbar.php';
?>

<!-- Incio Page Content -->
<div id="page-wrapper">
  <div class="container-fluid">
    <div class="row"><div class="col-lg-12"><h1 class="page-header"><?= APP_NAME ?></h1></div></div>

    <div class="row">
      <div class="col-lg-12">
<!-- Meio Page Content -->

<?php include_once __DIR__ . '/includes/dmn_nav.php'; ?>

<div class="wrap">
  <div class="card">
    <div style="font-weight:900">Runner — testar rule_key</div>
    <div class="hint">
      Engine v1: Decision Table (hitPolicy FIRST). Condições suportadas (v1): =, &lt;, &lt;=, &gt;, &gt;=, in(...), matches("...").
    </div>

    <div class="row">
      <div class="col">
        <label>Rule Key</label>
        <input id="rule_key" placeholder="ex: ti.sla.route">

        <label>Decision DMN ID (opcional)</label>
        <input id="decision_dmn_id" placeholder="ex: Decision_abc123 (se o XML tem múltiplas Decisions)">

        <label>Context JSON</label>
        <textarea id="context" placeholder='{"categoria":"Telefonia","prioridade":3}'></textarea>

        <div style="margin-top:10px; display:flex; gap:8px; flex-wrap:wrap;">
          <button class="btn primary" id="btnRun">Executar</button>
          <button class="btn" id="btnPretty">Formatar JSON</button>
          <button class="btn danger" id="btnClearHist">Limpar histórico</button>
        </div>

        <div class="hint">
          Dica: você pode salvar como testcase informando um nome e o expected (opcional).
        </div>

        <div style="margin-top:14px; border-top:1px solid var(--bd); padding-top:12px;">
          <div style="font-weight:900">Salvar como testcase</div>

          <label>Nome do testcase</label>
          <input id="tc_name" placeholder="ex: Telefonia alta">

          <label>Expected JSON (opcional)</label>
          <input id="tc_expected" placeholder='{"fila":"TI Infra","slaHoras":8}'>

          <div style="margin-top:10px; display:flex; gap:8px; flex-wrap:wrap;">
            <button class="btn" id="btnSaveTc">Salvar testcase</button>
          </div>

          <div class="hint">
            Para salvar testcase precisamos resolver o <b>decision_id</b> via rule_key. (é automático aqui).
          </div>
        </div>
      </div>

      <div class="col">
        <label>Resultado</label>
        <pre id="result">{}</pre>

        <label>Histórico (local)</label>
        <div class="history" id="history"></div>
      </div>
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

<script>
const API = {
  evaluate: 'api/evaluate.php',
  // vamos resolver decision_id via rule_key buscando na lista (leve e já existe)
  // (poderíamos ter um endpoint resolve_rule_key.php, mas pra v1 isso resolve)
  decisionList: 'api/decision_list.php?q=',
  testcaseSave: 'api/testcase_save.php'
};

const $ = (s)=>document.querySelector(s);

async function apiPost(url, payload){
  const r = await fetch(url, {
    method:'POST',
    credentials:'same-origin',
    headers:{'Content-Type':'application/json; charset=utf-8'},
    body: JSON.stringify(payload||{})
  });
  const j = await r.json().catch(()=>null);
  if (!r.ok || !j || j.ok === false) throw new Error(j?.error || ('HTTP '+r.status));
  return j;
}

async function apiGet(url){
  const r = await fetch(url, { credentials:'same-origin' });
  const j = await r.json().catch(()=>null);
  if (!r.ok || !j || j.ok === false) throw new Error(j?.error || ('HTTP '+r.status));
  return j;
}

function safeJsonParse(text){
  try { return JSON.parse(text); } catch { return null; }
}

function pretty(){
  const obj = safeJsonParse($('#context').value || '{}');
  if (!obj) return alert('JSON inválido.');
  $('#context').value = JSON.stringify(obj, null, 2);
}

function setResult(obj){
  $('#result').textContent = typeof obj === 'string' ? obj : JSON.stringify(obj, null, 2);
}

function loadHistory(){
  const h = JSON.parse(localStorage.getItem('dmn_runner_hist') || '[]');
  const box = $('#history');
  box.innerHTML = '';
  if (!h.length) {
    box.innerHTML = '<div class="hint">Sem histórico.</div>';
    return;
  }

  for (const item of h) {
    const div = document.createElement('div');
    div.className = 'hitem';
    div.innerHTML = `
      <div class="t">${item.rule_key}</div>
      <div class="s">${item.at} • ${item.ok ? 'ok' : 'erro'} • ${item.exec_ms ?? ''}ms</div>
      <div class="a">
        <button class="btn" data-act="load">Carregar</button>
        <button class="btn" data-act="view">Ver resultado</button>
      </div>
    `;
    div.querySelector('[data-act="load"]').onclick = () => {
      $('#rule_key').value = item.rule_key;
      $('#decision_dmn_id').value = item.decision_dmn_id || '';
      $('#context').value = item.context_pretty || item.context_raw || '';
      setResult(item.response || {});
      window.scrollTo({top:0, behavior:'smooth'});
    };
    div.querySelector('[data-act="view"]').onclick = () => {
      setResult(item.response || {});
    };
    box.appendChild(div);
  }
}

function pushHistory(item){
  const h = JSON.parse(localStorage.getItem('dmn_runner_hist') || '[]');
  h.unshift(item);
  while (h.length > 25) h.pop();
  localStorage.setItem('dmn_runner_hist', JSON.stringify(h));
  loadHistory();
}

async function run(){
  const rk = $('#rule_key').value.trim();
  if (!rk) return alert('Informe rule_key.');

  const ctxObj = safeJsonParse($('#context').value || '{}');
  if (!ctxObj) return alert('Context JSON inválido.');

  const decision_dmn_id = $('#decision_dmn_id').value.trim();

  setResult('Executando…');

  const started = Date.now();
  try {
    const resp = await apiPost(API.evaluate, {
      rule_key: rk,
      decision_dmn_id: decision_dmn_id || undefined,
      context: ctxObj
    });

    setResult(resp);

    pushHistory({
      at: new Date().toISOString().slice(0,19).replace('T',' '),
      ok: true,
      rule_key: rk,
      decision_dmn_id: decision_dmn_id || '',
      exec_ms: resp.exec_ms ?? (Date.now()-started),
      context_raw: $('#context').value,
      context_pretty: JSON.stringify(ctxObj, null, 2),
      response: resp
    });

  } catch (e) {
    const msg = { ok:false, error: e.message };
    setResult(msg);

    pushHistory({
      at: new Date().toISOString().slice(0,19).replace('T',' '),
      ok: false,
      rule_key: rk,
      decision_dmn_id: decision_dmn_id || '',
      exec_ms: (Date.now()-started),
      context_raw: $('#context').value,
      context_pretty: JSON.stringify(ctxObj, null, 2),
      response: msg
    });
  }
}

async function resolveDecisionIdByRuleKey(ruleKey){
  // Faz uma busca por rule_key (usando decision_list.php?q=...)
  const data = await apiGet(API.decisionList + encodeURIComponent(ruleKey));
  const items = data.items || [];
  // preferir match exato
  let exact = items.find(x => (x.rule_key || '') === ruleKey);
  if (exact) return parseInt(exact.id, 10);
  // fallback: primeiro
  if (items[0]) return parseInt(items[0].id, 10);
  return 0;
}

async function saveTestcase(){
  const rk = $('#rule_key').value.trim();
  if (!rk) return alert('Informe rule_key.');
  const name = $('#tc_name').value.trim();
  if (!name) return alert('Informe o nome do testcase.');

  const ctxObj = safeJsonParse($('#context').value || '{}');
  if (!ctxObj) return alert('Context JSON inválido.');

  let expectedObj = null;
  const expectedRaw = $('#tc_expected').value.trim();
  if (expectedRaw) {
    expectedObj = safeJsonParse(expectedRaw);
    if (!expectedObj) return alert('Expected JSON inválido.');
  }

  // resolver decision_id
  const decisionId = await resolveDecisionIdByRuleKey(rk);
  if (!decisionId) return alert('Não encontrei decision_id para este rule_key (salve a decisão na biblioteca primeiro).');

  try {
    await apiPost(API.testcaseSave, {
      id: 0,
      decision_id: decisionId,
      name,
      context: ctxObj,
      expected: expectedObj || null,
      is_active: 1
    });
    alert('Testcase salvo! (decision_id=' + decisionId + ')');
  } catch(e) {
    alert('Erro ao salvar testcase: ' + e.message);
  }
}

$('#btnRun').onclick = run;
$('#btnPretty').onclick = pretty;
$('#btnSaveTc').onclick = saveTestcase;

$('#btnClearHist').onclick = () => {
  if (!confirm('Limpar histórico local do Runner?')) return;
  localStorage.removeItem('dmn_runner_hist');
  loadHistory();
};

window.addEventListener('keydown', (e)=>{
  const mod = e.ctrlKey || e.metaKey;
  if (mod && e.key.toLowerCase()==='enter') { e.preventDefault(); run(); }
});

(function init(){
  $('#context').value = JSON.stringify({ categoria: "Telefonia", prioridade: 3 }, null, 2);
  loadHistory();
})();
</script>


<?php
include_once ROOT_PATH . 'system/includes/footer.php';
?>
